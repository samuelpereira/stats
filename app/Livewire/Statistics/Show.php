<?php

namespace App\Livewire\Statistics;

use App\Actions\ShareGameStatistics;
use App\Enums\HomeAway;
use App\Models\Game;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.collect', ['titleAsGiven' => true])]
class Show extends Component
{
    private const LINEUP_SORTS = ['seconds', 'points', 'opponentPoints'];

    #[Locked]
    public Game $game;

    public string $homeShotFilter = 'team';

    public string $awayShotFilter = 'team';

    public string $mobileShotChartFilter = 'home|team';

    public string $backUrl;

    #[Locked]
    public bool $shared = false;

    public bool $sharing = false;

    public string $lineupSort = 'points';

    public function mount(Game $game): void
    {
        $this->authorize('collect', $game);

        $this->game = $game->loadMissing('team');
        $this->backUrl = $this->resolveBackUrl(request()->query('from'));
        $this->mobileShotChartFilter = $this->defaultMobileShotChartFilter();
    }

    public function resolveBackUrl(?string $from): string
    {
        return match ($from) {
            'dashboard' => route('dashboard'),
            'games' => route('games.index'),
            'statistics' => route('statistics.index'),
            'record' => route('statistics.record', $this->game),
            default => route('statistics.index'),
        };
    }

    public function openShare(ShareGameStatistics $share): void
    {
        $this->guardShare();
        $share->publish($this->game);
        $this->sharing = true;
    }

    public function rotateShare(ShareGameStatistics $share): void
    {
        $this->guardShare();
        $share->rotate($this->game);

        Flux::toast(variant: 'success', text: __('New share link created.'));
    }

    public function revokeShare(ShareGameStatistics $share): void
    {
        $this->guardShare();
        $share->revoke($this->game);
        $this->sharing = false;

        Flux::toast(text: __('Sharing stopped.'));
    }

    public function copiedShareLink(): void
    {
        $this->guardShare();

        Flux::toast(variant: 'success', text: __('Link copied.'));
    }

    public function render(): View
    {
        $boxScore = $this->game->boxScore();
        $pointsByQuarter = $this->game->pointsByQuarter();
        $weAreHome = $this->game->home_away === HomeAway::Home;

        $periods = [];
        $homeTotal = 0;
        $awayTotal = 0;

        foreach ($pointsByQuarter as $quarter => $sides) {
            $periods[] = [
                'label' => $this->periodLabel((int) $quarter),
                'home' => $sides['home'],
                'away' => $sides['away'],
            ];
            $homeTotal += $sides['home'];
            $awayTotal += $sides['away'];
        }

        $homeLine = $weAreHome ? $boxScore['team'] : $boxScore['opponent'];
        $awayLine = $weAreHome ? $boxScore['opponent'] : $boxScore['team'];
        $homeName = $weAreHome ? $this->game->team->name : $this->game->opponent;
        $awayName = $weAreHome ? $this->game->opponent : $this->game->team->name;
        [$courtPlayers, $benchPlayers] = $this->splitSquad($boxScore['players'], $boxScore['court']);
        $boxScore['players'] = $this->sortBoxScorePlayers($boxScore['players']);
        $defaultColors = Game::defaultAttributes();
        $teamColor = $weAreHome ? $this->game->home_color : $this->game->away_color;

        return view('livewire.statistics.show', [
            'boxScore' => $boxScore,
            'courtPlayers' => $courtPlayers,
            'benchPlayers' => $benchPlayers,
            'periods' => $periods,
            'homeTotal' => $homeTotal,
            'awayTotal' => $awayTotal,
            'matchups' => $this->matchups($homeLine, $awayLine),
            'shotChartsByPeriod' => $this->filteredShotChartsByPeriod(),
            'gameFlow' => $this->game->gameFlow(),
            'lineupStats' => $this->sortLineupStats($this->game->lineupStats()),
            'homeShotChartPlayers' => $this->game->shotChartPlayers(HomeAway::Home),
            'awayShotChartPlayers' => $this->game->shotChartPlayers(HomeAway::Away),
            'homeColor' => $defaultColors['home_color']->hex(),
            'awayColor' => $defaultColors['away_color']->hex(),
            'teamColor' => $teamColor->hex(),
            'teamTextColor' => $teamColor->textHex(),
            'teamName' => $this->game->team->name,
            'opponentName' => $this->game->opponent,
            'homeName' => $homeName,
            'awayName' => $awayName,
            'homeLogoUrl' => $weAreHome ? $this->game->team->photo_url : $this->game->opponent_photo_url,
            'awayLogoUrl' => $weAreHome ? $this->game->opponent_photo_url : $this->game->team->photo_url,
            'homeInitials' => $this->initials($homeName),
            'awayInitials' => $this->initials($awayName),
            'gameDate' => $this->game->date_time->isoFormat('D MMM YYYY'),
            'mobileShotChartSide' => $this->mobileShotChartSide(),
            'shareUrl' => $this->shareUrl(),
        ])->title($this->scoreTitle($homeName, $homeTotal, $awayName, $awayTotal));
    }

