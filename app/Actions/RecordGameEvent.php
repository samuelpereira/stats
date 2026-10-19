<?php

namespace App\Actions;

use App\Enums\FoulKind;
use App\Enums\HomeAway;
use App\Enums\StatType;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Player;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

class RecordGameEvent
{
    public function handle(
        Game $game,
        ?int $playerId,
        StatType $type,
        int $quarter,
        string $clock,
        ?HomeAway $side = null,
        ?int $courtX = null,
        ?int $courtY = null,
        ?FoulKind $foulKind = null,
    ): GameEvent {
        if ($type->isPeriod()) {
            return GameEvent::query()->create([
                'game_id' => $game->id,
                'player_id' => null,
                'home_away' => null,
                'type' => $type,
                'foul_kind' => null,
                'quarter' => $quarter,
                'clock' => $this->clock($clock),
            ]);
        }

        if ($type === StatType::Substitution) {
            throw new InvalidArgumentException('A substitution needs the player who leaves.');
        }

        if (! $side instanceof HomeAway) {
            throw new InvalidArgumentException('A game event needs a home or away side.');
        }

        if ($type !== StatType::Foul) {
            $foulKind = null;
        }

        if ($type === StatType::Timeout || $side !== $game->home_away) {
            return GameEvent::query()->create([
                'game_id' => $game->id,
                'player_id' => null,
                'home_away' => $side,
                'type' => $type,
                'foul_kind' => $foulKind,
                'quarter' => $quarter,
                'clock' => $this->clock($clock),
                'court_x' => $courtX,
                'court_y' => $courtY,
            ]);
        }

        if ($playerId === null) {
            if ($type !== StatType::Foul || ! $foulKind?->allowsCoach()) {
                throw new InvalidArgumentException('A game event needs a player.');
            }

            return GameEvent::query()->create([
                'game_id' => $game->id,
                'player_id' => null,
                'home_away' => $side,
                'type' => $type,
                'foul_kind' => $foulKind,
                'quarter' => $quarter,
                'clock' => $this->clock($clock),
                'court_x' => $courtX,
                'court_y' => $courtY,
            ]);
        }

        $player = Player::query()
            ->where('team_id', $game->team_id)
            ->whereKey($playerId)
            ->first();

        if (! $player instanceof Player) {
            throw (new ModelNotFoundException)->setModel(Player::class, [$playerId]);
        }

        return GameEvent::query()->create([
            'game_id' => $game->id,
            'player_id' => $player->id,
            'home_away' => $side,
            'type' => $type,
            'foul_kind' => $foulKind,
            'quarter' => $quarter,
            'clock' => $this->clock($clock),
            'court_x' => $courtX,
            'court_y' => $courtY,
        ]);
    }

    public function substitute(Game $game, int $incomingId, int $outgoingId, int $quarter, string $clock): GameEvent
    {
        $incoming = $this->squadPlayer($game, $incomingId);
        $outgoing = $this->squadPlayer($game, $outgoingId);

        if ($incoming->id === $outgoing->id) {
            throw (new ModelNotFoundException)->setModel(Player::class, [$incomingId]);
        }

        return GameEvent::query()->create([
            'game_id' => $game->id,
            'player_id' => $incoming->id,
            'outgoing_player_id' => $outgoing->id,
            'home_away' => $game->home_away,
            'type' => StatType::Substitution,
            'foul_kind' => null,
            'quarter' => $quarter,
            'clock' => $this->clock($clock),
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
        [$minutes, $seconds] = explode(':', $clock);

        return sprintf('%02d:%02d', (int) $minutes, (int) $seconds);
    }
}
