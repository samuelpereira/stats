<?php

use App\Livewire\Teams\SeasonStatistics;
use App\Livewire\Teams\SharedSeasonStatistics;
use App\Models\Club;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('a public link shows the season statistics without signing in', function () {
    [$access, $team] = sharedSeasonStatisticsTeam();

    Livewire::actingAs($access)
        ->test(SeasonStatistics::class, ['team' => $team])
        ->call('openShare')
        ->assertSet('sharing', true)
        ->assertSee(route('teams.season-statistics.shared', ['team' => $team->refresh()->share_token]), false);

    $this->get(route('teams.season-statistics.shared', ['team' => $team->share_token]))
        ->assertOk()
        ->assertSee('ADC')
        ->assertSee(__('Season statistics'))
        ->assertSee(__('Season Box Score'))
        ->assertSee(__('Games history'))
        ->assertSee(__('Matchup history'))
        ->assertDontSee(__('Back'))
        ->assertDontSee(__('Stop sharing'));
});

test('the team uuid does not open the public season statistics', function () {
    [, $team] = sharedSeasonStatisticsTeam();

    $this->get(route('teams.season-statistics.shared', ['team' => $team->uuid]))
        ->assertNotFound();
});

test('a replaced share link stops opening the previous season statistics', function () {
    [$access, $team] = sharedSeasonStatisticsTeam();

    Livewire::actingAs($access)
        ->test(SeasonStatistics::class, ['team' => $team])
        ->call('openShare');

    $previous = $team->refresh()->share_token;

    Livewire::actingAs($access)
        ->test(SeasonStatistics::class, ['team' => $team])
        ->call('rotateShare');

    expect($team->refresh()->share_token)->not->toBe($previous);

    $this->get(route('teams.season-statistics.shared', ['team' => $previous]))
        ->assertNotFound();

    $this->get(route('teams.season-statistics.shared', ['team' => $team->share_token]))
        ->assertSee('ADC');
});

test('stopping sharing removes the public season statistics link', function () {
    [$access, $team] = sharedSeasonStatisticsTeam();

    Livewire::actingAs($access)
        ->test(SeasonStatistics::class, ['team' => $team])
        ->call('openShare');

    $token = $team->refresh()->share_token;

    Livewire::actingAs($access)
        ->test(SeasonStatistics::class, ['team' => $team])
        ->call('revokeShare')
        ->assertSet('sharing', false);

    expect($team->refresh()->share_token)->toBeNull();

    $this->get(route('teams.season-statistics.shared', ['team' => $token]))->assertNotFound();
});

test('another club cannot publish a share link for the season statistics', function () {
    [, $team] = sharedSeasonStatisticsTeam();
    $account = User::factory()->clubAccessFor(Club::factory()->create())->create();

    Livewire::actingAs($account)
        ->test(SeasonStatistics::class, ['team' => $team])
        ->assertForbidden();

    expect($team->refresh()->share_token)->toBeNull();
});

test('a visitor cannot change the public season statistics link', function () {
    [$access, $team] = sharedSeasonStatisticsTeam();

    Livewire::actingAs($access)
        ->test(SeasonStatistics::class, ['team' => $team])
        ->call('openShare');

    $token = $team->refresh()->share_token;

    Livewire::test(SharedSeasonStatistics::class, ['team' => $team])
        ->call('revokeShare')
        ->assertNotFound();

    expect($team->refresh()->share_token)->toBe($token);
});

/**
 * @return array{0: User, 1: Team}
 */
function sharedSeasonStatisticsTeam(): array
{
    $club = Club::factory()->create();
    $team = Team::factory()->create([
        'club_id' => $club->id,
        'name' => 'ADC',
    ]);
    $access = User::factory()->clubAccessFor($club)->create();

    return [$access, $team];
}
