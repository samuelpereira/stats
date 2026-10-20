<?php

use App\Enums\GameStatus;
use App\Enums\HomeAway;
use App\Livewire\Teams\Edit as EditTeam;
use App\Models\Club;
use App\Models\Game;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use App\Services\Fpb\FpbGamesImporter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('fpb roster import creates players and keeps disabled players disabled', function () {
    Http::fake([
        'https://fpb.test/equipa' => Http::response(<<<'HTML'
<div class="roster__player">
<a href="/atletas/4242"></a>
<h2 class="header__heading">Sample Athlete</h2>
<div class="header_position">Base</div>
<div class="header_jnumber">7</div>
</div>
HTML),
    ]);

    $team = Team::factory()->create([
        'fpb_url' => 'https://fpb.test/equipa',
    ]);

    app(FpbGamesImporter::class)->importPlayers($team, prune: false);

    $player = Player::query()->where('fpb_id', '4242')->first();

    expect($player)->not->toBeNull()
        ->and($player->name)->toBe('Sample Athlete')
        ->and($player->enabled)->toBeTrue();

    $player->update(['enabled' => false]);

    app(FpbGamesImporter::class)->importPlayers($team, prune: false);

    expect($player->fresh()->enabled)->toBeFalse();
});

test('fpb roster import leaves a duplicate shirt number empty', function () {
    Http::fake([
        'https://fpb.test/equipa' => Http::response(<<<'HTML'
<div class="roster__player">
<a href="/atletas/1001"></a>
<h2 class="header__heading">João Martim</h2>
<div class="header_position">Base</div>
<div class="header_jnumber">5</div>
</div>
<div class="roster__player">
<a href="/atletas/1002"></a>
<h2 class="header__heading">Riquelme Sissé</h2>
<div class="header_position">Extremo</div>
<div class="header_jnumber">5</div>
</div>
HTML),
    ]);

    $team = Team::factory()->create([
        'fpb_url' => 'https://fpb.test/equipa',
    ]);

    app(FpbGamesImporter::class)->importPlayers($team, prune: false);

    $first = Player::query()->where('fpb_id', '1001')->first();
    $second = Player::query()->where('fpb_id', '1002')->first();

    expect($first)->not->toBeNull()
        ->and($first->number)->toBe(5)
        ->and($second)->not->toBeNull()
        ->and($second->number)->toBeNull();
});

test('fpb games import upserts fixtures for the club on the page', function () {
    Http::fake([
        'https://fpb.test/equipa' => Http::response(<<<'HTML'
<h3 class="date">15 OUT 2025</h3>
<a href="/ficha-de-jogo?internalID=1001">
<span class="fullName">Test Club Alpha</span>
<span class="fullName">Test Club Beta</span>
<div class="results_text">80</div>
<div class="results_text">70</div>
</a>
HTML),
    ]);

    $team = Team::factory()->create([
        'name' => 'Test Club Alpha',
        'fpb_url' => 'https://fpb.test/equipa',
    ]);

    $stats = app(FpbGamesImporter::class)->import($team, prune: false);

    expect($stats['created'])->toBe(1);

    $game = Game::query()->where('fpb_id', '1001')->first();

    expect($game)->not->toBeNull()
        ->and($game->team_id)->toBe($team->id)
        ->and($game->opponent)->toBe('Test Club Beta')
        ->and($game->home_away)->toBe(HomeAway::Home)
        ->and($game->home_points)->toBe(80)
        ->and($game->away_points)->toBe(70)
        ->and($game->status)->toBe(GameStatus::Played);
});

test('fpb games import stores the opponent logo once', function () {
    Storage::fake('public');

    Http::fake([
        'https://fpb.test/equipa' => Http::response(<<<'HTML'
<h3 class="date">15 OUT 2025</h3>
<a href="/ficha-de-jogo?internalID=1002">
<div class="image-container">
<img alt="Logo Test Club Alpha" src="https://logos.test/alpha.jpg">
</div>
<span class="fullName">Test Club Alpha</span>
<div class="image-container">
<img alt="Logo Test Club Beta" src="https://logos.test/beta.jpg">
</div>
<span class="fullName">Test Club Beta</span>
</a>
HTML),
        'https://logos.test/beta.jpg' => Http::response('beta-logo'),
    ]);

    $team = Team::factory()->create([
        'name' => 'Test Club Alpha',
        'fpb_url' => 'https://fpb.test/equipa',
    ]);

    app(FpbGamesImporter::class)->import($team, prune: false);

    $game = Game::query()->where('fpb_id', '1002')->first();

    expect($game?->opponent_photo)->not->toBeNull();
    Storage::disk('public')->assertExists((string) $game->opponent_photo);

    app(FpbGamesImporter::class)->import($team, prune: false);

    expect(Storage::disk('public')->allFiles('opponents'))->toHaveCount(1)
        ->and(collect(Http::recorded())->filter(
            fn (array $pair): bool => $pair[0]->url() === 'https://logos.test/beta.jpg',
        )->count())->toBe(1)
        ->and(collect(Http::recorded())->contains(
            fn (array $pair): bool => $pair[0]->url() === 'https://logos.test/alpha.jpg',
        ))->toBeFalse();
});

