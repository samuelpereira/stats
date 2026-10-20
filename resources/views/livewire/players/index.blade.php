<x-listing.page
    :title="__('Players')"
    :subtitle="__('Players on each team')"
    :action-href="route('players.create')"
    :action-label="__('New player')"
>
    <div class="border-b border-zinc-200 px-4 py-4">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
            <flux:select wire:model.live="seasonId" :label="__('Season')" class="sm:max-w-xs">
                <flux:select.option value="">{{ __('All seasons') }}</flux:select.option>
                @foreach ($this->seasons as $season)
                    <flux:select.option :value="$season->id">{{ $season->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="teamId" :label="__('Team')" class="sm:max-w-md">
                <flux:select.option value="">{{ __('All teams') }}</flux:select.option>
                @foreach ($this->teamsForFilter as $team)
                    <flux:select.option :value="$team->id">
                        {{ $team->name }} — {{ $team->club->name }}
                    </flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <x-listing.table>
        <thead>
            <tr class="border-b border-zinc-200 text-left text-xs font-medium tracking-wide text-zinc-500 uppercase">
                <th class="px-4 py-3 font-medium">{{ __('Name') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Number') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Team') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Enabled') }}</th>
                <th class="px-4 py-3 text-right font-medium">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($this->players as $player)
                <tr wire:key="player-{{ $player->uuid }}" class="border-b border-zinc-100 last:border-b-0">
                    <x-listing.cell primary>{{ $player->name }}</x-listing.cell>
                    <x-listing.cell :label="__('Number')">{{ $player->number }}</x-listing.cell>
                    <x-listing.cell :label="__('Team')">{{ $player->team->name }}</x-listing.cell>
                    <x-listing.cell :label="__('Enabled')">
                        <x-listing.status :enabled="$player->enabled" />
                    </x-listing.cell>
                    <x-listing.cell actions>
                        <x-listing.actions :edit-href="route('players.edit', $player)" :delete-record-id="$player->uuid" />
                    </x-listing.cell>
                </tr>
            @empty
                <tr class="listing-empty">
                    <td colspan="5" class="px-4 py-8 text-center text-zinc-500">{{ __('No players yet.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </x-listing.table>

    <x-listing.pagination :paginator="$this->players" />

    <x-slot:modal>
        <x-listing.delete-modal
            name="confirm-delete"
            :title="__('Delete this player?')"
            :message="__('This will permanently delete the player :name.', ['name' => $this->recordToDelete?->name])"
        />
    </x-slot:modal>
</x-listing.page>
