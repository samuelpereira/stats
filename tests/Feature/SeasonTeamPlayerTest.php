<?php

use App\Enums\Gender;
use App\Livewire\Clubs\Index as ClubIndex;
use App\Livewire\Players\Create as CreatePlayer;
use App\Livewire\Players\Edit as EditPlayer;
use App\Livewire\Players\Index as PlayerIndex;
use App\Livewire\Seasons\Create as CreateSeason;
use App\Livewire\Seasons\Edit as EditSeason;
use App\Livewire\Seasons\Index as SeasonIndex;
use App\Livewire\Teams\Create as CreateTeam;
use App\Livewire\Teams\Index as TeamIndex;
use App\Models\Club;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('an administrator creates a season from its name', function () {
    $administrator = User::factory()->administrator()->create();

    Livewire::actingAs($administrator)
        ->test(CreateSeason::class)
        ->set('name', '2026/2027')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('seasons.index'));

    $season = Season::query()->where('name', '2026/2027')->first();

    expect($season)->not->toBeNull()
        ->and($season->uuid)->toBeUuid()
        ->and($season->slug)->toBe('2026-2027')
        ->and($season->starts_at->toDateString())->toBe('2026-09-01')
        ->and($season->ends_at->toDateString())->toBe('2027-08-31')
        ->and($season->is_current)->toBeTrue();

    Livewire::actingAs($administrator)
        ->test(CreateSeason::class)
        ->set('name', '2027/2028')
        ->call('save')
        ->assertHasNoErrors();

    expect(Season::query()->where('name', '2027/2028')->first()?->is_current)->toBeFalse();
});

test('a season name must be two consecutive years', function () {
    $administrator = User::factory()->administrator()->create();

    Livewire::actingAs($administrator)
        ->test(CreateSeason::class)
        ->set('name', '2026/2028')
        ->call('save')
        ->assertHasErrors('name');

    expect(Season::query()->count())->toBe(0);
});

test('editing a season regenerates its dates and keeps it current', function () {
    $administrator = User::factory()->administrator()->create();
    $season = Season::factory()->current()->create([
        'name' => '2025/2026',
        'slug' => '2025-2026',
        'starts_at' => '2025-09-01',
        'ends_at' => '2026-08-31',
    ]);

    Livewire::actingAs($administrator)
        ->test(EditSeason::class, ['season' => $season])
        ->set('name', '2026/2027')
        ->call('save')
        ->assertHasNoErrors();

    $season->refresh();

    expect($season->name)->toBe('2026/2027')
        ->and($season->slug)->toBe('2026-2027')
        ->and($season->starts_at->toDateString())->toBe('2026-09-01')
        ->and($season->ends_at->toDateString())->toBe('2027-08-31')
        ->and($season->is_current)->toBeTrue();
});

test('marking a season as current clears the previous one', function () {
    $administrator = User::factory()->administrator()->create();
    $current = Season::factory()->current()->create();
    $other = Season::factory()->create();

    Livewire::actingAs($administrator)
        ->test(SeasonIndex::class)
        ->assertSee('Current')
        ->assertSee('Mark as current')
        ->call('markAsCurrent', $other->uuid);

    expect($other->refresh()->is_current)->toBeTrue()
        ->and($current->refresh()->is_current)->toBeFalse();
});

test('an administrator can delete a season without teams', function () {
    $administrator = User::factory()->administrator()->create();
    $season = Season::factory()->create();

    Livewire::actingAs($administrator)
        ->test(SeasonIndex::class)
        ->call('confirmDelete', $season->uuid)
        ->call('deleteConfirmed');

    expect(Season::query()->find($season->id))->toBeNull();
});

test('an administrator cannot delete a season that still has teams', function () {
    $administrator = User::factory()->administrator()->create();
    $team = Team::factory()->create();

    Livewire::actingAs($administrator)
        ->test(SeasonIndex::class)
        ->call('confirmDelete', $team->season->uuid)
        ->call('deleteConfirmed');

    expect(Season::query()->find($team->season_id))->not->toBeNull();
});

test('a club role cannot open seasons', function () {
    $club = Club::factory()->create();
    $access = User::factory()->clubAccessFor($club)->create();

    $this->actingAs($access)->get(route('seasons.index'))->assertForbidden();
});

