<?php

namespace App\Livewire\Statistics;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\PaginatesListings;
use App\Models\Game;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Statistics')]
class Index extends Component
{
    use InteractsWithAuthenticatedUser, PaginatesListings;

    public function mount(): void
    {
        $this->authorize('collectAny', Game::class);
    }

    /**
     * @return LengthAwarePaginator<int, Game>
     */
    #[Computed]
    public function games(): LengthAwarePaginator
    {
        $games = Game::query()
            ->with(['team.season'])
            ->withExists('events')
            ->orderByDesc('date_time');

        $games->forStatisticsCollector($this->actor());

        return $games->paginate($this->listingPerPage());
    }

    public function render(): View
    {
        return view('livewire.statistics.index');
    }
}
