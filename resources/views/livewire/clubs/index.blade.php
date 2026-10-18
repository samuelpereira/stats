<x-listing.page
    :title="__('Clubs')"
    :subtitle="__('Clubs and their access')"
    :action-href="route('clubs.create')"
    :action-label="__('New club')"
>
    <x-listing.table>
        <thead>
            <tr class="border-b border-zinc-200 text-left text-xs font-medium tracking-wide text-zinc-500 uppercase">
                <th class="px-4 py-3 font-medium">{{ __('Name') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Access') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Enabled') }}</th>
                <th class="px-4 py-3 text-right font-medium">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($this->clubs as $club)
                <tr wire:key="club-{{ $club->id }}" class="border-b border-zinc-100 last:border-b-0">
                    <x-listing.cell primary>{{ $club->name }}</x-listing.cell>
                    <x-listing.cell :label="__('Access')">{{ $club->access?->email }}</x-listing.cell>
                    <x-listing.cell :label="__('Enabled')">
                        @if ($club->access)
                            <x-listing.status :enabled="$club->access->enabled" />
                        @endif
                    </x-listing.cell>
                    <x-listing.cell actions>
                        <x-listing.actions :edit-href="route('clubs.edit', $club)">
                            <x-slot:delete>
                                <x-listing.delete-button :record-id="$club->id" />
                            </x-slot:delete>
                        </x-listing.actions>
                    </x-listing.cell>
                </tr>
            @empty
                <tr class="listing-empty">
                    <td colspan="4" class="px-4 py-8 text-center text-zinc-500">{{ __('No clubs yet.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </x-listing.table>

    <x-listing.pagination :paginator="$this->clubs" />

    <x-slot:modal>
        <x-listing.delete-modal
            name="confirm-delete"
            :title="__('Delete this club?')"
            :message="__('This will permanently delete the club :name and its users.', ['name' => $this->recordToDelete?->name])"
        />
    </x-slot:modal>
</x-listing.page>
