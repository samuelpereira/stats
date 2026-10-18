<?php

namespace App\Livewire\Seasons;

use App\Actions\DeleteSeason;
use App\Actions\MarkSeasonAsCurrent;
use App\Livewire\Concerns\ConfirmsDeletion;
use App\Livewire\Concerns\PaginatesListings;
use App\Models\Season;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Seasons')]
class Index extends Component
{
    use ConfirmsDeletion, PaginatesListings;

    public function mount(): void
    {
        $this->authorize('viewAny', Season::class);
    }

    public function markAsCurrent(string $seasonUuid, MarkSeasonAsCurrent $markSeasonAsCurrent): void
    {
        $season = Season::findByPublicKey($seasonUuid);

        $this->authorize('update', $season);

        $markSeasonAsCurrent->handle($season);

        Flux::toast(variant: 'success', text: __('Season marked as current.'));
    }

    /**
     * @return LengthAwarePaginator<int, Season>
     */
    #[Computed]
    public function seasons(): LengthAwarePaginator
    {
        return Season::query()->withCount('teams')->orderByDesc('starts_at')->paginate($this->listingPerPage());
    }

    protected function deletionModalName(): string
    {
        return 'confirm-delete';
    }

    protected function recordForDeletion(int|string $key): Model
    {
        return Season::findByPublicKey($key);
    }

    protected function performDeletion(Model $record): void
    {
        if (! $record instanceof Season) {
            return;
        }

        (new DeleteSeason)->handle($record);
    }

    protected function deletedMessage(): string
    {
        return __('Season deleted.');
    }

    protected function clearListedRecords(): void
    {
        unset($this->seasons);
    }

    public function render(): View
    {
        return view('livewire.seasons.index');
    }
}
