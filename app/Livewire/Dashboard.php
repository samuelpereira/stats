<?php

namespace App\Livewire;

use App\Enums\GameStatus;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Models\Club;
use App\Models\Game;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    use InteractsWithAuthenticatedUser;

    #[Computed]
    public function currentSeason(): ?Season
    {
        return Season::query()->where('is_current', true)->first();
    }

    /**
     * @return list<array{label: string, value: int|string, href: string|null}>
     */
    #[Computed]
    public function summaryCards(): array
    {
        $actor = $this->actor();
        $cards = [];

        if ($actor->can('viewAny', Club::class)) {
            $cards[] = [
                'label' => __('Clubs'),
                'value' => Club::query()->count(),
                'href' => route('clubs.index'),
            ];
        }

        if ($actor->can('viewAny', User::class)) {
            $usersQuery = User::query()->role(RoleName::User);

            if (! $actor->can(PermissionName::CreateClubs->value)) {
                $usersQuery->where('club_id', $actor->club_id);
            }

            $cards[] = [
                'label' => __('Users'),
                'value' => $usersQuery->count(),
                'href' => route('users.index'),
            ];
        }

        if ($actor->can('viewAny', Team::class)) {
            $cards[] = [
                'label' => __('Teams'),
                'value' => $this->teamsQuery()->count(),
                'href' => route('teams.index'),
            ];
        }

        if ($actor->can('viewAny', Game::class)) {
            $cards[] = [
                'label' => __('Games'),
                'value' => $this->gamesQuery()->count(),
                'href' => route('games.index'),
            ];
        }

        return $cards;
    }

    /**
     * @return Collection<int, Game>
     */
    #[Computed]
    public function upcomingGames(): Collection
    {
        if (! Gate::check('viewAny', Game::class) && ! Gate::check('collectAny', Game::class)) {
            return new Collection;
        }

        return $this->gamesQuery()
            ->where('status', GameStatus::Scheduled)
            ->where('date_time', '>=', now())
            ->orderBy('date_time')
            ->limit(5)
            ->get();
    }

    /**
     * @return Collection<int, Game>
     */
    #[Computed]
    public function recentGames(): Collection
    {
        if (! Gate::check('viewAny', Game::class) && ! Gate::check('collectAny', Game::class)) {
            return new Collection;
        }

        return $this->gamesQuery()
            ->where('date_time', '<', now())
            ->whereHas('events')
            ->orderByDesc('date_time')
            ->limit(5)
            ->get();
    }

    public function canCollectStatistics(): bool
    {
        return Gate::check('collectAny', Game::class);
    }

    public function render(): View
    {
        return view('livewire.dashboard');
    }

    /**
     * @return Builder<Team>
     */
    private function teamsQuery(): Builder
    {
        $query = Team::query();

        $currentSeason = $this->currentSeason();

        if ($currentSeason !== null) {
            $query->where('season_id', $currentSeason->id);
        }

        if (! $this->actor()->can(PermissionName::CreateClubs->value)) {
            $query->where('club_id', $this->actor()->club_id);
        }

        return $query;
    }

    /**
     * @return Builder<Game>
     */
    private function gamesQuery(): Builder
    {
        $query = Game::query()->with(['team.season', 'team.club'])->withExists('events');

        $currentSeason = $this->currentSeason();

        if ($currentSeason !== null) {
            $query->whereHas('team', fn (Builder $teamQuery) => $teamQuery->where('season_id', $currentSeason->id));
        }

        if (! $this->actor()->can(PermissionName::CreateClubs->value)) {
            $query->forStatisticsCollector($this->actor());
        }

        return $query;
    }
}
