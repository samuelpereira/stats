<?php

use App\Enums\HomeAway;
use App\Enums\StatType;
use App\Livewire\Games\Edit as EditGame;
use App\Models\Club;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use App\Support\ShotCourt;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * @return array{administrator: User, game: Game}
 */
function gameReadyForPlayByPlayImport(): array
{
    $club = Club::factory()->create();
    $administrator = User::factory()->administrator()->create();
    $team = Team::factory()->create(['club_id' => $club->id, 'name' => 'North City U18']);
    $game = Game::factory()->create([
        'team_id' => $team->id,
        'opponent' => 'South Valley',
        'home_away' => HomeAway::Home,
        'quarter_minutes' => 10,
    ]);

    foreach ([0, 9, 18, 21, 22, 23, 24, 30, 33, 84] as $number) {
        Player::factory()->create([
            'team_id' => $team->id,
            'number' => $number,
            'name' => 'Player '.$number.' Surname',
        ]);
    }

    return [
        'administrator' => $administrator,
        'game' => $game,
    ];
}

/**
 * @param  list<list<string>>  $rows
 */
function playByPlayCsv(array $rows): string
{
    $handle = fopen('php://temp', 'r+');
    fputcsv($handle, ['player', 'user_team', 'opp_team', 'stat', 'created_at', 'lineup', 'lineup_created_at', 'time_period', 'is_opp', 'player_id', 'game_id', 'team_id', 'opp_id', 'court_pos_x', 'court_pos_y', 'game_time_sec']);

    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }

    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    return is_string($csv) ? $csv : '';
}

/**
 * @return list<string>
 */
function playByPlayRow(
    string $player,
    string $stat,
    string $lineup,
    string $period,
    string $opponent,
    string $team = 'North City U18',
    string $opposition = 'South Valley',
    string $courtX = '',
    string $courtY = '',
    string $createdAt = '2026-10-02T20:00:00Z',
): array {
    return [$player, $team, $opposition, $stat, $createdAt, $lineup, $createdAt, $period, $opponent, '', '', '', '', $courtX, $courtY, '0'];
}

test('an administrator replaces the statistics log from a play-by-play csv', function () {
    ['administrator' => $administrator, 'game' => $game] = gameReadyForPlayByPlayImport();
    $incoming = Player::query()->where('team_id', $game->team_id)->where('number', 21)->first();
    $outgoing = Player::query()->where('team_id', $game->team_id)->where('number', 30)->first();

    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => null,
        'home_away' => null,
        'type' => StatType::QuarterEnded,
        'quarter' => 9,
        'clock' => '12:34',
    ]);

    Livewire::actingAs($administrator)
        ->test(EditGame::class, ['game' => $game])
        ->assertSee('This game has a statistics log.')
        ->assertSee('Importing replaces every event in this game.')
        ->set('logFile', UploadedFile::fake()->createWithContent('play-by-play.csv', (string) file_get_contents(base_path('tests/Fixtures/play-by-play.csv'))))
        ->call('importLog')
        ->assertHasNoErrors()
        ->assertSee('This game has a statistics log.')
        ->set('logFile', UploadedFile::fake()->createWithContent('play-by-play.csv', (string) file_get_contents(base_path('tests/Fixtures/play-by-play.csv'))))
        ->call('importLog')
        ->assertHasNoErrors();

    expect($game->events()->count())->toBe(325)
        ->and($game->events()->where('type', StatType::TwoPointMade)->count())->toBe(38)
        ->and($game->events()->where('quarter', 9)->exists())->toBeFalse()
        ->and($game->events()->where('type', StatType::QuarterStarted)->where('clock', '10:00')->exists())->toBeTrue()
        ->and($game->events()->where('type', StatType::TwoPointMade)->where('clock', '!=', '00:00')->exists())->toBeTrue()
        ->and($game->events()->where('type', StatType::Substitution)->where('clock', '!=', '00:00')->exists())->toBeTrue()
        ->and($game->events()->where('type', StatType::Substitution)->where('player_id', $incoming?->id)->where('outgoing_player_id', $outgoing?->id)->exists())->toBeTrue();

    foreach ([22, 23, 24, 30, 84] as $number) {
        $player = Player::query()->where('team_id', $game->team_id)->where('number', $number)->first();

        expect($game->players()->whereKey($player?->id)->wherePivot('starter', true)->exists())->toBeTrue();
    }

    expect($game->players()->whereKey($incoming?->id)->wherePivot('starter', false)->exists())->toBeTrue();

    $shooter = Player::query()->where('team_id', $game->team_id)->where('number', 23)->first();
    $corner = ShotCourt::fromFeet(47.12, 4.99);
    $paint = ShotCourt::fromFeet(22.42, 8.05);
    $cornerShot = $game->events()->where('type', StatType::ThreePointMissed)->where('player_id', $shooter?->id)->orderBy('id')->first();
    $paintShot = $game->events()->where('type', StatType::TwoPointMissed)->whereNull('player_id')->orderBy('id')->first();

    expect($cornerShot?->court_x)->toBe($corner['x'])
        ->and($cornerShot?->court_y)->toBe($corner['y'])
        ->and($paintShot?->court_x)->toBe($paint['x'])
        ->and($paintShot?->court_y)->toBe($paint['y']);
});

