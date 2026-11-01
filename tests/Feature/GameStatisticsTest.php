<?php

use App\Enums\FoulKind;
use App\Enums\GameStatus;
use App\Enums\HomeAway;
use App\Enums\StatType;
use App\Enums\TeamColor;
use App\Livewire\Statistics\Show as StatisticsShow;
use App\Models\Club;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use App\Support\ShotCourt;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('the statistics button opens the game box score', function () {
    $club = Club::factory()->create();
    $team = Team::factory()->create([
        'club_id' => $club->id,
        'name' => 'ADC',
        'photo' => 'teams/badge.png',
    ]);
    $member = User::factory()->memberOf($club)->withTeams($team)->create();
    $game = Game::factory()->create([
        'team_id' => $team->id,
        'opponent' => 'Metro United',
        'home_away' => HomeAway::Home,
        'date_time' => '2026-10-02 20:30:00',
    ]);
    $game->forceFill(['opponent_photo' => 'opponents/crest.png'])->save();
    $player = Player::factory()->create([
        'team_id' => $team->id,
        'name' => 'Jordan Lake',
        'number' => 7,
    ]);
    $bench = Player::factory()->create([
        'team_id' => $team->id,
        'name' => 'Pedro Silva',
        'number' => 12,
    ]);

    $game->players()->attach([
        $player->id => ['starter' => true],
        $bench->id => ['starter' => false],
    ]);

    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => null,
        'home_away' => null,
        'type' => StatType::QuarterStarted,
        'quarter' => 1,
        'clock' => '10:00',
    ]);
    $made = ShotCourt::fromFeet(22.42, 8.05);
    $missed = ShotCourt::fromFeet(47.12, 4.99);

    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => $player->id,
        'home_away' => HomeAway::Home,
        'type' => StatType::TwoPointMade,
        'quarter' => 1,
        'clock' => '09:00',
        'court_x' => $made['x'],
        'court_y' => $made['y'],
    ]);
    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => $player->id,
        'home_away' => HomeAway::Home,
        'type' => StatType::ThreePointMissed,
        'quarter' => 1,
        'clock' => '08:30',
        'court_x' => $missed['x'],
        'court_y' => $missed['y'],
    ]);
    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => null,
        'home_away' => HomeAway::Away,
        'type' => StatType::ThreePointMade,
        'quarter' => 1,
        'clock' => '08:00',
    ]);
    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => $player->id,
        'home_away' => HomeAway::Home,
        'type' => StatType::Foul,
        'quarter' => 1,
        'clock' => '07:30',
    ]);
    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => $player->id,
        'home_away' => HomeAway::Home,
        'type' => StatType::Foul,
        'quarter' => 1,
        'clock' => '07:00',
    ]);
    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => null,
        'home_away' => HomeAway::Away,
        'type' => StatType::Foul,
        'quarter' => 1,
        'clock' => '06:30',
    ]);

    $this->actingAs($member)
        ->get(route('statistics.show', ['game' => $game, 'from' => 'dashboard']))
        ->assertOk()
        ->assertSee(route('dashboard'), false)
        ->assertDontSee(__('Turn the screen sideways'))
        ->assertSee('ADC 2 vs Metro United 3', false)
        ->assertDontSee('ADC 2 vs Metro United 3 - ', false)
        ->assertSeeInOrder(['Box score', 'Matchup'])
        ->assertSee('/storage/opponents/crest.png', false);

    $this->actingAs($member)
        ->get('http://127.0.0.1:8082'.route('statistics.show', ['game' => $game, 'from' => 'dashboard'], false))
        ->assertSee('http://127.0.0.1:8082/storage/teams/badge.png', false)
        ->assertDontSee('http://localhost:8000/storage/teams/badge.png', false);

    $this->actingAs($member)
        ->get(route('statistics.show', ['game' => $game, 'from' => 'statistics']))
        ->assertSee(route('statistics.index'), false);

    $this->actingAs($member)
        ->get(route('statistics.show', ['game' => $game, 'from' => 'games']))
        ->assertSee(route('games.index'), false);

    $this->actingAs($member)
        ->get(route('statistics.show', [
            'game' => $game,
            'from' => 'games',
            'season' => $team->season_id,
            'team' => $team->id,
        ]))
        ->assertOk()
        ->assertSee(route('games.index', [
            'season' => $team->season_id,
            'team' => $team->id,
        ]));

    $this->actingAs($member)
        ->get(route('statistics.show', ['game' => $game, 'from' => 'record']))
        ->assertSee(route('statistics.record', $game), false);

    Livewire::actingAs($member)
        ->test(StatisticsShow::class, ['game' => $game])
        ->assertSee('ADC')
        ->assertSee('Metro United')
        ->assertDontSee('2 Oct 2026')
        ->assertSeeInOrder(['Jordan Lake', '*', 'Pedro Silva'])
        ->assertSee('color: '.$game->home_color->hex(), false)
        ->assertSee('Starting five')
        ->assertSee('On court')
        ->assertSee('Bench')
        ->assertViewHas('courtPlayers', fn (array $players): bool => count($players) === 1 && $players[0]['name'] === 'Jordan Lake')
        ->assertViewHas('benchPlayers', fn (array $players): bool => count($players) === 1 && $players[0]['name'] === 'Pedro Silva')
        ->assertSee('Q1')
        ->assertSee('T')
        ->assertDontSee('>F<', false)
        ->assertSee('1-1')
        ->assertSee('Matchup')
        ->assertSee('Game flow')
        ->assertViewHas('gameFlow', fn (?array $flow): bool => $flow !== null
            && $flow['points'][0] === ['x' => 0.0, 'home' => 0, 'away' => 0]
            && $flow['points'][1]['home'] === 2
            && $flow['points'][1]['away'] === 0
            && $flow['points'][2]['home'] === 2
            && $flow['points'][2]['away'] === 3
            && $flow['markers'][0]['label'] === 'Q1'
            && $flow['maxScore'] === 3)
        ->assertSee('Shot Charts')
        ->assertSee('Q1')
        ->assertViewHas('shotChartsByPeriod', fn (array $charts): bool => count($charts) === 2
            && $charts[0]['label'] === 'Q1'
            && count($charts[0]['home']) === 2
            && count($charts[0]['away']) === 0
            && $charts[0]['home'][0]['made'] === true
            && $charts[0]['home'][1]['made'] === false
            && $charts[1]['label'] === 'Totals'
            && count($charts[1]['home']) === 2
            && count($charts[1]['away']) === 0)
        ->assertSeeInOrder(['PTS', 'FG (%)', '2P (%)', '3P (%)', 'FT (%)', 'DFR', 'OFR', 'TR', 'AST', 'BLK', 'STL', 'TO', 'PF+TF'])
        ->assertSet('backUrl', route('statistics.index'));
});

