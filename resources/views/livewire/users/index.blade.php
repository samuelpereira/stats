<x-listing.page
    :title="__('Users')"
    :subtitle="__('Users of the clubs')"
    :action-href="route('users.create')"
    :action-label="__('New user')"
>
    <x-listing.table>
        <thead>
            <tr class="border-b border-zinc-200 text-left text-xs font-medium tracking-wide text-zinc-500 uppercase">
                <th class="px-4 py-3 font-medium">{{ __('Name') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Email') }}</th>
                @can(\App\Enums\PermissionName::CreateClubs->value)
                    <th class="px-4 py-3 font-medium">{{ __('Club') }}</th>
                @endcan
                <th class="px-4 py-3 font-medium">{{ __('Teams') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Enabled') }}</th>
                <th class="px-4 py-3 text-right font-medium">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($this->users as $user)
                <tr wire:key="user-{{ $user->id }}" class="border-b border-zinc-100 last:border-b-0">
                    <x-listing.cell primary>{{ $user->name }}</x-listing.cell>
                    <x-listing.cell :label="__('Email')">{{ $user->email }}</x-listing.cell>
                    @can(\App\Enums\PermissionName::CreateClubs->value)
                        <x-listing.cell :label="__('Club')">{{ $user->club?->name }}</x-listing.cell>
                    @endcan
                    <x-listing.cell :label="__('Teams')">
                        @if ($user->teams->isEmpty())
                            <span class="text-zinc-400">—</span>
                        @else
                            {{ $user->teams->pluck('name')->join(', ') }}
                        @endif
                    </x-listing.cell>
                    <x-listing.cell :label="__('Enabled')">
                        <x-listing.status :enabled="$user->enabled" />
                    </x-listing.cell>
                    <x-listing.cell actions>
                        <x-listing.actions :edit-href="route('users.edit', $user)" :delete-record-id="$user->id" />
                    </x-listing.cell>
                </tr>
            @empty
                <tr class="listing-empty">
                    <td colspan="{{ auth()->user()->can(\App\Enums\PermissionName::CreateClubs->value) ? 6 : 5 }}" class="px-4 py-8 text-center text-zinc-500">{{ __('No users yet.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </x-listing.table>

    <x-listing.pagination :paginator="$this->users" />

    <x-slot:modal>
        <x-listing.delete-modal
            name="confirm-delete"
            :title="__('Delete this user?')"
            :message="__('This will permanently delete the user :name.', ['name' => $this->recordToDelete?->name])"
        />
    </x-slot:modal>
</x-listing.page>