    private function scoreTitle(string $homeName, int $homeTotal, string $awayName, int $awayTotal): string
    {
        return $homeName.' '.$homeTotal.' vs '.$awayName.' '.$awayTotal;
    }

    public function updatedMobileShotChartFilter(): void
    {
        if (! str_contains($this->mobileShotChartFilter, '|')) {
            return;
        }

        [$side, $filter] = explode('|', $this->mobileShotChartFilter, 2);

        if ($side === 'home') {
            $this->homeShotFilter = $filter;

            return;
        }

        if ($side === 'away') {
            $this->awayShotFilter = $filter;
        }
    }

    public function mobileShotChartSide(): string
    {
        if (! str_contains($this->mobileShotChartFilter, '|')) {
            return 'home';
        }

        [$side] = explode('|', $this->mobileShotChartFilter, 2);

        return $side === 'away' ? 'away' : 'home';
    }

    public function sortLineupsBy(string $column): void
    {
        if (in_array($column, self::LINEUP_SORTS, true)) {
            $this->lineupSort = $column;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sortLineupStats(array $rows): array
    {
        $column = in_array($this->lineupSort, self::LINEUP_SORTS, true) ? $this->lineupSort : 'points';

        usort($rows, fn (array $left, array $right): int => [$right[$column], $right['seconds']] <=> [$left[$column], $left['seconds']]);

        return $rows;
    }

    protected function defaultMobileShotChartFilter(): string
    {
        return $this->game->home_away === HomeAway::Away ? 'away|team' : 'home|team';
    }

    private function guardShare(): void
    {
        abort_if($this->shared, 404);

        $this->authorize('collect', $this->game);
    }

    private function shareUrl(): ?string
    {
        if ($this->game->share_token === null) {
            return null;
        }

        return route('statistics.shared', ['game' => $this->game->share_token]);
    }

    /**
     * @param  array{points: int, twoMade: int, twoAttempts: int, threeMade: int, threeAttempts: int, freeMade: int, freeAttempts: int, offensiveRebounds: int, defensiveRebounds: int, assists: int, turnovers: int, steals: int, blocks: int, fouls: int, technicalFouls: int}  $home
     * @param  array{points: int, twoMade: int, twoAttempts: int, threeMade: int, threeAttempts: int, freeMade: int, freeAttempts: int, offensiveRebounds: int, defensiveRebounds: int, assists: int, turnovers: int, steals: int, blocks: int, fouls: int, technicalFouls: int}  $away
     * @return list<array{label: string, left: string, right: string, leftShare: float, rightShare: float}>
     */
    private function matchups(array $home, array $away): array
    {
        $homeFgMade = $home['twoMade'] + $home['threeMade'];
        $homeFgAttempts = $home['twoAttempts'] + $home['threeAttempts'];
        $awayFgMade = $away['twoMade'] + $away['threeMade'];
        $awayFgAttempts = $away['twoAttempts'] + $away['threeAttempts'];
        $homeReb = $home['offensiveRebounds'] + $home['defensiveRebounds'];
        $awayReb = $away['offensiveRebounds'] + $away['defensiveRebounds'];

        return [
            $this->countMatchup('PTS', $home['points'], $away['points']),
            $this->shootingMatchup('FG (%)', $homeFgMade, $homeFgAttempts, $awayFgMade, $awayFgAttempts),
            $this->shootingMatchup('2P (%)', $home['twoMade'], $home['twoAttempts'], $away['twoMade'], $away['twoAttempts']),
            $this->shootingMatchup('3P (%)', $home['threeMade'], $home['threeAttempts'], $away['threeMade'], $away['threeAttempts']),
            $this->shootingMatchup('FT (%)', $home['freeMade'], $home['freeAttempts'], $away['freeMade'], $away['freeAttempts']),
            $this->countMatchup('DFR', $home['defensiveRebounds'], $away['defensiveRebounds']),
            $this->countMatchup('OFR', $home['offensiveRebounds'], $away['offensiveRebounds']),
            $this->countMatchup('TR', $homeReb, $awayReb),
            $this->countMatchup('AST', $home['assists'], $away['assists']),
            $this->countMatchup('BLK', $home['blocks'], $away['blocks']),
            $this->countMatchup('STL', $home['steals'], $away['steals']),
            $this->countMatchup('TO', $home['turnovers'], $away['turnovers']),
            $this->countMatchup('PF+TF', $home['fouls'] + $home['technicalFouls'], $away['fouls'] + $away['technicalFouls']),
        ];
    }

    /**
     * @return array{label: string, left: string, right: string, leftShare: float, rightShare: float}
     */
    private function shootingMatchup(string $label, int $homeMade, int $homeAttempts, int $awayMade, int $awayAttempts): array
    {
        return [
            'label' => $label,
            'left' => $this->shootingLabel($homeMade, $homeAttempts),
            'right' => $this->shootingLabel($awayMade, $awayAttempts),
            ...$this->shares($homeMade, $awayMade),
        ];
    }

    /**
     * @return array{label: string, left: string, right: string, leftShare: float, rightShare: float}
     */
    private function countMatchup(string $label, int $home, int $away): array
    {
        return [
            'label' => $label,
            'left' => (string) $home,
            'right' => (string) $away,
            ...$this->shares($home, $away),
        ];
    }

    private function shootingLabel(int $made, int $attempts): string
    {
        if ($attempts === 0) {
            return '0-0';
        }

        return $made.'-'.$attempts.' ('.round(100 * $made / $attempts).'%)';
    }

    /**
     * @return array{leftShare: float, rightShare: float}
     */
    private function shares(int $left, int $right): array
    {
        $total = $left + $right;

        if ($total === 0) {
            return ['leftShare' => 50.0, 'rightShare' => 50.0];
        }

        $leftShare = round(100 * $left / $total, 1);

        return [
            'leftShare' => $leftShare,
            'rightShare' => round(100 - $leftShare, 1),
        ];
    }

    /**
     * @param  list<array{id: int, number: int|null, name: string, minutes: string, points: int, fouls: int}>  $players
     * @param  list<int>  $courtIds
     * @return array{0: list<array{id: int, number: int|null, name: string, minutes: string, points: int, fouls: int}>, 1: list<array{id: int, number: int|null, name: string, minutes: string, points: int, fouls: int}>}
     */
    private function splitSquad(array $players, array $courtIds): array
    {
        $onCourt = array_flip($courtIds);
        $court = [];
        $bench = [];

        foreach ($players as $player) {
            if (isset($onCourt[$player['id']])) {
                $court[] = $player;

                continue;
            }

            $bench[] = $player;
        }

        return [$court, $bench];
    }

    /**
     * @return list<array{
     *     quarter: int,
     *     label: string,
     *     home: list<array{id: int, x: int, y: int, made: bool, player_id: int|null}>,
     *     away: list<array{id: int, x: int, y: int, made: bool, player_id: int|null}>
     * }>
     */
    private function filteredShotChartsByPeriod(): array
    {
        $charts = $this->game->shotChartsByPeriod();

        if ($charts === []) {
            return [];
        }

        $periods = [];

        foreach ($charts as $chart) {
            if ($chart['quarter'] === 0) {
                continue;
            }

            $periods[] = [
                ...$chart,
                'home' => $this->filterShots($chart['home'], $this->homeShotFilter),
                'away' => $this->filterShots($chart['away'], $this->awayShotFilter),
            ];
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
     * @param  list<array{id: int, x: int, y: int, made: bool, player_id: int|null}>  $shots
     * @return list<array{id: int, x: int, y: int, made: bool, player_id: int|null}>
     */
    private function filterShots(array $shots, string $filter): array
    {
        if ($filter === 'team') {
            return $shots;
        }

        if (! str_starts_with($filter, 'player-')) {
            return $shots;
        }

        $playerId = (int) substr($filter, strlen('player-'));

        return array_values(array_filter(
            $shots,
            fn (array $shot): bool => ($shot['player_id'] ?? null) === $playerId,
        ));
    }

    /**
     * @param  list<array{id: int, number: int|null, name: string, minutes: string, points: int, fouls: int, twoMade: int, twoAttempts: int, threeMade: int, threeAttempts: int, freeMade: int, freeAttempts: int, offensiveRebounds: int, defensiveRebounds: int, assists: int, turnovers: int, steals: int, blocks: int}>  $players
     * @return list<array{id: int, number: int|null, name: string, minutes: string, points: int, fouls: int, twoMade: int, twoAttempts: int, threeMade: int, threeAttempts: int, freeMade: int, freeAttempts: int, offensiveRebounds: int, defensiveRebounds: int, assists: int, turnovers: int, steals: int, blocks: int}>
     */
    private function sortBoxScorePlayers(array $players): array
    {
        usort($players, function (array $left, array $right): int {
            $byPoints = $right['points'] <=> $left['points'];

            if ($byPoints !== 0) {
                return $byPoints;
            }

            return strcasecmp($left['name'], $right['name']);
        });

        return $players;
    }

    private function periodLabel(int $quarter): string
    {
        if ($quarter <= 4) {
            return 'Q'.$quarter;
        }

        return 'OT'.($quarter - 4);
    }

    private function initials(string $name): string
    {
        $letters = preg_replace('/[^A-Za-z0-9]/u', '', $name) ?? '';

        return mb_strtoupper(mb_substr($letters !== '' ? $letters : $name, 0, 3));
    }
}
