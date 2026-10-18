<?php

use App\Enums\GameStatus;
use App\Enums\HomeAway;
use App\Enums\StatType;
use App\Livewire\Dashboard;
use App\Models\Club;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the dashboard shows season summaries and upcoming games for a club', function () {
    $club = Club::factory()->create();
    $access = User::factory()->clubAccessFor($club)->create();
    $season = Season::factory()->current()->create(['name' => '2024/2025']);
    $team = Team::factory()->create(['club_id' => $club->id, 'season_id' => $season->id]);
    Game::factory()->create([
        'team_id' => $team->id,
        'opponent' => 'Metro United',
        'date_time' => now()->addDay(),
        'status' => GameStatus::Scheduled,
        'home_away' => HomeAway::Home,
    ]);

    Livewire::actingAs($access)
        ->test(Dashboard::class)
        ->assertSee('2024/2025')
        ->assertSee('Metro United')
        ->assertSee(__('Upcoming games'))
        ->assertSee('1')
        ->assertSee(__('Teams'));
});

test('the dashboard lists the home side before the away side', function () {
    $club = Club::factory()->create();
    $access = User::factory()->clubAccessFor($club)->create();
    $season = Season::factory()->current()->create();
    $team = Team::factory()->create(['club_id' => $club->id, 'season_id' => $season->id, 'name' => 'North City']);
    Game::factory()->create([
        'team_id' => $team->id,
        'opponent' => 'South Valley',
        'date_time' => now()->addDay(),
        'status' => GameStatus::Scheduled,
        'home_away' => HomeAway::Away,
    ]);

    Livewire::actingAs($access)
        ->test(Dashboard::class)
        ->assertSeeInOrder(['South Valley', 'North City'])
        ->assertSee('font-bold text-zinc-900">North City', false)
        ->assertDontSee('font-bold text-zinc-900">South Valley', false)
        ->assertDontSee('At home');
});

test('the dashboard recent games list only includes past games with a statistics log', function () {
    $club = Club::factory()->create();
    $access = User::factory()->clubAccessFor($club)->create();
    $season = Season::factory()->current()->create();
    $team = Team::factory()->create(['club_id' => $club->id, 'season_id' => $season->id]);

    $logged = Game::factory()->create([
        'team_id' => $team->id,
        'opponent' => 'Logged Opponent',
        'date_time' => now()->subDay(),
    ]);

    GameEvent::factory()->create([
        'game_id' => $logged->id,
        'type' => StatType::QuarterStarted,
        'quarter' => 1,
        'clock' => '10:00',
    ]);

    Game::factory()->create([
        'team_id' => $team->id,
        'opponent' => 'Empty Opponent',
        'date_time' => now()->subDays(2),
    ]);

    Livewire::actingAs($access)
        ->test(Dashboard::class)
        ->assertSee(__('Recent games'))
        ->assertSee('Logged Opponent')
        ->assertDontSee('Empty Opponent');
});