test('plus minus counts the margin only while that player is on the court', function () {
    $club = Club::factory()->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $member = User::factory()->memberOf($club)->withTeams($team)->create();
    $game = Game::factory()->create([
        'team_id' => $team->id,
        'home_away' => HomeAway::Away,
    ]);
    $stayer = Player::factory()->create(['team_id' => $team->id, 'name' => 'Nia Costa', 'number' => 4]);
    $outgoing = Player::factory()->create(['team_id' => $team->id, 'name' => 'Alex Reed', 'number' => 7]);
    $incoming = Player::factory()->create(['team_id' => $team->id, 'name' => 'Casey Dunn', 'number' => 11]);
    $unused = Player::factory()->create(['team_id' => $team->id, 'name' => 'Rui Melo', 'number' => 15]);

    $game->players()->attach([
        $stayer->id => ['starter' => true],
        $outgoing->id => ['starter' => true],
        $incoming->id => ['starter' => false],
        $unused->id => ['starter' => false],
    ]);

    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => null,
        'home_away' => null,
        'type' => StatType::QuarterStarted,
        'quarter' => 1,
        'clock' => '10:00',
    ]);
    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => $outgoing->id,
        'home_away' => HomeAway::Away,
        'type' => StatType::TwoPointMade,
        'quarter' => 1,
        'clock' => '09:00',
    ]);
    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => null,
        'home_away' => HomeAway::Home,
        'type' => StatType::FreeThrowMade,
        'quarter' => 1,
        'clock' => '08:30',
    ]);
    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => $incoming->id,
        'outgoing_player_id' => $outgoing->id,
        'home_away' => HomeAway::Away,
        'type' => StatType::Substitution,
        'quarter' => 1,
        'clock' => '08:00',
    ]);
    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => null,
        'home_away' => HomeAway::Home,
        'type' => StatType::ThreePointMade,
        'quarter' => 1,
        'clock' => '07:00',
    ]);

    $plusMinus = collect($game->boxScore()['players'])->pluck('plusMinus', 'name');

    expect($plusMinus->get('Nia Costa'))->toBe(-2)
        ->and($plusMinus->get('Alex Reed'))->toBe(1)
        ->and($plusMinus->get('Casey Dunn'))->toBe(-3)
        ->and($plusMinus->get('Rui Melo'))->toBe(0);

    $this->actingAs($member)
        ->get(route('statistics.show', $game))
        ->assertOk()
        ->assertSeeInOrder(['Player', 'Min', 'Pts'])
        ->assertSee('+1')
        ->assertSee('-2')
        ->assertSee('-3');
});

