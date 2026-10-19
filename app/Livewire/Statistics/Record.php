<?php

namespace App\Livewire\Statistics;

use App\Actions\ApplyCollectedAction;
use App\Actions\DeleteLatestGameEvent;
use App\Actions\FinishCollectedGame;
use App\Actions\RecordGameEvent;
use App\Actions\UpdateGameEventType;
use App\Enums\GameStatus;
use App\Enums\HomeAway;
use App\Enums\StatType;
use App\Enums\TeamColor;
use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Player;
use App\Support\ShotCourt;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Statistics')]
#[Layout('layouts.collect')]
class Record extends Component
{
    use InteractsWithAuthenticatedUser;

    #[Locked]
    public Game $game;

    public ?string $pendingType = null;

    public int $quarter = 1;

    public string $clock = '10:00';

    public bool $editingClock = false;

    public bool $configuringGame = false;

    public int $quarterMinutes = 10;

    public int $overtimeMinutes = 5;

    public string $homeColor = 'purple';

    public string $awayColor = 'blue';

    /** @var list<int> */
    public array $settingsSquadIds = [];

    public bool $editingAction = false;

    public ?int $editingEventId = null;

    public ?string $editingType = null;

    public ?int $editingPlayerId = null;

    public ?int $editingOutgoingPlayerId = null;

    public string $editingEventClock = '';

    public bool $editingForOpponent = false;

    public bool $choosingTimeout = false;

    public bool $choosingPlayer = false;

    public bool $choosingShotLocation = false;

    public bool $markShotLocation = false;

    public ?int $shotPlayerId = null;

    public bool $shotForOpponent = false;

    public bool $confirmingQuarterEnd = false;

    public bool $confirmingGameFinish = false;

    public bool $confirmingExit = false;

    /** @var Collection<int, GameEvent>|null */
    private ?Collection $loadedEvents = null;

    /** @var array{court: list<int>, seconds: array<int, int>}|null */
    private ?array $lineupMemo = null;

    private ?string $lineupMemoKey = null;

    public bool $quarterEnded = false;

    public bool $awaitingQuarterStart = false;

    /** @var list<int> */
    public array $squadIds = [];

    /** @var list<int> */
    public array $starterIds = [];

    public bool $editingSquad = false;

    public bool $substituting = false;

    /** @var list<int> */
    public array $courtSelection = [];

    /** @var array<int, bool>|null */
    private ?array $overtimeByQuarter = null;

    public function mount(Game $game): void
    {
        $this->authorize('collect', $game);

        $this->game = $game;
        $this->restorePeriod();

        if (! $this->game->events()->exists()) {
            $this->clock = $this->openingClock();
        }
    }

    public function select(string $type): void
    {
        $this->authorize('collect', $this->game);

        if ($this->awaitingQuarterStart) {
            return;
        }

        $stat = StatType::from($type);

        if ($stat !== StatType::Substitution && ! $this->periodIsOpen()) {
            return;
        }

        if ($stat === StatType::Substitution) {
            $this->pendingType = null;
            $this->choosingPlayer = false;

            if (! $this->gameStarted()) {
                $this->changeLineup();

                return;
            }

            if (! $this->periodIsOpen()) {
                return;
            }

            $this->substituting = true;
            $this->courtSelection = $this->lineup()['court'];

            return;
        }

        $this->substituting = false;
        $this->courtSelection = [];
        $this->pendingType = $this->pendingType === $stat->value ? null : $stat->value;
        $this->choosingPlayer = $this->pendingType !== null;
    }

    public function updatedChoosingPlayer(bool $open): void
    {
        if (! $open) {
            $this->pendingType = null;
        }
    }

    public function updatedChoosingShotLocation(bool $open): void
    {
        if ($open || $this->pendingType === null || $this->choosingPlayer) {
            return;
        }

        $this->cancelShotLocation();
    }

    public function cancel(): void
    {
        $this->pendingType = null;
        $this->choosingPlayer = false;
        $this->choosingShotLocation = false;
        $this->shotPlayerId = null;
        $this->shotForOpponent = false;
        $this->resetErrorBag('shotLocation');
        $this->substituting = false;
        $this->courtSelection = [];
    }

    public function toggleSquadPlayer(int $playerId): void
    {
        $this->authorize('collect', $this->game);

        if ($this->lineupLocked()) {
            return;
        }

        $this->teamPlayer($playerId);
        $this->squadIds = $this->toggleId($this->squadIds, $playerId);
    }

    public function saveSquad(): void
    {
        $this->authorize('collect', $this->game);

        if ($this->lineupLocked()) {
            return;
        }

        $ids = $this->selectedIds($this->squadIds);
        $players = Player::query()
            ->where('team_id', $this->game->team_id)
            ->where('enabled', true)
            ->whereKey($ids)
            ->pluck('id');

        if ($players->count() < 5) {
            $this->addError('squad', __('Choose at least 5 players.'));

            return;
        }

        $this->resetErrorBag('squad');
        $attached = [];

        foreach ($players as $id) {
            $attached[(int) $id] = ['starter' => false];
        }

        $this->game->players()->sync($attached);
        $this->squadIds = [];
        $this->starterIds = [];
        $this->editingSquad = false;
        unset($this->lineup, $this->squad, $this->courtPlayers, $this->benchPlayers, $this->attachedPlayers);
    }

    public function toggleStarter(int $playerId): void
    {
        $this->authorize('collect', $this->game);

        if ($this->lineupLocked() || ! $this->game->players()->whereKey($playerId)->exists()) {
            return;
        }

        $selected = $this->selectedIds($this->starterIds);

        if (! in_array($playerId, $selected, true) && count($selected) >= 5) {
            return;
        }

        $this->starterIds = $this->toggleId($this->starterIds, $playerId);
    }

    public function saveStarters(): void
    {
        $this->authorize('collect', $this->game);

        if ($this->lineupLocked()) {
            return;
        }

        $ids = $this->selectedIds($this->starterIds);
        $squadIds = $this->game->players()->pluck('players.id')->map(fn (mixed $id): int => (int) $id)->all();
        $starters = [];

        foreach ($ids as $id) {
            if (in_array($id, $squadIds, true)) {
                $starters[] = $id;
            }
        }

        if (count($starters) !== 5) {
            $this->addError('starters', __('Choose the 5 players who start.'));

            return;
        }

        $this->resetErrorBag('starters');

        foreach ($squadIds as $id) {
            $this->game->players()->updateExistingPivot($id, [
                'starter' => in_array($id, $starters, true),
            ]);
        }

        $this->starterIds = [];
        unset($this->lineup, $this->squad, $this->courtPlayers, $this->benchPlayers, $this->attachedPlayers);
    }

