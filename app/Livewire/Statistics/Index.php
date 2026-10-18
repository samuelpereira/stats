<?php

namespace App\Livewire\Statistics;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Models\Game;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Statistics')]
class Index extends Component
{
    use InteractsWithAuthenticatedUser;

    public function mount(): void
    {
        $this->authorize('collectAny', Game::class);
    }

    /**
     * @return Collection<int, Game>
     */
    #[Computed]
    public function games(): Collection
    {
        $games = Game::query()
            ->with(['team.season'])
            ->withExists('events')
            ->orderByDesc('date_time');

        $games->forStatisticsCollector($this->actor());

        return $games->get();
    }

    public function render(): View
    {
        return view('livewire.statistics.index');
    }
}
