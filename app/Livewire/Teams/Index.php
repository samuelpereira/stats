<?php

namespace App\Livewire\Teams;

use App\Actions\DeleteTeam;
use App\Enums\PermissionName;
use App\Livewire\Concerns\ConfirmsDeletion;
use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\PaginatesListings;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Teams')]
class Index extends Component
{
    use ConfirmsDeletion, InteractsWithAuthenticatedUser, PaginatesListings;

    public string $seasonId = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Team::class);

        $currentSeason = Season::query()->where('is_current', true)->first();

        if ($currentSeason !== null) {
            $this->seasonId = (string) $currentSeason->id;
        }
    }

    public function updatedSeasonId(): void
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
     * @return LengthAwarePaginator<int, Team>
     */
    #[Computed]
    public function teams(): LengthAwarePaginator
    {
        $actor = $this->actor();

        $query = Team::query()->with(['club', 'season'])->orderBy('order')->orderBy('name');

        if (! $actor->can(PermissionName::CreateClubs->value)) {
            $query->where('club_id', $actor->club_id);
        }

        if ($this->seasonId !== '') {
            $query->where('season_id', (int) $this->seasonId);
        }

        return $query->paginate($this->listingPerPage());
    }

    protected function deletionModalName(): string
    {
        return 'confirm-delete';
    }

    protected function recordForDeletion(int|string $key): Model
    {
        return Team::findByPublicKey($key);
    }

    protected function performDeletion(Model $record): void
    {
        if (! $record instanceof Team) {
            return;
        }

        (new DeleteTeam)->handle($record);
    }

    protected function deletedMessage(): string
    {
        return __('Team deleted.');
    }

    protected function clearListedRecords(): void
    {
        unset($this->teams);
    }

    public function render(): View
    {
        return view('livewire.teams.index');
    }
}
