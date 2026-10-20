<x-listing.page
    :title="__('Administrators')"
    :subtitle="__('Administrators and managers')"
    :action-href="route('administrators.create')"
    :action-label="__('New administrator')"
>
    <x-listing.table>
        <thead>
            <tr class="border-b border-zinc-200 text-left text-xs font-medium tracking-wide text-zinc-500 uppercase">
                <th class="px-4 py-3 font-medium">{{ __('Name') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Email') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Role') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Enabled') }}</th>
                <th class="px-4 py-3 text-right font-medium">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($this->administrators as $administrator)
                <tr wire:key="administrator-{{ $administrator->id }}" class="border-b border-zinc-100 last:border-b-0">
                    <x-listing.cell primary>{{ $administrator->name }}</x-listing.cell>
                    <x-listing.cell :label="__('Email')">{{ $administrator->email }}</x-listing.cell>
                    <x-listing.cell :label="__('Role')">{{ __($administrator->assignedRole()->label()) }}</x-listing.cell>
                    <x-listing.cell :label="__('Enabled')">
                        <x-listing.status :enabled="$administrator->enabled" />
                    </x-listing.cell>
                    <x-listing.cell actions>
                        <x-listing.actions
                            :edit-href="route('administrators.edit', $administrator)"
                            :delete-record-id="auth()->id() !== $administrator->id ? $administrator->id : null"
                        />
                    </x-listing.cell>
                </tr>
            @empty
                <tr class="listing-empty">
                    <td colspan="5" class="px-4 py-8 text-center text-zinc-500">{{ __('No administrators yet.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </x-listing.table>

    <x-listing.pagination :paginator="$this->administrators" />

    <x-slot:modal>
        <x-listing.delete-modal
            name="confirm-delete"
            :title="__('Delete this administrator?')"
            :message="__('This will permanently delete the user :name.', ['name' => $this->recordToDelete?->name])"
        />
    </x-slot:modal>
</x-listing.page>
