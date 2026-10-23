<?php

namespace App\Services\Fpb;

use App\Enums\GameStatus;
use App\Enums\HomeAway;
use App\Models\Game;
use App\Models\Player;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class FpbGamesImporter
{
    protected const MESES = [
        'JAN' => 1, 'FEV' => 2, 'MAR' => 3, 'ABR' => 4, 'MAI' => 5, 'JUN' => 6,
        'JUL' => 7, 'AGO' => 8, 'SET' => 9, 'OUT' => 10, 'NOV' => 11, 'DEZ' => 12,
    ];

    /**
     * @return array{created:int, updated:int, deleted:int, total:int}
     */
    public function import(Team $team, ?bool $prune = null): array
    {
        $prune ??= (bool) config('fpb.prune_missing');

        if (! $team->fpb_url) {
            throw new RuntimeException(__('Team ":name" has no FPB link.', ['name' => $team->name]));
        }

        $html = $this->fetch($team->fpb_url);
        $fixtures = $this->parse($html);

        if ($fixtures === []) {
            throw new RuntimeException(__('No games were found on the page (unexpected structure or empty page).'));
        }

        $clubName = $this->detectClub($fixtures);

        $created = 0;
        $updated = 0;
        $seenIds = [];

        foreach ($fixtures as $fixture) {
            $isHome = $fixture['home'] === $clubName;
            $isAway = $fixture['away'] === $clubName;

            if (! $isHome && ! $isAway) {
                continue;
            }

            $seenIds[] = $fixture['id'];

            $hasScore = $fixture['pc'] !== null && $fixture['pf'] !== null;

            $game = Game::firstOrNew([
                'team_id' => $team->id,
                'fpb_id' => $fixture['id'],
            ]);

            $exists = $game->exists;

            $attributes = [
                'opponent' => $isHome ? $fixture['away'] : $fixture['home'],
                'date_time' => $this->resolveFixtureDateTime(
                    $fixture['year'],
                    $fixture['month'],
                    $fixture['day'],
                    $fixture['time'],
                    $exists ? $game->date_time : null,
                ),
                'place' => $fixture['loc'],
                'home_away' => $isHome ? HomeAway::Home : HomeAway::Away,
                'competition' => $fixture['comp'],
                'home_points' => $hasScore ? $fixture['pc'] : null,
                'away_points' => $hasScore ? $fixture['pf'] : null,
                'status' => $hasScore ? GameStatus::Played : GameStatus::Scheduled,
            ];

            if (! $exists) {
                $game->fill(Game::defaultAttributes());
            }

            $game->fill($attributes);
            $this->storeOpponentPhoto(
                $game,
                $isHome ? $fixture['away_logo'] : $fixture['home_logo'],
            );
            $dirty = $game->isDirty();
            $game->save();

            if (! $exists) {
                $created++;
            } elseif ($dirty) {
                $updated++;
            }
        }

        $deleted = 0;

        if ($prune) {
            $stale = Game::query()
                ->where('team_id', $team->id)
                ->whereNotNull('fpb_id')
                ->whereNotIn('fpb_id', $seenIds)
                ->get();

            foreach ($stale as $game) {
                $this->deleteLocalImage($game->opponent_photo);
                $game->delete();
                $deleted++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'deleted' => $deleted,
            'total' => count($seenIds),
        ];
    }

    /**
     * @return array{created:int, updated:int, deleted:int, total:int}
     */
    public function importPlayers(Team $team, ?bool $prune = null): array
    {
        $prune ??= (bool) config('fpb.prune_missing');

        if (! $team->fpb_url) {
            throw new RuntimeException(__('Team ":name" has no FPB link.', ['name' => $team->name]));
        }

        $html = $this->fetch($team->fpb_url);
        $athletes = $this->parsePlayers($html);

        if ($athletes === []) {
            throw new RuntimeException(__('No roster was found on the page (unexpected structure or no athletes).'));
        }

        $created = 0;
        $updated = 0;
        $seenIds = [];

        foreach ($athletes as $athlete) {
            $seenIds[] = $athlete['fpb_id'];

            $player = Player::firstOrNew([
                'team_id' => $team->id,
                'fpb_id' => $athlete['fpb_id'],
            ]);

            if (! $player->exists) {
                $player->fill([
                    'name' => $athlete['name'],
                    'slug' => $this->uniquePlayerSlug($team, $athlete['name']),
                    'number' => $this->availableShirtNumber($team, $athlete['number']),
                    'position' => $athlete['position'] !== '' ? $athlete['position'] : null,
                    'order' => 0,
                    'enabled' => true,
                    'photo' => $athlete['photo_url'] !== '' ? $this->downloadImage($athlete['photo_url'], 'players') : null,
                ]);
                $player->save();
                $created++;

                continue;
            }

            $player->name = $athlete['name'];
            $player->number = $this->availableShirtNumber($team, $athlete['number'], $player->id);

            if ($athlete['position'] !== '') {
                $player->position = $athlete['position'];
            }

            if ($player->isDirty()) {
                $player->save();
                $updated++;
            }
        }

        $deleted = 0;

        if ($prune) {
            $stale = Player::query()
                ->where('team_id', $team->id)
                ->whereNotNull('fpb_id')
                ->whereNotIn('fpb_id', $seenIds)
                ->get();

            foreach ($stale as $player) {
                $this->deleteLocalImage($player->photo);
                $player->delete();
                $deleted++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'deleted' => $deleted,
            'total' => count($seenIds),
        ];
    }

    /**
     * Fetch a single game sheet and map it for the given team.
     *
     * @return array{
     *     opponent: string,
     *     home_away: HomeAway,
     *     date_time: CarbonImmutable,
     *     place: string|null,
     *     competition: string|null,
     *     journey: string|null,
     *     home_points: int|null,
     *     away_points: int|null,
     *     status: GameStatus,
     *     opponent_logo: string|null,
     * }
     */
    public function fetchGameForTeam(Team $team, string $fpbId): array
    {
        $fpbId = trim($fpbId);

        if ($fpbId === '' || preg_match('/^\d+$/', $fpbId) !== 1) {
            throw new RuntimeException(__('The FPB ID is invalid.'));
        }

        $html = $this->fetch($this->gameSheetUrl($fpbId));
        $sheet = $this->parseGameSheet($html);

        if ($sheet === null) {
            throw new RuntimeException(__('No game was found for FPB ID :id.', ['id' => $fpbId]));
        }

        $team->loadMissing('club');

        $isHome = $this->nameMatchesTeam($sheet['home'], $team);
        $isAway = $this->nameMatchesTeam($sheet['away'], $team);

        if ($isHome === $isAway) {
            throw new RuntimeException(__('Could not match team ":name" to either side of the FPB game.', ['name' => $team->name]));
        }

        $hasScore = $sheet['home_points'] !== null && $sheet['away_points'] !== null;

        return [
            'opponent' => $isHome ? $sheet['away'] : $sheet['home'],
            'home_away' => $isHome ? HomeAway::Home : HomeAway::Away,
            'date_time' => $sheet['date_time'],
            'place' => $sheet['place'],
            'competition' => $sheet['competition'],
            'journey' => $sheet['journey'],
            'home_points' => $sheet['home_points'],
            'away_points' => $sheet['away_points'],
            'status' => $hasScore ? GameStatus::Played : GameStatus::Scheduled,
            'opponent_logo' => $isHome ? $sheet['away_logo'] : $sheet['home_logo'],
        ];
    }

    public function attachOpponentLogo(Game $game, ?string $logoUrl): void
    {
        if ($logoUrl === null || $logoUrl === '') {
            return;
        }

        $this->storeOpponentPhoto($game, $logoUrl);

        if ($game->isDirty('opponent_photo')) {
            $game->save();
        }
    }

    /**
     * @return array{
     *     home: string,
     *     away: string,
     *     place: string|null,
     *     competition: string|null,
     *     journey: string|null,
     *     home_points: int|null,
     *     away_points: int|null,
     *     home_logo: string|null,
     *     away_logo: string|null,
     *     date_time: CarbonImmutable,
     * }|null
     */
    public function parseGameSheet(string $html): ?array
    {
        if (! preg_match('/<div class="match-wrapper">(.*?)<\/section>/su', $html, $wrapper)) {
            return null;
        }

        $block = $wrapper[1];

        if (! preg_match('/<div class="team home">(.*?)<\/div>\s*<div class="points">/su', $block, $homeBlock)) {
            return null;
        }

        if (! preg_match('/<div class="team away">(.*?)<\/div>\s*<\/div>/su', $block, $awayBlock)) {
            return null;
        }

        if (! preg_match('/class="bigName"[^>]*>\s*(.*?)\s*<\/p>/su', $homeBlock[1], $homeName)) {
            return null;
        }

        if (! preg_match('/class="bigName"[^>]*>\s*(.*?)\s*<\/p>/su', $awayBlock[1], $awayName)) {
            return null;
        }

        preg_match('/class="date">\s*(.*?)\s*<\/p>/su', $block, $date);
        preg_match('/class="phase">\s*(.*?)\s*<\/p>/su', $block, $phase);
        preg_match('/class="location">.*?<a[^>]*>\s*(.*?)\s*<\/a>/su', $block, $location);
        preg_match('/<div class="points">(.*?)<\/div>\s*<div class="team away">/su', $block, $pointsBlock);
        preg_match('/<title>\s*(.*?)\s*<\/title>/su', $html, $title);

        $homeLogo = null;
        $awayLogo = null;

        if (preg_match('/<img[^>]*\bsrc=(?:"([^"]+)"|([^\s>]+))/su', $homeBlock[1], $homeImg)) {
            $homeLogo = $this->absoluteFpbUrl($homeImg[1] !== '' ? $homeImg[1] : $homeImg[2]);
        }

        if (preg_match('/<img[^>]*\bsrc=(?:"([^"]+)"|([^\s>]+))/su', $awayBlock[1], $awayImg)) {
            $awayLogo = $this->absoluteFpbUrl($awayImg[1] !== '' ? $awayImg[1] : $awayImg[2]);
        }

        $homePoints = null;
        $awayPoints = null;

        if (isset($pointsBlock[1]) && preg_match_all('/<span[^>]*>\s*(\d+)\s*<\/span>/su', $pointsBlock[1], $scores) && count($scores[1]) >= 2) {
            $homePoints = (int) $scores[1][0];
            $awayPoints = (int) $scores[1][1];
        }

        $competition = null;

        if (isset($title[1]) && str_contains($title[1], '|')) {
            $competition = $this->clean((string) Str::afterLast($title[1], '|'));
        }

        $time = null;

        if (! isset($date[1]) || ! preg_match('/(\d{1,2})\s+([A-Z]{3})\s+(20\d\d)/u', $this->clean($date[1]), $dateParts)) {
            return null;
        }

        $day = (int) $dateParts[1];
        $month = self::MESES[$dateParts[2]] ?? null;
        $year = (int) $dateParts[3];

        if ($month === null) {
            return null;
        }

        return [
            'home' => $this->clean($homeName[1]),
            'away' => $this->clean($awayName[1]),
            'place' => isset($location[1]) ? $this->clean($location[1]) : null,
            'competition' => $competition !== null && $competition !== '' ? $competition : null,
            'journey' => isset($phase[1]) ? $this->clean($phase[1]) : null,
            'home_points' => $homePoints,
            'away_points' => $awayPoints,
            'home_logo' => $homeLogo,
            'away_logo' => $awayLogo,
            'date_time' => $this->resolveFixtureDateTime($year, $month, $day, $time, null),
        ];
    }

    /**
     * @return array<int, array{fpb_id: string, name: string, position: string, number: string, photo_url: string}>
     */
    public function parsePlayers(string $html): array
    {
        $segments = explode('<div class="roster__player">', $html);
        array_shift($segments);

        $athletes = [];

        foreach ($segments as $segment) {
            if (! preg_match('#/atletas/(\d+)#', $segment, $idMatch)) {
                continue;
            }

            preg_match('/header__heading">\s*(.*?)\s*<\/h2>/su', $segment, $name);
            preg_match('/header_position">\s*(.*?)\s*<\/div>/su', $segment, $position);
            preg_match('/header_jnumber">\s*(\d*)/su', $segment, $number);
            preg_match('/roster__player__bust"\s+src="([^"]*)"/su', $segment, $photo);

            if (empty($name[1])) {
                continue;
            }

            $athletes[$idMatch[1]] = [
                'fpb_id' => $idMatch[1],
                'name' => $this->clean($name[1]),
                'position' => isset($position[1]) ? $this->clean($position[1]) : '',
                'number' => $number[1] ?? '',
                'photo_url' => $photo[1] ?? '',
            ];
        }

        return array_values($athletes);
    }

    /**
     * @param  list<array{home: string, away: string}>  $fixtures
     */
    public function detectClub(array $fixtures): string
    {
        $counts = [];

        foreach ($fixtures as $fixture) {
            $counts[$fixture['home']] = ($counts[$fixture['home']] ?? 0) + 1;
            $counts[$fixture['away']] = ($counts[$fixture['away']] ?? 0) + 1;
        }

        arsort($counts);

        return (string) array_key_first($counts);
    }

    public function fetch(string $url): string
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
            'Accept-Language' => 'pt-PT,pt;q=0.9',
        ])->timeout(30)->retry(2, 500)->get($url);

        if (! $response->successful()) {
            throw new RuntimeException(__('Failed to fetch the FPB page (HTTP :status).', ['status' => $response->status()]));
        }

        return $response->body();
    }

    /**
     * @return list<array{
     *     id: string,
     *     year: int,
     *     month: int,
     *     day: int,
     *     home: string,
     *     away: string,
     *     loc: string|null,
     *     comp: string|null,
     *     pc: int|null,
     *     pf: int|null,
     *     time: string|null,
     *     home_logo: string|null,
     *     away_logo: string|null,
     *     date_time: CarbonImmutable,
     * }>
     */
    public function parse(string $html): array
    {
        preg_match_all('/<h3 class="date">\s*(.*?)\s*<\/h3>/su', $html, $dateMatches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        $games = [];
        $count = count($dateMatches);

        for ($i = 0; $i < $count; $i++) {
            $offset = $dateMatches[$i][0][1];
            $end = $i + 1 < $count ? $dateMatches[$i + 1][0][1] : strlen($html);
            $block = substr($html, $offset, $end - $offset);

            if (! preg_match('/(\d{1,2})\s+([A-Z]{3})\s+(20\d\d)/u', $dateMatches[$i][1][0], $dateParts)) {
                continue;
            }

            $day = (int) $dateParts[1];
            $month = self::MESES[$dateParts[2]] ?? null;
            $year = (int) $dateParts[3];

            if (! $month) {
                continue;
            }

            $segments = explode('href="/ficha-de-jogo?internalID=', $block);
            array_shift($segments);

            foreach ($segments as $segment) {
                if (! preg_match('/^(\d+)/', $segment, $idMatch)) {
                    continue;
                }

                $id = $idMatch[1];

                preg_match_all('/<div class="image-container">\s*<img\b[^>]*\bsrc="([^"]*)"/su', $segment, $logos);
                preg_match_all('/<span class="fullName">\s*(.*?)\s*<\/span>/su', $segment, $names);

                if (count($names[1]) < 2) {
                    continue;
                }

                $home = $this->clean($names[1][0]);
                $away = $this->clean($names[1][1]);

                preg_match_all('/results_text[^>]*>\s*(\d+)/su', $segment, $scores);
                preg_match('/class="hour[^"]*">\s*<h3>\s*(\d{1,2})H(\d{2})/su', $segment, $hour);
                preg_match('/location-wrapper.*?<b>\s*(.*?)\s*<\/b>/su', $segment, $location);
                preg_match('/class="competition">\s*<span>\s*(.*?)\s*<\/span>/su', $segment, $competition);

                $game = $games[$id] ?? [
                    'id' => $id,
                    'year' => $year,
                    'month' => $month,
                    'day' => $day,
                    'home' => $home,
                    'away' => $away,
                    'loc' => null,
                    'comp' => null,
                    'pc' => null,
                    'pf' => null,
                    'time' => null,
                    'home_logo' => null,
                    'away_logo' => null,
                ];

                $homeLogo = $this->absoluteFpbUrl($logos[1][0] ?? null);
                $awayLogo = $this->absoluteFpbUrl($logos[1][1] ?? null);

                if ($homeLogo !== null) {
                    $game['home_logo'] = $homeLogo;
                }

                if ($awayLogo !== null) {
                    $game['away_logo'] = $awayLogo;
                }

                if (isset($location[1])) {
                    $game['loc'] = str_replace(' ,', ', ', $this->clean($location[1]));
                }

                if (isset($competition[1])) {
                    $parts = explode('|', $this->clean($competition[1]));
                    $game['comp'] = trim((string) end($parts));
                }

                if (count($scores[1]) >= 2 && $game['pc'] === null) {
                    $game['pc'] = (int) $scores[1][0];
                    $game['pf'] = (int) $scores[1][1];
                }

                if ($hour && ! $game['time']) {
                    $game['time'] = sprintf('%02d:%s', (int) $hour[1], $hour[2]);
                }

                $games[$id] = $game;
            }
        }

        /** @var list<array{id: string, year: int, month: int, day: int, home: string, away: string, loc: string|null, comp: string|null, pc: int|null, pf: int|null, time: string|null, home_logo: string|null, away_logo: string|null, date_time: CarbonImmutable}> $fixtures */
        $fixtures = array_map(function (array $game): array {
            $game['date_time'] = $this->resolveFixtureDateTime(
                $game['year'],
                $game['month'],
                $game['day'],
                $game['time'],
                null,
            );

            return $game;
        }, array_values($games));

        return $fixtures;
    }

    protected function resolveFixtureDateTime(int $year, int $month, int $day, ?string $time, ?CarbonImmutable $existing): CarbonImmutable
    {
        $date = CarbonImmutable::create($year, $month, $day);

        if ($time !== null) {
            return $date->setTime(
                (int) substr($time, 0, 2),
                (int) substr($time, 3, 2),
            );
        }

        if ($existing !== null) {
            return $date->setTime($existing->hour, $existing->minute, $existing->second);
        }

        return $date->startOfDay();
    }

    protected function uniquePlayerSlug(Team $team, string $name, ?int $ignorePlayerId = null): string
    {
        $base = Player::slugFromName($name);
        $slug = $base;
        $suffix = 2;

        while (Player::query()
            ->where('team_id', $team->id)
            ->where('slug', $slug)
            ->when($ignorePlayerId !== null, fn ($query) => $query->whereKeyNot($ignorePlayerId))
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    protected function availableShirtNumber(Team $team, string $number, ?int $ignorePlayerId = null): ?int
    {
        if ($number === '') {
            return null;
        }

        $shirtNumber = (int) $number;

        $taken = Player::query()
            ->where('team_id', $team->id)
            ->where('number', $shirtNumber)
            ->when($ignorePlayerId !== null, fn ($query) => $query->whereKeyNot($ignorePlayerId))
            ->exists();

        return $taken ? null : $shirtNumber;
    }

    protected function storeOpponentPhoto(Game $game, ?string $logoUrl): void
    {
        if ($logoUrl === null || ($game->opponent_photo !== null && ! $game->isDirty('opponent'))) {
            return;
        }

        $downloaded = $this->downloadImage($logoUrl, 'opponents');

        if ($downloaded === null) {
            return;
        }

        $this->deleteLocalImage($game->opponent_photo);
        $game->opponent_photo = $downloaded;
    }

    protected function absoluteFpbUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        return 'https://www.fpb.pt/'.ltrim($url, '/');
    }

    protected function downloadImage(string $url, string $directory): ?string
    {
        try {
            $response = Http::timeout(20)->get($url);

            if (! $response->successful() || $response->body() === '') {
                return null;
            }

            $extension = pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $path = $directory.'/'.Str::random(40).'.'.$extension;

            Storage::disk('public')->put($path, $response->body());

            return $path;
        } catch (Throwable) {
            return null;
        }
    }

    protected function deleteLocalImage(?string $path): void
    {
        if ($path && ! Str::startsWith($path, ['http://', 'https://', '/']) && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    protected function gameSheetUrl(string $fpbId): string
    {
        $template = (string) config('fpb.game_sheet_url');

        if (str_contains($template, '{id}')) {
            return str_replace('{id}', $fpbId, $template);
        }

        return rtrim($template, '=').'='.$fpbId;
    }

    protected function clean(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/<\?(?:php|=)?[\s\S]*?\?>/i', '', $value) ?? '';
        $value = strip_tags($value);

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    protected function nameMatchesTeam(string $fpbName, Team $team): bool
    {
        $normalized = $this->normalizeName($fpbName);

        if ($normalized === '') {
            return false;
        }

        foreach ([$team->name, $team->club->name] as $candidate) {
            if ($candidate === '') {
                continue;
            }

            $normalizedCandidate = $this->normalizeName($candidate);

            if ($normalizedCandidate === '') {
                continue;
            }

            if (
                $normalized === $normalizedCandidate
                || str_contains($normalized, $normalizedCandidate)
                || str_contains($normalizedCandidate, $normalized)
            ) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeName(string $value): string
    {
        $value = Str::lower($this->clean($value));
        $value = Str::ascii($value);

        return preg_replace('/[^a-z0-9]+/u', '', $value) ?? '';
    }
}
