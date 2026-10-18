<?php

namespace App\Livewire\Users;

use App\Actions\DeleteUser;
use App\Enums\PermissionName;
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

#[Title('Users')]
class Index extends Component
{
    use ConfirmsDeletion, InteractsWithAuthenticatedUser, PaginatesListings;

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        $actor = $this->actor();

        $query = User::query()->role(RoleName::User)->with(['club', 'teams.season'])->orderBy('name');

        if (! $actor->can(PermissionName::CreateClubs->value)) {
            $query->where('club_id', $actor->club_id);
        }

        return $query->paginate($this->listingPerPage());
    }

    protected function deletionModalName(): string
    {
        return 'confirm-delete';
    }

    protected function recordForDeletion(int|string $key): Model
    {
        return User::query()->findOrFail($key);
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
        return __('User deleted.');
    }

    protected function clearListedRecords(): void
    {
        unset($this->users);
    }

    public function render(): View
    {
        return view('livewire.users.index');
    }
}
