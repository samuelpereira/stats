<?php

use App\Actions\AddLateGameEvent;
use App\Actions\UpdateGameEventType;
use App\Enums\FoulKind;
use App\Enums\GameStatus;
use App\Enums\HomeAway;
use App\Enums\StatType;
use App\Livewire\Statistics\Record as StatisticsRecord;
use App\Models\Club;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * A game with a starting five whose four quarters were started and ended.
 *
 * @return array{0: Game, 1: Player}
 */
function endedGame(GameStatus $status = GameStatus::Scheduled, int $periods = 4): array
{
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => Club::factory()->create()->id])->id,
        'home_away' => HomeAway::Home,
        'status' => $status,
        'quarter_minutes' => 10,
        'overtime_minutes' => 5,
        'home_points' => $status === GameStatus::Played ? 0 : null,
        'away_points' => $status === GameStatus::Played ? 0 : null,
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $player);

    foreach ($periods > 0 ? range(1, $periods) : [] as $quarter) {
        $game->events()->create(['type' => StatType::QuarterStarted, 'quarter' => $quarter, 'clock' => '10:00']);
        $game->events()->create(['type' => StatType::QuarterEnded, 'quarter' => $quarter, 'clock' => '00:00']);
    }

    return [$game, $player];
}

test('an administrator adds a basket to an earlier period of a played game and the saved score is refreshed', function () {
    [$game, $player] = endedGame(GameStatus::Played);
    $admin = User::factory()->administrator()->create();

    Livewire::actingAs($admin)
        ->test(StatisticsRecord::class, ['game' => $game])
        ->assertSee(__('Add action'))
        ->call('openLateAction')
        ->assertSet('addingLateAction', true)
        ->assertSet('lateEvent.quarter', 4)
        ->assertSet('lateEvent.clock', '10:00')
        ->set('lateEvent.quarter', 2)
        ->set('lateEvent.clock', '3:15')
        ->set('lateEvent.type', StatType::ThreePointMade->value)
        ->set('lateEvent.actor', 'player:'.$player->id)
        ->call('saveLateAction')
        ->assertHasNoErrors()
        ->assertSet('addingLateAction', false);

    $event = $game->events()->where('type', StatType::ThreePointMade)->sole();

    expect($event->quarter)->toBe(2)
        ->and($event->clock)->toBe('03:15')
        ->and($event->player_id)->toBe($player->id)
        ->and($event->home_away)->toBe(HomeAway::Home);

    $game->refresh();

    expect($game->status)->toBe(GameStatus::Played)
        ->and($game->home_points)->toBe(3)
        ->and($game->away_points)->toBe(0)
        ->and($game->pointsByQuarter()[2])->toBe(['home' => 3, 'away' => 0]);
});

test('an administrator adds an action once the fourth quarter ended without finishing the game', function () {
    [$game] = endedGame();
    $admin = User::factory()->administrator()->create();

    Livewire::actingAs($admin)
        ->test(StatisticsRecord::class, ['game' => $game])
        ->call('openLateAction')
        ->set('lateEvent.quarter', 1)
        ->set('lateEvent.clock', '08:00')
        ->set('lateEvent.type', StatType::TwoPointMade->value)
        ->set('lateEvent.actor', 'opponent')
        ->call('saveLateAction')
        ->assertHasNoErrors();

    $event = $game->events()->where('type', StatType::TwoPointMade)->sole();
    $game->refresh();

    expect($event->home_away)->toBe(HomeAway::Away)
        ->and($event->player_id)->toBeNull()
        ->and($game->status)->toBe(GameStatus::Scheduled)
        ->and($game->home_points)->toBeNull();
});

