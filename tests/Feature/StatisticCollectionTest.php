<?php

use App\Actions\ApplyCollectedAction;
use App\Enums\FoulKind;
use App\Enums\GameStatus;
use App\Enums\HomeAway;
use App\Enums\StatType;
use App\Enums\TeamColor;
use App\Livewire\Games\Index as GameIndex;
use App\Livewire\Players\Index as PlayerIndex;
use App\Livewire\Statistics\Index as StatisticsIndex;
use App\Models\Club;
use App\Models\CollectCommand;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('statisticsRecord opens the period by default', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);

    giveStartingFive($game);

    statisticsRecord($member, $game)
        ->assertSet('periodIsOpen', true);

    expect(GameEvent::query()->where('type', StatType::QuarterStarted)->count())->toBe(1);
});

test('stats cannot be recorded before the period starts', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $player);

    statisticsRecord($member, $game, false)
        ->call('select', StatType::TwoPointMade->value)
        ->assertSet('choosingPlayer', false)
        ->assertSet('pendingType', null)
        ->call('record', $player->id)
        ->call('chooseTimeout')
        ->assertSet('choosingTimeout', false);

    expect(GameEvent::query()->count())->toBe(0);

    statisticsRecord($member, $game, false)
        ->call('recordQuarterStart')
        ->call('select', StatType::TwoPointMade->value)
        ->call('record', $player->id);

    expect(GameEvent::query()->where('type', StatType::TwoPointMade)->count())->toBe(1);
});

test('a club member records a shot on the statistics log', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id, 'name' => 'North City']);
    $game = Game::factory()->create([
        'team_id' => $team->id,
        'opponent' => 'South Valley U18',
        'home_away' => HomeAway::Home,
        'away_points' => 54,
    ]);
    $player = Player::factory()->create([
        'team_id' => $team->id,
        'name' => 'Alex North',
        'number' => 84,
    ]);

    giveStartingFive($game, $player);

    statisticsRecord($member, $game, false)
        ->assertSee('North City')
        ->assertSee('South Valley U18')
        ->assertSee('54')
        ->set('quarter', 4)
        ->set('clock', '5:00')
        ->call('recordQuarterStart')
        ->call('select', StatType::TwoPointMade->value)
        ->call('record', $player->id)
        ->assertHasNoErrors()
        ->assertSee('Clock')
        ->assertSee('2pt')
        ->assertSee('#84 Alex North')
        ->assertSee('05:00')
        ->assertSee('2pts 1-1 fg')
        ->call('select', StatType::Steal->value)
        ->call('record', $player->id)
        ->assertSeeInOrder(['(1)', '2pts 1-1 fg']);

    $event = GameEvent::query()->where('type', StatType::TwoPointMade)->first();

    expect($event)->not->toBeNull()
        ->and($event->game_id)->toBe($game->id)
        ->and($event->player_id)->toBe($player->id)
        ->and($event->type)->toBe(StatType::TwoPointMade)
        ->and($event->quarter)->toBe(4)
        ->and($event->clock)->toBe('05:00');
});

test('field goals can require marking the shot location on the court', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $game = Game::factory()->create([
        'team_id' => $team->id,
        'home_away' => HomeAway::Home,
        'mark_shot_location' => true,
    ]);
    $player = Player::factory()->create(['team_id' => $team->id]);

    giveStartingFive($game, $player);

    statisticsRecord($member, $game)
        ->call('select', StatType::TwoPointMade->value)
        ->call('record', $player->id)
        ->assertSet('choosingShotLocation', true)
        ->call('recordShotLocation', 500, 520)
        ->assertHasErrors(['shotLocation'])
        ->call('recordShotLocation', 20, 300)
        ->assertHasErrors(['shotLocation'])
        ->call('recordShotLocation', 500, 300)
        ->assertHasNoErrors()
        ->assertSet('choosingShotLocation', false)
        ->assertSee('2pt');

    $event = GameEvent::query()->where('type', StatType::TwoPointMade)->first();

    expect($event)->not->toBeNull()
        ->and($event->type)->toBe(StatType::TwoPointMade)
        ->and($event->court_x)->toBe(500)
        ->and($event->court_y)->toBe(300);
});

test('a queued three-point shot is only stored outside the arc', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
        'mark_shot_location' => true,
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $player);

    $payload = [
        'type' => StatType::ThreePointMade->value,
        'playerId' => $player->id,
        'forOpponent' => false,
        'quarter' => 1,
        'clock' => '04:15',
        'courtX' => 500,
        'courtY' => 300,
    ];

    statisticsRecord($member, $game)
        ->call('applyCollectedAction', fake()->uuid(), 'record', $payload)
        ->assertHasErrors(['shotLocation']);

    expect(GameEvent::query()->where('type', StatType::ThreePointMade)->count())->toBe(0);

    statisticsRecord($member, $game, false)
        ->call('applyCollectedAction', fake()->uuid(), 'record', [
            ...$payload,
            'courtY' => 520,
        ])
        ->assertHasNoErrors();

    expect(GameEvent::query()->where('type', StatType::ThreePointMade)->count())->toBe(1)
        ->and(GameEvent::query()->where('type', StatType::ThreePointMade)->value('court_y'))->toBe(520);
});

test('the log marks a two as fg, a three as 3pt, and a free throw as ft', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $game = Game::factory()->create([
        'team_id' => $team->id,
        'home_away' => HomeAway::Home,
    ]);
    $player = Player::factory()->create(['team_id' => $team->id]);

    giveStartingFive($game, $player);

    statisticsRecord($member, $game)
        ->call('select', StatType::TwoPointMade->value)
        ->call('record', $player->id)
        ->call('select', StatType::ThreePointMissed->value)
        ->call('record', $player->id)
        ->call('select', StatType::FreeThrowMade->value)
        ->call('record', $player->id)
        ->assertSee('2pts 1-1 fg')
        ->assertSee('2pts 0-1 3pt')
        ->assertSee('3pts 1-1 ft');
});

test('a logged scoring action cannot become a play action or foul', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $game = Game::factory()->create([
        'team_id' => $team->id,
        'home_away' => HomeAway::Home,
    ]);
    $player = Player::factory()->create(['team_id' => $team->id]);
    $event = GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => $player->id,
        'home_away' => $game->home_away,
        'type' => StatType::TwoPointMade,
        'clock' => '08:00',
    ]);

    giveStartingFive($game, $player);

    statisticsRecord($member, $game)
        ->call('editAction', $event->id)
        ->call('selectEditingType', StatType::Assist->value)
        ->call('saveLogLine');

    expect($event->refresh()->type)->toBe(StatType::TwoPointMade);

    statisticsRecord($member, $game)
        ->set('editingEventId', $event->id)
        ->set('editingType', StatType::Foul->value)
        ->set('editingPlayerId', $player->id)
        ->set('editingEventClock', '08:00')
        ->call('saveLogLine')
        ->assertNotFound();

    expect($event->refresh()->type)->toBe(StatType::TwoPointMade);
});

test('a logged play action can change among play actions only', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $game = Game::factory()->create([
        'team_id' => $team->id,
        'home_away' => HomeAway::Home,
    ]);
    $player = Player::factory()->create(['team_id' => $team->id]);
    $event = GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => $player->id,
        'home_away' => $game->home_away,
        'type' => StatType::Steal,
        'clock' => '07:00',
    ]);

    giveStartingFive($game, $player);

    statisticsRecord($member, $game)
        ->call('editAction', $event->id)
        ->call('selectEditingType', StatType::Assist->value)
        ->call('saveLogLine')
        ->assertHasNoErrors();

    expect($event->refresh()->type)->toBe(StatType::Assist);

    statisticsRecord($member, $game)
        ->set('editingEventId', $event->id)
        ->set('editingType', StatType::TwoPointMade->value)
        ->set('editingPlayerId', $player->id)
        ->set('editingEventClock', '07:00')
        ->call('saveLogLine')
        ->assertNotFound();

    expect($event->refresh()->type)->toBe(StatType::Assist);
});

test('a logged foul keeps its type when edited', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $game = Game::factory()->create([
        'team_id' => $team->id,
        'home_away' => HomeAway::Home,
    ]);
    $player = Player::factory()->create(['team_id' => $team->id]);
    $event = GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => $player->id,
        'home_away' => $game->home_away,
        'type' => StatType::Foul,
        'clock' => '06:00',
    ]);

    giveStartingFive($game, $player);

    $replacement = $game->players()->whereKeyNot($player->id)->firstOrFail();

    statisticsRecord($member, $game)
        ->call('editAction', $event->id)
        ->call('selectEditingType', StatType::Steal->value)
        ->call('selectEditingPlayer', $replacement->id)
        ->set('editingEventClock', '02:45')
        ->call('saveLogLine')
        ->assertHasNoErrors();

    expect($event->refresh()->type)->toBe(StatType::Foul)
        ->and($event->player_id)->toBe($replacement->id)
        ->and($event->clock)->toBe('02:45');
});

test('a logged two can be changed into a three', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $game = Game::factory()->create([
        'team_id' => $team->id,
        'home_away' => HomeAway::Home,
    ]);
    $player = Player::factory()->create(['team_id' => $team->id]);

    giveStartingFive($game, $player);

    $screen = statisticsRecord($member, $game)
        ->call('select', StatType::TwoPointMade->value)
        ->call('record', $player->id);

    $event = GameEvent::query()->where('type', StatType::TwoPointMade)->first();

    $screen->call('editAction', $event->id)
        ->assertSet('editingAction', true)
        ->call('selectEditingType', StatType::ThreePointMade->value)
        ->call('saveLogLine')
        ->assertSet('editingAction', false)
        ->assertSee('3pts 1-1 3pt');

    expect($event->refresh()->type)->toBe(StatType::ThreePointMade)
        ->and($event->player_id)->toBe($player->id);
});

test('a logged line can be given to another player or the opponent', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $game = Game::factory()->create([
        'team_id' => $team->id,
        'opponent' => 'South Valley U18',
        'home_away' => HomeAway::Home,
    ]);
    $player = Player::factory()->create([
        'team_id' => $team->id,
        'name' => 'Jordan Lake',
        'number' => 23,
    ]);
    $replacement = Player::factory()->create([
        'team_id' => $team->id,
        'name' => 'Casey Brooks',
        'number' => 24,
    ]);

    giveStartingFive($game, $player);
    $game->players()->updateExistingPivot(
        $game->players()->whereKeyNot($player->id)->firstOrFail()->id,
        ['starter' => false],
    );
    $game->players()->syncWithoutDetaching([
        $replacement->id => ['starter' => true],
    ]);

    $screen = statisticsRecord($member, $game)
        ->call('select', StatType::TwoPointMade->value)
        ->call('record', $player->id);

    $event = GameEvent::query()->where('type', StatType::TwoPointMade)->first();

    $screen->call('editAction', $event->id)
        ->call('selectEditingPlayer', $replacement->id)
        ->call('saveLogLine')
        ->assertSee('#24 Casey Brooks');

    expect($event->refresh()->player_id)->toBe($replacement->id)
        ->and($event->home_away)->toBe(HomeAway::Home);

    $screen->call('editAction', $event->id)
        ->call('selectEditingOpponent')
        ->call('saveLogLine')
        ->assertSee('South Valley U18');

    expect($event->refresh()->player_id)->toBeNull()
        ->and($event->home_away)->toBe(HomeAway::Away);
});

test('a period marker cannot be edited from the log', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $game = Game::factory()->create(['team_id' => $team->id]);
    $marker = GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => null,
        'home_away' => null,
        'type' => StatType::QuarterStarted,
        'quarter' => 1,
        'clock' => '10:00',
    ]);

    giveStartingFive($game);

    statisticsRecord($member, $game)
        ->call('editAction', $marker->id)
        ->assertSet('editingAction', false);

    expect($marker->refresh()->type)->toBe(StatType::QuarterStarted);
});

