<x-listing.page
    :title="__('Seasons')"
    :subtitle="__('Sports seasons of the club')"
    :action-href="route('seasons.create')"
    :action-label="__('New season')"
>
    <x-listing.table>
        <thead>
            <tr class="border-b border-zinc-200 text-left text-xs font-medium tracking-wide text-zinc-500 uppercase">
                <th class="px-4 py-3 font-medium">{{ __('Season') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Period') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Teams') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                <th class="px-4 py-3 text-right font-medium">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($this->seasons as $season)
                <tr wire:key="season-{{ $season->uuid }}" class="border-b border-zinc-100 last:border-b-0">
                    <x-listing.cell primary>{{ $season->name }}</x-listing.cell>
                    <x-listing.cell :label="__('Period')">{{ $season->starts_at->format('d/m/Y') }} – {{ $season->ends_at->format('d/m/Y') }}</x-listing.cell>
                    <x-listing.cell :label="__('Teams')">{{ $season->teams_count }}</x-listing.cell>
                    <x-listing.cell :label="__('Status')">
                        @if ($season->is_current)
                            <span class="inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">{{ __('Current') }}</span>
                        @else
                            <button type="button" wire:click="markAsCurrent('{{ $season->uuid }}')" class="text-sm text-zinc-500 hover:text-zinc-900">
                                {{ __('Mark as current') }}
                            </button>
                        @endif
                    </x-listing.cell>
                    <x-listing.cell actions>
                        <x-listing.actions :edit-href="route('seasons.edit', $season)">
                            <x-slot:delete>
                                <x-listing.delete-button :record-id="$season->uuid" />
                            </x-slot:delete>
                        </x-listing.actions>
                    </x-listing.cell>
                </tr>
            @empty
                <tr class="listing-empty">
                    <td colspan="5" class="px-4 py-8 text-center text-zinc-500">{{ __('No seasons yet.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </x-listing.table>

    <x-listing.pagination :paginator="$this->seasons" />

    <x-slot:modal>
        <x-listing.delete-modal
            name="confirm-delete"
            :title="__('Delete this season?')"
            :message="__('This will permanently delete the season :name.', ['name' => $this->recordToDelete?->name])"
        />
    </x-slot:modal>
</x-listing.page>
