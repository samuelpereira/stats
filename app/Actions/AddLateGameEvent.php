<?php

namespace App\Actions;

use App\Enums\FoulKind;
use App\Enums\StatType;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Player;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adds an action to a period that already ended, during the game or after it.
 * A played game gets its saved final score refreshed from the log.
 */
class AddLateGameEvent
{
    public function __construct(private RecordGameEvent $recordGameEvent) {}

    public function handle(
        User $actor,
        Game $game,
        int $quarter,
        string $clock,
        StatType $type,
        ?int $playerId,
        bool $forOpponent,
        ?FoulKind $foulKind = null,
    ): GameEvent {
        if (! $actor->can('addLateEvent', $game)) {
            throw new AuthorizationException;
        }

        if ($type !== StatType::Timeout && ! $type->isAssignableStat()) {
            $this->invalid('type', __('The collected action is not valid.'));
        }

        if (! in_array($quarter, $game->endedPeriods(), true)) {
            $this->invalid('quarter', __('Choose a period that has already ended.'));
        }

        $clock = $this->clock($game, $quarter, $clock);

        if ($type !== StatType::Foul) {
            $foulKind = null;
        }

        if ($type === StatType::Timeout || $forOpponent) {
            $playerId = null;
        } elseif ($playerId === null) {
            if ($type !== StatType::Foul || ! $foulKind?->allowsCoach()) {
                $this->invalid('actor', __('Choose who made the action.'));
            }
        } else {
            $this->courtPlayer($game, $playerId, $quarter, $clock);
        }

        return DB::transaction(function () use ($game, $quarter, $clock, $type, $playerId, $forOpponent, $foulKind): GameEvent {
            $event = $this->recordGameEvent->handle(
                $game,
                $playerId,
                $type,
                $quarter,
                $clock,
                $game->sideFor($forOpponent),
                null,
                null,
                $foulKind,
            );

            $game->refreshFinalScore();

            return $event;
        });
    }

    private function courtPlayer(Game $game, int $playerId, int $quarter, string $clock): Player
    {
        $player = $game->players()->whereKey($playerId)->first();

        if (! $player instanceof Player) {
            $this->invalid('actor', __('Choose who made the action.'));
        }

        if (! in_array($player->id, $game->courtAtMoment($quarter, $clock), true)) {
            $this->invalid('actor', __('The player was not on the court at that moment.'));
        }

        return $player;
    }

    private function clock(Game $game, int $quarter, string $clock): string
    {
        if (preg_match('/^\d{1,2}:\d{2}$/', $clock) !== 1) {
            $this->invalid('clock', __('The clock must use minutes and seconds, such as 05:00.'));
        }

        [$minutes, $seconds] = array_map('intval', explode(':', $clock));

        if ($seconds > 59 || ($minutes * 60) + $seconds > $game->periodMinutes($quarter) * 60) {
            $this->invalid('clock', __('The clock must be within the period length.'));
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
