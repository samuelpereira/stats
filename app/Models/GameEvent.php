<?php

namespace App\Models;

use App\Enums\FoulKind;
use App\Enums\HomeAway;
use App\Enums\StatType;
use Carbon\CarbonImmutable;
use Database\Factories\GameEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $game_id
 * @property int|null $player_id
 * @property int|null $outgoing_player_id
 * @property HomeAway|null $home_away
 * @property StatType $type
 * @property FoulKind|null $foul_kind
 * @property int $quarter
 * @property string $clock
 * @property int|null $court_x
 * @property int|null $court_y
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Game $game
 * @property-read Player|null $player
 * @property-read Player|null $outgoingPlayer
 */
#[Fillable(['game_id', 'player_id', 'outgoing_player_id', 'home_away', 'type', 'foul_kind', 'quarter', 'clock', 'court_x', 'court_y'])]
class GameEvent extends Model
{
    /** @use HasFactory<GameEventFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StatType::class,
            'foul_kind' => FoulKind::class,
            'quarter' => 'integer',
            'court_x' => 'integer',
            'court_y' => 'integer',
            'home_away' => HomeAway::class,
        ];
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * @return BelongsTo<Player, $this>
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * @return BelongsTo<Player, $this>
     */
    public function outgoingPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'outgoing_player_id');
    }
}
