<?php

use App\Enums\StatType;
use App\Livewire\Statistics\Shared;
use App\Livewire\Statistics\Show as StatisticsShow;
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

test('a public link shows the game statistics without signing in', function () {
    [$member, $game] = sharedStatisticsGame();
    $starters = Player::factory()->count(5)->create(['team_id' => $game->team_id]);

    foreach ($starters as $starter) {
        $game->players()->attach($starter->id, ['starter' => true]);
    }

    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => null,
        'home_away' => null,
        'type' => StatType::QuarterStarted,
        'quarter' => 1,
        'clock' => '10:00',
    ]);

    Livewire::actingAs($member)
        ->test(StatisticsShow::class, ['game' => $game])
        ->call('openShare')
        ->assertSet('sharing', true)
        ->assertSee(route('statistics.shared', ['game' => $game->refresh()->share_token]), false);

    $this->get(route('statistics.shared', ['game' => $game->share_token]))
        ->assertSee('ADC')
        ->assertSee('Metro United')
        ->assertSee('2 Oct 2026')
        ->assertSee('ADC 0 vs Metro United 0', false)
        ->assertDontSee('ADC 0 vs Metro United 0 - ', false)
        ->assertDontSee(__('Turn the screen sideways'))
        ->assertDontSee(__('Back'))
        ->assertDontSee(__('Bench'))
        ->assertSee(__('Lineups'))
        ->assertSee(__('Period start'))
        ->assertDontSee(__('Stop sharing'))
        ->assertSeeInOrder(['Box score', 'Matchup', 'Lineups']);
});

test('the game uuid does not open the public statistics', function () {
    [, $game] = sharedStatisticsGame();

    $this->get(route('statistics.shared', ['game' => $game->uuid]))
        ->assertNotFound();
});

test('a replaced share link stops opening the previous statistics', function () {
    [$member, $game] = sharedStatisticsGame();

    Livewire::actingAs($member)
        ->test(StatisticsShow::class, ['game' => $game])
        ->call('openShare');

    $previous = $game->refresh()->share_token;

    Livewire::actingAs($member)
        ->test(StatisticsShow::class, ['game' => $game])
        ->call('rotateShare');

    expect($game->refresh()->share_token)->not->toBe($previous);

    $this->get(route('statistics.shared', ['game' => $previous]))
        ->assertNotFound();

    $this->get(route('statistics.shared', ['game' => $game->share_token]))
        ->assertSee('Metro United');
});

test('stopping sharing removes the public statistics link', function () {
    [$member, $game] = sharedStatisticsGame();

    Livewire::actingAs($member)
        ->test(StatisticsShow::class, ['game' => $game])
        ->call('openShare');

    $token = $game->refresh()->share_token;

    Livewire::actingAs($member)
        ->test(StatisticsShow::class, ['game' => $game])
        ->call('revokeShare')
        ->assertSet('sharing', false);

    expect($game->refresh()->share_token)->toBeNull();

    $this->get(route('statistics.shared', ['game' => $token]))->assertNotFound();
});

test('another club cannot publish a share link for the game', function () {
    [, $game] = sharedStatisticsGame();
    $account = User::factory()->clubAccessFor(Club::factory()->create())->create();

    Livewire::actingAs($account)
        ->test(StatisticsShow::class, ['game' => $game])
        ->assertForbidden();

    expect($game->refresh()->share_token)->toBeNull();
});

test('a visitor cannot change the public statistics link', function () {
    [$member, $game] = sharedStatisticsGame();

    Livewire::actingAs($member)
        ->test(StatisticsShow::class, ['game' => $game])
        ->call('openShare');

    $token = $game->refresh()->share_token;

    Livewire::test(Shared::class, ['game' => $game])
        ->call('revokeShare')
        ->assertNotFound();

    expect($game->refresh()->share_token)->toBe($token);
});

/**
 * @return array{0: User, 1: Game}
 */
function sharedStatisticsGame(): array
{
    $club = Club::factory()->create();
    $team = Team::factory()->create([
        'club_id' => $club->id,
        'name' => 'ADC',
    ]);
    $member = User::factory()->memberOf($club)->withTeams($team)->create();
    $game = Game::factory()->create([
        'team_id' => $team->id,
        'opponent' => 'Metro United',
        'date_time' => '2026-10-02 20:30:00',
    ]);

    return [$member, $game];
}
