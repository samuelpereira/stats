<?php

namespace App\Models;

use App\Enums\GameStatus;
use App\Enums\HomeAway;
use App\Enums\RoleName;
use App\Enums\StatType;
use App\Enums\TeamColor;
use App\Models\Concerns\HasPublicUuid;
use Carbon\CarbonImmutable;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property int $team_id
 * @property string|null $fpb_id
 * @property string $opponent
 * @property string|null $opponent_photo
 * @property-read string|null $opponent_photo_url
 * @property CarbonImmutable $date_time
 * @property string|null $place
 * @property HomeAway $home_away
 * @property string|null $competition
 * @property string|null $journey
 * @property int|null $home_points
 * @property int|null $away_points
 * @property int $quarter_minutes
 * @property int $overtime_minutes
 * @property TeamColor $home_color
 * @property TeamColor $away_color
 * @property bool $mark_shot_location
 * @property string|null $share_token
 * @property GameStatus $status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Team $team
 * @property-read Collection<int, GameEvent> $events
 * @property-read Collection<int, Player> $players
 */
#[Fillable(['team_id', 'fpb_id', 'opponent', 'date_time', 'place', 'home_away', 'competition', 'journey', 'home_points', 'away_points', 'quarter_minutes', 'overtime_minutes', 'home_color', 'away_color', 'mark_shot_location', 'status'])]
#[Hidden(['share_token'])]
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_time' => 'datetime',
            'home_away' => HomeAway::class,
            'home_points' => 'integer',
            'away_points' => 'integer',
            'quarter_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'home_color' => TeamColor::class,
            'away_color' => TeamColor::class,
            'mark_shot_location' => 'boolean',
            'status' => GameStatus::class,
        ];
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function opponentPhotoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->opponent_photo === null
            ? null
            : asset('storage/'.$this->opponent_photo));
    }

    /**
     * @return array{quarter_minutes: int, overtime_minutes: int, home_color: TeamColor, away_color: TeamColor, mark_shot_location: bool}
     */
    public static function defaultAttributes(): array
    {
        return [
            'quarter_minutes' => self::configuredMinutes('game.quarter_minutes', 10),
            'overtime_minutes' => self::configuredMinutes('game.overtime_minutes', 5),
            'home_color' => self::configuredColor('game.home_color', TeamColor::Purple),
            'away_color' => self::configuredColor('game.away_color', TeamColor::Blue),
            'mark_shot_location' => filter_var(config('game.mark_shot_location'), FILTER_VALIDATE_BOOL),
        ];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @param  Builder<Game>  $query
     * @return Builder<Game>
     */
    public function scopeForStatisticsCollector(Builder $query, User $user): Builder
    {
        if ($user->hasRole(RoleName::Administrator)) {
            return $query;
        }

        return $query->whereHas('team', function (Builder $teamQuery) use ($user): void {
            $teamQuery->where('club_id', $user->club_id);

            if ($user->hasRole(RoleName::User)) {
                $teamQuery->whereIn('teams.id', $user->teams()->select('teams.id'));
            }
        });
    }

    /**
     * @return HasMany<GameEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(GameEvent::class);
    }

    public function hasCollectedStatistics(): bool
    {
        if ($this->status !== GameStatus::Played) {
            return false;
        }

        if (array_key_exists('events_exists', $this->attributes)) {
            return (bool) $this->getAttribute('events_exists');
        }

        return $this->events()->exists();
    }

    /**
     * @return BelongsToMany<Player, $this>
     */
    public function players(): BelongsToMany
    {
        return $this->belongsToMany(Player::class)
            ->withPivot('starter')
            ->withTimestamps();
    }

    private static function configuredMinutes(string $key, int $fallback): int
    {
        $value = config($key);

        if (! is_numeric($value)) {
            return $fallback;
        }

        $minutes = (int) $value;

        if ($minutes < 1 || $minutes > 99) {
            return $fallback;
        }

        return $minutes;
    }

    private static function configuredColor(string $key, TeamColor $fallback): TeamColor
    {
        $value = config($key);

        if (! is_string($value)) {
            return $fallback;
        }

        return TeamColor::tryFrom($value) ?? $fallback;
    }

    public function ourPoints(): int
    {
        $points = $this->home_away === HomeAway::Home ? $this->home_points : $this->away_points;

        return $points ?? 0;
    }

    public function opponentPoints(): int
    {
        $points = $this->home_away === HomeAway::Home ? $this->away_points : $this->home_points;

        return $points ?? 0;
    }

    public function sideFor(bool $forOpponent): HomeAway
    {
        return $forOpponent ? $this->home_away->opposite() : $this->home_away;
    }

    public function isOurSide(?HomeAway $side): bool
    {
        return $side === $this->home_away;
    }

    public function canFinishAfterFourthQuarter(): bool
    {
        if ($this->status === GameStatus::Played) {
            return false;
        }

        $marker = $this->events()
            ->whereIn('type', [StatType::QuarterStarted, StatType::QuarterEnded])
            ->orderByDesc('id')
            ->first();

        return $marker instanceof GameEvent
            && $marker->type === StatType::QuarterEnded
            && $marker->quarter >= 4;
    }

    public function openPeriod(): int
    {
        $started = $this->events()
            ->where('type', StatType::QuarterStarted)
            ->orderByDesc('id')
            ->first();

        if (! $started instanceof GameEvent) {
            return 1;
        }

        return $started->quarter;
    }

    /**
     * The latest action recorded while the current period is still open.
     */
    public function latestUndoableEvent(): ?GameEvent
    {
        $candidate = null;

        foreach ($this->events()->latest('id')->get() as $event) {
            if ($event->type === StatType::QuarterStarted) {
                return $candidate;
            }

            if ($event->type === StatType::QuarterEnded) {
                return null;
            }

            $candidate ??= $event;
        }

        return null;
    }

    /**
     * The period that was open when each event was recorded. A new period starts only when it is started.
     *
     * @return array<int, int>
     */
    public function periodsByEventId(): array
    {
        $periods = [];
        $open = 1;

        foreach ($this->events()->orderBy('id')->get(['id', 'type', 'quarter']) as $event) {
            if ($event->type === StatType::QuarterStarted) {
                $open = $event->quarter;
            }

            $periods[$event->id] = $open;
        }

        return $periods;
    }

    /**
     * @return array<int, array{home: int, away: int}>
     */
    public function pointsByQuarter(): array
    {
        $events = $this->events()->get(['home_away', 'type', 'quarter']);
        $quarters = $this->quarterTotals($events);

        foreach ($events as $event) {
            $points = $event->type->points();

            if ($points === 0 || $event->home_away === null || ! isset($quarters[$event->quarter])) {
                continue;
            }

            $quarters[$event->quarter][$event->home_away->value] += $points;
        }

        return $quarters;
    }

    /**
     * @return array<int, array{home: int, away: int}>
     */
    public function teamFoulsByQuarter(): array
    {
        $events = $this->events()
            ->where('type', StatType::Foul)
            ->get(['home_away', 'quarter']);
        $quarters = $this->quarterTotals($events);

        foreach ($events as $event) {
            if ($event->home_away === null || ! isset($quarters[$event->quarter])) {
                continue;
            }

            $quarters[$event->quarter][$event->home_away->value]++;
        }

        return $quarters;
    }

    /**
     * Quarters that contribute to the team-foul count for a period.
     * Each regulation quarter stands alone; overtime carries from the fourth quarter.
     *
     * @return list<int>
     */
    public function teamFoulQuarters(int $period): array
    {
        if ($period <= 4) {
            return [$period];
        }

        return range(4, $period);
    }

    /**
     * @return array{home: int, away: int}
     */
    public function teamFoulsForPeriod(int $period): array
    {
        $byQuarter = $this->teamFoulsByQuarter();
        $totals = ['home' => 0, 'away' => 0];

        foreach ($this->teamFoulQuarters($period) as $quarter) {
            $totals['home'] += $byQuarter[$quarter]['home'] ?? 0;
            $totals['away'] += $byQuarter[$quarter]['away'] ?? 0;
        }

        return $totals;
    }

    /**
     * Regulation quarters, plus any overtime period present in the given events.
     *
     * @param  Collection<int, GameEvent>  $events
     * @return array<int, array{home: int, away: int}>
     */
    private function quarterTotals(Collection $events): array
    {
        $highest = 4;

        foreach ($events as $event) {
            $highest = max($highest, $event->quarter);
        }

        $quarters = [];

        foreach (range(1, $highest) as $quarter) {
            $quarters[$quarter] = ['home' => 0, 'away' => 0];
        }

        return $quarters;
    }

    /**
     * @return array{
     *     players: list<array{id: int, number: int|null, name: string, seconds: int, minutes: string, plusMinus: int, points: int, twoMade: int, twoAttempts: int, threeMade: int, threeAttempts: int, freeMade: int, freeAttempts: int, offensiveRebounds: int, defensiveRebounds: int, assists: int, turnovers: int, steals: int, blocks: int, fouls: int, technicalFouls: int}>,
     *     court: list<int>,
     *     team: array{seconds: int, minutes: string, points: int, twoMade: int, twoAttempts: int, threeMade: int, threeAttempts: int, freeMade: int, freeAttempts: int, offensiveRebounds: int, defensiveRebounds: int, assists: int, turnovers: int, steals: int, blocks: int, fouls: int, technicalFouls: int},
     *     opponent: array{points: int, twoMade: int, twoAttempts: int, threeMade: int, threeAttempts: int, freeMade: int, freeAttempts: int, offensiveRebounds: int, defensiveRebounds: int, assists: int, turnovers: int, steals: int, blocks: int, fouls: int, technicalFouls: int}
     * }
     */
    public function boxScore(): array
    {
        $players = $this->players()->orderBy('number')->orderBy('name')->get();
        $eventsById = $this->events()->orderBy('id')->get();
        $latest = $eventsById->last();
        $marker = $eventsById->reverse()->first(fn (GameEvent $event): bool => $event->type->isPeriod());
        $periodOpen = $marker instanceof GameEvent && $marker->type === StatType::QuarterStarted;
        $lineup = $this->lineupState($latest instanceof GameEvent ? $latest->clock : '00:00', $periodOpen);
        $seconds = $lineup['seconds'];
        $events = GameEvent::sortChronologically($eventsById);

        $lines = [];
        $plusMinus = [];
        $onCourt = [];

        foreach ($this->players()->wherePivot('starter', true)->pluck('players.id') as $playerId) {
            $onCourt[] = (int) $playerId;
        }

        foreach ($players as $player) {
            $lines[$player->id] = $this->emptyBoxLine();
            $plusMinus[$player->id] = 0;
        }

        $team = $this->emptyBoxLine();
        $opponent = $this->emptyBoxLine();

        foreach ($events as $event) {
            if ($event->type === StatType::Substitution && $event->player_id !== null && $event->outgoing_player_id !== null) {
                $outgoingId = (int) $event->outgoing_player_id;
                $onCourt = array_values(array_filter(
                    $onCourt,
                    fn (int $playerId): bool => $playerId !== $outgoingId,
                ));
                $incomingId = (int) $event->player_id;

                if (! in_array($incomingId, $onCourt, true)) {
                    $onCourt[] = $incomingId;
                }
            }

            $scored = $event->type->points();

            if ($scored > 0 && $event->home_away !== null) {
                $margin = $this->isOurSide($event->home_away) ? $scored : -$scored;

                foreach ($onCourt as $playerId) {
                    if (array_key_exists($playerId, $plusMinus)) {
                        $plusMinus[$playerId] += $margin;
                    }
                }
            }

            if ($event->home_away === null || $event->type->isPeriod() || $event->type === StatType::Substitution || $event->type === StatType::Timeout) {
                continue;
            }

            if ($this->isOurSide($event->home_away)) {
                if ($event->type === StatType::Foul && $event->player_id === null) {
                    continue;
                }

                $this->addBoxStat($team, $event);

                if ($event->player_id !== null && isset($lines[$event->player_id])) {
                    $this->addBoxStat($lines[$event->player_id], $event);
                }

                continue;
            }

            $this->addBoxStat($opponent, $event);
        }

        $played = 0;
        $rows = [];

        foreach ($players as $player) {
            $playerSeconds = $seconds[$player->id] ?? 0;
            $played += $playerSeconds;
            $rows[] = [
                'id' => $player->id,
                'number' => $player->number,
                'name' => $player->name,
                'seconds' => $playerSeconds,
                'minutes' => self::formatPlayed($playerSeconds),
                'plusMinus' => $plusMinus[$player->id] ?? 0,
                ...$lines[$player->id],
            ];
        }

        return [
            'players' => $rows,
            'court' => $lineup['court'],
            'team' => [
                'seconds' => $played,
                'minutes' => self::formatPlayed($played),
                ...$team,
            ],
            'opponent' => $opponent,
        ];
    }

    /**
     * @return array{points: int, twoMade: int, twoAttempts: int, threeMade: int, threeAttempts: int, freeMade: int, freeAttempts: int, offensiveRebounds: int, defensiveRebounds: int, assists: int, turnovers: int, steals: int, blocks: int, fouls: int, technicalFouls: int}
     */
    private function emptyBoxLine(): array
    {
        return [
            'points' => 0,
            'twoMade' => 0,
            'twoAttempts' => 0,
            'threeMade' => 0,
            'threeAttempts' => 0,
            'freeMade' => 0,
            'freeAttempts' => 0,
            'offensiveRebounds' => 0,
            'defensiveRebounds' => 0,
            'assists' => 0,
            'turnovers' => 0,
            'steals' => 0,
            'blocks' => 0,
            'fouls' => 0,
            'technicalFouls' => 0,
        ];
    }

    /**
     * @param  array{points: int, twoMade: int, twoAttempts: int, threeMade: int, threeAttempts: int, freeMade: int, freeAttempts: int, offensiveRebounds: int, defensiveRebounds: int, assists: int, turnovers: int, steals: int, blocks: int, fouls: int, technicalFouls: int}  $line
     */
    private function addBoxStat(array &$line, GameEvent $event): void
    {
        $type = $event->type;
        $line['points'] += $type->points();

        if ($type === StatType::TwoPointMade || $type === StatType::TwoPointMissed) {
            $line['twoAttempts']++;

            if ($type === StatType::TwoPointMade) {
                $line['twoMade']++;
            }
        }

        if ($type === StatType::ThreePointMade || $type === StatType::ThreePointMissed) {
            $line['threeAttempts']++;

            if ($type === StatType::ThreePointMade) {
                $line['threeMade']++;
            }
        }

        if ($type === StatType::FreeThrowMade || $type === StatType::FreeThrowMissed) {
            $line['freeAttempts']++;

            if ($type === StatType::FreeThrowMade) {
                $line['freeMade']++;
            }
        }

        if ($type === StatType::OffensiveRebound) {
            $line['offensiveRebounds']++;
        }

        if ($type === StatType::DefensiveRebound) {
            $line['defensiveRebounds']++;
        }

        if ($type === StatType::Assist) {
            $line['assists']++;
        }

        if ($type === StatType::Turnover) {
            $line['turnovers']++;
        }

        if ($type === StatType::Steal) {
            $line['steals']++;
        }

        if ($type === StatType::Block) {
            $line['blocks']++;
        }

        if ($type === StatType::Foul) {
            if ($event->foul_kind?->countsInTechnicalColumn()) {
                $line['technicalFouls']++;
            } else {
                $line['fouls']++;
            }
        }
    }

    public static function formatPlayed(int $seconds): string
    {
        return intdiv($seconds, 60).':'.sprintf('%02d', $seconds % 60);
    }

    /**
     * Who is on the court, and how many seconds each squad player has played.
     *
     * @return array{court: list<int>, seconds: array<int, int>}
     */
    public function lineupState(string $clock, bool $counting): array
    {
        $lineup = [];

        foreach ($this->players()->wherePivot('starter', true)->pluck('players.id') as $playerId) {
            $lineup[] = (int) $playerId;
        }

        $openedAt = [];
        $played = [];
        $running = false;

        $events = $this->events()
            ->chronological()
            ->get(['id', 'type', 'clock', 'player_id', 'outgoing_player_id']);

        foreach ($events as $event) {
            $mark = self::clockToSeconds($event->clock);

            if ($event->type === StatType::QuarterStarted) {
                $running = true;

                foreach ($lineup as $playerId) {
                    $openedAt[$playerId] = $mark;
                }

                continue;
            }

            if ($event->type === StatType::QuarterEnded) {
                foreach ($openedAt as $playerId => $from) {
                    $played[$playerId] = ($played[$playerId] ?? 0) + max(0, $from - $mark);
                }

                $openedAt = [];
                $running = false;

                continue;
            }

            if ($event->type !== StatType::Substitution || $event->player_id === null || $event->outgoing_player_id === null) {
                continue;
            }

            $outgoingId = (int) $event->outgoing_player_id;
            $incomingId = (int) $event->player_id;

            if ($running && isset($openedAt[$outgoingId])) {
                $played[$outgoingId] = ($played[$outgoingId] ?? 0) + max(0, $openedAt[$outgoingId] - $mark);
                unset($openedAt[$outgoingId]);
            }

            $next = [];

            foreach ($lineup as $playerId) {
                if ($playerId !== $outgoingId) {
                    $next[] = $playerId;
                }
            }

            $lineup = $next;

            if (! in_array($incomingId, $lineup, true)) {
                $lineup[] = $incomingId;
            }

            if ($running) {
                $openedAt[$incomingId] = $mark;
            }
        }

        if ($counting && $running) {
            $mark = self::clockToSeconds($clock);

            foreach ($openedAt as $playerId => $from) {
                $played[$playerId] = ($played[$playerId] ?? 0) + max(0, $from - $mark);
            }
        }

        return [
            'court' => $lineup,
            'seconds' => $played,
        ];
    }

    private static function clockToSeconds(string $clock): int
    {
        if (! str_contains($clock, ':')) {
            return 0;
        }

        [$minutes, $seconds] = explode(':', $clock, 2);

        return ((int) $minutes * 60) + (int) $seconds;
    }

    /**
     * @return list<array{
     *     quarter: int,
     *     label: string,
     *     home: list<array{id: int, x: int, y: int, made: bool, player_id: int|null}>,
     *     away: list<array{id: int, x: int, y: int, made: bool, player_id: int|null}>
     * }>
     */
    public function shotChartsByPeriod(): array
    {
        $events = $this->events()
            ->whereNotNull('court_x')
            ->whereNotNull('court_y')
            ->whereIn('type', [
                StatType::TwoPointMade,
                StatType::TwoPointMissed,
                StatType::ThreePointMade,
                StatType::ThreePointMissed,
            ])
            ->whereNotNull('home_away')
            ->orderBy('quarter')
            ->orderBy('id')
            ->get(['id', 'type', 'quarter', 'court_x', 'court_y', 'home_away', 'player_id']);

        $charts = [];

        foreach ($events as $event) {
            $quarter = $event->quarter;

            if (! isset($charts[$quarter])) {
                $charts[$quarter] = [
                    'quarter' => $quarter,
                    'label' => $this->shotChartPeriodLabel($quarter),
                    'home' => [],
                    'away' => [],
                ];
            }

            $side = $event->home_away === HomeAway::Home ? 'home' : 'away';

            $charts[$quarter][$side][] = [
                'id' => $event->id,
                'x' => $event->court_x,
                'y' => $event->court_y,
                'made' => $event->type->isSolid(),
                'player_id' => $event->player_id,
            ];
        }

        ksort($charts);

        $periods = array_values($charts);

        if ($periods === []) {
            return [];
        }

        $homeTotal = [];
        $awayTotal = [];

        foreach ($periods as $period) {
            array_push($homeTotal, ...$period['home']);
            array_push($awayTotal, ...$period['away']);
        }

        $periods[] = [
            'quarter' => 0,
            'label' => 'Totals',
            'home' => $homeTotal,
            'away' => $awayTotal,
        ];

        return $periods;
    }

    /**
     * @return list<array{id: int, number: int|null, name: string}>
     */
    public function shotChartPlayers(HomeAway $side): array
    {
        $playerIds = $this->events()
            ->whereNotNull('court_x')
            ->whereNotNull('court_y')
            ->where('home_away', $side)
            ->whereNotNull('player_id')
            ->whereIn('type', [
                StatType::TwoPointMade,
                StatType::TwoPointMissed,
                StatType::ThreePointMade,
                StatType::ThreePointMissed,
            ])
            ->distinct()
            ->pluck('player_id');

        if ($playerIds->isEmpty()) {
            return [];
        }

        $players = $this->players()
            ->whereIn('players.id', $playerIds)
            ->orderBy('number')
            ->orderBy('name')
            ->get(['players.id', 'players.number', 'players.name'])
            ->map(fn (Player $player): array => [
                'id' => $player->id,
                'number' => $player->number,
                'name' => $player->name,
            ])
            ->all();

        return array_values($players);
    }

    public function score(): ?string
    {
        if ($this->home_points === null || $this->away_points === null) {
            return null;
        }

        return $this->home_points.'–'.$this->away_points;
    }

    public function resultLabel(): string
    {
        $ours = $this->ourPoints();
        $theirs = $this->opponentPoints();

        if ($ours > $theirs) {
            return 'Win';
        }

        if ($ours < $theirs) {
            return 'Loss';
        }

        return 'Tie';
    }

    private function shotChartPeriodLabel(int $quarter): string
    {
        if ($quarter <= 4) {
            return 'Q'.$quarter;
        }

        return 'OT'.($quarter - 4);
    }
}