test('lineup stats total time, points and shooting for each court five', function () {
    $club = Club::factory()->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $member = User::factory()->memberOf($club)->withTeams($team)->create();
    $game = Game::factory()->create([
        'team_id' => $team->id,
        'home_away' => HomeAway::Home,
        'quarter_minutes' => 10,
    ]);
    $starters = Player::factory()->count(5)->sequence(
        ['name' => 'Starter A', 'number' => 1],
        ['name' => 'Starter B', 'number' => 2],
        ['name' => 'Starter C', 'number' => 3],
        ['name' => 'Starter D', 'number' => 4],
        ['name' => 'Starter E', 'number' => 5],
    )->create(['team_id' => $team->id]);
    $bench = Player::factory()->create([
        'team_id' => $team->id,
        'name' => 'Bench In',
        'number' => 12,
    ]);

    foreach ($starters as $starter) {
        $game->players()->attach($starter->id, ['starter' => true]);
    }
    $game->players()->attach($bench->id, ['starter' => false]);

    $event = fn (array $attributes) => GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => null,
        'home_away' => null,
        'quarter' => 1,
        ...$attributes,
    ]);

    $event(['type' => StatType::QuarterStarted, 'clock' => '10:00']);
    $event(['type' => StatType::TwoPointMade, 'player_id' => $starters[0]->id, 'home_away' => HomeAway::Home, 'clock' => '09:30']);
    $event(['type' => StatType::ThreePointMissed, 'player_id' => $starters[1]->id, 'home_away' => HomeAway::Home, 'clock' => '09:00']);
    $event(['type' => StatType::ThreePointMade, 'home_away' => HomeAway::Away, 'clock' => '08:30']);
    $event(['type' => StatType::DefensiveRebound, 'player_id' => $starters[0]->id, 'home_away' => HomeAway::Home, 'clock' => '08:25']);
    $event(['type' => StatType::OffensiveRebound, 'player_id' => $starters[1]->id, 'home_away' => HomeAway::Home, 'clock' => '08:20']);
    $event(['type' => StatType::Assist, 'player_id' => $starters[2]->id, 'home_away' => HomeAway::Home, 'clock' => '08:18']);
    $event(['type' => StatType::Steal, 'player_id' => $starters[3]->id, 'home_away' => HomeAway::Home, 'clock' => '08:16']);
    $event(['type' => StatType::Block, 'player_id' => $starters[4]->id, 'home_away' => HomeAway::Home, 'clock' => '08:14']);
    $event(['type' => StatType::Turnover, 'player_id' => $starters[0]->id, 'home_away' => HomeAway::Home, 'clock' => '08:12']);
    $event(['type' => StatType::Foul, 'player_id' => $starters[1]->id, 'home_away' => HomeAway::Home, 'clock' => '08:10']);
    $event(['type' => StatType::Substitution, 'player_id' => $bench->id, 'outgoing_player_id' => $starters[0]->id, 'home_away' => HomeAway::Home, 'clock' => '08:00']);
    $event(['type' => StatType::Foul, 'player_id' => $bench->id, 'home_away' => HomeAway::Home, 'foul_kind' => FoulKind::Technical, 'clock' => '06:30']);
    $event(['type' => StatType::ThreePointMade, 'home_away' => HomeAway::Away, 'clock' => '06:00']);
    $event(['type' => StatType::ThreePointMade, 'home_away' => HomeAway::Away, 'clock' => '05:30']);
    $event(['type' => StatType::FreeThrowMade, 'player_id' => $bench->id, 'home_away' => HomeAway::Home, 'clock' => '07:00']);
    $event(['type' => StatType::FreeThrowMissed, 'player_id' => $bench->id, 'home_away' => HomeAway::Home, 'clock' => '07:00']);
    $event(['type' => StatType::Substitution, 'player_id' => $starters[0]->id, 'outgoing_player_id' => $bench->id, 'home_away' => HomeAway::Home, 'clock' => '05:00']);
    $event(['type' => StatType::ThreePointMade, 'player_id' => $starters[2]->id, 'home_away' => HomeAway::Home, 'clock' => '03:00']);
    $event(['type' => StatType::QuarterEnded, 'clock' => '00:00']);

    $lineups = $game->lineupStats();

    expect($lineups)->toHaveCount(2)
        ->and(collect($lineups[0]['players'])->pluck('number')->all())->toBe([1, 2, 3, 4, 5])
        ->and($lineups[0]['minutes'])->toBe('7:00')
        ->and($lineups[0]['points'])->toBe(5)
        ->and($lineups[0]['opponentPoints'])->toBe(3)
        ->and($lineups[0]['plusMinus'])->toBe(2)
        ->and([$lineups[0]['twoMade'], $lineups[0]['twoAttempts']])->toBe([1, 1])
        ->and([$lineups[0]['threeMade'], $lineups[0]['threeAttempts']])->toBe([1, 2])
        ->and($lineups[0]['defensiveRebounds'])->toBe(1)
        ->and($lineups[0]['offensiveRebounds'])->toBe(1)
        ->and($lineups[0]['assists'])->toBe(1)
        ->and($lineups[0]['steals'])->toBe(1)
        ->and($lineups[0]['blocks'])->toBe(1)
        ->and($lineups[0]['turnovers'])->toBe(1)
        ->and($lineups[0]['fouls'])->toBe(1)
        ->and($lineups[0]['technicalFouls'])->toBe(0)
        ->and(collect($lineups[1]['players'])->pluck('number')->all())->toBe([2, 3, 4, 5, 12])
        ->and($lineups[1]['minutes'])->toBe('3:00')
        ->and($lineups[1]['points'])->toBe(1)
        ->and($lineups[1]['opponentPoints'])->toBe(6)
        ->and($lineups[1]['technicalFouls'])->toBe(1)
        ->and([$lineups[1]['freeMade'], $lineups[1]['freeAttempts']])->toBe([1, 2]);

    Livewire::actingAs($member)
        ->test(StatisticsShow::class, ['game' => $game])
        ->assertSee('Lineup stats')
        ->assertSee('Court five')
        ->assertSet('lineupSort', 'points')
        ->assertViewHas('lineupStats', fn (array $rows): bool => $rows[0]['minutes'] === '7:00')
        ->call('sortLineupsBy', 'freeMade')
        ->assertSet('lineupSort', 'points')
        ->call('sortLineupsBy', 'opponentPoints')
        ->assertViewHas('lineupStats', fn (array $rows): bool => $rows[0]['minutes'] === '3:00')
        ->call('sortLineupsBy', 'unknown')
        ->assertSet('lineupSort', 'opponentPoints');
});

