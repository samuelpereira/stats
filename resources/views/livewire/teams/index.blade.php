<x-listing.page
    :title="__('Teams')"
    :subtitle="__('Teams in each season')"
    :action-href="route('teams.create')"
    :action-label="__('New team')"
>
    <div class="border-b border-zinc-200 px-4 py-4">
        <flux:select wire:model.live="seasonId" :label="__('Season')" class="sm:max-w-xs">
            <flux:select.option value="">{{ __('All seasons') }}</flux:select.option>
            @foreach ($this->seasons as $season)
                <flux:select.option :value="$season->id">{{ $season->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <x-listing.table>
        <thead>
            <tr class="border-b border-zinc-200 text-left text-xs font-medium tracking-wide text-zinc-500 uppercase">
                <th class="px-4 py-3 font-medium">{{ __('Name') }}</th>
                @can(\App\Enums\PermissionName::CreateClubs->value)
                    <th class="px-4 py-3 font-medium">{{ __('Club') }}</th>
                @endcan
                <th class="px-4 py-3 font-medium">{{ __('Season') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Enabled') }}</th>
                <th class="px-4 py-3 text-right font-medium">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($this->teams as $team)
                <tr wire:key="team-{{ $team->uuid }}" class="border-b border-zinc-100 last:border-b-0">
                    <x-listing.cell primary>{{ $team->name }}</x-listing.cell>
                    @can(\App\Enums\PermissionName::CreateClubs->value)
                        <x-listing.cell :label="__('Club')">{{ $team->club->name }}</x-listing.cell>
                    @endcan
                    <x-listing.cell :label="__('Season')">{{ $team->season->name }}</x-listing.cell>
                    <x-listing.cell :label="__('Enabled')">
                        <x-listing.status :enabled="$team->enabled" />
                    </x-listing.cell>
                    <x-listing.cell actions>
                        <x-listing.actions :edit-href="route('teams.edit', $team)" :delete-record-id="$team->uuid">
                            @can('viewSeasonStatistics', $team)
                                <a href="{{ route('teams.season-statistics', $team) }}" wire:navigate class="inline-flex size-9 items-center justify-center rounded-lg border border-zinc-300 text-zinc-800 hover:border-zinc-900" aria-label="{{ __('Season statistics') }}">
                                    <flux:icon name="chart-bar" class="size-4" />
                                </a>
                            @endcan
                        </x-listing.actions>
                    </x-listing.cell>
                </tr>
            @empty
                <tr class="listing-empty">
                    <td colspan="{{ auth()->user()->can(\App\Enums\PermissionName::CreateClubs->value) ? 5 : 4 }}" class="px-4 py-8 text-center text-zinc-500">{{ __('No teams yet.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </x-listing.table>

    <x-listing.pagination :paginator="$this->teams" />

    <x-slot:modal>
        <x-listing.delete-modal
            name="confirm-delete"
            :title="__('Delete this team?')"
            :message="__('This will permanently delete the team :name.', ['name' => $this->recordToDelete?->name])"
        />
    </x-slot:modal>
</x-listing.page>