test('a play-by-play csv for another game does not replace the log', function () {
    ['administrator' => $administrator, 'game' => $game] = gameReadyForPlayByPlayImport();
    $lineup = '#22 A,#23 B,#24 C,#30 D,#84 E';

    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => null,
        'home_away' => null,
        'type' => StatType::QuarterStarted,
        'quarter' => 1,
        'clock' => '10:00',
    ]);

    Livewire::actingAs($administrator)
        ->test(EditGame::class, ['game' => $game])
        ->set('logFile', UploadedFile::fake()->createWithContent('log.csv', playByPlayCsv([
            playByPlayRow('#22 A', 'FloorTime', $lineup, '1', '0', opposition: 'Metro United'),
        ])))
        ->call('importLog')
        ->assertSee('The CSV is for North City U18 against Metro United.');

    expect($game->events()->count())->toBe(1);
});

test('a play-by-play import derives player minutes from created_at', function () {
    ['administrator' => $administrator, 'game' => $game] = gameReadyForPlayByPlayImport();
    $starters = '#22 A,#23 B,#24 C,#30 D,#84 E';
    $afterSub = '#21 F,#22 A,#23 B,#24 C,#84 E';
    $outgoing = Player::query()->where('team_id', $game->team_id)->where('number', 30)->first();
    $incoming = Player::query()->where('team_id', $game->team_id)->where('number', 21)->first();

    Livewire::actingAs($administrator)
        ->test(EditGame::class, ['game' => $game])
        ->set('logFile', UploadedFile::fake()->createWithContent('log.csv', playByPlayCsv([
            playByPlayRow('#22 A', 'FloorTime', $starters, '1', '0', createdAt: '2026-10-02T20:00:00Z'),
            playByPlayRow('#22 A', 'TwoPointFG', $starters, '1', '0', createdAt: '2026-10-02T20:02:00Z'),
            playByPlayRow('#21 F', 'FloorTime', $afterSub, '1', '0', createdAt: '2026-10-02T20:05:00Z'),
            playByPlayRow('#22 A', 'FloorTime', $afterSub, '1', '0', createdAt: '2026-10-02T20:05:00Z'),
            playByPlayRow('#23 B', 'FloorTime', $afterSub, '1', '0', createdAt: '2026-10-02T20:05:00Z'),
            playByPlayRow('#24 C', 'FloorTime', $afterSub, '1', '0', createdAt: '2026-10-02T20:05:00Z'),
            playByPlayRow('#84 E', 'FloorTime', $afterSub, '1', '0', createdAt: '2026-10-02T20:05:00Z'),
            playByPlayRow('#22 A', 'Foul', $afterSub, '1', '0', createdAt: '2026-10-02T20:10:00Z'),
        ])))
        ->call('importLog')
        ->assertHasNoErrors();

    $boxScore = $game->fresh()->boxScore();
    $outgoingLine = collect($boxScore['players'])->firstWhere('id', $outgoing?->id);
    $incomingLine = collect($boxScore['players'])->firstWhere('id', $incoming?->id);
    $substitution = $game->events()->where('type', StatType::Substitution)->first();

    expect($substitution?->clock)->toBe('05:00')
        ->and($outgoingLine['minutes'])->toBe('5:00')
        ->and($incomingLine['minutes'])->toBe('5:00');
});