test('a club role creates a team in its own club', function () {
    $club = Club::factory()->create();
    $other = Club::factory()->create();
    $access = User::factory()->clubAccessFor($club)->create();
    $season = Season::factory()->create();

    Livewire::actingAs($access)
        ->test(CreateTeam::class)
        ->set('name', 'Seniores')
        ->set('clubId', (string) $other->id)
        ->set('seasonId', (string) $season->id)
        ->set('gender', Gender::Male->value)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('teams.index'));

    $team = Team::query()->where('name', 'Seniores')->first();

    expect($team)->not->toBeNull()
        ->and($team->club_id)->toBe($club->id)
        ->and($team->season_id)->toBe($season->id)
        ->and($team->slug)->toBe('seniores');
});

test('an administrator creates a team for the selected club', function () {
    Storage::fake('public');

    $administrator = User::factory()->administrator()->create();
    $club = Club::factory()->create();
    $season = Season::factory()->create();

    Livewire::actingAs($administrator)
        ->test(CreateTeam::class)
        ->set('name', 'Sub 16')
        ->set('clubId', (string) $club->id)
        ->set('seasonId', (string) $season->id)
        ->set('gender', Gender::Female->value)
        ->set('photo', UploadedFile::fake()->image('team.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $team = Team::query()->where('name', 'Sub 16')->first();

    expect($team)->not->toBeNull()
        ->and($team->club_id)->toBe($club->id)
        ->and($team->gender)->toBe(Gender::Female)
        ->and($team->photo)->not->toBeNull();

    Storage::disk('public')->assertExists((string) $team->photo);
});

test('a user role cannot open teams', function () {
    $member = User::factory()->memberOf(Club::factory()->create())->create();

    $this->actingAs($member)->get(route('teams.index'))->assertForbidden();
});

test('a club role creates a player on its own team', function () {
    $club = Club::factory()->create();
    $access = User::factory()->clubAccessFor($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id]);

    Livewire::actingAs($access)
        ->test(CreatePlayer::class)
        ->set('name', 'Example Player')
        ->set('teamId', (string) $team->id)
        ->set('number', '10')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('players.index'));

    $player = Player::query()->where('name', 'Example Player')->first();

    expect($player)->not->toBeNull()
        ->and($player->team_id)->toBe($team->id)
        ->and($player->number)->toBe(10)
        ->and($player->slug)->toBe('example-player');
});

test('a repeated shirt number on the same team is rejected', function () {
    $administrator = User::factory()->administrator()->create();
    $team = Team::factory()->create();
    Player::factory()->create([
        'team_id' => $team->id,
        'number' => 7,
    ]);

    Livewire::actingAs($administrator)
        ->test(CreatePlayer::class)
        ->set('name', 'Example Member')
        ->set('teamId', (string) $team->id)
        ->set('number', '7')
        ->call('save')
        ->assertHasErrors('number');
});

test('the same fpb id may be used on more than one player', function () {
    $administrator = User::factory()->administrator()->create();
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();

    Livewire::actingAs($administrator)
        ->test(CreatePlayer::class)
        ->set('name', 'Player One')
        ->set('teamId', (string) $teamA->id)
        ->set('fpbId', '4242')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::actingAs($administrator)
        ->test(CreatePlayer::class)
        ->set('name', 'Player Two')
        ->set('teamId', (string) $teamB->id)
        ->set('fpbId', '4242')
        ->call('save')
        ->assertHasNoErrors();

    expect(Player::query()->where('fpb_id', '4242')->count())->toBe(2);
});

test('an administrator can delete a team without players', function () {
    $administrator = User::factory()->administrator()->create();
    $team = Team::factory()->create();

    Livewire::actingAs($administrator)
        ->test(TeamIndex::class)
        ->assertSee('Edit')
        ->assertSee('Delete')
        ->call('confirmDelete', $team->uuid)
        ->call('deleteConfirmed');

    expect(Team::query()->find($team->id))->toBeNull();
});

test('an administrator cannot delete a team that still has players', function () {
    $administrator = User::factory()->administrator()->create();
    $player = Player::factory()->create();

    Livewire::actingAs($administrator)
        ->test(TeamIndex::class)
        ->call('confirmDelete', $player->team->uuid)
        ->call('deleteConfirmed');

    expect(Team::query()->find($player->team_id))->not->toBeNull();
});