test('a logged action cannot be changed into a substitution or another game', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $game = Game::factory()->create([
        'team_id' => $team->id,
        'home_away' => HomeAway::Home,
    ]);
    $player = Player::factory()->create(['team_id' => $team->id]);
    $shot = GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => $player->id,
        'home_away' => $game->home_away,
        'type' => StatType::TwoPointMade,
        'clock' => '05:00',
    ]);
    $other = GameEvent::factory()->create([
        'type' => StatType::TwoPointMade,
        'clock' => '05:00',
    ]);

    giveStartingFive($game, $player);

    statisticsRecord($member, $game)
        ->set('editingEventId', $shot->id)
        ->set('editingType', StatType::Substitution->value)
        ->set('editingPlayerId', $player->id)
        ->set('editingOutgoingPlayerId', $player->id)
        ->set('editingEventClock', '05:00')
        ->call('saveLogLine')
        ->assertNotFound();

    expect($shot->refresh()->type)->toBe(StatType::TwoPointMade);

    statisticsRecord($member, $game)
        ->set('editingEventId', $other->id)
        ->set('editingType', StatType::ThreePointMade->value)
        ->set('editingPlayerId', $player->id)
        ->set('editingEventClock', '05:00')
        ->call('saveLogLine')
        ->assertNotFound();

    expect($other->refresh()->type)->toBe(StatType::TwoPointMade);

    $outsider = Player::factory()->create(['team_id' => $team->id]);

    statisticsRecord($member, $game)
        ->set('editingEventId', $shot->id)
        ->set('editingType', StatType::TwoPointMade->value)
        ->set('editingPlayerId', $outsider->id)
        ->set('editingEventClock', '05:00')
        ->call('saveLogLine')
        ->assertNotFound();

    expect($shot->refresh()->player_id)->toBe($player->id);
});

test('a logged action clock can be edited', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $game = Game::factory()->create([
        'team_id' => $team->id,
        'home_away' => HomeAway::Home,
    ]);
    $player = Player::factory()->create(['team_id' => $team->id]);
    $event = GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => $player->id,
        'home_away' => $game->home_away,
        'type' => StatType::TwoPointMade,
        'clock' => '08:00',
    ]);

    giveStartingFive($game, $player);

    statisticsRecord($member, $game)
        ->call('editAction', $event->id)
        ->set('editingEventClock', '03:15')
        ->call('saveLogLine')
        ->assertHasNoErrors();

    expect($event->refresh()->clock)->toBe('03:15')
        ->and($event->type)->toBe(StatType::TwoPointMade)
        ->and($event->player_id)->toBe($player->id);
});

test('a logged substitution can change the players and the clock', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $game = Game::factory()->create([
        'team_id' => $team->id,
        'home_away' => HomeAway::Home,
    ]);
    $incoming = Player::factory()->create(['team_id' => $team->id, 'name' => 'In Player', 'number' => 1]);
    $outgoing = Player::factory()->create(['team_id' => $team->id, 'name' => 'Out Player', 'number' => 2]);
    $nextIn = Player::factory()->create(['team_id' => $team->id, 'name' => 'Next In', 'number' => 3]);
    $nextOut = Player::factory()->create(['team_id' => $team->id, 'name' => 'Next Out', 'number' => 4]);
    $event = GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => $incoming->id,
        'outgoing_player_id' => $outgoing->id,
        'home_away' => $game->home_away,
        'type' => StatType::Substitution,
        'clock' => '07:00',
    ]);

    giveStartingFive($game, $outgoing);
    $game->players()->syncWithoutDetaching([
        $incoming->id => ['starter' => false],
        $nextIn->id => ['starter' => false],
        $nextOut->id => ['starter' => false],
    ]);

    statisticsRecord($member, $game)
        ->call('editAction', $event->id)
        ->assertSet('editingAction', true)
        ->assertSee('In Player')
        ->assertSee('Out Player')
        ->assertSee('On court')
        ->assertSee('Goes out')
        ->call('selectEditingPlayer', $nextIn->id)
        ->call('selectEditingOutgoingPlayer', $nextOut->id)
        ->assertSet('editingPlayerId', $nextIn->id)
        ->assertSet('editingOutgoingPlayerId', $nextOut->id)
        ->set('editingEventClock', '04:30')
        ->call('saveLogLine')
        ->assertHasNoErrors();

    expect($event->refresh()->player_id)->toBe($nextIn->id)
        ->and($event->outgoing_player_id)->toBe($nextOut->id)
        ->and($event->clock)->toBe('04:30')
        ->and($event->type)->toBe(StatType::Substitution);
});

test('a logged timeout can change side and clock', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $game = Game::factory()->create([
        'team_id' => $team->id,
        'opponent' => 'South Valley U18',
        'home_away' => HomeAway::Home,
    ]);
    $event = GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => null,
        'home_away' => HomeAway::Home,
        'type' => StatType::Timeout,
        'clock' => '06:00',
    ]);

    giveStartingFive($game);

    statisticsRecord($member, $game)
        ->call('editAction', $event->id)
        ->assertSet('editingAction', true)
        ->assertSet('editingForOpponent', false)
        ->call('selectEditingOpponent')
        ->set('editingEventClock', '01:20')
        ->call('saveLogLine')
        ->assertHasNoErrors();

    expect($event->refresh()->home_away)->toBe(HomeAway::Away)
        ->and($event->clock)->toBe('01:20')
        ->and($event->type)->toBe(StatType::Timeout)
        ->and($event->player_id)->toBeNull();
});

test('a club member records an opponent basket from the player screen', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $game = Game::factory()->create([
        'team_id' => $team->id,
        'opponent' => 'South Valley U18',
        'home_away' => HomeAway::Home,
        'away_points' => 54,
    ]);
    $player = Player::factory()->create([
        'team_id' => $team->id,
        'name' => 'Alex North',
        'number' => 84,
    ]);

    giveStartingFive($game, $player);

    statisticsRecord($member, $game)
        ->call('select', StatType::TwoPointMade->value)
        ->assertSet('pendingType', StatType::TwoPointMade->value)
        ->assertSet('choosingPlayer', true)
        ->assertSee('Cancel')
        ->assertSee('#84')
        ->assertSee('Alex')
        ->assertSee('South Valley U18')
        ->call('recordOpponent')
        ->assertSet('pendingType', null)
        ->assertSet('choosingPlayer', false)
        ->assertSee('56')
        ->assertSee('South Valley U18');

    $event = GameEvent::query()->where('type', StatType::TwoPointMade)->first();

    expect($event)->not->toBeNull()
        ->and($event->home_away)->toBe(HomeAway::Away)
        ->and($event->player_id)->toBeNull()
        ->and($event->type)->toBe(StatType::TwoPointMade);
});

test('a recorded foul is shown beside the player', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id, 'name' => 'North City']);
    $game = Game::factory()->create([
        'team_id' => $team->id,
        'opponent' => 'South Valley U18',
        'home_away' => HomeAway::Home,
    ]);
    $player = Player::factory()->create([
        'team_id' => $team->id,
        'name' => 'Alex North',
        'number' => 84,
    ]);

    giveStartingFive($game, $player);

    statisticsRecord($member, $game)
        ->call('select', StatType::Foul->value)
        ->call('record', $player->id)
        ->assertSee('Alex North')
        ->assertSee('">1</span> FP', false)
        ->assertSee('foul')
        ->assertSee('(1)');
});

test('points are totalled for each period', function () {
    $game = Game::factory()->create();
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => $player->id,
        'type' => StatType::TwoPointMade,
        'quarter' => 1,
    ]);
    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => null,
        'home_away' => HomeAway::Away,
        'type' => StatType::ThreePointMade,
        'quarter' => 2,
    ]);
    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => $player->id,
        'type' => StatType::FreeThrowMade,
        'quarter' => 1,
    ]);

    expect($game->pointsByQuarter())->toBe([
        1 => ['home' => 3, 'away' => 0],
        2 => ['home' => 0, 'away' => 3],
        3 => ['home' => 0, 'away' => 0],
        4 => ['home' => 0, 'away' => 0],
    ]);
});

test('personal fouls are green through three, orange on four, and red from five', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $player);

    $screen = statisticsRecord($member, $game);

    foreach (range(1, 3) as $ignored) {
        $screen->call('select', StatType::Foul->value)
            ->call('record', $player->id);
    }

    expect($screen->html())->toContain('">3</span> FP')
        ->and($screen->html())->not->toContain('">5</span> FP');

    $screen->call('select', StatType::Foul->value)
        ->call('record', $player->id);

    expect($screen->html())->toContain('">4</span> FP')
        ->and($screen->html())->not->toContain('">5</span> FP');

    $screen->call('select', StatType::Foul->value)
        ->call('record', $player->id);

    expect($screen->html())->toContain('">5</span> FP');
});

test('team fouls are green through three and orange on the fourth', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $player);

    $screen = statisticsRecord($member, $game);

    foreach (range(1, 3) as $ignored) {
        $screen->call('select', StatType::Foul->value)
            ->call('record', $player->id);
    }

    expect($screen->html())->toContain('teamFoulCount()">3</span>')
        ->and($screen->html())->not->toContain('teamFoulCount()">4</span>')
        ->and($screen->html())->not->toContain('teamFoulCount()">5</span>');

    $screen->call('select', StatType::Foul->value)
        ->call('record', $player->id);

    expect($screen->html())->toContain('teamFoulCount()">4</span>')
        ->and($screen->html())->not->toContain('teamFoulCount()">5</span>');
});

test('team fouls restart each period', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $player);

    $screen = statisticsRecord($member, $game);

    foreach (range(1, 5) as $ignored) {
        $screen->call('select', StatType::Foul->value)
            ->call('record', $player->id);
    }

    $screen->assertSee('teamFoulCount()">5</span>', false)
        ->assertSee('">5</span> FP', false)
        ->set('clock', '00:00')
        ->call('endQuarter')
        ->call('startQuarter')
        ->assertDontSee('teamFoulCount()">5</span>', false)
        ->assertSee('teamFoulCount()">0</span>', false)
        ->assertSee('">5</span> FP', false)
        ->call('select', StatType::Foul->value)
        ->call('record', $player->id)
        ->assertDontSee('Free throw 1 of 2')
        ->assertSee('teamFoulCount()">1</span>', false)
        ->assertSee('">6</span> FP', false);

    expect($game->teamFoulsByQuarter()[1])->toBe(['home' => 5, 'away' => 0])
        ->and($game->teamFoulsByQuarter()[2])->toBe(['home' => 1, 'away' => 0]);
});

test('team fouls carry from the fourth quarter through overtime', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $player);

    $screen = statisticsRecord($member, $game);

    foreach (range(1, 3) as $ignored) {
        $screen->set('clock', '00:00')
            ->call('endQuarter')
            ->call('startQuarter');
    }

    foreach (range(1, 3) as $ignored) {
        $screen->call('select', StatType::Foul->value)
            ->call('record', $player->id);
    }

    $screen->assertSee('teamFoulCount()">3</span>', false)
        ->assertSee('">3</span> FP', false)
        ->set('clock', '00:00')
        ->call('endQuarter')
        ->call('startQuarter')
        ->assertSee('teamFoulCount()">3</span>', false)
        ->assertSee('">3</span> FP', false)
        ->call('select', StatType::Foul->value)
        ->call('record', $player->id)
        ->assertSee('teamFoulCount()">4</span>', false)
        ->assertSee('">4</span> FP', false)
        ->set('clock', '00:00')
        ->call('endQuarter')
        ->call('startQuarter')
        ->assertSee('teamFoulCount()">4</span>', false)
        ->assertSee('">4</span> FP', false)
        ->call('select', StatType::Foul->value)
        ->call('record', $player->id)
        ->assertSee('teamFoulCount()">5</span>', false)
        ->assertSee('">5</span> FP', false);

    expect($game->teamFoulsByQuarter()[4])->toBe(['home' => 3, 'away' => 0])
        ->and($game->teamFoulsByQuarter()[5])->toBe(['home' => 1, 'away' => 0])
        ->and($game->teamFoulsByQuarter()[6])->toBe(['home' => 1, 'away' => 0])
        ->and($game->teamFoulsForPeriod(5))->toBe(['home' => 4, 'away' => 0])
        ->and($game->teamFoulsForPeriod(6))->toBe(['home' => 5, 'away' => 0]);
});

