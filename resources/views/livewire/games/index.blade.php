<x-listing.page
    :title="__('Games')"
    :subtitle="__('Games of the teams')"
    :action-href="route('games.create')"
    :action-label="__('New game')"
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
                <th class="px-4 py-3 font-medium">{{ __('Date and time') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('At home') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Away') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Score') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                <th class="px-4 py-3 text-right font-medium">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($this->games as $game)
                <tr wire:key="game-{{ $game->uuid }}" class="border-b border-zinc-100 last:border-b-0">
                    <x-listing.cell :label="__('Date and time')">{{ $game->date_time->format('d/m/Y H:i') }}</x-listing.cell>
                    <x-listing.cell :label="__('At home')">
                        @if ($game->home_away === \App\Enums\HomeAway::Home)
                            <x-games.side :name="$game->team->name" :photo-url="$game->team->photo_url" emphasis />
                        @else
                            <x-games.side :name="$game->opponent" :photo-url="$game->opponent_photo_url" />
                        @endif
                    </x-listing.cell>
                    <x-listing.cell :label="__('Away')">
                        @if ($game->home_away === \App\Enums\HomeAway::Away)
                            <x-games.side :name="$game->team->name" :photo-url="$game->team->photo_url" emphasis />
                        @else
                            <x-games.side :name="$game->opponent" :photo-url="$game->opponent_photo_url" />
                        @endif
                    </x-listing.cell>
                    <x-listing.cell :label="__('Score')">{{ $game->score() ?? '—' }}</x-listing.cell>
                    <x-listing.cell :label="__('Status')">
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $game->status->badgeClasses() }}">
                            {{ __($game->status->label()) }}
                        </span>
                    </x-listing.cell>
                    <x-listing.cell actions>
                        <x-listing.actions :edit-href="route('games.edit', $game)" :delete-record-id="$game->uuid">
                            <x-statistics.game-actions :game="$game" from="games" />
                        </x-listing.actions>
                    </x-listing.cell>
                </tr>
            @empty
                <tr class="listing-empty">
                    <td colspan="6" class="px-4 py-8 text-center text-zinc-500">{{ __('No games yet.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </x-listing.table>

    <x-listing.pagination :paginator="$this->games" />

    <x-slot:modal>
        <x-listing.delete-modal
            name="confirm-delete"
            :title="__('Delete this game?')"
            :message="__('This will permanently delete the game against :name.', ['name' => $this->recordToDelete?->opponent])"
        />
    </x-slot:modal>
</x-listing.page>