test('an administrator can delete a player', function () {
    $administrator = User::factory()->administrator()->create();
    $player = Player::factory()->create();

    Livewire::actingAs($administrator)
        ->test(PlayerIndex::class)
        ->call('confirmDelete', $player->uuid)
        ->call('deleteConfirmed');

    expect(Player::query()->find($player->id))->toBeNull();
});

test('an administrator can delete a club without teams', function () {
    $administrator = User::factory()->administrator()->create();
    $club = Club::factory()->create();
    $access = User::factory()->clubAccessFor($club)->create();

    Livewire::actingAs($administrator)
        ->test(ClubIndex::class)
        ->call('confirmDelete', $club->id)
        ->call('deleteConfirmed');

    expect(Club::query()->find($club->id))->toBeNull()
        ->and(User::query()->find($access->id))->toBeNull();
});

test('an administrator cannot delete a club that still has teams', function () {
    $administrator = User::factory()->administrator()->create();
    $team = Team::factory()->create();

    Livewire::actingAs($administrator)
        ->test(ClubIndex::class)
        ->call('confirmDelete', $team->club_id)
        ->call('deleteConfirmed');

    expect(Club::query()->find($team->club_id))->not->toBeNull();
});

test('a season is listed and edited by its uuid', function () {
    $administrator = User::factory()->administrator()->create();
    $season = Season::factory()->create();

    Livewire::actingAs($administrator)
        ->test(SeasonIndex::class)
        ->assertSee(route('seasons.edit', $season), false)
        ->assertSee("confirmDelete('{$season->uuid}')", false)
        ->assertSee("markAsCurrent('{$season->uuid}')", false)
        ->assertDontSee("confirmDelete('{$season->id}')", false)
        ->assertDontSee("markAsCurrent({$season->id})", false);

    $this->actingAs($administrator)
        ->get(route('seasons.edit', $season->id))
        ->assertNotFound();
});

test('teams index filters by season', function () {
    $administrator = User::factory()->administrator()->create();
    $pastSeason = Season::factory()->create(['is_current' => false, 'name' => '2023/2024']);
    $currentSeason = Season::factory()->current()->create(['name' => '2024/2025']);
    Team::factory()->create(['season_id' => $pastSeason->id, 'name' => 'Past Team']);
    Team::factory()->create(['season_id' => $currentSeason->id, 'name' => 'Current Team']);

    Livewire::actingAs($administrator)
        ->test(TeamIndex::class)
        ->assertSee('Current Team')
        ->assertDontSee('Past Team');

    Livewire::actingAs($administrator)
        ->test(TeamIndex::class)
        ->set('seasonId', (string) $pastSeason->id)
        ->assertSee('Past Team')
        ->assertDontSee('Current Team');

    Livewire::actingAs($administrator)
        ->test(TeamIndex::class)
        ->set('seasonId', '')
        ->assertSee('Current Team')
        ->assertSee('Past Team');
});

test('a team is listed and edited by its uuid', function () {
    $administrator = User::factory()->administrator()->create();
    $team = Team::factory()->create();

    Livewire::actingAs($administrator)
        ->test(TeamIndex::class)
        ->assertSee(route('teams.edit', $team), false)
        ->assertSee("confirmDelete('{$team->uuid}')", false)
        ->assertDontSee("confirmDelete('{$team->id}')", false);

    $this->actingAs($administrator)
        ->get(route('teams.edit', $team->id))
        ->assertNotFound();
});

