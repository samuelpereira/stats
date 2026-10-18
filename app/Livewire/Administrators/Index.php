<?php

namespace App\Livewire\Administrators;

use App\Actions\DeleteUser;
use App\Enums\RoleName;
use App\Livewire\Concerns\ConfirmsDeletion;
use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\PaginatesListings;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Administrators')]
class Index extends Component
{
    use ConfirmsDeletion, InteractsWithAuthenticatedUser, PaginatesListings;

    public function mount(): void
    {
        $this->authorize('viewAnyStaff', User::class);
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function administrators(): LengthAwarePaginator
    {
        return User::query()
            ->role(RoleName::staff())
            ->with('roles')
            ->orderBy('name')
            ->paginate($this->listingPerPage());
    }

    protected function deletionModalName(): string
    {
        return 'confirm-delete';
    }

    protected function recordForDeletion(int|string $key): Model
    {
        return User::query()->findOrFail($key);
    }

    protected function authorizeDeletion(Model $record): void
    {
        $this->authorize('deleteStaff', $record);
    }

    protected function performDeletion(Model $record): void
    {
        if (! $record instanceof User) {
            return;
        }

        (new DeleteUser)->handle($this->actor(), $record);
    }

    protected function deletedMessage(): string
    {
        return __('Administrator deleted.');
    }

    protected function clearListedRecords(): void
    {
        unset($this->administrators);
    }

    public function render(): View
    {
        return view('livewire.administrators.index');
    }
}
