<?php

use App\Http\Controllers\WebAppManifestController;
use App\Livewire\Administrators\Create as CreateAdministrator;
use App\Livewire\Administrators\Edit as EditAdministrator;
use App\Livewire\Administrators\Index as AdministratorIndex;
use App\Livewire\Clubs\Create as CreateClub;
use App\Livewire\Clubs\Edit as EditClub;
use App\Livewire\Clubs\Index as ClubIndex;
use App\Livewire\Dashboard;
use App\Livewire\Games\Create as CreateGame;
use App\Livewire\Games\Edit as EditGame;
use App\Livewire\Games\Index as GameIndex;
use App\Livewire\Players\Create as CreatePlayer;
use App\Livewire\Players\Edit as EditPlayer;
use App\Livewire\Players\Index as PlayerIndex;
use App\Livewire\Seasons\Create as CreateSeason;
use App\Livewire\Seasons\Edit as EditSeason;
use App\Livewire\Seasons\Index as SeasonIndex;
use App\Livewire\Statistics\Index as StatisticsIndex;
use App\Livewire\Statistics\Record as StatisticsRecord;
use App\Livewire\Statistics\Shared;
use App\Livewire\Statistics\Show;
use App\Livewire\Teams\Create as CreateTeam;
use App\Livewire\Teams\Edit as EditTeam;
use App\Livewire\Teams\Index as TeamIndex;
use App\Livewire\Teams\SeasonStatistics as TeamSeasonStatistics;
use App\Livewire\Teams\SharedSeasonStatistics;
use App\Livewire\Users\Create as CreateUser;
use App\Livewire\Users\Edit as EditUser;
use App\Livewire\Users\Index as UserIndex;
use App\Models\Club;
use App\Models\Game;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('/manifest.webmanifest', WebAppManifestController::class)->name('manifest');

Route::livewire('s/{game:share_token}', Shared::class)->name('statistics.shared');
Route::livewire('s/season/{team:share_token}', SharedSeasonStatistics::class)->name('teams.season-statistics.shared');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', Dashboard::class)->name('dashboard');

    Route::livewire('administrators', AdministratorIndex::class)->name('administrators.index')->can('viewAnyStaff', User::class);
    Route::livewire('administrators/create', CreateAdministrator::class)->name('administrators.create')->can('createStaff', User::class);
    Route::livewire('administrators/{user}/edit', EditAdministrator::class)->name('administrators.edit')->can('updateStaff', 'user');

    Route::livewire('clubs', ClubIndex::class)->name('clubs.index')->can('viewAny', Club::class);
    Route::livewire('clubs/create', CreateClub::class)->name('clubs.create')->can('create', Club::class);
    Route::livewire('clubs/{club}/edit', EditClub::class)->name('clubs.edit')->can('update', 'club');

    Route::livewire('seasons', SeasonIndex::class)->name('seasons.index')->can('viewAny', Season::class);
    Route::livewire('seasons/create', CreateSeason::class)->name('seasons.create')->can('create', Season::class);
    Route::livewire('seasons/{season}/edit', EditSeason::class)->name('seasons.edit')->can('update', 'season');

    Route::livewire('teams', TeamIndex::class)->name('teams.index')->can('viewAny', Team::class);
    Route::livewire('teams/create', CreateTeam::class)->name('teams.create')->can('create', Team::class);
    Route::livewire('teams/{team}/season-statistics', TeamSeasonStatistics::class)->name('teams.season-statistics')->can('viewSeasonStatistics', 'team');
    Route::livewire('teams/{team}/edit', EditTeam::class)->name('teams.edit')->can('update', 'team');

    Route::livewire('players', PlayerIndex::class)->name('players.index')->can('viewAny', Player::class);
    Route::livewire('players/create', CreatePlayer::class)->name('players.create')->can('create', Player::class);
    Route::livewire('players/{player}/edit', EditPlayer::class)->name('players.edit')->can('update', 'player');

    Route::livewire('games', GameIndex::class)->name('games.index')->can('viewAny', Game::class);
    Route::livewire('games/create', CreateGame::class)->name('games.create')->can('create', Game::class);
    Route::livewire('games/{game}/edit', EditGame::class)->name('games.edit')->can('update', 'game');

    Route::livewire('statistics', StatisticsIndex::class)->name('statistics.index')->can('collectAny', Game::class);
    Route::livewire('statistics/{game}/box', Show::class)->name('statistics.show')->can('collect', 'game');
    Route::livewire('statistics/{game}', StatisticsRecord::class)->name('statistics.record')->can('collect', 'game');

    Route::livewire('users', UserIndex::class)->name('users.index')->can('viewAny', User::class);
    Route::livewire('users/create', CreateUser::class)->name('users.create')->can('create', User::class);
    Route::livewire('users/{user}/edit', EditUser::class)->name('users.edit')->can('update', 'user');
});

require __DIR__.'/settings.php';
