<?php

use App\Enums\GameStatus;
use App\Enums\HomeAway;
use App\Enums\TeamColor;
use App\Livewire\Games\Create as CreateGame;
use App\Livewire\Games\Edit as EditGame;
use App\Livewire\Games\Index as GameIndex;
use App\Livewire\Teams\Index as TeamIndex;
use App\Models\Club;
use App\Models\Game;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('a club role creates a game for its own team', function () {
    $club = Club::factory()->create();
    $access = User::factory()->clubAccessFor($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id]);

    Livewire::actingAs($access)
        ->test(CreateGame::class)
        ->set('teamId', (string) $team->id)
        ->set('fpbId', 'FPB-1')
        ->set('opponent', 'Metro United')
        ->set('dateTime', '2026-10-04T18:30')
        ->set('place', 'Pavilhão')
        ->set('homeAway', HomeAway::Home->value)
        ->set('competition', 'CN1')
        ->set('journey', '1')
        ->set('status', GameStatus::Scheduled->value)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('games.index'));

    $game = Game::query()->where('opponent', 'Metro United')->first();

    expect($game)->not->toBeNull()
        ->and($game->uuid)->toBeUuid()
        ->and($game->team_id)->toBe($team->id)
        ->and($game->fpb_id)->toBe('FPB-1')
        ->and($game->date_time->format('Y-m-d H:i'))->toBe('2026-10-04 18:30')
        ->and($game->place)->toBe('Pavilhão')
        ->and($game->home_away)->toBe(HomeAway::Home)
        ->and($game->competition)->toBe('CN1')
        ->and($game->journey)->toBe('1')
        ->and($game->home_points)->toBeNull()
        ->and($game->away_points)->toBeNull()
        ->and($game->status)->toBe(GameStatus::Scheduled);
});

test('a new game stores the configured period lengths and team colors', function () {
    config([
        'game.quarter_minutes' => 12,
        'game.overtime_minutes' => 4,
        'game.home_color' => TeamColor::Red->value,
        'game.away_color' => TeamColor::Green->value,
    ]);

    $club = Club::factory()->create();
    $access = User::factory()->clubAccessFor($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id]);

    Livewire::actingAs($access)
        ->test(CreateGame::class)
        ->set('teamId', (string) $team->id)
        ->set('opponent', 'Sporting')
        ->set('dateTime', '2026-10-04T18:30')
        ->set('homeAway', HomeAway::Home->value)
        ->set('status', GameStatus::Scheduled->value)
        ->call('save')
        ->assertHasNoErrors();

    $game = Game::query()->where('opponent', 'Sporting')->firstOrFail();

    expect($game->quarter_minutes)->toBe(12)
        ->and($game->overtime_minutes)->toBe(4)
        ->and($game->home_color)->toBe(TeamColor::Red)
        ->and($game->away_color)->toBe(TeamColor::Green);
});

test('a club role cannot create a game for another club team', function () {
    $club = Club::factory()->create();
    $access = User::factory()->clubAccessFor($club)->create();
    $otherTeam = Team::factory()->create();

    Livewire::actingAs($access)
        ->test(CreateGame::class)
        ->set('opponent', 'Metro United')
        ->set('dateTime', '2026-10-04T18:30')
        ->set('homeAway', HomeAway::Away->value)
        ->set('status', GameStatus::Scheduled->value)
        ->set('teamId', (string) $otherTeam->id)
        ->call('save')
        ->assertForbidden();
});

test('a user role cannot open games', function () {
    $member = User::factory()->memberOf(Club::factory()->create())->create();

    $this->actingAs($member)->get(route('games.index'))->assertForbidden();
});

test('an administrator records the score of a game', function () {
    $administrator = User::factory()->administrator()->create();
    $game = Game::factory()->create();

    Livewire::actingAs($administrator)
        ->test(EditGame::class, ['game' => $game])
        ->set('homePoints', '78')
        ->set('awayPoints', '71')
        ->set('status', GameStatus::Played->value)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('games.index'));

    $game->refresh();

    expect($game->home_points)->toBe(78)
        ->and($game->away_points)->toBe(71)
        ->and($game->status)->toBe(GameStatus::Played)
        ->and($game->score())->toBe('78–71');
});

test('a repeated fpb id is rejected', function () {
    $administrator = User::factory()->administrator()->create();
    $team = Team::factory()->create();
    Game::factory()->create([
        'team_id' => $team->id,
        'fpb_id' => 'FPB-9',
    ]);

    Livewire::actingAs($administrator)
        ->test(CreateGame::class)
        ->set('teamId', (string) $team->id)
        ->set('opponent', 'River City')
        ->set('dateTime', '2026-10-04T18:30')
        ->set('homeAway', HomeAway::Home->value)
        ->set('status', GameStatus::Scheduled->value)
        ->set('fpbId', 'FPB-9')
        ->call('save')
        ->assertHasErrors('fpbId');
});