test('fpb games re-import without kickoff time keeps existing date time', function () {
    Http::fake([
        'https://fpb.test/equipa' => Http::response(<<<'HTML'
<h3 class="date">15 OUT 2025</h3>
<a href="/ficha-de-jogo?internalID=1001">
<span class="fullName">Test Club Alpha</span>
<span class="fullName">Test Club Beta</span>
<div class="results_text">80</div>
<div class="results_text">70</div>
</a>
HTML),
    ]);

    $team = Team::factory()->create([
        'name' => 'Test Club Alpha',
        'fpb_url' => 'https://fpb.test/equipa',
    ]);

    $game = Game::factory()->for($team)->create([
        'fpb_id' => '1001',
        'date_time' => '2025-10-15 20:30:00',
        'home_points' => 75,
        'away_points' => 70,
        'status' => GameStatus::Played,
    ]);

    app(FpbGamesImporter::class)->import($team, prune: false);

    expect($game->fresh()->date_time->format('Y-m-d H:i'))->toBe('2025-10-15 20:30')
        ->and($game->fresh()->home_points)->toBe(80);
});

test('fpb fetch game for team maps opponent and home away', function () {
    Http::fake([
        'https://www.fpb.pt/ficha-de-jogo?internalID=1001' => Http::response(<<<'HTML'
<title>North City vs. South Valley | CN1</title>
<section class="match-highlights">
<div class="match-wrapper">
<p class="phase">Jornada 2</p>
<p class="date">04 OUT 2026</p>
<div class="match-inner-wrapper">
<div class="team home">
<p class="bigName">North City</p>
<img alt="Home Team Logo" src="https://logos.test/home.png">
</div>
<div class="points">
<span>80</span><div class="dash"></div><span>70</span>
</div>
<div class="team away">
<img alt="Away Team Logo" src="https://logos.test/away.png">
<p class="bigName">South Valley</p>
</div>
</div>
<p class="location"><a href="/recinto/1">Arena</a></p>
</div>
</section>
HTML),
    ]);

    $club = Club::factory()->create(['name' => 'North Club']);
    $team = Team::factory()->create([
        'club_id' => $club->id,
        'name' => 'North City',
    ]);

    $sheet = app(FpbGamesImporter::class)->fetchGameForTeam($team, '1001');

    expect($sheet['opponent'])->toBe('South Valley')
        ->and($sheet['home_away'])->toBe(HomeAway::Home)
        ->and($sheet['home_points'])->toBe(80)
        ->and($sheet['away_points'])->toBe(70)
        ->and($sheet['status'])->toBe(GameStatus::Played)
        ->and($sheet['competition'])->toBe('CN1')
        ->and($sheet['journey'])->toBe('Jornada 2')
        ->and($sheet['opponent_logo'])->toBe('https://logos.test/away.png');
});

test('fpb games import keeps local fpb games missing from the page unless prune is enabled', function () {
    Http::fake([
        'https://fpb.test/equipa' => Http::response(<<<'HTML'
<h3 class="date">15 OUT 2025</h3>
<a href="/ficha-de-jogo?internalID=1001">
<span class="fullName">Test Club Alpha</span>
<span class="fullName">Test Club Beta</span>
</a>
HTML),
    ]);

    $team = Team::factory()->create([
        'name' => 'Test Club Alpha',
        'fpb_url' => 'https://fpb.test/equipa',
    ]);

    $kept = Game::factory()->for($team)->create(['fpb_id' => '9999']);

    config(['fpb.prune_missing' => false]);

    $withoutPrune = app(FpbGamesImporter::class)->import($team);

    expect($withoutPrune['deleted'])->toBe(0)
        ->and(Game::query()->whereKey($kept->id)->exists())->toBeTrue();

    config(['fpb.prune_missing' => true]);

    $withPrune = app(FpbGamesImporter::class)->import($team);

    expect($withPrune['deleted'])->toBe(1)
        ->and(Game::query()->whereKey($kept->id)->exists())->toBeFalse();
});

test('team edit can trigger fpb imports from livewire', function () {
    Http::fake([
        'https://fpb.test/equipa' => Http::response(<<<'HTML'
<div class="roster__player">
<a href="/atletas/777"></a>
<h2 class="header__heading">Example Member</h2>
<div class="header_jnumber">11</div>
</div>
HTML),
    ]);

    $administrator = User::factory()->administrator()->create();
    $team = Team::factory()->create(['fpb_url' => null]);

    Livewire::actingAs($administrator)
        ->test(EditTeam::class, ['team' => $team])
        ->set('fpbUrl', 'https://fpb.test/equipa')
        ->call('importFpbRoster')
        ->assertHasNoErrors();

    expect(Player::query()->where('fpb_id', '777')->exists())->toBeTrue()
        ->and($team->fresh()->fpb_url)->toBe('https://fpb.test/equipa');
});