test('a team foul recorded during the first period does not count in the next one', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => null,
        'home_away' => null,
        'type' => StatType::QuarterStarted,
        'quarter' => 1,
    ]);
    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => $player->id,
        'home_away' => HomeAway::Home,
        'type' => StatType::Foul,
        'quarter' => 2,
    ]);
    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => null,
        'home_away' => null,
        'type' => StatType::QuarterEnded,
        'quarter' => 1,
    ]);

    giveStartingFive($game, $player);

    statisticsRecord($member, $game)
        ->set('quarter', 2)
        ->assertDontSee('teamFoulCount()">1</span>', false)
        ->assertSee('teamFoulCount()">0</span>', false)
        ->assertSee('">1</span> FP', false)
        ->set('quarter', 1)
        ->assertSee('teamFoulCount()">1</span>', false)
        ->assertSee('">1</span> FP', false);
});

test('a fifth team foul stays on the collector so a one can be marked', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
        'opponent' => 'South Valley',
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $player);

    $screen = statisticsRecord($member, $game);

    foreach (range(1, 5) as $ignored) {
        $screen->call('select', StatType::Foul->value)
            ->call('record', $player->id);
    }

    $screen->assertDontSee('Free throw 1 of 2')
        ->assertSee('1P')
        ->call('select', StatType::FreeThrowMade->value)
        ->call('record', $player->id)
        ->call('select', StatType::FreeThrowMissed->value)
        ->call('recordOpponent');

    $shots = GameEvent::query()
        ->whereIn('type', [StatType::FreeThrowMade, StatType::FreeThrowMissed])
        ->orderBy('id')
        ->get();

    expect($shots)->toHaveCount(2)
        ->and($shots[0]->type)->toBe(StatType::FreeThrowMade)
        ->and($shots[0]->player_id)->toBe($player->id)
        ->and($shots[1]->type)->toBe(StatType::FreeThrowMissed)
        ->and($shots[1]->player_id)->toBeNull()
        ->and(GameEvent::query()->where('type', StatType::Foul)->count())->toBe(5);
});

test('a special foul on a player counts for personal and team fouls and appears as TF in the box score', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
        'home_away' => HomeAway::Home,
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $player);

    statisticsRecord($member, $game)
        ->call('select', StatType::Foul->value)
        ->call('setPendingFoulKind', FoulKind::Technical->value)
        ->call('record', $player->id)
        ->assertSee('">1</span> FP', false)
        ->assertSee('teamFoulCount()">1</span>', false)
        ->assertSee('tech foul');

    $event = GameEvent::query()->where('type', StatType::Foul)->first();
    $boxScore = $game->fresh()->boxScore();
    $line = collect($boxScore['players'])->firstWhere('id', $player->id);

    expect($event)->not->toBeNull()
        ->and($event->foul_kind)->toBe(FoulKind::Technical)
        ->and($event->player_id)->toBe($player->id)
        ->and($line['fouls'])->toBe(0)
        ->and($line['technicalFouls'])->toBe(1)
        ->and($boxScore['team']['fouls'])->toBe(0)
        ->and($boxScore['team']['technicalFouls'])->toBe(1)
        ->and($game->teamFoulsForPeriod(1))->toBe(['home' => 1, 'away' => 0]);
});

test('a technical foul on the coach counts for team fouls only and is omitted from the box score', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
        'home_away' => HomeAway::Home,
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $player);

    statisticsRecord($member, $game)
        ->call('select', StatType::Foul->value)
        ->call('setPendingFoulKind', FoulKind::Technical->value)
        ->call('recordCoach')
        ->assertSee('teamFoulCount()">1</span>', false)
        ->assertSee('Coach / Bench')
        ->assertSee('tech foul');

    $event = GameEvent::query()->where('type', StatType::Foul)->first();
    $boxScore = $game->fresh()->boxScore();

    expect($event)->not->toBeNull()
        ->and($event->foul_kind)->toBe(FoulKind::Technical)
        ->and($event->player_id)->toBeNull()
        ->and($boxScore['team']['fouls'])->toBe(0)
        ->and($boxScore['team']['technicalFouls'])->toBe(0)
        ->and($game->teamFoulsForPeriod(1))->toBe(['home' => 1, 'away' => 0]);
});

test('a disruptive foul cannot be recorded against the coach', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $player);

    statisticsRecord($member, $game)
        ->call('select', StatType::Foul->value)
        ->call('setPendingFoulKind', FoulKind::Disruptive->value)
        ->call('recordCoach');

    expect(GameEvent::query()->where('type', StatType::Foul)->count())->toBe(0);
});

test('a disruptive foul counts in the PF box score column not TF', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
        'home_away' => HomeAway::Home,
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $player);

    statisticsRecord($member, $game)
        ->call('select', StatType::Foul->value)
        ->call('setPendingFoulKind', FoulKind::Disruptive->value)
        ->call('record', $player->id);

    $boxScore = $game->fresh()->boxScore();
    $line = collect($boxScore['players'])->firstWhere('id', $player->id);

    expect($line['fouls'])->toBe(1)
        ->and($line['technicalFouls'])->toBe(0)
        ->and($boxScore['team']['fouls'])->toBe(1)
        ->and($boxScore['team']['technicalFouls'])->toBe(0)
        ->and($game->teamFoulsForPeriod(1))->toBe(['home' => 1, 'away' => 0]);
});

test('a queued special foul stores its kind', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
        'home_away' => HomeAway::Home,
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $player);

    statisticsRecord($member, $game)
        ->call('applyCollectedAction', fake()->uuid(), 'record', [
            'type' => StatType::Foul->value,
            'playerId' => $player->id,
            'forOpponent' => false,
            'foulKind' => FoulKind::Flagrant->value,
            'quarter' => 1,
            'clock' => '05:00',
            'courtX' => null,
            'courtY' => null,
        ])
        ->assertHasNoErrors();

    expect(GameEvent::query()->where('type', StatType::Foul)->first()?->foul_kind)->toBe(FoulKind::Flagrant);
});

test('a club member undoes the latest statistics entry', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $game = Game::factory()->create(['team_id' => $team->id]);
    $player = Player::factory()->create(['team_id' => $team->id]);
    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => null,
        'type' => StatType::QuarterStarted,
        'quarter' => 1,
        'clock' => '10:00',
    ]);
    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => $player->id,
        'type' => StatType::Steal,
    ]);

    giveStartingFive($game, $player);

    statisticsRecord($member, $game)
        ->call('undo');

    expect(GameEvent::query()->where('type', StatType::Steal)->count())->toBe(0)
        ->and(GameEvent::query()->where('type', StatType::QuarterStarted)->count())->toBe(1);
});

test('a club member cannot record a player from another team', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $game = Game::factory()->create(['team_id' => $team->id]);
    $otherPlayer = Player::factory()->create();

    giveStartingFive($game);

    statisticsRecord($member, $game, false)
        ->call('recordQuarterStart')
        ->set('pendingType', StatType::Steal->value)
        ->call('record', $otherPlayer->id)
        ->assertNotFound();

    expect(GameEvent::query()->where('type', StatType::Steal)->count())->toBe(0);
});

test('a club member only sees games of their club', function () {
    $club = Club::factory()->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $member = User::factory()->memberOf($club)->withTeams($team)->create();
    Game::factory()->create([
        'team_id' => $team->id,
        'opponent' => 'Sporting',
    ]);
    Game::factory()->create([
        'opponent' => 'Oponente Externo',
    ]);

    Livewire::actingAs($member)
        ->test(StatisticsIndex::class)
        ->assertSee('Sporting')
        ->assertDontSee('Oponente Externo');
});

test('the statistics index filters by season and team', function () {
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
        ->test(StatisticsIndex::class)
        ->assertSee('Team A Opponent')
        ->assertSee('Team B Opponent')
        ->assertDontSee('Past Opponent');

    Livewire::actingAs($administrator)
        ->test(StatisticsIndex::class)
        ->set('seasonId', (string) $pastSeason->id)
        ->assertSee('Past Opponent')
        ->assertDontSee('Team A Opponent');

    Livewire::actingAs($administrator)
        ->test(StatisticsIndex::class)
        ->set('seasonId', (string) $currentSeason->id)
        ->set('teamId', (string) $currentTeamA->id)
        ->assertSee('Team A Opponent')
        ->assertDontSee('Team B Opponent')
        ->assertDontSee('Past Opponent');
});

test('the statistics list shows who plays at home and who plays away', function () {
    $club = Club::factory()->create();
    $team = Team::factory()->create(['club_id' => $club->id, 'name' => 'North City']);
    $member = User::factory()->memberOf($club)->withTeams($team)->create();
    Game::factory()->create([
        'team_id' => $team->id,
        'opponent' => 'South Valley',
        'home_away' => HomeAway::Away,
    ]);

    Livewire::actingAs($member)
        ->test(StatisticsIndex::class)
        ->assertSeeInOrder(['At home', 'Away', 'South Valley', 'North City'])
        ->assertSee('font-bold text-zinc-900">North City', false)
        ->assertDontSee('font-bold text-zinc-900">South Valley', false);
});

test('the statistics index links a finished collected game to the box score', function () {
    $club = Club::factory()->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $member = User::factory()->memberOf($club)->withTeams($team)->create();
    $played = Game::factory()->create([
        'team_id' => $team->id,
        'opponent' => 'Played Opponent',
        'status' => GameStatus::Played,
    ]);
    GameEvent::factory()->create(['game_id' => $played->id]);
    $imported = Game::factory()->create([
        'team_id' => $team->id,
        'opponent' => 'Imported Opponent',
        'status' => GameStatus::Played,
    ]);
    $scheduled = Game::factory()->create([
        'team_id' => $team->id,
        'opponent' => 'Scheduled Opponent',
        'status' => GameStatus::Scheduled,
    ]);

    Livewire::actingAs($member)
        ->test(StatisticsIndex::class)
        ->assertSee(route('statistics.show', $played), false)
        ->assertSee(route('statistics.record', $played), false)
        ->assertSee(route('statistics.record', $imported), false)
        ->assertDontSee(route('statistics.show', $imported), false)
        ->assertSee(route('statistics.record', $scheduled), false)
        ->assertDontSee(route('statistics.show', $scheduled), false)
        ->assertSeeHtml('wire:key="game-'.$played->uuid.'"')
        ->assertSee('Played Opponent')
        ->assertSee('Scheduled Opponent');
});

test('a club member opens a game log by its uuid', function () {
    $club = Club::factory()->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $member = User::factory()->memberOf($club)->withTeams($team)->create();
    $game = Game::factory()->create([
        'team_id' => $team->id,
        'opponent' => 'Sporting',
    ]);

    Livewire::actingAs($member)
        ->test(StatisticsIndex::class)
        ->assertSee(route('statistics.record', $game), false)
        ->assertDontSee(route('statistics.record', $game->id), false);

    $this->actingAs($member)
        ->get(route('statistics.record', $game))
        ->assertOk()
        ->assertDontSee(__('Turn the screen sideways'));

    $this->actingAs($member)
        ->get(route('statistics.record', $game->id))
        ->assertNotFound();
});

test('a user from another club cannot open the statistics log', function () {
    $game = Game::factory()->create();
    $member = User::factory()->memberOf(Club::factory()->create())->create();

    $this->actingAs($member)->get(route('statistics.record', $game))->assertForbidden();
});

