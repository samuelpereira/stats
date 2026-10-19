<?php

namespace App\Models;

use App\Enums\GameStatus;
use App\Enums\Gender;
use App\Models\Concerns\HasPublicUuid;
use Carbon\CarbonImmutable;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $club_id
 * @property int $season_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $competition
 * @property string|null $age_group
 * @property Gender $gender
 * @property string|null $fpb_url
 * @property string|null $photo
 * @property int $order
 * @property bool $enabled
 * @property string|null $share_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read string|null $photo_url
 * @property-read Club $club
 * @property-read Season $season
 */
#[Fillable(['club_id', 'season_id', 'name', 'slug', 'description', 'competition', 'age_group', 'gender', 'fpb_url', 'photo', 'order', 'enabled'])]
#[Hidden(['share_token'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'order' => 'integer',
            'enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return BelongsTo<Season, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * @return HasMany<Player, $this>
     */
    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    /**
     * @return HasMany<Game, $this>
     */
    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public static function slugFromName(string $name): string
    {
        return Str::slug($name);
    }

    /**
     * Played games with a stored score, newest first.
     *
     * @return Collection<int, Game>
     */
    public function gamesHistory(): Collection
    {
        return $this->scoredPlayedGames()
            ->orderByDesc('date_time')
            ->get();
    }

    /**
     * @return list<array{opponent: string, wins: int, losses: int, record: string, games: int}>
     */
    public function matchupHistory(): array
    {
        /** @var array<string, array{opponent: string, wins: int, losses: int, games: int}> $rows */
        $rows = [];

        foreach ($this->scoredPlayedGames()->orderBy('date_time')->get() as $game) {
            $opponent = $game->opponent;

            if (! isset($rows[$opponent])) {
                $rows[$opponent] = [
                    'opponent' => $opponent,
                    'wins' => 0,
                    'losses' => 0,
                    'games' => 0,
                ];
            }

            $rows[$opponent]['games']++;

            $ours = $game->ourPoints();
            $theirs = $game->opponentPoints();

            if ($ours > $theirs) {
                $rows[$opponent]['wins']++;
            } elseif ($ours < $theirs) {
                $rows[$opponent]['losses']++;
            }
        }

        $history = array_values(array_map(
            fn (array $row): array => [
                ...$row,
                'record' => $row['wins'].'-'.$row['losses'],
            ],
            $rows,
        ));

        usort($history, function (array $left, array $right): int {
            if ($left['games'] !== $right['games']) {
                return $right['games'] <=> $left['games'];
            }

            return strcasecmp($left['opponent'], $right['opponent']);
        });

        return $history;
    }

    /**
     * @return array{players: list<array<string, mixed>>, team: array<string, mixed>, opponent: array<string, mixed>}
     */
    public function seasonBoxScore(): array
    {
        $players = $this->players()->orderBy('number')->orderBy('name')->get();
        $statKeys = [
            'points',
            'twoMade',
            'twoAttempts',
            'threeMade',
            'threeAttempts',
            'freeMade',
            'freeAttempts',
            'offensiveRebounds',
            'defensiveRebounds',
            'assists',
            'turnovers',
            'steals',
            'blocks',
            'fouls',
            'plusMinus',
        ];

        $totals = [];
        $seconds = [];
        $gamesPlayed = [];

        foreach ($players as $player) {
            $totals[$player->id] = array_fill_keys($statKeys, 0);
            $seconds[$player->id] = 0;
            $gamesPlayed[$player->id] = 0;
        }

        $teamTotals = array_fill_keys($statKeys, 0);
        $opponentTotals = array_fill_keys(array_diff($statKeys, ['plusMinus']), 0);
        $teamSeconds = 0;

        $games = $this->games()
            ->whereHas('events')
            ->with(['players', 'events'])
            ->orderBy('date_time')
            ->get();

        foreach ($games as $game) {
            $boxScore = $game->boxScore();

            foreach ($boxScore['players'] as $line) {
                $playerId = (int) $line['id'];

                if (! isset($totals[$playerId])) {
                    continue;
                }

                foreach ($statKeys as $key) {
                    $totals[$playerId][$key] += $line[$key];
                }

                $playerSeconds = $line['seconds'];
                $seconds[$playerId] += $playerSeconds;

                $appeared = $playerSeconds > 0
                    || $line['points'] > 0
                    || $line['twoAttempts'] > 0
                    || $line['threeAttempts'] > 0
                    || $line['freeAttempts'] > 0
                    || $line['offensiveRebounds'] > 0
                    || $line['defensiveRebounds'] > 0
                    || $line['assists'] > 0
                    || $line['turnovers'] > 0
                    || $line['steals'] > 0
                    || $line['blocks'] > 0
                    || $line['fouls'] > 0;

                if ($appeared) {
                    $gamesPlayed[$playerId]++;
                }
            }

            foreach ($statKeys as $key) {
                if ($key === 'plusMinus') {
                    continue;
                }

                $teamTotals[$key] += $boxScore['team'][$key];
                $opponentTotals[$key] += $boxScore['opponent'][$key];
            }

            $teamSeconds += $boxScore['team']['seconds'];
        }

        $rows = [];

        foreach ($players as $player) {
            $rows[] = [
                'id' => $player->id,
                'number' => $player->number,
                'name' => $player->name,
                'gp' => $gamesPlayed[$player->id],
                'seconds' => $seconds[$player->id],
                'minutes' => Game::formatPlayed($seconds[$player->id]),
                ...$totals[$player->id],
            ];
        }

        return [
            'players' => $rows,
            'team' => [
                ...$teamTotals,
                'gp' => null,
                'seconds' => $teamSeconds,
                'minutes' => Game::formatPlayed($teamSeconds),
                'plusMinus' => null,
            ],
            'opponent' => $opponentTotals,
        ];
    }

    /**
     * @return HasMany<Game, $this>
     */
    private function scoredPlayedGames(): HasMany
    {
        return $this->games()
            ->where('status', GameStatus::Played)
            ->whereNotNull('home_points')
            ->whereNotNull('away_points');
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->photo === null
            ? null
            : asset('storage/'.$this->photo));
    }
}