test('a technical foul on the coach and an opponent timeout can be added after the game', function () {
    [$game] = endedGame(GameStatus::Played);
    $admin = User::factory()->administrator()->create();

    Livewire::actingAs($admin)
        ->test(StatisticsRecord::class, ['game' => $game])
        ->call('openLateAction')
        ->set('lateEvent.quarter', 3)
        ->set('lateEvent.clock', '05:00')
        ->set('lateEvent.type', StatType::Foul->value)
        ->set('lateEvent.foulKind', FoulKind::Technical->value)
        ->set('lateEvent.actor', 'coach')
        ->call('saveLateAction')
        ->assertHasNoErrors()
        ->call('openLateAction')
        ->set('lateEvent.quarter', 3)
        ->set('lateEvent.clock', '04:00')
        ->set('lateEvent.type', StatType::Timeout->value)
        ->set('lateEvent.actor', 'opponent')
        ->call('saveLateAction')
        ->assertHasNoErrors();

    $foul = $game->events()->where('type', StatType::Foul)->sole();
    $timeout = $game->events()->where('type', StatType::Timeout)->sole();

    expect($foul->foul_kind)->toBe(FoulKind::Technical)
        ->and($foul->player_id)->toBeNull()
        ->and($foul->home_away)->toBe(HomeAway::Home)
        ->and($timeout->home_away)->toBe(HomeAway::Away)
        ->and($timeout->quarter)->toBe(3);
});

test('a late action needs a period that has already ended', function () {
    [$game, $player] = endedGame(GameStatus::Played);
    $admin = User::factory()->administrator()->create();

    Livewire::actingAs($admin)
        ->test(StatisticsRecord::class, ['game' => $game])
        ->call('openLateAction')
        ->set('lateEvent.quarter', 5)
        ->set('lateEvent.clock', '02:00')
        ->set('lateEvent.type', StatType::Steal->value)
        ->set('lateEvent.actor', 'player:'.$player->id)
        ->call('saveLateAction')
        ->assertHasErrors(['lateEvent.quarter'])
        ->assertSet('addingLateAction', true);

    expect($game->events()->where('type', StatType::Steal)->exists())->toBeFalse();
});

test('a late action clock must fit the period length', function () {
    [$game, $player] = endedGame(GameStatus::Played);
    $admin = User::factory()->administrator()->create();

    Livewire::actingAs($admin)
        ->test(StatisticsRecord::class, ['game' => $game])
        ->call('openLateAction')
        ->set('lateEvent.quarter', 2)
        ->set('lateEvent.clock', '10:01')
        ->set('lateEvent.type', StatType::Steal->value)
        ->set('lateEvent.actor', 'player:'.$player->id)
        ->call('saveLateAction')
        ->assertHasErrors(['lateEvent.clock']);

    expect($game->events()->where('type', StatType::Steal)->exists())->toBeFalse();
});

test('a late action player must be on the court at that moment', function () {
    [$game] = endedGame(GameStatus::Played);
    $admin = User::factory()->administrator()->create();
    $starter = $game->players()->wherePivot('starter', true)->firstOrFail();
    $bench = Player::factory()->create(['team_id' => $game->team_id]);
    $game->players()->attach($bench->id, ['starter' => false]);

    GameEvent::query()->create([
        'game_id' => $game->id,
        'player_id' => $bench->id,
        'outgoing_player_id' => $starter->id,
        'home_away' => HomeAway::Home,
        'type' => StatType::Substitution,
        'quarter' => 2,
        'clock' => '05:00',
    ]);

    $record = Livewire::actingAs($admin)->test(StatisticsRecord::class, ['game' => $game]);

    $record->call('openLateAction')
        ->set('lateEvent.quarter', 2)
        ->set('lateEvent.clock', '06:00')
        ->set('lateEvent.type', StatType::Assist->value)
        ->set('lateEvent.actor', 'player:'.$bench->id)
        ->call('saveLateAction')
        ->assertHasErrors(['lateEvent.actor']);

    $record->set('lateEvent.clock', '04:00')
        ->call('saveLateAction')
        ->assertHasNoErrors();

    expect($game->events()->where('type', StatType::Assist)->sole()->player_id)->toBe($bench->id);
});