    public function changeLineup(): void
    {
        $this->authorize('collect', $this->game);

        if ($this->game->events()->where('type', StatType::QuarterStarted)->exists()) {
            return;
        }

        $this->editingSquad = true;
        $this->squadIds = [];
        unset($this->lineupReady, $this->squadReady);

        foreach ($this->game->players()->pluck('players.id') as $id) {
            $this->squadIds[] = (int) $id;
        }
    }

    public function leaveSquadSelection(): void
    {
        $this->authorize('collect', $this->game);

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function openGameSettings(): void
    {
        $this->authorize('collect', $this->game);

        $this->quarterMinutes = $this->game->quarter_minutes;
        $this->overtimeMinutes = $this->game->overtime_minutes;
        $this->homeColor = $this->game->home_color->value;
        $this->awayColor = $this->game->away_color->value;
        $this->markShotLocation = $this->game->mark_shot_location;
        $this->settingsSquadIds = [];

        foreach ($this->game->players()->pluck('players.id') as $id) {
            $this->settingsSquadIds[] = (int) $id;
        }

        $this->configuringGame = true;
        $this->resetErrorBag();
    }

    public function toggleSettingsPlayer(int $playerId): void
    {
        $this->authorize('collect', $this->game);

        if (! $this->configuringGame || in_array($playerId, $this->protectedSquadIds(), true)) {
            return;
        }

        $this->teamPlayer($playerId);

        $this->settingsSquadIds = $this->toggleId($this->settingsSquadIds, $playerId);
    }

    public function saveGameSettings(): void
    {
        $this->authorize('collect', $this->game);

        if (! $this->configuringGame) {
            return;
        }

        $validated = $this->validate([
            'quarterMinutes' => ['required', 'integer', 'min:1', 'max:99'],
            'overtimeMinutes' => ['required', 'integer', 'min:1', 'max:99'],
            'homeColor' => ['required', Rule::enum(TeamColor::class)],
            'awayColor' => ['required', Rule::enum(TeamColor::class)],
        ]);

        $protected = $this->protectedSquadIds();
        $selected = array_values(array_unique([...$this->selectedIds($this->settingsSquadIds), ...$protected]));

        $allowed = Player::query()
            ->where('team_id', $this->game->team_id)
            ->where('enabled', true)
            ->whereIn('id', $selected)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        foreach ($protected as $playerId) {
            if (! in_array($playerId, $allowed, true)) {
                $allowed[] = $playerId;
            }
        }

        if (count($allowed) < 5) {
            $this->addError('settingsSquad', __('Choose at least 5 players.'));

            return;
        }

        $starters = [];

        foreach ($this->game->players()->wherePivot('starter', true)->pluck('players.id') as $id) {
            $starters[] = (int) $id;
        }

        $sync = [];

        foreach ($allowed as $playerId) {
            $sync[$playerId] = ['starter' => in_array($playerId, $starters, true)];
        }

        $this->game->players()->sync($sync);

        $quarterMinutes = (int) $validated['quarterMinutes'];
        $overtimeMinutes = (int) $validated['overtimeMinutes'];

        $this->game->update([
            'quarter_minutes' => $quarterMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'home_color' => $validated['homeColor'],
            'away_color' => $validated['awayColor'],
            'mark_shot_location' => $this->markShotLocation,
        ]);

        if (! $this->gameStarted() && ! $this->awaitingQuarterStart) {
            $this->clock = sprintf('%02d:00', $quarterMinutes);
            $clock = $this->clock;
            $this->js("window.Alpine.store('gameClock')?.apply('{$clock}')");
        }

        $this->configuringGame = false;
        unset($this->lineup, $this->squad, $this->players, $this->protectedSquadIds);
    }

    public function toggleCourtSelection(int $playerId): void
    {
        $this->authorize('collect', $this->game);

        if (! $this->substituting || ! $this->periodIsOpen()) {
            return;
        }

        if (! $this->game->players()->whereKey($playerId)->exists()) {
            throw (new ModelNotFoundException)->setModel(Player::class, [$playerId]);
        }

        $selected = $this->selectedIds($this->courtSelection);

        if (! in_array($playerId, $selected, true) && count($selected) >= 5) {
            return;
        }

        $this->courtSelection = $this->toggleId($this->courtSelection, $playerId);
    }

    public function confirmSubstitutions(RecordGameEvent $recordGameEvent, ?string $clock = null): void
    {
        $this->authorize('collect', $this->game);

        if (! $this->substituting || ! $this->periodIsOpen()) {
            return;
        }

        $selected = $this->selectedIds($this->courtSelection);
        $squadIds = [];

        foreach ($this->orderedSquad() as $player) {
            $squadIds[] = $player->id;
        }

        $marked = [];

        foreach ($selected as $id) {
            if (! in_array($id, $squadIds, true)) {
                throw (new ModelNotFoundException)->setModel(Player::class, [$id]);
            }

            $marked[] = $id;
        }

        if (count($marked) !== 5) {
            $this->addError('court', __('Mark 5 players.'));

            return;
        }

        $this->resetErrorBag('court');
        $this->applyCollectedClock($clock);
        $current = $this->lineup()['court'];
        $leaving = [];
        $entering = [];

        foreach ($this->orderedSquad() as $player) {
            $onCourt = in_array($player->id, $current, true);
            $stays = in_array($player->id, $marked, true);

            if ($onCourt && ! $stays) {
                $leaving[] = $player->id;
            }

            if (! $onCourt && $stays) {
                $entering[] = $player->id;
            }
        }

        if ($leaving !== []) {
            $validated = $this->validate($this->rules());
            $quarter = $this->recordingQuarter((int) $validated['quarter']);

            foreach ($leaving as $index => $outgoingId) {
                $this->rememberCollectedEvent($recordGameEvent->substitute(
                    $this->game,
                    $entering[$index],
                    $outgoingId,
                    $quarter,
                    $validated['clock'],
                ));
            }
        }

        $this->substituting = false;
        $this->courtSelection = [];
        unset($this->lines, $this->fouls, $this->timeouts, $this->lineup, $this->squad, $this->courtPlayers, $this->benchPlayers);
    }

    public function record(int $playerId, RecordGameEvent $recordGameEvent, ?string $clock = null): void
    {
        $this->applyCollectedClock($clock);

        if ($this->shouldMarkShotLocation()) {
            $this->beginShotLocation($playerId, false);

            return;
        }

        $this->store($recordGameEvent, $playerId, false);
    }

    public function recordOpponent(RecordGameEvent $recordGameEvent, ?string $clock = null): void
    {
        $this->applyCollectedClock($clock);

        if ($this->shouldMarkShotLocation()) {
            $this->beginShotLocation(null, true);

            return;
        }

        $this->store($recordGameEvent, null, true);
    }

    public function recordShotLocation(int $courtX, int $courtY, RecordGameEvent $recordGameEvent, ?string $clock = null): void
    {
        $this->authorize('collect', $this->game);
        $this->applyCollectedClock($clock);

        if (! $this->periodIsOpen() || ! $this->choosingShotLocation || $this->pendingType === null) {
            return;
        }

        $type = StatType::from($this->pendingType);

        if (! ShotCourt::allows($type, $courtX, $courtY)) {
            $this->addError('shotLocation', __('Mark the shot inside the 2-point area for 2P, or outside for 3P.'));

            return;
        }

        $this->resetErrorBag('shotLocation');
        $this->store($recordGameEvent, $this->shotPlayerId, $this->shotForOpponent, $courtX, $courtY);
        $this->finishShotLocation();
    }

    public function cancelShotLocation(): void
    {
        $this->choosingShotLocation = false;
        $this->shotPlayerId = null;
        $this->shotForOpponent = false;
        $this->resetErrorBag('shotLocation');
        $this->choosingPlayer = $this->pendingType !== null;
    }

    public function chooseTimeout(): void
    {
        $this->authorize('collect', $this->game);

        if (! $this->periodIsOpen()) {
            return;
        }

        $this->pendingType = null;
        $this->choosingPlayer = false;
        $this->choosingTimeout = true;
    }

    public function recordTimeout(string $side, RecordGameEvent $recordGameEvent, ?string $clock = null): void
    {
        $this->authorize('collect', $this->game);
        $this->applyCollectedClock($clock);

        if (! $this->periodIsOpen()) {
            return;
        }

        $calledBy = HomeAway::tryFrom($side);

        if (! $calledBy instanceof HomeAway) {
            return;
        }

        $validated = $this->validate($this->rules());

        $this->rememberCollectedEvent($recordGameEvent->handle(
            $this->game,
            null,
            StatType::Timeout,
            $this->recordingQuarter((int) $validated['quarter']),
            $validated['clock'],
            $calledBy,
        ));

        $this->choosingTimeout = false;
        unset($this->lines, $this->timeouts, $this->lineup);
    }

    public function cancelChoosingTimeout(): void
    {
        $this->choosingTimeout = false;
    }

    public function cancelEditingAction(): void
    {
        $this->clearEditingAction();
    }

    public function cancelConfirmingQuarterEnd(): void
    {
        $this->confirmingQuarterEnd = false;
    }

    public function attemptExit(): void
    {
        $this->authorize('collect', $this->game);

        $this->confirmingExit = true;
    }

    public function cancelConfirmingExit(): void
    {
        $this->confirmingExit = false;
    }

    public function confirmExit(): void
    {
        $this->authorize('collect', $this->game);

        $this->confirmingExit = false;
        $this->game->refresh();

        if ($this->game->canFinishAfterFourthQuarter()) {
            $this->confirmingGameFinish = true;

            return;
        }

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function leaveWithoutFinishing(): void
    {
        $this->authorize('collect', $this->game);

        $this->confirmingGameFinish = false;
        $this->redirect(route('dashboard'), navigate: true);
    }

    public function finishGameAndExit(FinishCollectedGame $finishCollectedGame): void
    {
        $this->authorize('collect', $this->game);

        $finishCollectedGame->handle($this->actor(), $this->game->refresh());
        $this->confirmingGameFinish = false;
        $this->redirect(route('dashboard'), navigate: true);
    }

    public function askToEndQuarter(): void
    {
        $this->authorize('collect', $this->game);

        if (! $this->clockIsZero() || $this->quarterEnded || $this->awaitingQuarterStart) {
            return;
        }

        $this->confirmingQuarterEnd = true;
    }

    public function endQuarter(RecordGameEvent $recordGameEvent): void
    {
        $this->authorize('collect', $this->game);

        if (! $this->clockIsZero() || $this->awaitingQuarterStart || $this->quarterEnded) {
            $this->confirmingQuarterEnd = false;

            return;
        }

        $marker = $this->latestPeriodMarker();

        if ($marker?->type === StatType::QuarterEnded && $marker->quarter === $this->quarter) {
            $this->confirmingQuarterEnd = false;

            return;
        }

        $endedQuarter = $this->quarter;
        $this->recordPeriod($recordGameEvent, StatType::QuarterEnded, $endedQuarter, '00:00');
        $this->overtimeByQuarter = null;

        $this->quarter = $endedQuarter + 1;
        $this->clock = '00:00';
        $this->quarterEnded = false;
        $this->awaitingQuarterStart = true;
        $this->confirmingQuarterEnd = false;
        $this->js("window.Alpine.store('gameClock')?.hold()");
    }

    public function startQuarter(RecordGameEvent $recordGameEvent): void
    {
        $this->authorize('collect', $this->game);

        if (! $this->awaitingQuarterStart || ! $this->nextPeriodCanStart()) {
            return;
        }

        $clock = $this->openingClock();
        $this->recordPeriod($recordGameEvent, StatType::QuarterStarted, $this->quarter, $clock);
        $this->awaitingQuarterStart = false;
        $this->clock = $clock;
        $this->js("window.Alpine.store('gameClock')?.begin('{$clock}')");
    }

    public function recordQuarterStart(RecordGameEvent $recordGameEvent, ?string $clock = null): void
    {
        $this->authorize('collect', $this->game);
        $this->applyCollectedClock($clock);

        if ($this->awaitingQuarterStart || $this->quarterEnded || $this->clockIsZero()) {
            return;
        }

        $marker = $this->latestPeriodMarker();

        if ($marker?->type === StatType::QuarterStarted && $marker->quarter === $this->quarter) {
            return;
        }

        $validated = $this->validate($this->rules());

        $this->recordPeriod(
            $recordGameEvent,
            StatType::QuarterStarted,
            (int) $validated['quarter'],
            $validated['clock'],
        );
    }

    public function updatedQuarter(): void
    {
        $this->quarterEnded = false;

        if (! $this->awaitingQuarterStart) {
            return;
        }

        $this->awaitingQuarterStart = false;
        $this->js("window.Alpine.store('gameClock')?.apply('{$this->clock}')");
    }

    public function editAction(int $eventId): void
    {
        $this->authorize('collect', $this->game);

        $event = $this->game->events()->whereKey($eventId)->first();

        if (! $event instanceof GameEvent || ! $event->type->isEditableAction()) {
            return;
        }

        $this->editingEventId = $event->id;
        $this->editingType = $event->type->value;
        $this->editingEventClock = $event->clock;
        $this->editingOutgoingPlayerId = $event->outgoing_player_id;
        $this->editingForOpponent = $event->type === StatType::Timeout
            ? ! $this->game->isOurSide($event->home_away)
            : ($event->type->isAssignableStat() && ! $this->game->isOurSide($event->home_away));
        $this->editingPlayerId = $event->type === StatType::Timeout || $this->editingForOpponent
            ? null
            : $event->player_id;
        $this->editingAction = true;
        $this->resetErrorBag('editingEventClock');
        unset($this->editingTypeChoices);
    }

    public function selectEditingType(string $type): void
    {
        $this->authorize('collect', $this->game);

        $stat = StatType::tryFrom($type);
        $event = $this->editingEventId === null
            ? null
            : $this->game->events()->whereKey($this->editingEventId)->first();

        if (! $stat instanceof StatType || ! $event instanceof GameEvent || ! $event->type->canSwapTo($stat)) {
            return;
        }

        $this->editingType = $stat->value;
    }

    public function selectEditingPlayer(int $playerId): void
    {
        $this->authorize('collect', $this->game);

        if ($this->editingType === StatType::Timeout->value) {
            return;
        }

        $player = $this->game->players()->whereKey($playerId)->first();

        if (! $player instanceof Player) {
            throw (new ModelNotFoundException)->setModel(Player::class, [$playerId]);
        }

        $this->editingPlayerId = $player->id;
        $this->editingForOpponent = false;
    }

    public function selectEditingOutgoingPlayer(int $playerId): void
    {
        $this->authorize('collect', $this->game);

        if ($this->editingType !== StatType::Substitution->value) {
            return;
        }

        $player = $this->game->players()->whereKey($playerId)->first();

        if (! $player instanceof Player) {
            throw (new ModelNotFoundException)->setModel(Player::class, [$playerId]);
        }

        $this->editingOutgoingPlayerId = $player->id;
    }

    public function selectEditingOpponent(): void
    {
        $this->authorize('collect', $this->game);

        if ($this->editingType === StatType::Substitution->value) {
            return;
        }

        $this->editingPlayerId = null;
        $this->editingForOpponent = true;
    }

    public function selectEditingTeam(): void
    {
        $this->authorize('collect', $this->game);

        if ($this->editingType !== StatType::Timeout->value) {
            return;
        }

        $this->editingPlayerId = null;
        $this->editingForOpponent = false;
    }

    public function saveLogLine(UpdateGameEventType $updateGameEventType): void
    {
        $this->authorize('collect', $this->game);

        if ($this->editingEventId === null || $this->editingType === null) {
            return;
        }

        $stat = StatType::tryFrom($this->editingType);

        if (! $stat instanceof StatType) {
            return;
        }

        if ($stat === StatType::Substitution) {
            if ($this->editingPlayerId === null || $this->editingOutgoingPlayerId === null) {
                return;
            }
        } elseif ($stat->isAssignableStat() && ! $this->editingForOpponent && $this->editingPlayerId === null) {
            return;
        }

        $updateGameEventType->handle(
            $this->game,
            $this->editingEventId,
            $stat,
            $this->editingForOpponent ? null : $this->editingPlayerId,
            $this->editingEventClock,
            $this->editingOutgoingPlayerId,
            $this->editingForOpponent,
        );

        $this->clearEditingAction();

        $this->forgetCollectedEvents();
        unset($this->lines, $this->fouls, $this->timeouts, $this->lineup);
    }

    public function updatedEditingAction(bool $open): void
    {
        if ($open) {
            return;
        }

        $this->clearEditingActionState();
    }

    private function clearEditingAction(): void
    {
        $this->editingAction = false;
        $this->clearEditingActionState();
    }

    private function clearEditingActionState(): void
    {
        $this->editingEventId = null;
        $this->editingType = null;
        $this->editingPlayerId = null;
        $this->editingOutgoingPlayerId = null;
        $this->editingEventClock = '';
        $this->editingForOpponent = false;
        $this->resetErrorBag('editingEventClock');
        unset($this->editingTypeChoices);
    }

    /**
     * @return list<array{stat: StatType, tone: string}>
     */
    #[Computed]
    public function editingTypeChoices(): array
    {
        $event = $this->editingEventId === null
            ? null
            : $this->game->events()->whereKey($this->editingEventId)->first();

        if (! $event instanceof GameEvent) {
            return [];
        }

        $choices = [];

        foreach ($event->type->editableTypeOptions() as $stat) {
            $choices[] = [
                'stat' => $stat,
                'tone' => match (true) {
                    $stat->isSolid() => 'made',
                    $stat->isMiss() => 'missed',
                    default => 'plain',
                },
            ];
        }

        return $choices;
    }

    public function hydrate(): void
    {
        $this->forgetCollectedEvents();
    }

    public function undo(DeleteLatestGameEvent $deleteLatestGameEvent): void
    {
        $this->authorize('collect', $this->game);

        $deleteLatestGameEvent->handle($this->game);
        $this->forgetCollectedEvents();

        unset($this->lines, $this->fouls, $this->timeouts, $this->lineup, $this->canUndo);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function applyCollectedAction(string $id, string $operation, array $payload, ApplyCollectedAction $action): void
    {
        $this->authorize('collect', $this->game);

        $action->handle($this->game, $id, $operation, $payload);

        $this->forgetCollectedEvents();
        $this->overtimeByQuarter = null;
        $this->restorePeriod();

        if ($operation === 'edit') {
            $this->clearEditingAction();
        }

        unset($this->lines, $this->fouls, $this->timeouts, $this->lineup, $this->squad, $this->courtPlayers, $this->benchPlayers, $this->canUndo, $this->periodIsOpen, $this->gameStarted);
    }

    #[Computed]
    public function canUndo(): bool
    {
        return $this->game->latestUndoableEvent() instanceof GameEvent;
    }

    /**
     * @return Collection<int, Player>
     */
    #[Computed]
    public function players(): Collection
    {
        return Player::query()
            ->where('team_id', $this->game->team_id)
            ->where('enabled', true)
            ->orderBy('number')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array{court: list<int>, seconds: array<int, int>}
     */
    #[Computed]
    public function lineup(): array
    {
        $key = $this->clock.'|'.(int) $this->periodIsOpen().'|'.$this->collectedEvents()->count();

        if ($this->lineupMemoKey === $key && $this->lineupMemo !== null) {
            return $this->lineupMemo;
        }

        $this->lineupMemoKey = $key;

        return $this->lineupMemo = $this->game->lineupState($this->clock, $this->periodIsOpen());
    }

    /**
     * Players on the court, and players who already have an action, stay on the game.
     *
     * @return list<int>
     */
    #[Computed]
    public function protectedSquadIds(): array
    {
        $ids = $this->lineup()['court'];

        $events = $this->game->events()
            ->where(function ($query): void {
                $query->whereNotNull('player_id')
                    ->orWhereNotNull('outgoing_player_id');
            })
            ->get(['player_id', 'outgoing_player_id']);

        foreach ($events as $event) {
            if ($event->player_id !== null) {
                $ids[] = (int) $event->player_id;
            }

            if ($event->outgoing_player_id !== null) {
                $ids[] = (int) $event->outgoing_player_id;
            }
        }

        return array_values(array_unique($ids));
    }

    #[Computed]
    public function squadReady(): bool
    {
        return $this->game->players()->count() >= 5;
    }

    #[Computed]
    public function lineupReady(): bool
    {
        if ($this->editingSquad) {
            return false;
        }

        return $this->game->players()->wherePivot('starter', true)->count() === 5;
    }

    #[Computed]
    public function gameStarted(): bool
    {
        return $this->game->events()->where('type', StatType::QuarterStarted)->exists();
    }

    /**
     * @return Collection<int, Player>
     */
    #[Computed]
    public function squad(): Collection
    {
        $court = array_flip($this->lineup()['court']);

        return $this->orderedSquad()
            ->sortBy(fn (Player $player): int => isset($court[$player->id]) ? 0 : 1)
            ->values();
    }

    /**
     * @return Collection<int, Player>
     */
    #[Computed]
    public function courtPlayers(): Collection
    {
        $court = array_flip($this->lineup()['court']);

        return $this->orderedSquad()
            ->filter(fn (Player $player): bool => isset($court[$player->id]))
            ->values();
    }

    /**
     * @return Collection<int, Player>
     */
    #[Computed]
    public function benchPlayers(): Collection
    {
        $court = array_flip($this->lineup()['court']);

        return $this->orderedSquad()
            ->reject(fn (Player $player): bool => isset($court[$player->id]))
            ->values();
    }

    /**
     * @return array{team: int, opponent: int, players: array<int, int>}
     */
    #[Computed]
    public function fouls(): array
    {
        $periods = $this->game->periodsByEventId();
        $events = $this->collectedEvents()->where('type', StatType::Foul);

        $players = [];
        $team = 0;
        $opponent = 0;

        foreach ($events as $event) {
            if (($periods[$event->id] ?? 1) !== $this->quarter) {
                continue;
            }

            if (! $this->game->isOurSide($event->home_away)) {
                $opponent++;

                continue;
            }

            $team++;

            if ($event->player_id === null) {
                continue;
            }

            $players[$event->player_id] = ($players[$event->player_id] ?? 0) + 1;
        }

        return [
            'team' => $team,
            'opponent' => $opponent,
            'players' => $players,
        ];
    }

    /**
     * Timeouts already called in the current half, or in the current overtime, and how many each team may call there.
     *
     * @return array{team: int, opponent: int, allowance: int}
     */
    #[Computed]
    public function timeouts(): array
    {
        $window = $this->timeoutWindow($this->quarter);

        $events = $this->collectedEvents()
            ->where('type', StatType::Timeout)
            ->whereIn('quarter', $window['quarters']);

        $team = 0;
        $opponent = 0;

        foreach ($events as $event) {
            if ($this->game->isOurSide($event->home_away)) {
                $team++;

                continue;
            }

            $opponent++;
        }

        return [
            'team' => $team,
            'opponent' => $opponent,
            'allowance' => $window['allowance'],
        ];
    }

    /**
     * The first half shares two timeouts, the second half shares three, and each overtime has one.
     *
     * @return array{quarters: list<int>, allowance: int}
     */
    private function timeoutWindow(int $quarter): array
    {
        if ($quarter <= 2) {
            return ['quarters' => [1, 2], 'allowance' => 2];
        }

        if ($quarter <= 4) {
            return ['quarters' => [3, 4], 'allowance' => 3];
        }

        return ['quarters' => [$quarter], 'allowance' => 1];
    }

    public function teamPoints(): int
    {
        if ($this->game->status === GameStatus::Played) {
            return $this->game->ourPoints();
        }

        return $this->pointsFor(false);
    }

    public function opponentDisplayPoints(): int
    {
        if ($this->game->status === GameStatus::Played) {
            return $this->game->opponentPoints();
        }

        return $this->pointsFor(true);
    }

    /**
     * @return list<array{id: int, label: string, player: string, playerLines: list<array{enters: bool, name: string}>, detail: string, missed: bool, points: int, quarter: int, clock: string, editable: bool, side: string|null}>
     */
    #[Computed]
    public function lines(): array
    {
        return $this->lineItems();
    }

    /**
     * @return list<array{id: int, label: string, player: string, playerLines: list<array{enters: bool, name: string}>, detail: string, missed: bool, points: int, quarter: int, clock: string, editable: bool, side: string|null}>
     */
    private function lineItems(): array
    {
        $events = $this->collectedEvents();

        $points = [];
        $shotsMade = [];
        $shotsAttempted = [];
        $counts = [];
        $lines = [];

        foreach ($events as $event) {
            if ($event->type->isPeriod()) {
                $lines[] = [
                    'id' => $event->id,
                    'label' => $this->periodMarkerLabel($event->type === StatType::QuarterStarted, $event->quarter),
                    'player' => '',
                    'playerLines' => [],
                    'detail' => '',
                    'missed' => false,
                    'points' => 0,
                    'quarter' => $event->quarter,
                    'clock' => $event->clock,
                    'editable' => false,
                    'side' => null,
                ];

                continue;
            }

            $ours = $this->game->isOurSide($event->home_away);
            $actorKey = $ours ? ($event->player_id === null ? 'team' : (string) $event->player_id) : 'opponent';
            $points[$actorKey] = ($points[$actorKey] ?? 0) + $event->type->points();

            $shooting = $event->type->shootingLog();

            if ($shooting !== null) {
                $shootingKey = $actorKey.'-'.$shooting;
                $shotsAttempted[$shootingKey] = ($shotsAttempted[$shootingKey] ?? 0) + 1;

                if ($event->type->points() > 0) {
                    $shotsMade[$shootingKey] = ($shotsMade[$shootingKey] ?? 0) + 1;
                }
            }

            $countKey = $actorKey.'-'.$event->type->value;
            $counts[$countKey] = ($counts[$countKey] ?? 0) + 1;

            if (! $ours) {
                $playerLabel = $this->game->opponent;
            } elseif ($event->player === null) {
                $playerLabel = $this->game->team->name;
            } else {
                $playerLabel = $this->loggedPlayer($event->player);
            }

            $playerLines = [];

            if ($event->type === StatType::Substitution && $event->player instanceof Player && $event->outgoingPlayer instanceof Player) {
                $playerLines = [
                    ['enters' => true, 'name' => $this->loggedPlayer($event->player)],
                    ['enters' => false, 'name' => $this->loggedPlayer($event->outgoingPlayer)],
                ];
            }

            $lines[] = [
                'id' => $event->id,
                'label' => $event->type->logLabel(),
                'player' => $playerLabel,
                'playerLines' => $playerLines,
                'detail' => $shooting === null
                    ? '('.$counts[$countKey].')'
                    : $points[$actorKey].'pts '.($shotsMade[$actorKey.'-'.$shooting] ?? 0).'-'.($shotsAttempted[$actorKey.'-'.$shooting] ?? 0).' '.$shooting,
                'missed' => $event->type->isMiss(),
                'points' => $ours ? $event->type->points() : 0,
                'quarter' => $event->quarter,
                'clock' => $event->clock,
                'editable' => $event->type->isEditableAction(),
                'side' => $event->home_away?->value,
            ];
        }

        return array_reverse($lines);
    }

    /**
     * @return array<string, mixed>
     */
    private function collectQueueConfig(): array
    {
        $labels = [];
        $editableTypes = [];
        $typeSwapGroups = [];

        foreach (StatType::cases() as $stat) {
            $labels[$stat->value] = __($stat->logLabel());

            if (! $stat->isAssignableStat()) {
                continue;
            }

            $tone = match (true) {
                $stat->isSolid() => 'made',
                $stat->isMiss() => 'missed',
                default => 'plain',
            };

            $editableTypes[] = [
                'value' => $stat->value,
                'label' => __($stat->logLabel()),
                'tone' => $tone,
            ];
        }

        foreach ([
            StatType::TwoPointMade,
            StatType::DefensiveRebound,
            StatType::Foul,
        ] as $representative) {
            $options = $representative->editableTypeOptions();

            if ($options === []) {
                continue;
            }

            $typeSwapGroups[] = array_map(
                fn (StatType $stat): string => $stat->value,
                $options,
            );
        }

        return [
            'gameId' => $this->game->id,
            'quarter' => $this->quarter,
            'awaitingQuarterStart' => $this->awaitingQuarterStart,
            'quarterEnded' => $this->quarterEnded,
            'periodOpen' => $this->periodIsOpen(),
            'gameStarted' => $this->gameStarted(),
            'canUndo' => $this->canUndo(),
            'court' => $this->lineup()['court'],
            'markShotLocation' => $this->game->mark_shot_location,
            'quarterMinutes' => $this->game->quarter_minutes,
            'overtimeMinutes' => $this->game->overtime_minutes,
            'finished' => $this->game->status === GameStatus::Played,
            'teamPoints' => $this->teamPoints(),
            'opponentPoints' => $this->opponentDisplayPoints(),
            'teamFouls' => $this->fouls()['team'],
            'opponentFouls' => $this->fouls()['opponent'],
            'teamTimeouts' => $this->timeouts()['team'],
            'opponentTimeouts' => $this->timeouts()['opponent'],
            'homeAway' => $this->game->home_away->value,
            'opponentName' => $this->game->opponent,
            'teamName' => $this->game->team->name,
            'homeColor' => $this->game->home_color->hex(),
            'awayColor' => $this->game->away_color->hex(),
            'shotCourt' => [
                'width' => ShotCourt::Width,
                'left' => ShotCourt::CourtLeft,
                'right' => ShotCourt::CourtRight,
                'top' => ShotCourt::BaselineY,
                'bottom' => ShotCourt::HalfCourtY,
                'hoopX' => ShotCourt::HoopX,
                'hoopY' => ShotCourt::hoopY(),
                'cornerX' => ShotCourt::cornerX(),
                'radius' => ShotCourt::threePointRadius(),
            ],
            'fieldGoals' => array_values(array_map(
                fn (StatType $stat): string => $stat->value,
                array_filter(StatType::cases(), fn (StatType $stat): bool => $stat->isFieldGoal()),
            )),
            'roster' => $this->orderedSquad()
                ->map(fn (Player $player): array => [
                    'id' => $player->id,
                    'name' => $player->name,
                    'number' => $player->number,
                    'label' => $this->loggedPlayer($player),
                    'fouls' => $this->fouls()['players'][$player->id] ?? 0,
                ])
                ->values()
                ->all(),
            'labels' => $labels,
            'editableTypes' => $editableTypes,
            'typeSwapGroups' => $typeSwapGroups,
            'pickerTitles' => [
                StatType::DefensiveRebound->value => __(StatType::DefensiveRebound->pickerTitle()),
                StatType::OffensiveRebound->value => __(StatType::OffensiveRebound->pickerTitle()),
                StatType::Turnover->value => __(StatType::Turnover->pickerTitle()),
                StatType::Steal->value => __(StatType::Steal->pickerTitle()),
                StatType::Assist->value => __(StatType::Assist->pickerTitle()),
                StatType::Block->value => __(StatType::Block->pickerTitle()),
                StatType::Foul->value => __(StatType::Foul->pickerTitle()),
            ],
            'templates' => [
                'startOfQuarter' => __('Start of Q:quarter', ['quarter' => ':quarter']),
                'startOfOvertime' => __('Start of OT:overtime', ['overtime' => ':overtime']),
                'endQuarter' => __('End Q:quarter', ['quarter' => ':quarter']),
                'startQuarter' => __('Start Q:quarter', ['quarter' => ':quarter']),
                'endOvertime' => __('End OT:overtime', ['overtime' => ':overtime']),
                'startOvertime' => __('Start OT:overtime', ['overtime' => ':overtime']),
                'endQuarterQuestion' => __('Do you want to end Q:quarter?', ['quarter' => ':quarter']),
                'endOvertimeQuestion' => __('Do you want to end OT:overtime?', ['overtime' => ':overtime']),
                'saving' => __('Saving…'),
                'markFive' => __('Mark 5 players.'),
            ],
        ];
    }

    public function render(): View
    {
        return view('livewire.statistics.record', [
            'collectQueue' => $this->collectQueueConfig(),
            'teamName' => $this->game->team->name,
            'opponentName' => $this->game->opponent,
            'opponentPoints' => $this->opponentDisplayPoints(),
            'substitutionLabel' => $this->game->events()->exists() ? 'Substitution' : 'Lineup',
            'periodName' => $this->periodName($this->quarter),
            'awaitingPeriodLabel' => $this->awaitingPeriodLabel(),
            'startPeriodAction' => $this->startPeriodAction(),
            'endPeriodAction' => $this->endPeriodAction(),
            'endPeriodQuestion' => $this->endPeriodQuestion(),
            'quarterTitle' => $this->quarterTitle(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'quarter' => ['required', 'integer', 'min:1', 'max:255'],
            'clock' => [
                'required',
                'regex:/^\d{1,2}:\d{2}$/',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! str_contains($value, ':')) {
                        return;
                    }

                    [$minutes, $seconds] = explode(':', $value);

                    if ((int) $minutes > 99 || (int) $seconds > 59) {
                        $fail(__('The clock must use minutes and seconds, such as 05:00.'));
                    }
                },
            ],
        ];
    }

    private function store(RecordGameEvent $recordGameEvent, ?int $playerId, bool $forOpponent, ?int $courtX = null, ?int $courtY = null): void
    {
        $this->authorize('collect', $this->game);

        if (! $this->periodIsOpen() || $this->pendingType === null || $this->pendingType === StatType::Substitution->value) {
            return;
        }

        $type = StatType::from($this->pendingType);

        if ($this->game->mark_shot_location && $type->isFieldGoal() && ($courtX === null || $courtY === null)) {
            return;
        }

        if (! $forOpponent && ($playerId === null || ! in_array($playerId, $this->lineup()['court'], true))) {
            throw (new ModelNotFoundException)->setModel(Player::class, [$playerId ?? 0]);
        }

        $validated = $this->validate($this->rules());

        $this->rememberCollectedEvent($recordGameEvent->handle(
            $this->game,
            $playerId,
            $type,
            $this->recordingQuarter((int) $validated['quarter']),
            $validated['clock'],
            $this->game->sideFor($forOpponent),
            $courtX,
            $courtY,
        ));

        $this->pendingType = null;
        $this->choosingPlayer = false;
        $this->finishShotLocation();
        unset($this->lines, $this->fouls, $this->timeouts, $this->lineup);
    }

    private function shouldMarkShotLocation(): bool
    {
        if ($this->pendingType === null || ! $this->game->mark_shot_location) {
            return false;
        }

        return StatType::from($this->pendingType)->isFieldGoal();
    }

    private function beginShotLocation(?int $playerId, bool $forOpponent): void
    {
        $this->shotPlayerId = $playerId;
        $this->shotForOpponent = $forOpponent;
        $this->choosingPlayer = false;
        $this->choosingShotLocation = true;
        $this->resetErrorBag('shotLocation');
    }

    private function finishShotLocation(): void
    {
        $this->choosingShotLocation = false;
        $this->shotPlayerId = null;
        $this->shotForOpponent = false;
    }

    private function recordPeriod(RecordGameEvent $recordGameEvent, StatType $type, int $quarter, string $clock): void
    {
        $this->rememberCollectedEvent($recordGameEvent->handle($this->game, null, $type, $quarter, $clock));

        unset($this->lines, $this->lineup);
    }

    private function restorePeriod(): void
    {
        $latest = GameEvent::query()
            ->where('game_id', $this->game->id)
            ->orderByDesc('id')
            ->first();

        if (! $latest instanceof GameEvent) {
            return;
        }

        $marker = $this->latestPeriodMarker();

        if (! $marker instanceof GameEvent) {
            $this->quarter = $latest->quarter;
            $this->clock = $latest->clock;

            return;
        }

        if ($marker->type === StatType::QuarterEnded) {
            $this->quarter = $marker->quarter + 1;
            $this->clock = '00:00';
            $this->awaitingQuarterStart = true;
            $this->quarterEnded = false;

            return;
        }

        $this->quarter = $marker->quarter;
        $this->clock = $latest->clock;
        $this->awaitingQuarterStart = false;
        $this->quarterEnded = false;
    }

    private function latestPeriodMarker(): ?GameEvent
    {
        return GameEvent::query()
            ->where('game_id', $this->game->id)
            ->whereIn('type', [StatType::QuarterStarted, StatType::QuarterEnded])
            ->orderByDesc('id')
            ->first();
    }

    private function recordingQuarter(int $selected): int
    {
        $started = $this->game->events()
            ->where('type', StatType::QuarterStarted)
            ->exists();

        if (! $started) {
            return $selected;
        }

        return $this->game->openPeriod();
    }

    #[Computed]
    public function periodIsOpen(): bool
    {
        if ($this->awaitingQuarterStart || $this->quarterEnded) {
            return false;
        }

        return $this->latestPeriodMarker()?->type === StatType::QuarterStarted;
    }

    private function lineupLocked(): bool
    {
        return $this->game->events()->where('type', StatType::QuarterStarted)->exists()
            && $this->game->players()->wherePivot('starter', true)->count() === 5;
    }

    private function teamPlayer(int $playerId): Player
    {
        $player = Player::query()
            ->where('team_id', $this->game->team_id)
            ->where('enabled', true)
            ->whereKey($playerId)
            ->first();

        if (! $player instanceof Player) {
            throw (new ModelNotFoundException)->setModel(Player::class, [$playerId]);
        }

        return $player;
    }

    private function loggedPlayer(Player $player): string
    {
        $number = $player->number === null ? '' : '#'.$player->number.' ';

        return trim($number.$player->name);
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function toggleId(array $ids, int $playerId): array
    {
        $selected = $this->selectedIds($ids);
        $next = [];
        $removed = false;

        foreach ($selected as $id) {
            if ($id === $playerId) {
                $removed = true;

                continue;
            }

            $next[] = $id;
        }

        if (! $removed) {
            $next[] = $playerId;
        }

        return $next;
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return list<int>
     */
    private function selectedIds(array $ids): array
    {
        $selected = [];

        foreach ($ids as $id) {
            $id = (int) $id;

            if (! in_array($id, $selected, true)) {
                $selected[] = $id;
            }
        }

        return $selected;
    }

    /**
     * @return Collection<int, Player>
     */
    #[Computed]
    public function attachedPlayers(): Collection
    {
        return $this->orderedSquad();
    }

    /**
     * @return Collection<int, Player>
     */
    private function orderedSquad(): Collection
    {
        return $this->game->players()->orderBy('number')->orderBy('name')->get();
    }

    private function clockIsZero(): bool
    {
        return in_array($this->clock, ['00:00', '0:00'], true);
    }

    public function nextPeriodCanStart(): bool
    {
        return ! ($this->game->status === GameStatus::Played && $this->awaitingQuarterStart && $this->quarter > 4);
    }

    private function openingClock(): string
    {
        $minutes = $this->periodIsOvertime($this->quarter)
            ? $this->game->overtime_minutes
            : $this->game->quarter_minutes;

        return sprintf('%02d:00', $minutes);
    }

    private function periodIsOvertime(int $quarter): bool
    {
        if ($quarter <= 4) {
            return false;
        }

        return $this->overtimePeriods()[$quarter] ?? false;
    }

    /**
     * A period after the fourth is overtime only when the previous one ended tied.
     *
     * @return array<int, bool>
     */
    private function overtimePeriods(): array
    {
        if ($this->overtimeByQuarter !== null) {
            return $this->overtimeByQuarter;
        }

        $team = 0;
        $opponent = 0;
        $flags = [];

        $events = GameEvent::query()
            ->where('game_id', $this->game->id)
            ->orderBy('id')
            ->get(['id', 'type', 'quarter', 'home_away']);

        foreach ($events as $event) {
            if ($event->home_away instanceof HomeAway) {
                $points = $event->type->points();

                if ($event->home_away === $this->game->home_away) {
                    $team += $points;
                } else {
                    $opponent += $points;
                }
            }

            if ($event->type === StatType::QuarterEnded && $event->quarter >= 4) {
                $flags[$event->quarter + 1] = $team === $opponent;
            }
        }

        return $this->overtimeByQuarter = $flags;
    }

    private function periodName(int $quarter): string
    {
        if ($this->periodIsOvertime($quarter)) {
            return 'OT'.($quarter - 4);
        }

        return 'Q'.$quarter;
    }

    private function awaitingPeriodLabel(): string
    {
        if (! $this->nextPeriodCanStart()) {
            return $this->periodName($this->quarter - 1);
        }

        if ($this->periodIsOvertime($this->quarter)) {
            return __('Start of OT:overtime', ['overtime' => $this->quarter - 4]);
        }

        return __('Start of Q:quarter', ['quarter' => $this->quarter]);
    }

    private function startPeriodAction(): string
    {
        if ($this->periodIsOvertime($this->quarter)) {
            return __('Start OT:overtime', ['overtime' => $this->quarter - 4]);
        }

        return __('Start Q:quarter', ['quarter' => $this->quarter]);
    }

    private function endPeriodAction(): string
    {
        if ($this->periodIsOvertime($this->quarter)) {
            return __('End OT:overtime', ['overtime' => $this->quarter - 4]);
        }

        return __('End Q:quarter', ['quarter' => $this->quarter]);
    }

    private function endPeriodQuestion(): string
    {
        if ($this->periodIsOvertime($this->quarter)) {
            return __('Do you want to end OT:overtime?', ['overtime' => $this->quarter - 4]);
        }

        return __('Do you want to end Q:quarter?', ['quarter' => $this->quarter]);
    }

    private function quarterTitle(): string
    {
        if ($this->periodIsOvertime($this->quarter)) {
            return __('End OT:overtime', ['overtime' => $this->quarter - 4]);
        }

        if ($this->quarter <= 4) {
            return __('End '.$this->quarterOrdinal().' quarter');
        }

        return __('End Q:quarter', ['quarter' => $this->quarter]);
    }

    private function periodMarkerLabel(bool $started, int $quarter): string
    {
        if ($this->periodIsOvertime($quarter)) {
            $overtime = $quarter - 4;

            return $started
                ? __('start of OT:overtime', ['overtime' => $overtime])
                : __('end of OT:overtime', ['overtime' => $overtime]);
        }

        return $started
            ? __('start of Q:quarter', ['quarter' => $quarter])
            : __('end of Q:quarter', ['quarter' => $quarter]);
    }

    private function quarterOrdinal(): string
    {
        return match ($this->quarter) {
            1 => '1st',
            2 => '2nd',
            3 => '3rd',
            default => '4th',
        };
    }

    private function pointsFor(bool $forOpponent): int
    {
        return (int) $this->collectedEvents()
            ->where('home_away', $this->game->sideFor($forOpponent))
            ->sum(fn (GameEvent $event): int => $event->type->points());
    }

    /**
     * @return Collection<int, GameEvent>
     */
    private function collectedEvents(): Collection
    {
        return $this->loadedEvents ??= GameEvent::query()
            ->with(['player', 'outgoingPlayer'])
            ->where('game_id', $this->game->id)
            ->orderBy('id')
            ->get();
    }

    private function rememberCollectedEvent(GameEvent $event): void
    {
        $this->lineupMemo = null;
        $this->lineupMemoKey = null;

        if (! $this->loadedEvents instanceof Collection) {
            return;
        }

        $event->loadMissing(['player', 'outgoingPlayer']);
        $this->loadedEvents->push($event);
    }

    private function forgetCollectedEvents(): void
    {
        $this->loadedEvents = null;
        $this->lineupMemo = null;
        $this->lineupMemoKey = null;
    }

    private function applyCollectedClock(?string $clock): void
    {
        if ($clock === null || preg_match('/^\d{1,2}:\d{2}$/', $clock) !== 1) {
            return;
        }

        [$minutes, $seconds] = explode(':', $clock);

        if ((int) $minutes > 99 || (int) $seconds > 59) {
            return;
        }

        $this->clock = sprintf('%02d:%02d', (int) $minutes, (int) $seconds);
    }
}
