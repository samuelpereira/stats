<?php

namespace App\Livewire\Players;

use App\Actions\DeletePlayer;
use App\Enums\PermissionName;
use App\Livewire\Concerns\ConfirmsDeletion;
use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\PaginatesListings;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Players')]
class Index extends Component
{
    use ConfirmsDeletion, InteractsWithAuthenticatedUser, PaginatesListings;

    #[Url(as: 'season', except: '')]
    public string $seasonId = '';

    #[Url(as: 'team', except: '')]
    public string $teamId = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Player::class);

        if (request()->has('season')) {
            $this->seasonId = request()->string('season')->toString();
        } else {
            $currentSeason = Season::query()->where('is_current', true)->first();

            if ($currentSeason !== null) {
                $this->seasonId = (string) $currentSeason->id;
            }
        }

        if (request()->has('team')) {
            $this->teamId = request()->string('team')->toString();
        }
    }

    /**
     * @return array<string, string>
     */
    public function listFilters(): array
    {
        $filters = [];

        if ($this->seasonId !== '') {
            $filters['season'] = $this->seasonId;
        }

        if ($this->teamId !== '') {
            $filters['team'] = $this->teamId;
        }

        return $filters;
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
     * @return LengthAwarePaginator<int, Player>
     */
    #[Computed]
    public function players(): LengthAwarePaginator
    {
        $actor = $this->actor();

        $query = Player::query()->with(['team.club', 'team.season'])->orderBy('order')->orderBy('name');

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

    protected function deletionModalName(): string
    {
        return 'confirm-delete';
    }

    protected function recordForDeletion(int|string $key): Model
    {
        return Player::findByPublicKey($key);
    }

    protected function performDeletion(Model $record): void
    {
        if (! $record instanceof Player) {
            return;
        }

        (new DeletePlayer)->handle($record);
    }

    protected function deletedMessage(): string
    {
        return __('Player deleted.');
    }

    protected function clearListedRecords(): void
    {
        unset($this->players, $this->teamsForFilter);
    }

    public function render(): View
    {
        return view('livewire.players.index');
    }
}