test('lineup chips use our home or away colour', function () {
    $club = Club::factory()->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $member = User::factory()->memberOf($club)->withTeams($team)->create();

    foreach ([[HomeAway::Home, TeamColor::Purple], [HomeAway::Away, TeamColor::Yellow]] as [$side, $expected]) {
        $game = Game::factory()->create([
            'team_id' => $team->id,
            'home_away' => $side,
            'home_color' => TeamColor::Purple,
            'away_color' => TeamColor::Yellow,
        ]);

        Livewire::actingAs($member)
            ->test(StatisticsShow::class, ['game' => $game])
            ->assertViewHas('teamColor', $expected->hex())
            ->assertViewHas('teamTextColor', $expected->textHex());
    }
});

test('lineup stats ignore fives that only exist between opening-clock substitutions', function () {
    $club = Club::factory()->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $game = Game::factory()->create([
        'team_id' => $team->id,
        'home_away' => HomeAway::Home,
        'quarter_minutes' => 10,
    ]);
    $starters = Player::factory()->count(5)->sequence(
        ['name' => 'Starter A', 'number' => 1],
        ['name' => 'Starter B', 'number' => 2],
        ['name' => 'Starter C', 'number' => 3],
        ['name' => 'Starter D', 'number' => 4],
        ['name' => 'Starter E', 'number' => 5],
    )->create(['team_id' => $team->id]);
    $firstBench = Player::factory()->create(['team_id' => $team->id, 'name' => 'First Bench', 'number' => 11]);
    $finalBench = Player::factory()->create(['team_id' => $team->id, 'name' => 'Final Bench', 'number' => 12]);

    foreach ($starters as $starter) {
        $game->players()->attach($starter->id, ['starter' => true]);
    }
    $game->players()->attach($firstBench->id, ['starter' => false]);
    $game->players()->attach($finalBench->id, ['starter' => false]);

    $event = fn (array $attributes) => GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => null,
        'home_away' => null,
        ...$attributes,
    ]);

    $event(['type' => StatType::QuarterStarted, 'quarter' => 1, 'clock' => '10:00']);
    $event(['type' => StatType::QuarterEnded, 'quarter' => 1, 'clock' => '00:00']);
    $event(['type' => StatType::Substitution, 'player_id' => $firstBench->id, 'outgoing_player_id' => $starters[0]->id, 'home_away' => HomeAway::Home, 'quarter' => 2, 'clock' => '10:00']);
    $event(['type' => StatType::Substitution, 'player_id' => $finalBench->id, 'outgoing_player_id' => $firstBench->id, 'home_away' => HomeAway::Home, 'quarter' => 2, 'clock' => '10:00']);
    $event(['type' => StatType::QuarterStarted, 'quarter' => 2, 'clock' => '10:00']);
    $event(['type' => StatType::QuarterEnded, 'quarter' => 2, 'clock' => '00:00']);

    $lineups = $game->lineupStats();

    expect($lineups)->toHaveCount(2)
        ->and(collect($lineups)->pluck('minutes')->all())->toBe(['10:00', '10:00'])
        ->and(collect($lineups)->map(fn (array $line): array => collect($line['players'])->pluck('number')->all())->all())
        ->toEqualCanonicalizing([[1, 2, 3, 4, 5], [2, 3, 4, 5, 12]]);
});

