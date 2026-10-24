<?php

namespace App\Models;

use App\Enums\FoulKind;
use App\Enums\HomeAway;
use App\Enums\StatType;
use Carbon\CarbonImmutable;
use Database\Factories\GameEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

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
     * Game order: period ascending, countdown clock descending, then id.
     *
     * @param  Builder<GameEvent>  $query
     * @return Builder<GameEvent>
     */
    public function scopeChronological(Builder $query): Builder
    {
        return $query
            ->orderBy('quarter')
            ->orderByDesc('clock')
            ->orderBy('id');
    }

    /**
     * @param  Collection<int, GameEvent>  $events
     * @return EloquentCollection<int, GameEvent>
     */
    public static function sortChronologically(Collection $events): EloquentCollection
    {
        return new EloquentCollection(
            $events
                ->sort(function (GameEvent $left, GameEvent $right): int {
                    if ($left->quarter !== $right->quarter) {
                        return $left->quarter <=> $right->quarter;
                    }

                    if ($left->clock !== $right->clock) {
                        return $right->clock <=> $left->clock;
                    }

                    return $left->id <=> $right->id;
                })
                ->values()
                ->all()
        );
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