test('a play-by-play import stores the court position', function () {
    ['administrator' => $administrator, 'game' => $game] = gameReadyForPlayByPlayImport();
    $lineup = '#22 A,#23 B,#24 C,#30 D,#84 E';
    $player = Player::query()->where('team_id', $game->team_id)->where('number', 22)->first();
    $made = ShotCourt::fromFeet(22.42, 8.05);
    $missed = ShotCourt::fromFeet(47.12, 4.99);

    Livewire::actingAs($administrator)
        ->test(EditGame::class, ['game' => $game])
        ->set('logFile', UploadedFile::fake()->createWithContent('log.csv', playByPlayCsv([
            playByPlayRow('#22 A', 'FloorTime', $lineup, '1', '0'),
            playByPlayRow('#22 A', 'TwoPointFG', $lineup, '1', '0', courtX: '22,42', courtY: '8,05'),
            playByPlayRow('South Valley', 'ThreePointFGMiss', $lineup, '1', '1', courtX: '47,12', courtY: '4,99'),
            playByPlayRow('#22 A', 'Foul', $lineup, '1', '0'),
        ])))
        ->call('importLog')
        ->assertHasNoErrors();

    $shot = $game->events()->where('type', StatType::TwoPointMade)->first();
    $opponentShot = $game->events()->where('type', StatType::ThreePointMissed)->first();
    $foul = $game->events()->where('type', StatType::Foul)->first();

    expect($shot?->player_id)->toBe($player?->id)
        ->and($shot?->court_x)->toBe($made['x'])
        ->and($shot?->court_y)->toBe($made['y'])
        ->and($opponentShot?->player_id)->toBeNull()
        ->and($opponentShot?->court_x)->toBe($missed['x'])
        ->and($opponentShot?->court_y)->toBe($missed['y'])
        ->and($foul?->court_x)->toBeNull()
        ->and($foul?->court_y)->toBeNull();
});

test('a play-by-play court position is rejected', function (string $courtX, string $courtY, string $message) {
    ['administrator' => $administrator, 'game' => $game] = gameReadyForPlayByPlayImport();
    $lineup = '#22 A,#23 B,#24 C,#30 D,#84 E';

    Livewire::actingAs($administrator)
        ->test(EditGame::class, ['game' => $game])
        ->set('logFile', UploadedFile::fake()->createWithContent('log.csv', playByPlayCsv([
            playByPlayRow('#22 A', 'FloorTime', $lineup, '1', '0'),
            playByPlayRow('#22 A', 'TwoPointFG', $lineup, '1', '0', courtX: $courtX, courtY: $courtY),
        ])))
        ->call('importLog')
        ->assertSee($message);

    expect($game->events()->count())->toBe(0);
})->with([
    'text' => ['nope', '8,05', 'Row 3: The court position nope is not a number.'],
    'only one coordinate' => ['22,42', '', 'Row 3: A court position needs both court_pos_x and court_pos_y.'],
    'outside the court' => ['99999', '8,05', 'Row 3: The court position is outside the court.'],
]);

test('a play-by-play player is rejected', function (string $player, string $message) {
    ['administrator' => $administrator, 'game' => $game] = gameReadyForPlayByPlayImport();
    $lineup = '#22 A,#23 B,#24 C,#30 D,#84 E';

    Livewire::actingAs($administrator)
        ->test(EditGame::class, ['game' => $game])
        ->set('logFile', UploadedFile::fake()->createWithContent('log.csv', playByPlayCsv([
            playByPlayRow('#22 A', 'FloorTime', $lineup, '1', '0'),
            playByPlayRow($player, 'TwoPointFG', $lineup, '1', '0'),
        ])))
        ->call('importLog')
        ->assertSee($message);

    expect($game->events()->count())->toBe(0);
})->with([
    'unknown number' => ['#99 Missing', 'Row 3: No player wears number 99.'],
    'text that is not a number' => ['<script>', 'Row 3: Player <script> is not a jersey number.'],
]);

test('only an administrator can import a statistics log', function (string $role) {
    $club = Club::factory()->create();
    $team = Team::factory()->create(['club_id' => $club->id, 'name' => 'North City U18']);
    $game = Game::factory()->create([
        'team_id' => $team->id,
        'opponent' => 'South Valley',
    ]);
    $user = $role === 'club'
        ? User::factory()->clubAccessFor($club)->create()
        : User::factory()->manager()->create();

    Livewire::actingAs($user)
        ->test(EditGame::class, ['game' => $game])
        ->assertDontSee('Statistics log')
        ->call('importLog')
        ->assertForbidden();
})->with(['club', 'manager']);

test('a statistics import requires a csv file', function () {
    ['administrator' => $administrator, 'game' => $game] = gameReadyForPlayByPlayImport();

    Livewire::actingAs($administrator)
        ->test(EditGame::class, ['game' => $game])
        ->assertSee('This game has no statistics log.')
        ->call('importLog')
        ->assertSee('The log file field is required.');
});
