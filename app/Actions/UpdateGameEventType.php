<?php

namespace App\Actions;

use App\Enums\FoulKind;
use App\Enums\StatType;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Player;
use App\Support\ShotCourt;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class UpdateGameEventType
{
    public function handle(
        Game $game,
        int $eventId,
        StatType $type,
        ?int $playerId,
        string $clock,
        ?int $outgoingPlayerId = null,
        bool $forOpponent = false,
        ?FoulKind $foulKind = null,
        ?int $courtX = null,
        ?int $courtY = null,
        bool $courtProvided = false,
    ): void {
        $event = $game->events()->whereKey($eventId)->first();

        if (! $event instanceof GameEvent || ! $event->type->isEditableAction()) {
            throw (new ModelNotFoundException)->setModel(GameEvent::class, [$eventId]);
        }

        $clock = $this->clock($clock);

        if ($event->type === StatType::Substitution) {
            $this->updateSubstitution($event, $type, $clock);

            return;
        }

        if ($event->type === StatType::Timeout) {
            $this->updateTimeout($game, $event, $type, $playerId, $forOpponent, $clock);

            return;
        }

        $this->updateStat($game, $event, $type, $playerId, $forOpponent, $clock, $foulKind, $courtX, $courtY, $courtProvided);
    }

    private function updateSubstitution(GameEvent $event, StatType $type, string $clock): void
    {
        if ($type !== StatType::Substitution) {
            throw (new ModelNotFoundException)->setModel(GameEvent::class, [$event->id]);
        }

        $event->update([
            'clock' => $clock,
        ]);
    }

    private function updateTimeout(
        Game $game,
        GameEvent $event,
        StatType $type,
        ?int $playerId,
        bool $forOpponent,
        string $clock,
    ): void {
        if ($type !== StatType::Timeout || $playerId !== null) {
            throw (new ModelNotFoundException)->setModel(GameEvent::class, [$event->id]);
        }

        $event->update([
            'type' => StatType::Timeout,
            'player_id' => null,
            'outgoing_player_id' => null,
            'home_away' => $game->sideFor($forOpponent),
            'foul_kind' => null,
            'clock' => $clock,
            'court_x' => null,
            'court_y' => null,
        ]);
    }

    private function updateStat(
        Game $game,
        GameEvent $event,
        StatType $type,
        ?int $playerId,
        bool $forOpponent,
        string $clock,
        ?FoulKind $foulKind,
        ?int $courtX = null,
        ?int $courtY = null,
        bool $courtProvided = false,
    ): void {
        if (! $event->type->isAssignableStat() || ! $event->type->canSwapTo($type)) {
            throw (new ModelNotFoundException)->setModel(GameEvent::class, [$event->id]);
        }

        if ($type !== StatType::Foul) {
            $foulKind = null;
        }

        [$nextCourtX, $nextCourtY] = $this->courtForType($game, $event, $type, $courtX, $courtY, $courtProvided);

        if ($forOpponent) {
            $event->update([
                'type' => $type,
                'player_id' => null,
                'outgoing_player_id' => null,
                'home_away' => $game->sideFor(true),
                'foul_kind' => $foulKind,
                'clock' => $clock,
                'court_x' => $nextCourtX,
                'court_y' => $nextCourtY,
            ]);

            return;
        }

        if ($playerId === null) {
            if ($type !== StatType::Foul || ! $foulKind?->allowsCoach()) {
                throw (new ModelNotFoundException)->setModel(GameEvent::class, [$event->id]);
            }

            $event->update([
                'type' => $type,
                'player_id' => null,
                'outgoing_player_id' => null,
                'home_away' => $game->home_away,
                'foul_kind' => $foulKind,
                'clock' => $clock,
                'court_x' => $nextCourtX,
                'court_y' => $nextCourtY,
            ]);

            return;
        }

        $player = $this->squadPlayer($game, $playerId);

        if ($player->id !== $event->player_id && ! in_array($player->id, $game->courtAt($event), true)) {
            throw ValidationException::withMessages([
                'editingPlayerId' => __('The player was not on the court at that moment.'),
            ]);
        }

        $event->update([
            'type' => $type,
            'player_id' => $player->id,
            'outgoing_player_id' => null,
            'home_away' => $game->home_away,
            'foul_kind' => $foulKind,
            'clock' => $clock,
            'court_x' => $nextCourtX,
            'court_y' => $nextCourtY,
        ]);
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function courtForType(
        Game $game,
        GameEvent $event,
        StatType $type,
        ?int $courtX,
        ?int $courtY,
        bool $courtProvided,
    ): array {
        if (! $type->isFieldGoal()) {
            return [null, null];
        }

        if ($courtProvided) {
            if ($courtX === null || $courtY === null) {
                return [null, null];
            }

            if ($game->mark_shot_location && ! ShotCourt::allows($type, $courtX, $courtY)) {
                throw ValidationException::withMessages([
                    'shotLocation' => __('Mark the shot inside the 2-point area for 2P, or outside for 3P.'),
                ]);
            }

            return [$courtX, $courtY];
        }

        $existingX = $event->court_x;
        $existingY = $event->court_y;

        if ($existingX === null || $existingY === null) {
            return [null, null];
        }

        if ($game->mark_shot_location && ! ShotCourt::allows($type, $existingX, $existingY)) {
            return [null, null];
        }

        return [$existingX, $existingY];
    }

    private function squadPlayer(Game $game, int $playerId): Player
    {
        $player = $game->players()->whereKey($playerId)->first();

        if (! $player instanceof Player) {
            throw (new ModelNotFoundException)->setModel(Player::class, [$playerId]);
        }

        return $player;
    }

    private function clock(string $clock): string
    {
        if (preg_match('/^\d{1,2}:\d{2}$/', $clock) !== 1) {
            throw ValidationException::withMessages([
                'editingEventClock' => __('The clock must use minutes and seconds, such as 05:00.'),
            ]);
        }

        [$minutes, $seconds] = explode(':', $clock);

        if ((int) $minutes > 99 || (int) $seconds > 59) {
            throw ValidationException::withMessages([
                'editingEventClock' => __('The clock must use minutes and seconds, such as 05:00.'),
            ]);
        }

        return sprintf('%02d:%02d', (int) $minutes, (int) $seconds);
    }
}