test('an administrator can collect statistics for any club', function () {
    $administrator = User::factory()->administrator()->create();
    $home = Game::factory()->create(['opponent' => 'Sporting']);
    $away = Game::factory()->create(['opponent' => 'Metro United']);

    Livewire::actingAs($administrator)
        ->test(StatisticsIndex::class)
        ->assertSee('Sporting')
        ->assertSee('Metro United');

    $this->actingAs($administrator)->get(route('statistics.record', $home))->assertOk();
    $this->actingAs($administrator)->get(route('statistics.record', $away))->assertOk();
});

test('a club can collect statistics for its own games', function () {
    $club = Club::factory()->create();
    $account = User::factory()->clubAccessFor($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $game = Game::factory()->create([
        'team_id' => $team->id,
        'opponent' => 'Sporting',
    ]);
    Game::factory()->create(['opponent' => 'Metro United']);

    Livewire::actingAs($account)
        ->test(StatisticsIndex::class)
        ->assertSee('Sporting')
        ->assertDontSee('Metro United');

    $this->actingAs($account)->get(route('statistics.record', $game))->assertOk();
});

test('a club cannot open another clubs statistics log', function () {
    $game = Game::factory()->create();
    $account = User::factory()->clubAccessFor(Club::factory()->create())->create();

    $this->actingAs($account)->get(route('statistics.record', $game))->assertForbidden();
});

test('a manager cannot open statistics collection', function () {
    $manager = User::factory()->manager()->create();

    $this->actingAs($manager)->get(route('statistics.index'))->assertForbidden();
});

test('an invalid clock is not stored', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $game = Game::factory()->create(['team_id' => $team->id]);
    $player = Player::factory()->create(['team_id' => $team->id]);

    giveStartingFive($game, $player);

    statisticsRecord($member, $game, false)
        ->call('recordQuarterStart')
        ->set('clock', '10:70')
        ->set('pendingType', StatType::Foul->value)
        ->call('record', $player->id)
        ->assertHasErrors('clock');

    expect(GameEvent::query()->where('type', StatType::Foul)->count())->toBe(0);
});

test('an administrator cannot delete a game that still has a statistics log', function () {
    $administrator = User::factory()->administrator()->create();
    $team = Team::factory()->create();
    $game = Game::factory()->create(['team_id' => $team->id]);
    $player = Player::factory()->create(['team_id' => $team->id]);
    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => $player->id,
    ]);

    Livewire::actingAs($administrator)
        ->test(GameIndex::class)
        ->call('confirmDelete', $game->uuid)
        ->call('deleteConfirmed');

    expect(Game::query()->find($game->id))->not->toBeNull();
});

test('a timeout is stored for the side that called it', function (HomeAway $homeAway, string $side, string $loggedName) {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id, 'name' => 'North City'])->id,
        'opponent' => 'South Valley',
        'home_away' => $homeAway,
    ]);

    giveStartingFive($game);

    statisticsRecord($member, $game)
        ->call('chooseTimeout')
        ->assertSet('choosingTimeout', true)
        ->assertSee('Record timeout')
        ->assertSee('Select which team called the timeout')
        ->call('recordTimeout', $side)
        ->assertSet('choosingTimeout', false)
        ->assertSee('timeout')
        ->assertSee('1/2')
        ->assertSee('0/2')
        ->assertDontSee('Timeout 1')
        ->assertSee($loggedName);

    $event = GameEvent::query()->where('type', StatType::Timeout)->first();

    expect($event)->not->toBeNull()
        ->and($event->type)->toBe(StatType::Timeout)
        ->and($event->home_away)->toBe(HomeAway::from($side))
        ->and($event->player_id)->toBeNull();
})->with([
    'home side while we are home' => [HomeAway::Home, 'home', 'North City'],
    'away side while we are home' => [HomeAway::Home, 'away', 'South Valley'],
    'away side while we are away' => [HomeAway::Away, 'away', 'North City'],
]);

test('the foul line shows timeouts used in the current half or overtime', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
        'home_away' => HomeAway::Home,
    ]);

    giveStartingFive($game);

    $screen = statisticsRecord($member, $game)
        ->assertSee('0/2')
        ->assertDontSee('0/3')
        ->call('recordTimeout', 'home')
        ->assertSee('1/2')
        ->set('quarter', 2)
        ->assertSee('1/2')
        ->set('quarter', 3)
        ->assertSee('0/3')
        ->assertDontSee('>1/2<', false)
        ->assertDontSee('1/3');

    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => null,
        'home_away' => HomeAway::Away,
        'type' => StatType::Timeout,
        'quarter' => 4,
        'clock' => '08:00',
    ]);

    $screen->set('quarter', 4)
        ->assertSee('1/3')
        ->assertSee('0/3');

    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => null,
        'home_away' => HomeAway::Home,
        'type' => StatType::Timeout,
        'quarter' => 5,
        'clock' => '03:00',
    ]);

    $screen->set('quarter', 5)
        ->assertSee('1/1')
        ->assertSee('0/1')
        ->assertDontSee('0/3')
        ->set('quarter', 6)
        ->assertSee('0/1')
        ->assertDontSee('1/1');
});

test('a timeout count turns red when the half allowance is used', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
        'home_away' => HomeAway::Home,
    ]);

    giveStartingFive($game);

    $screen = statisticsRecord($member, $game)
        ->call('recordTimeout', 'home');

    expect($screen->html())->toContain('timeoutAllowance()">1/2</span>')
        ->and($screen->html())->not->toContain('">2/2</span>');

    $screen->call('recordTimeout', 'home');

    expect($screen->html())->toContain('timeoutAllowance()">2/2</span>');

    $screen->set('quarter', 3)
        ->call('recordQuarterStart');

    expect($screen->html())->toContain('timeoutAllowance()">0/3</span>')
        ->and($screen->html())->not->toContain('">3/3</span>');

    foreach (range(1, 3) as $ignored) {
        $screen->call('recordTimeout', 'home');
    }

    expect($screen->html())->toContain('timeoutAllowance()">3/3</span>');
});

test('an unknown timeout side is not stored', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);

    giveStartingFive($game);

    statisticsRecord($member, $game)
        ->call('chooseTimeout')
        ->call('recordTimeout', 'bench')
        ->assertSet('choosingTimeout', true);

    expect(GameEvent::query()->where('type', StatType::Timeout)->count())->toBe(0);
});

test('a stat opens the player picker in a modal over the pad', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
        'opponent' => 'Metro United',
    ]);
    $game->forceFill(['opponent_photo' => 'opponents/crest.png'])->save();
    $player = Player::factory()->create([
        'team_id' => $game->team_id,
        'name' => 'Jordan Lake',
    ]);

    giveStartingFive($game, $player);

    statisticsRecord($member, $game)
        ->call('select', StatType::TwoPointMade->value)
        ->assertSet('choosingPlayer', true)
        ->assertSet('pendingType', StatType::TwoPointMade->value)
        ->assertSee('2P')
        ->assertSee('Jordan')
        ->assertSee('Metro United')
        ->assertSee('/storage/opponents/crest.png')
        ->call('select', StatType::DefensiveRebound->value)
        ->assertSet('choosingPlayer', true)
        ->assertSet('pendingType', StatType::DefensiveRebound->value)
        ->call('cancel')
        ->assertSet('choosingPlayer', false)
        ->assertSet('pendingType', null);
});

test('ending a quarter waits for the next one to start', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);

    giveStartingFive($game);

    statisticsRecord($member, $game, false)
        ->call('recordQuarterStart')
        ->set('clock', '00:00')
        ->call('askToEndQuarter')
        ->assertSet('confirmingQuarterEnd', true)
        ->assertSee('End 1st quarter')
        ->assertSee('Do you want to end Q1?')
        ->assertSee('You cannot undo this action.')
        ->call('endQuarter')
        ->assertSet('confirmingQuarterEnd', false)
        ->assertSet('quarter', 2)
        ->assertSet('clock', '00:00')
        ->assertSet('awaitingQuarterStart', true)
        ->assertSee('Start of Q2')
        ->assertSee('Start Q2')
        ->assertSee('--:--')
        ->call('select', StatType::TwoPointMade->value)
        ->assertSet('pendingType', null)
        ->call('startQuarter')
        ->assertSet('awaitingQuarterStart', false)
        ->assertSet('quarter', 2)
        ->assertSet('clock', '10:00')
        ->assertSee('end of Q1')
        ->assertSee('start of Q2');

    $events = GameEvent::query()->orderBy('id')->get();

    expect($events)->toHaveCount(3)
        ->and($events[0]->type)->toBe(StatType::QuarterStarted)
        ->and($events[1]->type)->toBe(StatType::QuarterEnded)
        ->and($events[1]->quarter)->toBe(1)
        ->and($events[1]->clock)->toBe('00:00')
        ->and($events[1]->player_id)->toBeNull()
        ->and($events[2]->type)->toBe(StatType::QuarterStarted)
        ->and($events[2]->quarter)->toBe(2)
        ->and($events[2]->clock)->toBe('10:00');
});

test('a refresh keeps the end of the first period', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);

    giveStartingFive($game);

    statisticsRecord($member, $game)
        ->set('clock', '00:00')
        ->call('endQuarter');

    statisticsRecord($member, $game, false)
        ->assertSet('quarter', 2)
        ->assertSet('clock', '00:00')
        ->assertSet('awaitingQuarterStart', true)
        ->assertSee('end of Q1')
        ->assertSee('Start of Q2');
});

test('a refresh resumes a period at the last recorded clock', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $game = Game::factory()->create(['team_id' => $team->id]);
    $player = Player::factory()->create(['team_id' => $team->id]);

    giveStartingFive($game, $player);

    statisticsRecord($member, $game, false)
        ->call('recordQuarterStart')
        ->set('clock', '07:30')
        ->call('select', StatType::TwoPointMade->value)
        ->call('record', $player->id);

    statisticsRecord($member, $game, false)
        ->assertSet('quarter', 1)
        ->assertSet('clock', '07:30')
        ->assertSet('awaitingQuarterStart', false)
        ->assertSee('start of Q1');
});

test('starting the clock records the start of the period once', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);

    giveStartingFive($game);

    $screen = statisticsRecord($member, $game, false)
        ->call('recordQuarterStart')
        ->assertSee('start of Q1');

    expect(GameEvent::query()->count())->toBe(1);

    $screen->call('recordQuarterStart');

    expect(GameEvent::query()->count())->toBe(1);
});

test('undo does nothing when the last action ended a period', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $game = Game::factory()->create(['team_id' => $team->id]);
    $player = Player::factory()->create(['team_id' => $team->id]);

    giveStartingFive($game, $player);

    statisticsRecord($member, $game)
        ->call('select', StatType::Steal->value)
        ->call('record', $player->id)
        ->set('clock', '00:00')
        ->call('endQuarter')
        ->assertSet('canUndo', false)
        ->call('undo');

    expect(GameEvent::query()->where('type', StatType::Steal)->count())->toBe(1)
        ->and(GameEvent::query()->where('type', StatType::QuarterEnded)->count())->toBe(1);

    statisticsRecord($member, $game, false)
        ->assertSet('quarter', 2)
        ->assertSet('awaitingQuarterStart', true)
        ->assertSee('end of Q1')
        ->assertSee('stl');
});

test('a recorded action stores the clock from the pad and undo removes it from the log', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id, 'name' => 'Alex North']);

    giveStartingFive($game, $player);

    $screen = statisticsRecord($member, $game)
        ->call('select', StatType::Steal->value)
        ->call('record', $player->id, '04:15')
        ->assertSee('04:15')
        ->assertSee('Alex North')
        ->assertSee('stl');

    expect(GameEvent::query()->where('type', StatType::Steal)->value('clock'))->toBe('04:15');

    $screen->call('undo');

    expect(GameEvent::query()->where('type', StatType::Steal)->count())->toBe(0)
        ->and(collect($screen->instance()->lines)->pluck('label'))->not->toContain('stl');
});

