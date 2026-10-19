<?php

namespace App\Actions;

use App\Enums\FoulKind;
use App\Enums\StatType;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Player;
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

        $this->updateStat($game, $event, $type, $playerId, $forOpponent, $clock, $foulKind);
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
    ): void {
        if (! $event->type->isAssignableStat() || ! $event->type->canSwapTo($type)) {
            throw (new ModelNotFoundException)->setModel(GameEvent::class, [$event->id]);
        }

        if ($type !== StatType::Foul) {
            $foulKind = null;
        }

        if ($forOpponent) {
            $event->update([
                'type' => $type,
                'player_id' => null,
                'outgoing_player_id' => null,
                'home_away' => $game->sideFor(true),
                'foul_kind' => $foulKind,
                'clock' => $clock,
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
            ]);

            return;
        }

        $player = $this->squadPlayer($game, $playerId);

        $event->update([
            'type' => $type,
            'player_id' => $player->id,
            'outgoing_player_id' => null,
            'home_away' => $game->home_away,
            'foul_kind' => $foulKind,
            'clock' => $clock,
        ]);
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
