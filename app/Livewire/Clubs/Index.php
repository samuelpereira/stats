<?php

namespace App\Livewire\Clubs;

use App\Actions\DeleteClub;
use App\Livewire\Concerns\ConfirmsDeletion;
use App\Livewire\Concerns\PaginatesListings;
use App\Models\Club;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Clubs')]
class Index extends Component
{
    use ConfirmsDeletion, PaginatesListings;

    public function mount(): void
    {
        $this->authorize('viewAny', Club::class);
    }

    /**
     * @return LengthAwarePaginator<int, Club>
     */
    #[Computed]
    public function clubs(): LengthAwarePaginator
    {
        return Club::query()->with('access')->orderBy('name')->paginate($this->listingPerPage());
    }

    protected function deletionModalName(): string
    {
        return 'confirm-delete';
    }

    protected function recordForDeletion(int|string $key): Model
    {
        return Club::query()->findOrFail($key);
    }

    protected function performDeletion(Model $record): void
    {
        if (! $record instanceof Club) {
            return;
        }

        (new DeleteClub)->handle($record);
    }

    protected function deletedMessage(): string
    {
        return __('Club deleted.');
    }

    protected function clearListedRecords(): void
    {
        unset($this->clubs);
    }

    public function render(): View
    {
        return view('livewire.clubs.index');
    }
}