test('players index filters by season and team', function () {
    $administrator = User::factory()->administrator()->create();
    $pastSeason = Season::factory()->create(['is_current' => false, 'name' => '2023/2024']);
    $currentSeason = Season::factory()->current()->create(['name' => '2024/2025']);
    $pastTeam = Team::factory()->create(['season_id' => $pastSeason->id]);
    $currentTeamA = Team::factory()->create(['season_id' => $currentSeason->id]);
    $currentTeamB = Team::factory()->create(['season_id' => $currentSeason->id]);
    Player::factory()->create(['team_id' => $pastTeam->id, 'name' => 'Past Player']);
    Player::factory()->create(['team_id' => $currentTeamA->id, 'name' => 'Team A Player']);
    Player::factory()->create(['team_id' => $currentTeamB->id, 'name' => 'Team B Player']);

    Livewire::actingAs($administrator)
        ->test(PlayerIndex::class)
        ->assertSee('Team A Player')
        ->assertSee('Team B Player')
        ->assertDontSee('Past Player');

    Livewire::actingAs($administrator)
        ->test(PlayerIndex::class)
        ->set('seasonId', (string) $pastSeason->id)
        ->assertSee('Past Player')
        ->assertDontSee('Team A Player');

    Livewire::actingAs($administrator)
        ->test(PlayerIndex::class)
        ->set('seasonId', (string) $currentSeason->id)
        ->set('teamId', (string) $currentTeamA->id)
        ->assertSee('Team A Player')
        ->assertDontSee('Team B Player')
        ->assertDontSee('Past Player');
});

test('the players list keeps the team filter in the address and on the edit link', function () {
    $administrator = User::factory()->administrator()->create();
    $pastSeason = Season::factory()->create(['is_current' => false]);
    $currentSeason = Season::factory()->current()->create();
    $pastTeam = Team::factory()->create(['season_id' => $pastSeason->id]);
    $currentTeam = Team::factory()->create(['season_id' => $currentSeason->id]);
    $pastPlayer = Player::factory()->create(['team_id' => $pastTeam->id, 'name' => 'Past Player']);
    Player::factory()->create(['team_id' => $currentTeam->id, 'name' => 'Current Player']);

    $this->actingAs($administrator)
        ->get(route('players.index', [
            'season' => $pastSeason->id,
            'team' => $pastTeam->id,
        ]))
        ->assertOk()
        ->assertSee('Past Player')
        ->assertDontSee('Current Player')
        ->assertSee(route('players.edit', [
            'player' => $pastPlayer,
            'season' => $pastSeason->id,
            'team' => $pastTeam->id,
        ]));
});

test('saving a player returns to the same players filter', function () {
    $administrator = User::factory()->administrator()->create();
    $season = Season::factory()->create();
    $team = Team::factory()->create(['season_id' => $season->id]);
    $player = Player::factory()->create(['team_id' => $team->id]);

    try {
        Livewire::withQueryParams([
            'season' => (string) $season->id,
            'team' => (string) $team->id,
        ])
            ->actingAs($administrator)
            ->test(EditPlayer::class, ['player' => $player])
            ->assertSee(route('players.index', [
                'season' => $season->id,
                'team' => $team->id,
            ]))
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('players.index', [
                'season' => $season->id,
                'team' => $team->id,
            ]));
    } finally {
        Livewire::withQueryParams([]);
    }
});

test('a player is listed and edited by its uuid', function () {
    $administrator = User::factory()->administrator()->create();
    $player = Player::factory()->create();

    Livewire::actingAs($administrator)
        ->test(PlayerIndex::class)
        ->assertSee(route('players.edit', $player), false)
        ->assertSee("confirmDelete('{$player->uuid}')", false)
        ->assertDontSee("confirmDelete('{$player->id}')", false);

    $this->actingAs($administrator)
        ->get(route('players.edit', $player->id))
        ->assertNotFound();
});

test('a club role cannot create a player on another club team', function () {
    $club = Club::factory()->create();
    $access = User::factory()->clubAccessFor($club)->create();
    $otherTeam = Team::factory()->create();

    Livewire::actingAs($access)
        ->test(CreatePlayer::class)
        ->set('name', 'Example Player')
        ->set('teamId', (string) $otherTeam->id)
        ->call('save')
        ->assertForbidden();
});

test('the clubs list shows fifteen clubs per page', function () {
    $administrator = User::factory()->administrator()->create();

    foreach (range(1, 16) as $number) {
        Club::factory()->create(['name' => sprintf('Club %02d', $number)]);
    }

    Livewire::actingAs($administrator)
        ->test(ClubIndex::class)
        ->assertSee('Club 01')
        ->assertDontSee('Club 16')
        ->call('nextPage')
        ->assertSee('Club 16')
        ->assertDontSee('Club 01');
});
