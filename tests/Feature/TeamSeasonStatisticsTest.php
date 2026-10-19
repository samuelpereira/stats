<?php

use App\Enums\GameStatus;
use App\Enums\HomeAway;
use App\Enums\StatType;
use App\Livewire\Teams\SeasonStatistics;
use App\Models\Club;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('an administrator can open season statistics for any team', function () {
    $administrator = User::factory()->administrator()->create();
    $team = Team::factory()->create(['name' => 'North City']);

    $this->actingAs($administrator)
        ->get(route('teams.season-statistics', $team))
        ->assertOk()
        ->assertSee('Season statistics')
        ->assertSee('North City');
});

test('a club can open season statistics for its own team', function () {
    $club = Club::factory()->create();
    $access = User::factory()->clubAccessFor($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id, 'name' => 'North City']);

    $this->actingAs($access)
        ->get(route('teams.season-statistics', $team))
        ->assertOk()
        ->assertSee('North City');
});

test('a club cannot open season statistics for another club team', function () {
    $club = Club::factory()->create();
    $access = User::factory()->clubAccessFor($club)->create();
    $team = Team::factory()->create();

    $this->actingAs($access)
        ->get(route('teams.season-statistics', $team))
        ->assertForbidden();
});

test('a manager cannot open season statistics', function () {
    $manager = User::factory()->manager()->create();
    $team = Team::factory()->create();

    $this->actingAs($manager)
        ->get(route('teams.season-statistics', $team))
        ->assertForbidden();
});

test('a user cannot open season statistics', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id]);

    $this->actingAs($member)
        ->get(route('teams.season-statistics', $team))
        ->assertForbidden();
});

test('games history shows opponent result score and date', function () {
    $administrator = User::factory()->administrator()->create();
    $team = Team::factory()->create();
    Game::factory()->create([
        'team_id' => $team->id,
        'opponent' => 'South Valley',
        'home_away' => HomeAway::Home,
        'home_points' => 72,
        'away_points' => 68,
        'status' => GameStatus::Played,
        'date_time' => now()->subDay()->setTime(18, 30),
    ])->forceFill([
        'opponent_photo' => 'opponents/crest.png',
    ])->save();

    Livewire::actingAs($administrator)
        ->test(SeasonStatistics::class, ['team' => $team])
        ->assertSee('South Valley')
        ->assertSee('Win')
        ->assertSee('72–68')
        ->assertSee(now()->subDay()->format('d/m/Y'))
        ->assertSee('/storage/opponents/crest.png', false);
});

test('matchup history shows the wins and losses against each opponent', function () {
    $administrator = User::factory()->administrator()->create();
    $team = Team::factory()->create();

    Game::factory()->create([
        'team_id' => $team->id,
        'opponent' => 'South Valley',
        'home_away' => HomeAway::Home,
        'home_points' => 80,
        'away_points' => 70,
        'status' => GameStatus::Played,
    ]);
    Game::factory()->create([
        'team_id' => $team->id,
        'opponent' => 'South Valley',
        'home_away' => HomeAway::Away,
        'home_points' => 60,
        'away_points' => 75,
        'status' => GameStatus::Played,
    ]);
    Game::factory()->create([
        'team_id' => $team->id,
        'opponent' => 'South Valley',
        'home_away' => HomeAway::Home,
        'home_points' => 55,
        'away_points' => 62,
        'status' => GameStatus::Played,
    ]);

    expect($team->matchupHistory())->toBe([
        [
            'opponent' => 'South Valley',
            'wins' => 2,
            'losses' => 1,
            'games' => 3,
            'record' => '2-1',
        ],
    ]);

    Livewire::actingAs($administrator)
        ->test(SeasonStatistics::class, ['team' => $team])
        ->assertSee('South Valley')
        ->assertSee('2-1');
});

test('season box score accumulates player stats across logged games', function () {
    $administrator = User::factory()->administrator()->create();
    $team = Team::factory()->create(['name' => 'North City']);
    $player = Player::factory()->create([
        'team_id' => $team->id,
        'name' => 'Alex North',
        'number' => 8,
    ]);
    $bench = Player::factory()->create([
        'team_id' => $team->id,
        'name' => 'Bench Never Played',
        'number' => 99,
    ]);

    foreach ([1, 2] as $index) {
        $game = Game::factory()->create([
            'team_id' => $team->id,
            'home_away' => HomeAway::Home,
            'status' => GameStatus::Played,
            'home_points' => 10,
            'away_points' => 8,
        ]);
        $game->players()->attach([$player->id => ['starter' => true]]);

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
            'quarter' => 1,
            'clock' => '08:30',
        ]);
    }

    Game::factory()->create([
        'team_id' => $team->id,
        'opponent' => 'Imported Only',
        'home_away' => HomeAway::Home,
        'status' => GameStatus::Played,
        'home_points' => 90,
        'away_points' => 80,
    ]);

    $boxScore = $team->seasonBoxScore();
    $line = collect($boxScore['players'])->firstWhere('id', $player->id);

    expect($line['points'])->toBe(4)
        ->and($line['twoMade'])->toBe(2)
        ->and($line['twoAttempts'])->toBe(2)
        ->and($line['gp'])->toBe(2)
        ->and($boxScore['team']['points'])->toBe(4)
        ->and($boxScore['team']['gp'])->toBe(2)
        ->and($boxScore['opponent']['points'])->toBe(6)
        ->and($boxScore['opponent']['threeMade'])->toBe(2)
        ->and($boxScore['opponent']['threeAttempts'])->toBe(2)
        ->and(collect($boxScore['players'])->firstWhere('id', $bench->id))->toBeNull();

    Livewire::actingAs($administrator)
        ->test(SeasonStatistics::class, ['team' => $team])
        ->assertSee(__('Season Box Score'))
        ->assertSee('Alex North')
        ->assertDontSee('Bench Never Played')
        ->assertSee(__('Opponents'))
        ->assertSee('GP');
});