test('a late action at the end of the period is listed before the end of that period', function () {
    [$game, $player] = endedGame(GameStatus::Played);
    $admin = User::factory()->administrator()->create();

    app(AddLateGameEvent::class)->handle($admin, $game, 2, '00:00', StatType::FreeThrowMade, $player->id, false);

    $quarterTwo = $game->events()->where('quarter', 2)->chronological()->pluck('type')->all();

    expect($quarterTwo)->toBe([StatType::QuarterStarted, StatType::FreeThrowMade, StatType::QuarterEnded]);

    $sorted = GameEvent::sortChronologically($game->events()->orderByDesc('id')->get())
        ->where('quarter', 2)
        ->pluck('type')
        ->values()
        ->all();

    expect($sorted)->toBe([StatType::QuarterStarted, StatType::FreeThrowMade, StatType::QuarterEnded]);
});

test('a club member cannot add actions after the game', function () {
    [$game] = endedGame(GameStatus::Played);
    $member = User::factory()->memberOf($game->team->club)->create();

    statisticsRecord($member, $game, false)
        ->assertDontSee(__('Add action'))
        ->call('openLateAction')
        ->assertForbidden();
});

test('an administrator cannot add late actions before any period has ended', function () {
    [$game, $player] = endedGame(periods: 0);
    $game->events()->create(['type' => StatType::QuarterStarted, 'quarter' => 1, 'clock' => '10:00']);
    $admin = User::factory()->administrator()->create();

    Livewire::actingAs($admin)
        ->test(StatisticsRecord::class, ['game' => $game])
        ->assertDontSee(__('Add action'))
        ->call('openLateAction')
        ->assertForbidden();

    expect(fn () => app(AddLateGameEvent::class)->handle($admin, $game, 1, '05:00', StatType::Steal, $player->id, false))
        ->toThrow(AuthorizationException::class);
});

test('an administrator adds an action to an ended period while the next one is being played', function () {
    [$game, $player] = endedGame(periods: 1);
    $admin = User::factory()->administrator()->create();
    $game->events()->create(['type' => StatType::QuarterStarted, 'quarter' => 2, 'clock' => '10:00']);
    $live = $game->events()->create([
        'type' => StatType::Steal,
        'quarter' => 2,
        'clock' => '07:30',
        'player_id' => $player->id,
        'home_away' => HomeAway::Home,
    ]);

    Livewire::actingAs($admin)
        ->test(StatisticsRecord::class, ['game' => $game])
        ->assertSee(__('Add action'))
        ->call('openLateAction')
        ->assertSet('lateEvent.quarter', 1)
        ->set('lateEvent.clock', '02:00')
        ->set('lateEvent.type', StatType::TwoPointMade->value)
        ->set('lateEvent.actor', 'player:'.$player->id)
        ->call('saveLateAction')
        ->assertHasNoErrors()
        ->assertSet('quarter', 2)
        ->assertSet('clock', '07:30');

    $late = $game->events()->where('type', StatType::TwoPointMade)->sole();

    expect($late->quarter)->toBe(1)
        ->and($game->latestLiveEvent()?->id)->toBe($live->id)
        ->and($game->latestUndoableEvent()?->id)->toBe($live->id);

    // Reopening the collector keeps the live period and clock.
    Livewire::actingAs($admin)
        ->test(StatisticsRecord::class, ['game' => $game])
        ->assertSet('quarter', 2)
        ->assertSet('clock', '07:30')
        ->assertSet('periodIsOpen', true);
});

test('undo never removes an action added to an ended period', function () {
    [$game, $player] = endedGame(GameStatus::Played);
    $admin = User::factory()->administrator()->create();

    app(AddLateGameEvent::class)->handle($admin, $game, 3, '04:00', StatType::Block, $player->id, false);

    expect($game->latestUndoableEvent())->toBeNull();

    Livewire::actingAs($admin)
        ->test(StatisticsRecord::class, ['game' => $game])
        ->call('undo');

    expect($game->events()->where('type', StatType::Block)->exists())->toBeTrue();
});

