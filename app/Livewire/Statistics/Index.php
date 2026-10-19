<?php

namespace App\Livewire\Statistics;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\PaginatesListings;
use App\Models\Game;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Statistics')]
class Index extends Component
{
    use InteractsWithAuthenticatedUser, PaginatesListings;

    public string $seasonId = '';

    public string $teamId = '';

    public function mount(): void
    {
        $this->authorize('collectAny', Game::class);

        $currentSeason = Season::query()->where('is_current', true)->first();

        if ($currentSeason !== null) {
            $this->seasonId = (string) $currentSeason->id;
        }
    }

    public function updatedSeasonId(): void
    {
        $this->teamId = '';
        $this->resetPage();
        $this->clearListedRecords();
    }

    public function updatedTeamId(): void
    {
        $this->resetPage();
        $this->clearListedRecords();
    }

    /**
     * @return Collection<int, Season>
     */
    #[Computed]
    public function seasons(): Collection
    {
        return Season::query()->orderByDesc('starts_at')->get();
    }

    /**
     * @return Collection<int, Team>
     */
    #[Computed]
    public function teamsForFilter(): Collection
    {
        $actor = $this->actor();

        $query = Team::query()->with(['club', 'season'])->orderBy('order')->orderBy('name');

        if (! $actor->can(PermissionName::CreateClubs->value)) {
            $query->where('club_id', $actor->club_id);

            if ($actor->hasRole(RoleName::User)) {
                $query->whereIn('teams.id', $actor->teams()->select('teams.id'));
            }
        }

        if ($this->seasonId !== '') {
            $query->where('season_id', (int) $this->seasonId);
        }

        return $query->get();
    }

    /**
     * @return LengthAwarePaginator<int, Game>
     */
    #[Computed]
    public function games(): LengthAwarePaginator
    {
        $games = Game::query()
            ->with(['team.season', 'team.club'])
            ->withExists('events')
            ->orderByDesc('date_time');

        $games->forStatisticsCollector($this->actor());

        if ($this->seasonId !== '') {
            $games->whereHas(
                'team',
                fn (Builder $teamQuery) => $teamQuery->where('season_id', (int) $this->seasonId),
            );
        }

        if ($this->teamId !== '') {
            $games->where('team_id', (int) $this->teamId);
        }

        return $games->paginate($this->listingPerPage());
    }

    public function render(): View
    {
        return view('livewire.statistics.index');
    }

    private function clearListedRecords(): void
    {
        unset($this->games, $this->teamsForFilter);
    }
}