test('the same collected action is stored once with the pad clock', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $player);

    $id = fake()->uuid();
    $payload = [
        'type' => StatType::Steal->value,
        'playerId' => $player->id,
        'forOpponent' => false,
        'quarter' => 1,
        'clock' => '04:15',
        'courtX' => null,
        'courtY' => null,
    ];

    statisticsRecord($member, $game)
        ->call('applyCollectedAction', $id, 'record', $payload)
        ->call('applyCollectedAction', $id, 'record', $payload);

    expect(GameEvent::query()->where('type', StatType::Steal)->count())->toBe(1)
        ->and(GameEvent::query()->where('type', StatType::Steal)->value('clock'))->toBe('04:15')
        ->and(CollectCommand::query()->count())->toBe(1);
});

test('the same undo command removes only one event', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $player);

    $payload = [
        'type' => StatType::Steal->value,
        'playerId' => $player->id,
        'forOpponent' => false,
        'quarter' => 1,
        'clock' => '04:15',
        'courtX' => null,
        'courtY' => null,
    ];
    $undoId = fake()->uuid();

    statisticsRecord($member, $game)
        ->call('applyCollectedAction', fake()->uuid(), 'record', $payload)
        ->call('applyCollectedAction', $undoId, 'undo', [])
        ->call('applyCollectedAction', $undoId, 'undo', []);

    expect(GameEvent::query()->where('type', StatType::Steal)->count())->toBe(0)
        ->and(GameEvent::query()->where('type', StatType::QuarterStarted)->count())->toBe(1);
});

test('a queued record followed by undo leaves the game without that event', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $player);

    statisticsRecord($member, $game)
        ->call('applyCollectedAction', fake()->uuid(), 'record', [
            'type' => StatType::Steal->value,
            'playerId' => $player->id,
            'forOpponent' => false,
            'quarter' => 1,
            'clock' => '03:10',
            'courtX' => null,
            'courtY' => null,
        ])
        ->call('applyCollectedAction', fake()->uuid(), 'undo', []);

    expect(GameEvent::query()->where('type', StatType::Steal)->count())->toBe(0)
        ->and(GameEvent::query()->where('type', StatType::QuarterStarted)->count())->toBe(1);
});

test('a batch of collected actions applies each command once and returns sync state', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $player);

    $stealId = fake()->uuid();
    $assistId = fake()->uuid();
    $batch = [
        [
            'id' => $stealId,
            'operation' => 'record',
            'payload' => [
                'type' => StatType::Steal->value,
                'playerId' => $player->id,
                'forOpponent' => false,
                'quarter' => 1,
                'clock' => '05:00',
                'courtX' => null,
                'courtY' => null,
            ],
        ],
        [
            'id' => $assistId,
            'operation' => 'record',
            'payload' => [
                'type' => StatType::Assist->value,
                'playerId' => $player->id,
                'forOpponent' => false,
                'quarter' => 1,
                'clock' => '04:50',
                'courtX' => null,
                'courtY' => null,
            ],
        ],
    ];

    $screen = statisticsRecord($member, $game);
    $result = $screen->instance()->applyCollectedActions($batch, app(ApplyCollectedAction::class));

    expect($result['applied'])->toBe([$stealId, $assistId])
        ->and($result['error'])->toBeNull()
        ->and($result['failedId'])->toBeNull()
        ->and($result['baseline'])->toHaveKeys(['teamPoints', 'canUndo', 'court', 'periodOpen', 'lines'])
        ->and($result['baseline']['canUndo'])->toBeTrue()
        ->and(collect($result['baseline']['lines'])->pluck('actionKey')->all())->toContain(StatType::Steal->value, StatType::Assist->value)
        ->and(GameEvent::query()->where('type', StatType::Steal)->count())->toBe(1)
        ->and(GameEvent::query()->where('type', StatType::Assist)->count())->toBe(1)
        ->and(CollectCommand::query()->count())->toBe(2);

    $again = $screen->instance()->applyCollectedActions($batch, app(ApplyCollectedAction::class));

    expect($again['applied'])->toBe([$stealId, $assistId])
        ->and($again['error'])->toBeNull()
        ->and(GameEvent::query()->where('type', StatType::Steal)->count())->toBe(1)
        ->and(GameEvent::query()->where('type', StatType::Assist)->count())->toBe(1)
        ->and(CollectCommand::query()->count())->toBe(2);

    $stealLine = collect($result['baseline']['lines'])->firstWhere('actionKey', StatType::Steal->value);

    expect($stealLine)->toMatchArray([
        'type' => StatType::Steal->value,
        'playerId' => $player->id,
        'forOpponent' => false,
        'foulKind' => null,
        'editable' => true,
    ]);
});

test('a collected batch stops on validation failure and returns the error', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $player);

    $invalidId = fake()->uuid();
    $validId = fake()->uuid();

    $result = statisticsRecord($member, $game)->instance()->applyCollectedActions([
        [
            'id' => $invalidId,
            'operation' => 'record',
            'payload' => [
                'type' => StatType::Steal->value,
                'playerId' => $player->id,
                'forOpponent' => false,
                'quarter' => 1,
                'clock' => 'bad-clock',
                'courtX' => null,
                'courtY' => null,
            ],
        ],
        [
            'id' => $validId,
            'operation' => 'record',
            'payload' => [
                'type' => StatType::Steal->value,
                'playerId' => $player->id,
                'forOpponent' => false,
                'quarter' => 1,
                'clock' => '05:00',
                'courtX' => null,
                'courtY' => null,
            ],
        ],
    ], app(ApplyCollectedAction::class));

    expect($result['applied'])->toBe([])
        ->and($result['failedId'])->toBe($invalidId)
        ->and($result['error'])->not->toBeNull()
        ->and(GameEvent::query()->where('type', StatType::Steal)->count())->toBe(0)
        ->and(CollectCommand::query()->count())->toBe(0);
});

test('a collected batch keeps earlier applied actions when a later item fails', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $player);

    $firstId = fake()->uuid();
    $failedId = fake()->uuid();

    $result = statisticsRecord($member, $game)->instance()->applyCollectedActions([
        [
            'id' => $firstId,
            'operation' => 'record',
            'payload' => [
                'type' => StatType::Steal->value,
                'playerId' => $player->id,
                'forOpponent' => false,
                'quarter' => 1,
                'clock' => '05:00',
                'courtX' => null,
                'courtY' => null,
            ],
        ],
        [
            'id' => $failedId,
            'operation' => 'record',
            'payload' => [
                'type' => StatType::Steal->value,
                'playerId' => $player->id,
                'forOpponent' => false,
                'quarter' => 1,
                'clock' => 'bad-clock',
                'courtX' => null,
                'courtY' => null,
            ],
        ],
    ], app(ApplyCollectedAction::class));

    expect($result['applied'])->toBe([$firstId])
        ->and($result['failedId'])->toBe($failedId)
        ->and($result['error'])->not->toBeNull()
        ->and(GameEvent::query()->where('type', StatType::Steal)->count())->toBe(1)
        ->and(CollectCommand::query()->count())->toBe(1);
});

test('a collected edit updates the event and returns hydrate fields on log lines', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
        'home_away' => HomeAway::Home,
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $player);

    $replacement = $game->players()->whereKeyNot($player->id)->firstOrFail();
    $screen = statisticsRecord($member, $game);
    $recordId = fake()->uuid();
    $screen->instance()->applyCollectedAction($recordId, 'record', [
        'type' => StatType::Steal->value,
        'playerId' => $player->id,
        'forOpponent' => false,
        'quarter' => 1,
        'clock' => '05:00',
        'courtX' => null,
        'courtY' => null,
    ], app(ApplyCollectedAction::class));

    $event = GameEvent::query()->where('type', StatType::Steal)->firstOrFail();
    $editId = fake()->uuid();
    $result = $screen->instance()->applyCollectedAction($editId, 'edit', [
        'eventId' => $event->id,
        'type' => StatType::Assist->value,
        'playerId' => $replacement->id,
        'outgoingPlayerId' => null,
        'forOpponent' => false,
        'foulKind' => null,
        'clock' => '04:30',
    ], app(ApplyCollectedAction::class));

    expect($result['applied'])->toBe([$editId])
        ->and($event->refresh()->type)->toBe(StatType::Assist)
        ->and($event->player_id)->toBe($replacement->id)
        ->and($event->clock)->toBe('04:30');

    $line = collect($result['baseline']['lines'])->firstWhere('id', $event->id);

    expect($line)->toMatchArray([
        'type' => StatType::Assist->value,
        'actionKey' => StatType::Assist->value,
        'playerId' => $replacement->id,
        'forOpponent' => false,
        'foulKind' => null,
        'clock' => '04:30',
        'editable' => true,
    ]);
});

test('a collected two-point record with three-point coordinates is rejected', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
        'mark_shot_location' => true,
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $player);

    $result = statisticsRecord($member, $game)->instance()->applyCollectedActions([
        [
            'id' => fake()->uuid(),
            'operation' => 'record',
            'payload' => [
                'type' => StatType::TwoPointMade->value,
                'playerId' => $player->id,
                'forOpponent' => false,
                'quarter' => 1,
                'clock' => '04:15',
                'courtX' => 500,
                'courtY' => 520,
            ],
        ],
    ], app(ApplyCollectedAction::class));

    expect($result['applied'])->toBe([])
        ->and($result['error'])->not->toBeNull()
        ->and(GameEvent::query()->where('type', StatType::TwoPointMade)->count())->toBe(0);
});

test('editing a three-point shot to two points clears incompatible court coordinates', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
        'mark_shot_location' => true,
        'home_away' => HomeAway::Home,
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $player);

    $screen = statisticsRecord($member, $game);
    $screen->instance()->applyCollectedAction(fake()->uuid(), 'record', [
        'type' => StatType::ThreePointMade->value,
        'playerId' => $player->id,
        'forOpponent' => false,
        'quarter' => 1,
        'clock' => '04:15',
        'courtX' => 500,
        'courtY' => 520,
    ], app(ApplyCollectedAction::class));

    $event = GameEvent::query()->where('type', StatType::ThreePointMade)->firstOrFail();

    expect($event->court_x)->toBe(500)
        ->and($event->court_y)->toBe(520);

    $result = $screen->instance()->applyCollectedAction(fake()->uuid(), 'edit', [
        'eventId' => $event->id,
        'type' => StatType::TwoPointMade->value,
        'playerId' => $player->id,
        'outgoingPlayerId' => null,
        'forOpponent' => false,
        'foulKind' => null,
        'clock' => '04:15',
    ], app(ApplyCollectedAction::class));

    expect($result['applied'])->toHaveCount(1)
        ->and($event->refresh()->type)->toBe(StatType::TwoPointMade)
        ->and($event->court_x)->toBeNull()
        ->and($event->court_y)->toBeNull();
});

test('a collected edit can replace the shot location when changing three points to two', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
        'mark_shot_location' => true,
        'home_away' => HomeAway::Home,
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $player);

    $screen = statisticsRecord($member, $game);
    $screen->instance()->applyCollectedAction(fake()->uuid(), 'record', [
        'type' => StatType::ThreePointMade->value,
        'playerId' => $player->id,
        'forOpponent' => false,
        'quarter' => 1,
        'clock' => '04:15',
        'courtX' => 500,
        'courtY' => 520,
    ], app(ApplyCollectedAction::class));

    $event = GameEvent::query()->where('type', StatType::ThreePointMade)->firstOrFail();
    $result = $screen->instance()->applyCollectedAction(fake()->uuid(), 'edit', [
        'eventId' => $event->id,
        'type' => StatType::TwoPointMade->value,
        'playerId' => $player->id,
        'outgoingPlayerId' => null,
        'forOpponent' => false,
        'foulKind' => null,
        'clock' => '04:15',
        'courtX' => 500,
        'courtY' => 300,
    ], app(ApplyCollectedAction::class));

    expect($result['applied'])->toHaveCount(1)
        ->and($event->refresh()->type)->toBe(StatType::TwoPointMade)
        ->and($event->court_x)->toBe(500)
        ->and($event->court_y)->toBe(300)
        ->and(collect($result['baseline']['lines'])->firstWhere('id', $event->id))->toMatchArray([
            'courtX' => 500,
            'courtY' => 300,
        ]);
});