test('game flow builds cumulative home and away scores over elapsed time', function () {
    $club = Club::factory()->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $game = Game::factory()->create([
        'team_id' => $team->id,
        'home_away' => HomeAway::Home,
        'quarter_minutes' => 10,
    ]);
    $player = Player::factory()->create(['team_id' => $team->id]);
    $game->players()->attach($player->id, ['starter' => true]);

    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => $player->id,
        'home_away' => HomeAway::Home,
        'type' => StatType::TwoPointMade,
        'quarter' => 1,
        'clock' => '09:00',
    ]);
    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => null,
        'home_away' => HomeAway::Away,
        'type' => StatType::ThreePointMade,
        'quarter' => 2,
        'clock' => '05:00',
    ]);
    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => $player->id,
        'home_away' => HomeAway::Home,
        'type' => StatType::FreeThrowMade,
        'quarter' => 2,
        'clock' => '04:00',
    ]);

    $flow = $game->gameFlow();

    expect($flow)->not->toBeNull()
        ->and($flow['points'][1]['x'])->toBe(60.0)
        ->and($flow['points'][1]['home'])->toBe(2)
        ->and($flow['points'][1]['away'])->toBe(0)
        ->and($flow['points'][2]['x'])->toBe(900.0)
        ->and($flow['points'][2]['home'])->toBe(2)
        ->and($flow['points'][2]['away'])->toBe(3)
        ->and($flow['points'][3]['x'])->toBe(960.0)
        ->and($flow['points'][3]['home'])->toBe(3)
        ->and($flow['points'][3]['away'])->toBe(3)
        ->and(collect($flow['markers'])->pluck('label')->all())->toBe(['Q1', 'Q2'])
        ->and($flow['maxX'])->toBe(1200.0)
        ->and($flow['maxScore'])->toBe(3);

    expect(Game::factory()->create(['team_id' => $team->id])->gameFlow())->toBeNull();
});