test('period markers cannot be added to an ended period', function () {
    [$game] = endedGame(GameStatus::Played);
    $admin = User::factory()->administrator()->create();

    expect(fn () => app(AddLateGameEvent::class)->handle($admin, $game, 1, '05:00', StatType::QuarterEnded, null, false))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(AddLateGameEvent::class)->handle($admin, $game, 1, '05:00', StatType::QuarterStarted, null, false))
        ->toThrow(ValidationException::class);
});

test('a substitution added to an ended period recalculates playing time', function () {
    [$game, $starter] = endedGame(GameStatus::Played);
    $admin = User::factory()->administrator()->create();
    $bench = Player::factory()->create(['team_id' => $game->team_id]);
    $game->players()->attach($bench->id, ['starter' => false]);

    Livewire::actingAs($admin)
        ->test(StatisticsRecord::class, ['game' => $game])
        ->call('openLateAction')
        ->set('lateEvent.quarter', 2)
        ->set('lateEvent.clock', '05:00')
        ->set('lateEvent.type', StatType::Substitution->value)
        ->set('lateEvent.actor', 'player:'.$bench->id)
        ->set('lateEvent.outgoing', $starter->id)
        ->call('saveLateAction')
        ->assertHasNoErrors();

    $substitution = $game->events()->where('type', StatType::Substitution)->sole();

    expect($substitution->quarter)->toBe(2)
        ->and($substitution->clock)->toBe('05:00')
        ->and($substitution->player_id)->toBe($bench->id)
        ->and($substitution->outgoing_player_id)->toBe($starter->id);

    $seconds = collect($game->boxScore()['players'])->pluck('seconds', 'id');

    expect($seconds[$starter->id])->toBe(15 * 60)
        ->and($seconds[$bench->id])->toBe(25 * 60);
});

test('a substitution is added even when it does not match who the log has on the court', function () {
    [$game] = endedGame(GameStatus::Played);
    $admin = User::factory()->administrator()->create();
    $benchOne = Player::factory()->create(['team_id' => $game->team_id]);
    $benchTwo = Player::factory()->create(['team_id' => $game->team_id]);
    $game->players()->attach([$benchOne->id => ['starter' => false], $benchTwo->id => ['starter' => false]]);

    app(AddLateGameEvent::class)->handle($admin, $game, 3, '04:00', StatType::Substitution, $benchOne->id, false, null, $benchTwo->id);

    expect($game->events()->where('type', StatType::Substitution)->sole()->outgoing_player_id)->toBe($benchTwo->id);
});

test('a late substitution needs two different players of the game', function () {
    [$game, $player] = endedGame(GameStatus::Played);
    $admin = User::factory()->administrator()->create();
    $outsider = Player::factory()->create(['team_id' => $game->team_id]);
    $add = fn (?int $in, ?int $out) => app(AddLateGameEvent::class)
        ->handle($admin, $game, 1, '05:00', StatType::Substitution, $in, false, null, $out);

    expect(fn () => $add($player->id, $player->id))->toThrow(ValidationException::class)
        ->and(fn () => $add($outsider->id, $player->id))->toThrow(ValidationException::class)
        ->and(fn () => $add($player->id, $outsider->id))->toThrow(ValidationException::class)
        ->and(fn () => $add($player->id, null))->toThrow(ValidationException::class)
        ->and($game->events()->where('type', StatType::Substitution)->exists())->toBeFalse();

    Livewire::actingAs($admin)
        ->test(StatisticsRecord::class, ['game' => $game])
        ->call('openLateAction')
        ->set('lateEvent.type', StatType::Substitution->value)
        ->set('lateEvent.actor', 'player:'.$player->id)
        ->call('saveLateAction')
        ->assertHasErrors(['lateEvent.outgoing']);
});

