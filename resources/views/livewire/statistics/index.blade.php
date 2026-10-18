<div class="-m-6 flex min-h-dvh flex-col gap-6 bg-white p-6 text-zinc-900 lg:-m-8 lg:p-8">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Statistics') }}</h1>
        <p class="mt-1 text-sm text-zinc-500">{{ __('Games of your club') }}</p>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs">
        <x-listing.table>
            <thead>
                <tr class="border-b border-zinc-200 text-left text-xs font-medium tracking-wide text-zinc-500 uppercase">
                    <th class="px-4 py-3 font-medium">{{ __('Date and time') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('At home') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Away') }}</th>
                    <th class="px-4 py-3 text-right font-medium">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->games as $game)
                    <tr wire:key="game-{{ $game->uuid }}" class="border-b border-zinc-100 last:border-b-0">
                        <x-listing.cell :label="__('Date and time')">{{ $game->date_time->format('d/m/Y H:i') }}</x-listing.cell>
                        <x-listing.cell :label="__('At home')">
                            @if ($game->home_away === \App\Enums\HomeAway::Home)
                                <span class="font-bold text-zinc-900">{{ $game->team->name }}</span>
                            @else
                                {{ $game->opponent }}
                            @endif
                        </x-listing.cell>
                        <x-listing.cell :label="__('Away')">
                            @if ($game->home_away === \App\Enums\HomeAway::Away)
                                <span class="font-bold text-zinc-900">{{ $game->team->name }}</span>
                            @else
                                {{ $game->opponent }}
                            @endif
                        </x-listing.cell>
                        <x-listing.cell actions>
                            <x-statistics.game-actions :game="$game" from="statistics" />
                        </x-listing.cell>
                    </tr>
                @empty
                    <tr class="listing-empty">
                        <td colspan="4" class="px-4 py-8 text-center text-zinc-500">{{ __('No games yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </x-listing.table>
    </div>
</div>