test('a logged action can only be given to a player who was on the court at that moment', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);
    $starter = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $starter);

    $teammate = $game->players()->whereKeyNot($starter->id)->firstOrFail();
    $bench = Player::factory()->create(['team_id' => $game->team_id]);
    $game->players()->attach($bench->id, ['starter' => false]);
    $court = $game->players()->wherePivot('starter', true)->pluck('players.id')->map(fn (mixed $id): int => (int) $id);

    $screen = statisticsRecord($member, $game);
    $action = app(ApplyCollectedAction::class);
    $screen->instance()->applyCollectedActions([
        ['id' => fake()->uuid(), 'operation' => 'record', 'payload' => [
            'type' => StatType::Steal->value,
            'playerId' => $teammate->id,
            'forOpponent' => false,
            'quarter' => 1,
            'clock' => '08:00',
        ]],
        ['id' => fake()->uuid(), 'operation' => 'substitution', 'payload' => [
            'playerIds' => $court->reject(fn (int $id): bool => $id === $starter->id)->push($bench->id)->values()->all(),
            'quarter' => 1,
            'clock' => '07:00',
        ]],
    ], $action);

    $steal = GameEvent::query()->where('type', StatType::Steal)->firstOrFail();
    $edit = fn (int $playerId): array => $screen->instance()->applyCollectedActions([
        ['id' => fake()->uuid(), 'operation' => 'edit', 'payload' => [
            'eventId' => $steal->id,
            'type' => StatType::Steal->value,
            'playerId' => $playerId,
            'forOpponent' => false,
            'clock' => '08:00',
        ]],
    ], $action);

    expect($edit($bench->id)['error'])->toBe(__('The player was not on the court at that moment.'))
        ->and($steal->refresh()->player_id)->toBe($teammate->id)
        ->and($edit($starter->id)['applied'])->toHaveCount(1)
        ->and($steal->refresh()->player_id)->toBe($starter->id);
});

test('a cancelled action that arrives late is not stored', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $player);

    $recordId = fake()->uuid();

    statisticsRecord($member, $game)
        ->call('applyCollectedAction', fake()->uuid(), 'cancel', ['targetId' => $recordId])
        ->call('applyCollectedAction', $recordId, 'record', [
            'type' => StatType::Steal->value,
            'playerId' => $player->id,
            'forOpponent' => false,
            'quarter' => 1,
            'clock' => '04:15',
            'courtX' => null,
            'courtY' => null,
        ]);

    expect(GameEvent::query()->where('type', StatType::Steal)->exists())->toBeFalse();
});

test('cancelling an applied action removes only the events it created', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $player);

    $stealId = fake()->uuid();
    $payload = [
        'playerId' => $player->id,
        'forOpponent' => false,
        'quarter' => 1,
        'clock' => '04:15',
        'courtX' => null,
        'courtY' => null,
    ];

    statisticsRecord($member, $game)
        ->call('applyCollectedAction', $stealId, 'record', ['type' => StatType::Steal->value] + $payload)
        ->call('applyCollectedAction', fake()->uuid(), 'period', [
            'type' => StatType::QuarterEnded->value,
            'quarter' => 1,
            'clock' => '00:00',
        ])
        ->call('applyCollectedAction', fake()->uuid(), 'cancel', ['targetId' => $stealId]);

    expect(GameEvent::query()->where('type', StatType::Steal)->exists())->toBeFalse()
        ->and(GameEvent::query()->where('type', StatType::QuarterEnded)->count())->toBe(1);
});

test('cancelling an applied edit restores the previous values', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
        'mark_shot_location' => true,
        'home_away' => HomeAway::Home,
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $player);

    $screen = statisticsRecord($member, $game);
    $screen->instance()->applyCollectedAction(fake()->uuid(), 'record', [
        'type' => StatType::ThreePointMade->value,
        'playerId' => $player->id,
        'forOpponent' => false,
        'quarter' => 1,
        'clock' => '04:15',
        'courtX' => 500,
        'courtY' => 520,
    ], app(ApplyCollectedAction::class));

    $event = GameEvent::query()->where('type', StatType::ThreePointMade)->firstOrFail();
    $edit = [
        'eventId' => $event->id,
        'playerId' => $player->id,
        'outgoingPlayerId' => null,
        'forOpponent' => false,
        'foulKind' => null,
        'clock' => '04:15',
    ];
    $editId = fake()->uuid();

    $screen->instance()->applyCollectedAction($editId, 'edit', [
        'type' => StatType::TwoPointMade->value,
        'courtX' => 500,
        'courtY' => 300,
    ] + $edit, app(ApplyCollectedAction::class));

    expect($event->refresh()->type)->toBe(StatType::TwoPointMade);

    $screen->instance()->applyCollectedAction(fake()->uuid(), 'cancel', [
        'targetId' => $editId,
        'revert' => [
            'type' => StatType::ThreePointMade->value,
            'courtX' => 500,
            'courtY' => 520,
        ] + $edit,
    ], app(ApplyCollectedAction::class));

    expect($event->refresh()->type)->toBe(StatType::ThreePointMade)
        ->and($event->court_x)->toBe(500)
        ->and($event->court_y)->toBe(520);
});

test('undo removes only an action from the open period', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $game = Game::factory()->create(['team_id' => $team->id]);
    $player = Player::factory()->create(['team_id' => $team->id]);

    giveStartingFive($game, $player);

    $screen = statisticsRecord($member, $game)
        ->call('recordQuarterStart')
        ->call('select', StatType::Steal->value)
        ->call('record', $player->id)
        ->set('clock', '00:00')
        ->call('endQuarter')
        ->call('startQuarter')
        ->assertSet('canUndo', false)
        ->call('undo');

    expect(GameEvent::query()->where('type', StatType::Steal)->count())->toBe(1);

    $screen->call('select', StatType::Assist->value)
        ->call('record', $player->id)
        ->assertSet('canUndo', true)
        ->call('undo');

    expect(GameEvent::query()->where('type', StatType::Steal)->count())->toBe(1)
        ->and(GameEvent::query()->where('type', StatType::Assist)->count())->toBe(0);
});

test('the quarter clock does not start again while it is already running', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);

    giveStartingFive($game);

    statisticsRecord($member, $game, false)
        ->set('clock', '08:00')
        ->call('recordQuarterStart')
        ->assertSet('awaitingQuarterStart', false)
        ->assertSet('quarter', 1)
        ->assertSet('clock', '08:00');
});

test('a decided fourth quarter can continue as the next period', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $player);

    $screen = statisticsRecord($member, $game)
        ->call('select', StatType::TwoPointMade->value)
        ->call('record', $player->id)
        ->set('quarter', 4)
        ->set('clock', '00:00')
        ->call('endQuarter')
        ->assertSet('quarter', 5)
        ->assertSet('clock', '00:00')
        ->assertSet('quarterEnded', false)
        ->assertSet('awaitingQuarterStart', true)
        ->assertSee('Start of OT1')
        ->assertSee('Start OT1')
        ->assertSee('end of Q4')
        ->assertDontSee('Start of Q5');

    statisticsRecord($member, $game, false)
        ->assertSet('quarter', 5)
        ->assertSet('awaitingQuarterStart', true)
        ->assertSee('Start of OT1');

    $screen->call('startQuarter')
        ->assertSet('quarter', 5)
        ->assertSet('clock', '10:00')
        ->assertSee('OT1')
        ->assertSee('start of OT1')
        ->set('clock', '00:00')
        ->call('endQuarter')
        ->assertSet('quarter', 6)
        ->assertSee('Start of OT2')
        ->assertDontSee('Start of Q6')
        ->call('startQuarter')
        ->set('clock', '00:00')
        ->call('endQuarter')
        ->assertSet('quarter', 7)
        ->assertSee('Start of OT3')
        ->assertDontSee('Start of Q7');

    $started = GameEvent::query()->where('type', StatType::QuarterStarted)->orderBy('id')->get();

    expect($started)->toHaveCount(3)
        ->and($started[0]->quarter)->toBe(1)
        ->and($started[1]->quarter)->toBe(5)
        ->and($started[1]->clock)->toBe('10:00')
        ->and($started[2]->quarter)->toBe(6)
        ->and($started[2]->clock)->toBe('10:00');
});

test('a tied fourth quarter continues into a five-minute overtime', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);

    giveStartingFive($game);

    statisticsRecord($member, $game)
        ->set('quarter', 4)
        ->set('clock', '00:00')
        ->call('endQuarter')
        ->assertSet('quarter', 5)
        ->assertSet('clock', '00:00')
        ->assertSet('quarterEnded', false)
        ->assertSet('awaitingQuarterStart', true)
        ->assertSee('Start of OT1')
        ->assertSee('Start OT1')
        ->assertSee('end of Q4');

    statisticsRecord($member, $game, false)
        ->assertSet('quarter', 5)
        ->assertSet('awaitingQuarterStart', true)
        ->call('startQuarter')
        ->assertSet('quarter', 5)
        ->assertSet('clock', '05:00')
        ->assertSet('awaitingQuarterStart', false)
        ->assertSee('OT1')
        ->assertSee('start of OT1');

    $started = GameEvent::query()->where('type', StatType::QuarterStarted)->orderBy('id')->get();

    expect($started)->toHaveCount(2)
        ->and($started[0]->quarter)->toBe(1)
        ->and($started[1]->quarter)->toBe(5)
        ->and($started[1]->clock)->toBe('05:00');
});

test('a tied overtime continues into the next overtime', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);

    giveStartingFive($game);

    statisticsRecord($member, $game)
        ->set('quarter', 4)
        ->set('clock', '00:00')
        ->call('endQuarter')
        ->call('startQuarter')
        ->set('clock', '00:00')
        ->call('askToEndQuarter')
        ->assertSee('End OT1')
        ->assertSee('Do you want to end OT1?')
        ->call('endQuarter')
        ->assertSet('quarter', 6)
        ->assertSet('awaitingQuarterStart', true)
        ->assertSee('Start of OT2')
        ->assertDontSee('Start of Q6')
        ->call('startQuarter')
        ->assertSet('quarter', 6)
        ->assertSet('clock', '05:00')
        ->assertSee('OT2')
        ->assertSee('start of OT2')
        ->set('clock', '00:00')
        ->call('endQuarter')
        ->assertSet('quarter', 7)
        ->assertSee('Start of OT3')
        ->assertDontSee('Start of Q7')
        ->call('startQuarter')
        ->assertSet('quarter', 7)
        ->assertSet('clock', '05:00')
        ->assertSee('OT3')
        ->assertSee('start of OT3');

    $started = GameEvent::query()->where('type', StatType::QuarterStarted)->orderBy('id')->get();

    expect($started)->toHaveCount(4)
        ->and($started[0]->quarter)->toBe(1)
        ->and($started[1]->quarter)->toBe(5)
        ->and($started[1]->clock)->toBe('05:00')
        ->and($started[2]->quarter)->toBe(6)
        ->and($started[2]->clock)->toBe('05:00')
        ->and($started[3]->quarter)->toBe(7)
        ->and($started[3]->clock)->toBe('05:00');
});