test('games index filters by season and team', function () {
    $administrator = User::factory()->administrator()->create();
    $pastSeason = Season::factory()->create(['is_current' => false, 'name' => '2023/2024']);
    $currentSeason = Season::factory()->current()->create(['name' => '2024/2025']);
    $pastTeam = Team::factory()->create(['season_id' => $pastSeason->id]);
    $currentTeamA = Team::factory()->create(['season_id' => $currentSeason->id]);
    $currentTeamB = Team::factory()->create(['season_id' => $currentSeason->id]);
    Game::factory()->create(['team_id' => $pastTeam->id, 'opponent' => 'Past Opponent']);
    Game::factory()->create(['team_id' => $currentTeamA->id, 'opponent' => 'Team A Opponent']);
    Game::factory()->create(['team_id' => $currentTeamB->id, 'opponent' => 'Team B Opponent']);

    Livewire::actingAs($administrator)
        ->test(GameIndex::class)
        ->assertSee('Team A Opponent')
        ->assertSee('Team B Opponent')
        ->assertDontSee('Past Opponent');

    Livewire::actingAs($administrator)
        ->test(GameIndex::class)
        ->set('seasonId', (string) $pastSeason->id)
        ->assertSee('Past Opponent')
        ->assertDontSee('Team A Opponent');

    Livewire::actingAs($administrator)
        ->test(GameIndex::class)
        ->set('seasonId', (string) $currentSeason->id)
        ->set('teamId', (string) $currentTeamA->id)
        ->assertSee('Team A Opponent')
        ->assertDontSee('Team B Opponent')
        ->assertDontSee('Past Opponent');
});

test('the games list shows who plays at home and who plays away', function () {
    $administrator = User::factory()->administrator()->create();
    $team = Team::factory()->create(['name' => 'North City']);
    Game::factory()->create([
        'team_id' => $team->id,
        'opponent' => 'South Valley',
        'home_away' => HomeAway::Away,
    ]);

    Livewire::actingAs($administrator)
        ->test(GameIndex::class)
        ->assertSeeInOrder(['At home', 'Away', 'South Valley', 'North City'])
        ->assertSee('font-bold text-zinc-900">North City', false)
        ->assertDontSee('font-bold text-zinc-900">South Valley', false)
        ->assertDontSee('Home or away');
});

test('changing the games season filter returns to the first page', function () {
    $administrator = User::factory()->administrator()->create();
    $currentSeason = Season::factory()->current()->create();
    $pastSeason = Season::factory()->create(['is_current' => false]);
    $currentTeam = Team::factory()->create(['season_id' => $currentSeason->id]);
    $pastTeam = Team::factory()->create(['season_id' => $pastSeason->id]);
    Game::factory()->create([
        'team_id' => $currentTeam->id,
        'opponent' => 'Current Only',
    ]);
    Game::factory()->create([
        'team_id' => $pastTeam->id,
        'opponent' => 'Past Only',
    ]);

    Livewire::actingAs($administrator)
        ->test(GameIndex::class)
        ->call('nextPage')
        ->assertDontSee('Past Only')
        ->set('seasonId', (string) $pastSeason->id)
        ->assertSee('Past Only');
});

test('a club only sees games of its own teams', function () {
    $club = Club::factory()->create();
    $access = User::factory()->clubAccessFor($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $ownGame = Game::factory()->create([
        'team_id' => $team->id,
        'opponent' => 'Sporting',
    ]);
    $otherGame = Game::factory()->create([
        'opponent' => 'Oponente Externo',
    ]);

    Livewire::actingAs($access)
        ->test(GameIndex::class)
        ->assertSee($ownGame->opponent)
        ->assertDontSee($otherGame->opponent);
});

test('an administrator can delete a game', function () {
    $administrator = User::factory()->administrator()->create();
    $game = Game::factory()->create();

    Livewire::actingAs($administrator)
        ->test(GameIndex::class)
        ->call('confirmDelete', $game->uuid)
        ->call('deleteConfirmed');

    expect(Game::query()->find($game->id))->toBeNull();
});

test('a game is listed and edited by its uuid', function () {
    $administrator = User::factory()->administrator()->create();
    $game = Game::factory()->create();

    Livewire::actingAs($administrator)
        ->test(GameIndex::class)
        ->assertSee(route('games.edit', $game), false)
        ->assertSee("confirmDelete('{$game->uuid}')", false)
        ->assertDontSee("confirmDelete('{$game->id}')", false);

    $this->actingAs($administrator)
        ->get(route('games.edit', $game->id))
        ->assertNotFound();
});

test('an administrator cannot delete a team that still has games', function () {
    $administrator = User::factory()->administrator()->create();
    $game = Game::factory()->create();

    Livewire::actingAs($administrator)
        ->test(TeamIndex::class)
        ->call('confirmDelete', $game->team->uuid)
        ->call('deleteConfirmed');

    expect(Team::query()->find($game->team_id))->not->toBeNull();
});