test('the mobile shot chart starts on the club team', function () {
    $club = Club::factory()->create();
    $team = Team::factory()->create(['club_id' => $club->id, 'name' => 'North City']);
    $member = User::factory()->memberOf($club)->withTeams($team)->create();
    $away = Game::factory()->create([
        'team_id' => $team->id,
        'opponent' => 'South Valley',
        'home_away' => HomeAway::Away,
    ]);
    $home = Game::factory()->create([
        'team_id' => $team->id,
        'opponent' => 'Metro United',
        'home_away' => HomeAway::Home,
    ]);

    Livewire::actingAs($member)
        ->test(StatisticsShow::class, ['game' => $away])
        ->assertSet('mobileShotChartFilter', 'away|team');

    Livewire::actingAs($member)
        ->test(StatisticsShow::class, ['game' => $home])
        ->assertSet('mobileShotChartFilter', 'home|team');
});

test('statistics charts use the configured default colors not the game colors', function () {
    $club = Club::factory()->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $member = User::factory()->memberOf($club)->withTeams($team)->create();
    $defaults = Game::defaultAttributes();
    $game = Game::factory()->create([
        'team_id' => $team->id,
        'home_color' => TeamColor::Red,
        'away_color' => TeamColor::Green,
    ]);

    expect($defaults['home_color'])->not->toBe(TeamColor::Red)
        ->and($defaults['away_color'])->not->toBe(TeamColor::Green);

    Livewire::actingAs($member)
        ->test(StatisticsShow::class, ['game' => $game])
        ->assertViewHas('homeColor', $defaults['home_color']->hex())
        ->assertViewHas('awayColor', $defaults['away_color']->hex());
});

test('a finished game hides the court and bench cards', function () {
    $club = Club::factory()->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $member = User::factory()->memberOf($club)->withTeams($team)->create();
    $game = Game::factory()->create([
        'team_id' => $team->id,
        'status' => GameStatus::Played,
    ]);
    $player = Player::factory()->create(['team_id' => $team->id]);
    $game->players()->attach($player->id, ['starter' => true]);

    Livewire::actingAs($member)
        ->test(StatisticsShow::class, ['game' => $game])
        ->assertDontSee('On court')
        ->assertDontSee('Bench')
        ->assertSee('Box score');
});

test('a club cannot open another clubs game statistics', function () {
    $game = Game::factory()->create();
    $account = User::factory()->clubAccessFor(Club::factory()->create())->create();

    $this->actingAs($account)->get(route('statistics.show', $game))->assertForbidden();
});
