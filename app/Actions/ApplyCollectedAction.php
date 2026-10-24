<?php

namespace App\Actions;

use App\Enums\FoulKind;
use App\Enums\GameStatus;
use App\Enums\HomeAway;
use App\Enums\StatType;
use App\Models\CollectCommand;
use App\Models\Game;
use App\Models\GameEvent;
use App\Support\ShotCourt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplyCollectedAction
{
    public function __construct(
        private RecordGameEvent $recordGameEvent,
        private DeleteLatestGameEvent $deleteLatestGameEvent,
        private UpdateGameEventType $updateGameEventType,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Game $game, string $id, string $operation, array $payload): void
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id) !== 1) {
            $this->invalid('id', __('The collected action is not valid.'));
        }

        DB::transaction(function () use ($game, $id, $operation, $payload): void {
            Game::query()->whereKey($game->id)->lockForUpdate()->first();

            $inserted = CollectCommand::query()->insertOrIgnore([
                'id' => $id,
                'game_id' => $game->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted === 0) {
                return;
            }

            $this->apply($game, $operation, $payload);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function apply(Game $game, string $operation, array $payload): void
    {
        match ($operation) {
            'record' => $this->record($game, $payload),
            'substitution' => $this->substitute($game, $payload),
            'timeout' => $this->timeout($game, $payload),
            'period' => $this->period($game, $payload),
            'undo' => $this->deleteLatestGameEvent->handle($game),
            'edit' => $this->edit($game, $payload),
            default => $this->invalid('operation', __('The collected action is not valid.')),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function record(Game $game, array $payload): void
    {
        $this->assertPeriodOpen($game);

        $type = $this->stat($payload['type'] ?? null);
        $clock = $this->clock($payload['clock'] ?? null);
        $quarter = $this->quarter($game, $payload['quarter'] ?? null);
        $forOpponent = $this->boolean($payload['forOpponent'] ?? false);

        if ($type->isPeriod() || $type === StatType::Substitution || $type === StatType::Timeout) {
            $this->invalid('type', __('The collected action is not valid.'));
        }

        $courtX = $this->nullableInt($payload['courtX'] ?? null);
        $courtY = $this->nullableInt($payload['courtY'] ?? null);

        if ($game->mark_shot_location && $type->isFieldGoal()) {
            if ($courtX === null || $courtY === null || ! ShotCourt::allows($type, $courtX, $courtY)) {
                $this->invalid('shotLocation', __('Mark the shot inside the 2-point area for 2P, or outside for 3P.'));
            }
        } else {
            $courtX = null;
            $courtY = null;
        }

        $foulKind = $this->foulKind($type, $payload['foulKind'] ?? null);
        $playerId = null;

        if (! $forOpponent) {
            $rawPlayerId = $payload['playerId'] ?? null;

            if ($rawPlayerId === null || $rawPlayerId === '') {
                if ($type !== StatType::Foul || ! $foulKind?->allowsCoach()) {
                    $this->invalid('playerId', __('The collected action is not valid.'));
                }
            } else {
                $playerId = $this->requiredInt($rawPlayerId, 'playerId');
                $court = $game->lineupState($clock, true)['court'];

                if (! in_array($playerId, $court, true)) {
                    $this->invalid('playerId', __('The collected action is not valid.'));
                }
            }
        }

        $this->recordGameEvent->handle(
            $game,
            $playerId,
            $type,
            $quarter,
            $clock,
            $game->sideFor($forOpponent),
            $courtX,
            $courtY,
            $foulKind,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function substitute(Game $game, array $payload): void
    {
        $this->assertCanSubstitute($game);

        $clock = $this->clock($payload['clock'] ?? null);
        $quarter = $this->substitutionQuarter($game, $payload['quarter'] ?? null);
        $playerIds = $payload['playerIds'] ?? null;

        if (! is_array($playerIds) || count($playerIds) !== 5) {
            $this->invalid('court', __('Mark 5 players.'));
        }

        $marked = [];

        foreach ($playerIds as $playerId) {
            if (! is_int($playerId) && ! (is_string($playerId) && ctype_digit($playerId))) {
                $this->invalid('court', __('Mark 5 players.'));
            }

            $marked[] = (int) $playerId;
        }

        $marked = array_values(array_unique($marked));

        if (count($marked) !== 5) {
            $this->invalid('court', __('Mark 5 players.'));
        }

        $squadIds = $game->players()
            ->orderBy('number')
            ->orderBy('name')
            ->pluck('players.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        foreach ($marked as $id) {
            if (! in_array($id, $squadIds, true)) {
                $this->invalid('court', __('Mark 5 players.'));
            }
        }

        $current = $game->lineupState($clock, true)['court'];
        $leaving = [];
        $entering = [];

        foreach ($squadIds as $id) {
            $onCourt = in_array($id, $current, true);
            $stays = in_array($id, $marked, true);

            if ($onCourt && ! $stays) {
                $leaving[] = $id;
            }

            if (! $onCourt && $stays) {
                $entering[] = $id;
            }
        }

        foreach ($leaving as $index => $outgoingId) {
            if (! isset($entering[$index])) {
                $this->invalid('court', __('Mark 5 players.'));
            }

            $this->recordGameEvent->substitute(
                $game,
                $entering[$index],
                $outgoingId,
                $quarter,
                $clock,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function timeout(Game $game, array $payload): void
    {
        $this->assertPeriodOpen($game);

        $side = HomeAway::tryFrom(is_string($payload['side'] ?? null) ? $payload['side'] : '');

        if (! $side instanceof HomeAway) {
            $this->invalid('side', __('The collected action is not valid.'));
        }

        $this->recordGameEvent->handle(
            $game,
            null,
            StatType::Timeout,
            $this->quarter($game, $payload['quarter'] ?? null),
            $this->clock($payload['clock'] ?? null),
            $side,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function period(Game $game, array $payload): void
    {
        $type = $this->stat($payload['type'] ?? null);
        $quarter = $this->requiredInt($payload['quarter'] ?? null, 'quarter');
        $clock = $this->clock($payload['clock'] ?? null);
        $marker = $this->latestPeriodMarker($game);

        if ($type === StatType::QuarterEnded) {
            if ($clock !== '00:00') {
                $this->invalid('clock', __('The collected action is not valid.'));
            }

            if (! $marker instanceof GameEvent || $marker->type !== StatType::QuarterStarted || $marker->quarter !== $quarter) {
                $this->invalid('quarter', __('The collected action is not valid.'));
            }

            $this->recordGameEvent->handle($game, null, StatType::QuarterEnded, $quarter, '00:00');

            return;
        }

        if ($type !== StatType::QuarterStarted) {
            $this->invalid('type', __('The collected action is not valid.'));
        }

        if ($marker instanceof GameEvent && $marker->type === StatType::QuarterStarted) {
            $this->invalid('quarter', __('The collected action is not valid.'));
        }

        if ($marker instanceof GameEvent && $marker->type === StatType::QuarterEnded && $quarter !== $marker->quarter + 1) {
            $this->invalid('quarter', __('The collected action is not valid.'));
        }

        if (! $marker instanceof GameEvent && $quarter !== 1) {
            $this->invalid('quarter', __('The collected action is not valid.'));
        }

        if ($game->status === GameStatus::Played) {
            $this->invalid('quarter', __('The collected action is not valid.'));
        }

        $this->recordGameEvent->handle($game, null, StatType::QuarterStarted, $quarter, $clock);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function edit(Game $game, array $payload): void
    {
        $type = $this->stat($payload['type'] ?? null);
        $eventId = $this->requiredInt($payload['eventId'] ?? null, 'eventId');
        $forOpponent = $this->boolean($payload['forOpponent'] ?? false);
        $clock = $this->clock($payload['clock'] ?? null);
        $playerId = null;
        $outgoingPlayerId = null;

        $foulKind = $this->foulKind($type, $payload['foulKind'] ?? null);

        if ($type === StatType::Substitution) {
            $playerId = $this->requiredInt($payload['playerId'] ?? null, 'playerId');
            $outgoingPlayerId = $this->requiredInt($payload['outgoingPlayerId'] ?? null, 'outgoingPlayerId');
        } elseif ($type->isAssignableStat()) {
            $rawPlayerId = $payload['playerId'] ?? null;

            if ($forOpponent) {
                $playerId = null;
            } elseif ($rawPlayerId === null || $rawPlayerId === '') {
                if ($type !== StatType::Foul || ! $foulKind?->allowsCoach()) {
                    $this->invalid('playerId', __('The collected action is not valid.'));
                }
            } else {
                $playerId = $this->requiredInt($rawPlayerId, 'playerId');
            }
        } elseif ($type !== StatType::Timeout) {
            $this->invalid('type', __('The collected action is not valid.'));
        }

        $this->updateGameEventType->handle(
            $game,
            $eventId,
            $type,
            $playerId,
            $clock,
            $outgoingPlayerId,
            $forOpponent,
            $foulKind,
        );
    }

    private function assertPeriodOpen(Game $game): void
    {
        $marker = $this->latestPeriodMarker($game);

        if (! $marker instanceof GameEvent || $marker->type !== StatType::QuarterStarted) {
            $this->invalid('period', __('The collected action is not valid.'));
        }
    }

    private function assertCanSubstitute(Game $game): void
    {
        $marker = $this->latestPeriodMarker($game);

        if (! $marker instanceof GameEvent) {
            $this->invalid('period', __('The collected action is not valid.'));
        }

        if ($marker->type === StatType::QuarterStarted || $marker->type === StatType::QuarterEnded) {
            return;
        }

        $this->invalid('period', __('The collected action is not valid.'));
    }

    private function substitutionQuarter(Game $game, mixed $quarter): int
    {
        $selected = $this->requiredInt($quarter, 'quarter');
        $marker = $this->latestPeriodMarker($game);

        if (! $marker instanceof GameEvent) {
            return $selected;
        }

        if ($marker->type === StatType::QuarterStarted) {
            if ($selected !== $marker->quarter) {
                $this->invalid('quarter', __('The collected action is not valid.'));
            }

            return $marker->quarter;
        }

        $expected = $marker->quarter + 1;

        if ($selected !== $expected) {
            $this->invalid('quarter', __('The collected action is not valid.'));
        }

        return $expected;
    }

    private function latestPeriodMarker(Game $game): ?GameEvent
    {
        $marker = $game->events()
            ->whereIn('type', [StatType::QuarterStarted, StatType::QuarterEnded])
            ->orderByDesc('id')
            ->first();

        return $marker instanceof GameEvent ? $marker : null;
    }

    private function quarter(Game $game, mixed $quarter): int
    {
        $selected = $this->requiredInt($quarter, 'quarter');
        $started = $game->events()->where('type', StatType::QuarterStarted)->exists();

        if (! $started) {
            return $selected;
        }

        $open = $game->openPeriod();

        if ($selected !== $open) {
            $this->invalid('quarter', __('The collected action is not valid.'));
        }

        return $open;
    }

    private function stat(mixed $type): StatType
    {
        if (! is_string($type)) {
            $this->invalid('type', __('The collected action is not valid.'));
        }

        $stat = StatType::tryFrom($type);

        if (! $stat instanceof StatType) {
            $this->invalid('type', __('The collected action is not valid.'));
        }

        return $stat;
    }

    private function foulKind(StatType $type, mixed $kind): ?FoulKind
    {
        if ($type !== StatType::Foul || $kind === null || $kind === '') {
            return null;
        }

        if (! is_string($kind)) {
            $this->invalid('foulKind', __('The collected action is not valid.'));
        }

        $foulKind = FoulKind::tryFrom($kind);

        if (! $foulKind instanceof FoulKind) {
            $this->invalid('foulKind', __('The collected action is not valid.'));
        }

        return $foulKind;
    }

    private function clock(mixed $clock): string
    {
        if (! is_string($clock) || preg_match('/^\d{1,2}:\d{2}$/', $clock) !== 1) {
            $this->invalid('clock', __('The clock must use minutes and seconds, such as 05:00.'));
        }

        [$minutes, $seconds] = explode(':', $clock);

        if ((int) $minutes > 99 || (int) $seconds > 59) {
            $this->invalid('clock', __('The clock must use minutes and seconds, such as 05:00.'));
        }

        return sprintf('%02d:%02d', (int) $minutes, (int) $seconds);
    }

    private function requiredInt(mixed $value, string $field): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        $this->invalid($field, __('The collected action is not valid.'));
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->requiredInt($value, 'shotLocation');
    }

    private function boolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([
            $field => $message,
        ]);
    }
}