test('editing a logged basket of a played game refreshes the saved score', function () {
    [$game, $player] = endedGame(GameStatus::Played);
    $admin = User::factory()->administrator()->create();
    $basket = app(AddLateGameEvent::class)->handle($admin, $game, 2, '05:00', StatType::TwoPointMade, $player->id, false);

    expect($game->refresh()->home_points)->toBe(2);

    Livewire::actingAs($admin)
        ->test(StatisticsRecord::class, ['game' => $game])
        ->call('editAction', $basket->id)
        ->call('selectEditingType', StatType::ThreePointMade->value)
        ->call('saveLogLine')
        ->assertHasNoErrors();

    expect($game->refresh()->home_points)->toBe(3)
        ->and($game->away_points)->toBe(0);

    app(UpdateGameEventType::class)->handle($game, $basket->id, StatType::ThreePointMade, null, '05:00', null, true);

    expect($game->refresh()->home_points)->toBe(0)
        ->and($game->away_points)->toBe(3);
});

test('editing a logged basket of a game that is not played leaves the score empty', function () {
    [$game, $player] = endedGame();
    $admin = User::factory()->administrator()->create();
    $basket = app(AddLateGameEvent::class)->handle($admin, $game, 2, '05:00', StatType::TwoPointMade, $player->id, false);

    app(UpdateGameEventType::class)->handle($game, $basket->id, StatType::ThreePointMade, $player->id, '05:00');

    expect($game->refresh()->home_points)->toBeNull()
        ->and($game->away_points)->toBeNull();
});

test('each log line carries the court at that moment, including substitutions added later to an ended period', function () {
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => Club::factory()->create()->id])->id,
        'home_away' => HomeAway::Home,
        'quarter_minutes' => 10,
    ]);
    $starter = Player::factory()->create(['team_id' => $game->team_id]);
    giveStartingFive($game, $starter);
    $forgotten = Player::factory()->create(['team_id' => $game->team_id]);
    $later = Player::factory()->create(['team_id' => $game->team_id]);
    $game->players()->attach([$forgotten->id => ['starter' => false], $later->id => ['starter' => false]]);
    $admin = User::factory()->administrator()->create();

    $game->events()->create(['type' => StatType::QuarterStarted, 'quarter' => 1, 'clock' => '10:00']);
    $steal = $game->events()->create(['type' => StatType::Steal, 'quarter' => 1, 'clock' => '04:00', 'player_id' => $starter->id, 'home_away' => HomeAway::Home]);
    $game->events()->create(['type' => StatType::QuarterEnded, 'quarter' => 1, 'clock' => '00:00']);
    $game->events()->create(['type' => StatType::QuarterStarted, 'quarter' => 2, 'clock' => '10:00']);
    // Recorded live while the collector still had the starter on the court.
    $game->events()->create(['type' => StatType::Substitution, 'quarter' => 2, 'clock' => '08:00', 'player_id' => $later->id, 'outgoing_player_id' => $starter->id, 'home_away' => HomeAway::Home]);

    app(AddLateGameEvent::class)->handle($admin, $game, 1, '05:00', StatType::Substitution, $forgotten->id, false, null, $starter->id);

    $record = Livewire::actingAs($admin)->test(StatisticsRecord::class, ['game' => $game]);
    $lines = collect($record->instance()->lines)->keyBy('id');
    $stealCourt = $lines[$steal->id]['court'];
    sort($stealCourt);
    $expected = $game->courtAt($steal);
    sort($expected);

    expect($stealCourt)->toBe($expected)
        ->and($stealCourt)->toContain($forgotten->id)
        ->and($stealCourt)->not->toContain($starter->id);

    $record->call('applyCollectedAction', (string) Str::uuid(), 'edit', [
        'eventId' => $steal->id,
        'type' => StatType::Steal->value,
        'playerId' => $forgotten->id,
        'forOpponent' => false,
        'clock' => '04:00',
    ]);

    expect($steal->refresh()->player_id)->toBe($forgotten->id);
});
