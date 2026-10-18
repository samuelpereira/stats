<?php

namespace App\Livewire\Games;

use App\Actions\DeleteGame;
use App\Enums\PermissionName;
use App\Livewire\Concerns\ConfirmsDeletion;
use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\PaginatesListings;
use App\Models\Game;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Games')]
class Index extends Component
{
    use ConfirmsDeletion, InteractsWithAuthenticatedUser, PaginatesListings;

    public string $seasonId = '';

    public string $teamId = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Game::class);

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
        $actor = $this->actor();

        $query = Game::query()->with(['team.club', 'team.season'])->orderByDesc('date_time');

        if (! $actor->can(PermissionName::CreateClubs->value)) {
            $query->whereHas('team', fn (Builder $teamQuery) => $teamQuery->where('club_id', $actor->club_id));
        }

        if ($this->seasonId !== '') {
            $query->whereHas('team', fn (Builder $teamQuery) => $teamQuery->where('season_id', (int) $this->seasonId));
        }

        if ($this->teamId !== '') {
            $query->where('team_id', (int) $this->teamId);
        }

        return $query->paginate($this->listingPerPage());
    }

    public function render(): View
    {
        return view('livewire.games.index');
    }

    protected function deletionModalName(): string
    {
        return 'confirm-delete';
    }

    protected function recordForDeletion(int|string $key): Model
    {
        return Game::findByPublicKey($key);
    }

    protected function performDeletion(Model $record): void
    {
        if (! $record instanceof Game) {
            return;
        }

        (new DeleteGame)->handle($record);
    }

    protected function deletedMessage(): string
    {
        return __('Game deleted.');
    }

    protected function clearListedRecords(): void
    {
        unset($this->games, $this->teamsForFilter);
    }
}