test('a lead after overtime continues as the next period', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $player);

    statisticsRecord($member, $game)
        ->set('quarter', 4)
        ->set('clock', '00:00')
        ->call('endQuarter')
        ->call('startQuarter')
        ->call('select', StatType::TwoPointMade->value)
        ->call('record', $player->id)
        ->set('clock', '00:00')
        ->call('endQuarter')
        ->assertSet('quarter', 6)
        ->assertSet('awaitingQuarterStart', true)
        ->assertSee('end of OT1')
        ->assertSee('Start of OT2')
        ->assertDontSee('Start of Q6')
        ->call('startQuarter')
        ->assertSet('quarter', 6)
        ->assertSet('clock', '10:00')
        ->assertSee('start of OT2');

    $started = GameEvent::query()->where('type', StatType::QuarterStarted)->orderBy('id')->get();

    expect($started)->toHaveCount(3)
        ->and($started[0]->quarter)->toBe(1)
        ->and($started[1]->quarter)->toBe(5)
        ->and($started[1]->clock)->toBe('05:00')
        ->and($started[2]->quarter)->toBe(6)
        ->and($started[2]->clock)->toBe('10:00');
});

test('a quarter cannot be ended while time remains', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);

    giveStartingFive($game);

    statisticsRecord($member, $game)
        ->set('clock', '05:00')
        ->call('askToEndQuarter')
        ->assertSet('confirmingQuarterEnd', false)
        ->call('endQuarter')
        ->assertSet('quarter', 1)
        ->assertSet('clock', '05:00');
});

test('the collection screen includes the game clock controls', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);

    giveStartingFive($game);

    statisticsRecord($member, $game)
        ->assertSee('10:00')
        ->assertSee('Scoring')
        ->assertSee('Roster')
        ->assertSee('Game log')
        ->assertSee('Start')
        ->assertSee('Pause')
        ->assertSee('-5 sec')
        ->assertSee('+5 sec')
        ->assertSee('Timeout');
});

test('a stopped clock can be changed without changing the period', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);

    giveStartingFive($game);

    statisticsRecord($member, $game)
        ->assertSee('! $store.gameClock.running && ! $store.collectQueue.awaitingStart()', false)
        ->set('editingClock', true)
        ->assertDontSee('wire:model="quarter"', false)
        ->assertDontSee('wire:model.live="quarter"', false)
        ->set('clock', '07:30')
        ->assertSet('clock', '07:30')
        ->assertSet('quarter', 1);
});

test('an administrator cannot delete a player that still has a statistics log', function () {
    $administrator = User::factory()->administrator()->create();
    $team = Team::factory()->create();
    $game = Game::factory()->create(['team_id' => $team->id]);
    $player = Player::factory()->create(['team_id' => $team->id]);
    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => $player->id,
    ]);

    Livewire::actingAs($administrator)
        ->test(PlayerIndex::class)
        ->call('confirmDelete', $player->uuid)
        ->call('deleteConfirmed');

    expect(Player::query()->find($player->id))->not->toBeNull();
});

test('the collector asks for the players and the starting five before the pad', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);
    $players = Player::factory()->count(6)->create(['team_id' => $game->team_id]);

    $screen = statisticsRecord($member, $game)
        ->assertSee('Players in this game')
        ->assertDontSee('>Foul<', false);

    foreach ($players->take(4) as $player) {
        $screen->call('toggleSquadPlayer', $player->id);
    }

    $screen->call('saveSquad')
        ->assertSee('Players in this game');

    expect($game->players()->count())->toBe(0);

    foreach ($players->slice(4) as $player) {
        $screen->call('toggleSquadPlayer', $player->id);
    }

    $screen->call('saveSquad')
        ->assertSee('Starting five')
        ->assertSee('Confirm')
        ->assertDontSee('Confirm lineup');

    foreach ($players->take(4) as $player) {
        $screen->call('toggleStarter', $player->id);
    }

    $screen->call('saveStarters')
        ->assertSee('Starting five');

    expect($game->players()->wherePivot('starter', true)->count())->toBe(0);

    $screen->call('toggleStarter', $players[4]->id)
        ->call('saveStarters')
        ->assertSee('>Foul<', false)
        ->assertDontSee('Change lineup');
});

test('lineup before the tip-off opens the squad already chosen for the game', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);

    giveStartingFive($game);

    statisticsRecord($member, $game, false)
        ->assertSee('Lineup')
        ->assertDontSee('Change lineup')
        ->call('select', StatType::Substitution->value)
        ->assertSet('editingSquad', true)
        ->assertSet('substituting', false)
        ->assertSee('Players in this game');

    expect(GameEvent::query()->where('type', StatType::Substitution)->count())->toBe(0);
});

test('a substitution can be recorded between periods before the next tip-off', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $game = Game::factory()->create([
        'team_id' => $team->id,
        'quarter_minutes' => 10,
    ]);
    $starters = Player::factory()->count(5)->create(['team_id' => $team->id]);
    $bench = Player::factory()->create(['team_id' => $team->id, 'name' => 'Halftime Bench']);

    foreach ($starters as $starter) {
        $game->players()->attach($starter->id, ['starter' => true]);
    }
    $game->players()->attach($bench->id, ['starter' => false]);

    $screen = statisticsRecord($member, $game, false)
        ->call('recordQuarterStart')
        ->set('clock', '00:00')
        ->call('endQuarter')
        ->assertSet('awaitingQuarterStart', true)
        ->assertSet('quarter', 2);

    $nextCourt = [
        $starters[1]->id,
        $starters[2]->id,
        $starters[3]->id,
        $starters[4]->id,
        $bench->id,
    ];

    $screen->call('select', StatType::Substitution->value)
        ->assertSet('substituting', true)
        ->set('courtSelection', $nextCourt)
        ->call('confirmSubstitutions')
        ->assertHasNoErrors()
        ->assertSet('substituting', false)
        ->assertSet('awaitingQuarterStart', true)
        ->assertSet('clock', '10:00');

    $event = GameEvent::query()->where('type', StatType::Substitution)->first();

    expect($event)->not->toBeNull()
        ->and($event->quarter)->toBe(2)
        ->and($event->clock)->toBe('10:00')
        ->and($event->player_id)->toBe($bench->id)
        ->and($event->outgoing_player_id)->toBe($starters[0]->id);
});

test('undo removes a substitution recorded between periods', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $game = Game::factory()->create([
        'team_id' => $team->id,
        'quarter_minutes' => 10,
    ]);
    $starters = Player::factory()->count(5)->create(['team_id' => $team->id]);
    $bench = Player::factory()->create(['team_id' => $team->id, 'name' => 'Undo Bench']);

    foreach ($starters as $starter) {
        $game->players()->attach($starter->id, ['starter' => true]);
    }
    $game->players()->attach($bench->id, ['starter' => false]);

    $nextCourt = [
        $starters[1]->id,
        $starters[2]->id,
        $starters[3]->id,
        $starters[4]->id,
        $bench->id,
    ];

    $screen = statisticsRecord($member, $game, false)
        ->call('recordQuarterStart')
        ->set('clock', '00:00')
        ->call('endQuarter')
        ->call('select', StatType::Substitution->value)
        ->set('courtSelection', $nextCourt)
        ->call('confirmSubstitutions')
        ->assertSet('canUndo', true)
        ->assertSet('awaitingQuarterStart', true);

    expect(GameEvent::query()->where('type', StatType::Substitution)->count())->toBe(1);

    $screen->call('undo')
        ->assertSet('canUndo', false)
        ->assertSet('awaitingQuarterStart', true);

    expect(GameEvent::query()->where('type', StatType::Substitution)->count())->toBe(0)
        ->and(GameEvent::query()->where('type', StatType::QuarterEnded)->count())->toBe(1)
        ->and($game->fresh()->lineupState('10:00', false)['court'])->toEqualCanonicalizing(
            $starters->pluck('id')->all(),
        );
});

test('back from the players in this game returns to the dashboard', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);
    Player::factory()->count(6)->create(['team_id' => $game->team_id]);

    statisticsRecord($member, $game)
        ->assertSee('Players in this game')
        ->call('leaveSquadSelection')
        ->assertRedirect(route('dashboard'));
});

test('the statistics log is ordered by period and countdown clock', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $game = Game::factory()->create(['team_id' => $team->id]);
    $player = Player::factory()->create(['team_id' => $team->id, 'name' => 'Clock Order']);

    giveStartingFive($game, $player);

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
        'home_away' => $game->home_away,
        'type' => StatType::Steal,
        'quarter' => 1,
        'clock' => '02:00',
    ]);
    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => $player->id,
        'home_away' => $game->home_away,
        'type' => StatType::Assist,
        'quarter' => 1,
        'clock' => '08:00',
    ]);

    $clocks = collect(statisticsRecord($member, $game)->instance()->lines)
        ->where(fn (array $line): bool => $line['editable'])
        ->pluck('clock')
        ->values()
        ->all();

    // The log is reversed for display, so later game clocks appear first.
    expect($clocks)->toBe(['02:00', '08:00']);
});

test('the statistics log shows the running home-away score under each clock', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $game = Game::factory()->create([
        'team_id' => $team->id,
        'home_away' => HomeAway::Home,
    ]);
    $player = Player::factory()->create(['team_id' => $team->id, 'name' => 'Score Line']);

    giveStartingFive($game, $player);

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
        'clock' => '08:00',
    ]);
    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => $player->id,
        'home_away' => HomeAway::Home,
        'type' => StatType::Steal,
        'quarter' => 1,
        'clock' => '07:00',
    ]);

    $screen = statisticsRecord($member, $game);
    $scores = collect($screen->instance()->lines)
        ->pluck('score', 'clock')
        ->all();

    expect($scores['10:00'])->toBe('0-0')
        ->and($scores['09:00'])->toBe('2-0')
        ->and($scores['08:00'])->toBe('2-3')
        ->and($scores['07:00'])->toBe('2-3');

    $screen->assertSee('2-3');
});

test('the statistics log exposes client filters for player and action', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $game = Game::factory()->create([
        'team_id' => $team->id,
        'opponent' => 'Filter United',
    ]);
    $player = Player::factory()->create([
        'team_id' => $team->id,
        'name' => 'Filter Player',
        'number' => 7,
    ]);

    giveStartingFive($game, $player);

    $screen = statisticsRecord($member, $game);

    $playerFilters = collect($screen->instance()->logPlayerFilters);
    $actionFilters = collect($screen->instance()->logActionFilters);
    $twoPoint = $actionFilters->firstWhere('id', StatType::TwoPointMade->value);
    $labels = $actionFilters->pluck('label')->all();

    expect($playerFilters->firstWhere('value', 'player:'.$player->id)['label'] ?? null)->toBe('#7 Filter Player')
        ->and($playerFilters->firstWhere('value', 'opponent')['label'] ?? null)->toBe('Filter United')
        ->and($actionFilters->firstWhere('id', StatType::Steal->value)['label'] ?? null)->toBe(__(StatType::Steal->logLabel()))
        ->and($twoPoint['keys'] ?? null)->toBe([
            StatType::TwoPointMade->value,
            StatType::TwoPointMissed->value,
        ])
        ->and($actionFilters->firstWhere('id', StatType::TwoPointMissed->value))->toBeNull()
        ->and($labels)->toBe(collect($labels)->sort(fn (string $left, string $right): int => strcasecmp($left, $right))->values()->all());

    $screen->assertSee(__('Filter'))
        ->assertSee(__('Clear filters'))
        ->assertSee('player:'.$player->id, false)
        ->assertSee(StatType::Steal->value, false);
});

test('editing a late substitution clock recounts minutes in game order', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $team = Team::factory()->create(['club_id' => $club->id]);
    $game = Game::factory()->create(['team_id' => $team->id]);
    $starter = Player::factory()->create(['team_id' => $team->id, 'name' => 'Starter Out']);
    $firstIn = Player::factory()->create(['team_id' => $team->id, 'name' => 'First In']);
    $secondIn = Player::factory()->create(['team_id' => $team->id, 'name' => 'Second In']);
    $others = Player::factory()->count(4)->create(['team_id' => $team->id]);

    foreach ([$starter, ...$others] as $player) {
        $game->players()->attach($player->id, ['starter' => true]);
    }

    $game->players()->attach($firstIn->id, ['starter' => false]);
    $game->players()->attach($secondIn->id, ['starter' => false]);

    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => null,
        'home_away' => null,
        'type' => StatType::QuarterStarted,
        'quarter' => 1,
        'clock' => '10:00',
    ]);

    $lateSub = GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => $firstIn->id,
        'outgoing_player_id' => $starter->id,
        'home_away' => $game->home_away,
        'type' => StatType::Substitution,
        'quarter' => 1,
        'clock' => '02:00',
    ]);

    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => $secondIn->id,
        'outgoing_player_id' => $firstIn->id,
        'home_away' => $game->home_away,
        'type' => StatType::Substitution,
        'quarter' => 1,
        'clock' => '05:00',
    ]);

    statisticsRecord($member, $game)
        ->call('editAction', $lateSub->id)
        ->set('editingEventClock', '08:00')
        ->call('saveLogLine')
        ->assertHasNoErrors();

    expect($lateSub->refresh()->clock)->toBe('08:00');

    $state = $game->fresh()->lineupState('03:00', true);

    expect($state['seconds'][$starter->id])->toBe(120)
        ->and($state['seconds'][$firstIn->id])->toBe(180)
        ->and($state['seconds'][$secondIn->id])->toBe(120)
        ->and($state['court'])->toContain($secondIn->id)
        ->and($state['court'])->not->toContain($starter->id)
        ->and($state['court'])->not->toContain($firstIn->id);
});

test('a substitution counts only the minutes each player is on the court', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);
    $outgoing = Player::factory()->create(['team_id' => $game->team_id, 'name' => 'Ana Costa']);
    $incoming = Player::factory()->create(['team_id' => $game->team_id, 'name' => 'Bruno Silva']);
    $others = Player::factory()->count(4)->create(['team_id' => $game->team_id]);

    $screen = statisticsRecord($member, $game);

    foreach ([$outgoing, $incoming, ...$others] as $player) {
        $screen->call('toggleSquadPlayer', $player->id);
    }

    $screen->call('saveSquad');

    foreach ([$outgoing, ...$others] as $player) {
        $screen->call('toggleStarter', $player->id);
    }

    $screen->call('saveStarters')
        ->call('recordQuarterStart')
        ->assertSee('Substitution')
        ->assertDontSee('Lineup')
        ->set('clock', '05:00')
        ->call('select', StatType::Substitution->value)
        ->call('toggleCourtSelection', $outgoing->id)
        ->call('confirmSubstitutions')
        ->assertSet('substituting', true);

    expect(GameEvent::query()->where('type', StatType::Substitution)->count())->toBe(0);

    $screen->call('toggleCourtSelection', $incoming->id)
        ->call('confirmSubstitutions')
        ->set('clock', '03:00')
        ->assertSee('5:00')
        ->assertSee('2:00');

    $state = $game->lineupState('03:00', true);

    expect($state['seconds'][$outgoing->id])->toBe(300)
        ->and($state['seconds'][$incoming->id])->toBe(120)
        ->and($state['court'])->toContain($incoming->id)
        ->and($state['court'])->not->toContain($outgoing->id);

    $screen->call('undo');

    $restored = $game->lineupState('05:00', true);

    expect($restored['court'])->toContain($outgoing->id)
        ->and($restored['court'])->not->toContain($incoming->id)
        ->and($restored['seconds'][$outgoing->id])->toBe(300)
        ->and($restored['seconds'][$incoming->id] ?? 0)->toBe(0);
});

test('each player who enters and leaves is written on the log', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);
    giveStartingFive($game);
    $bench = Player::factory()->count(2)->create(['team_id' => $game->team_id]);

    foreach ($bench as $player) {
        $game->players()->attach($player->id, ['starter' => false]);
    }

    $court = $game->players()->wherePivot('starter', true)->orderBy('id')->get();

    foreach ([...$court->take(2), ...$bench] as $index => $player) {
        $player->update(['number' => 40 + $index, 'name' => 'Player '.$index.' Surname']);
    }

    $screen = statisticsRecord($member, $game)
        ->call('recordQuarterStart')
        ->call('select', StatType::Substitution->value)
        ->assertSee('Mark 5 players.')
        ->call('toggleCourtSelection', $court[0]->id)
        ->call('toggleCourtSelection', $court[1]->id)
        ->call('toggleCourtSelection', $bench[0]->id)
        ->call('toggleCourtSelection', $bench[1]->id)
        ->call('confirmSubstitutions')
        ->assertSee('sub');

    $events = GameEvent::query()->where('type', StatType::Substitution)->with(['player', 'outgoingPlayer'])->orderBy('id')->get();

    foreach ($events as $event) {
        $screen->assertSee('#'.$event->player->number.' '.$event->player->name)
            ->assertSee('#'.$event->outgoingPlayer->number.' '.$event->outgoingPlayer->name);
    }

    expect($screen->html())->toMatch('/text-green-700[^>]*>#'.$events->first()->player->number.'/')
        ->and($screen->html())->toMatch('/text-red-700[^>]*>#'.$events->first()->outgoingPlayer->number.'/')
        ->and($screen->html())->not->toContain('arrow-left-end-on-rectangle')
        ->and($screen->html())->not->toContain('arrow-right-start-on-rectangle');

    expect($events)->toHaveCount(2)
        ->and($events->pluck('outgoing_player_id')->all())->toContain($court[0]->id)
        ->toContain($court[1]->id)
        ->and($events->pluck('player_id')->all())->toContain($bench[0]->id)
        ->toContain($bench[1]->id);
});

test('a game starts a period with the length saved on that game', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
        'quarter_minutes' => 8,
        'overtime_minutes' => 3,
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $player);

    statisticsRecord($member, $game)
        ->assertSet('clock', '08:00')
        ->call('recordQuarterStart');

    expect(GameEvent::query()->where('type', StatType::QuarterStarted)->sole()->clock)->toBe('08:00');

    statisticsRecord($member, $game->fresh())
        ->set('quarter', 4)
        ->set('clock', '00:00')
        ->call('endQuarter')
        ->call('startQuarter')
        ->assertSet('clock', '03:00');

    expect(GameEvent::query()->where('type', StatType::QuarterStarted)->latest('id')->firstOrFail()->clock)->toBe('03:00');
});

test('game settings change the next period and leave the current clock', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $player);

    statisticsRecord($member, $game, false)
        ->call('openGameSettings')
        ->set('quarterMinutes', 0)
        ->call('saveGameSettings')
        ->assertHasErrors(['quarterMinutes'])
        ->assertSet('configuringGame', true)
        ->set('quarterMinutes', 12)
        ->call('saveGameSettings')
        ->assertHasNoErrors()
        ->assertSet('clock', '12:00')
        ->call('recordQuarterStart')
        ->set('clock', '07:30')
        ->call('openGameSettings')
        ->set('quarterMinutes', 9)
        ->set('overtimeMinutes', 4)
        ->call('saveGameSettings')
        ->assertSet('clock', '07:30')
        ->set('clock', '00:00')
        ->call('endQuarter')
        ->call('startQuarter')
        ->assertSet('clock', '09:00');

    $game->refresh();

    expect($game->quarter_minutes)->toBe(9)
        ->and($game->overtime_minutes)->toBe(4);
});

test('game settings keep players who are on the court or already have an action', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
    ]);
    $starter = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $starter);

    $benched = Player::factory()->create(['team_id' => $game->team_id]);
    $replaced = Player::factory()->create(['team_id' => $game->team_id]);
    $spare = Player::factory()->create(['team_id' => $game->team_id]);
    $extra = Player::factory()->create(['team_id' => $game->team_id]);
    $game->players()->attach($benched->id, ['starter' => false]);
    $game->players()->attach($replaced->id, ['starter' => false]);
    $game->players()->attach($spare->id, ['starter' => false]);

    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => null,
        'outgoing_player_id' => null,
        'home_away' => null,
        'type' => StatType::QuarterStarted,
        'quarter' => 1,
        'clock' => '10:00',
    ]);
    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => $benched->id,
        'home_away' => $game->home_away,
        'type' => StatType::Foul,
        'quarter' => 1,
        'clock' => '09:00',
    ]);
    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => $starter->id,
        'outgoing_player_id' => $replaced->id,
        'home_away' => $game->home_away,
        'type' => StatType::Substitution,
        'quarter' => 1,
        'clock' => '08:00',
    ]);

    $screen = statisticsRecord($member, $game)
        ->call('openGameSettings')
        ->assertSee('Settings')
        ->call('toggleSettingsPlayer', $starter->id)
        ->call('toggleSettingsPlayer', $benched->id)
        ->call('toggleSettingsPlayer', $replaced->id);

    expect($screen->get('settingsSquadIds'))->toContain($starter->id)
        ->and($screen->get('settingsSquadIds'))->toContain($benched->id)
        ->and($screen->get('settingsSquadIds'))->toContain($replaced->id);

    $screen->set('settingsSquadIds', [$extra->id])
        ->call('saveGameSettings')
        ->assertSet('configuringGame', false);

    $attached = $game->players()->pluck('players.id')->map(fn (mixed $id): int => (int) $id)->all();

    expect($attached)->toContain($starter->id)
        ->and($attached)->toContain($benched->id)
        ->and($attached)->toContain($replaced->id)
        ->and($attached)->toContain($extra->id)
        ->and($attached)->not->toContain($spare->id)
        ->and($game->players()->whereKey($extra->id)->wherePivot('starter', false)->exists())->toBeTrue()
        ->and($game->players()->whereKey($starter->id)->wherePivot('starter', true)->exists())->toBeTrue();

    expect(GameEvent::query()->where('type', StatType::Foul)->where('player_id', $benched->id)->exists())->toBeTrue()
        ->and(GameEvent::query()->where('type', StatType::Substitution)->where('outgoing_player_id', $replaced->id)->exists())->toBeTrue();
});

test('game settings save the colors shown on each side of the log', function () {
    $club = Club::factory()->create();
    $member = User::factory()->memberOf($club)->create();
    $game = Game::factory()->create([
        'team_id' => Team::factory()->create(['club_id' => $club->id])->id,
        'home_away' => HomeAway::Home,
    ]);
    $player = Player::factory()->create(['team_id' => $game->team_id]);

    giveStartingFive($game, $player);

    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => $player->id,
        'home_away' => HomeAway::Home,
        'type' => StatType::TwoPointMade,
    ]);
    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => null,
        'home_away' => HomeAway::Away,
        'type' => StatType::Foul,
    ]);
    GameEvent::factory()->create([
        'game_id' => $game->id,
        'player_id' => null,
        'home_away' => null,
        'type' => StatType::QuarterStarted,
        'quarter' => 1,
        'clock' => '10:00',
    ]);

    expect($game->home_color)->toBe(TeamColor::Purple)
        ->and($game->away_color)->toBe(TeamColor::Blue);

    statisticsRecord($member, $game)
        ->assertSee('box-shadow: inset 4px 0 0 '.TeamColor::Purple->hex(), false)
        ->assertSee('box-shadow: inset -4px 0 0 '.TeamColor::Blue->hex(), false)
        ->call('openGameSettings')
        ->set('homeColor', 'not-a-color')
        ->call('saveGameSettings')
        ->assertHasErrors(['homeColor'])
        ->assertSet('configuringGame', true)
        ->set('homeColor', TeamColor::Red->value)
        ->set('awayColor', TeamColor::Green->value)
        ->call('saveGameSettings')
        ->assertHasNoErrors()
        ->assertSee('box-shadow: inset 4px 0 0 '.TeamColor::Red->hex(), false)
        ->assertSee('box-shadow: inset -4px 0 0 '.TeamColor::Green->hex(), false)
        ->assertDontSee('box-shadow: inset 4px 0 0 '.TeamColor::Purple->hex(), false);

    $game->refresh();

    expect($game->home_color)->toBe(TeamColor::Red)
        ->and($game->away_color)->toBe(TeamColor::Green);
});
