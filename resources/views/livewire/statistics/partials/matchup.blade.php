<div class="mt-4 rounded-lg border border-zinc-200 bg-white px-4 py-4 shadow-sm sm:px-6">
    <div class="mb-4 flex items-center justify-between gap-3">
        <div>
            <h2 class="text-base font-semibold">{{ __('Matchup') }}</h2>
        </div>
        <div class="flex min-w-0 items-center gap-4 text-xs font-medium text-zinc-500">
            <span class="flex min-w-0 items-center gap-2">
                <span class="size-3 shrink-0 rounded-full" style="background-color: {{ $homeColor }}"></span>
                <span class="truncate">{{ $homeName }}</span>
            </span>
            <span class="flex min-w-0 items-center gap-2">
                <span class="size-3 shrink-0 rounded-full" style="background-color: {{ $awayColor }}"></span>
                <span class="truncate">{{ $awayName }}</span>
            </span>
        </div>
    </div>
    <div class="space-y-4">
        @foreach ($matchups as $matchup)
            <div wire:key="matchup-{{ $matchup['label'] }}">
                <div class="grid items-center gap-3" style="grid-template-columns: 1fr auto 1fr">
                    <p class="min-w-0 text-sm tabular-nums">{{ $matchup['left'] }}</p>
                    <p class="text-center text-xs font-medium tracking-wide text-zinc-500 uppercase">{{ __($matchup['label']) }}</p>
                    <p class="min-w-0 text-right text-sm tabular-nums">{{ $matchup['right'] }}</p>
                </div>
                <div class="mt-2 flex h-1.5 overflow-hidden rounded-full bg-zinc-200">
                    <div class="h-full" style="width: {{ $matchup['leftShare'] }}%; background-color: {{ $homeColor }}"></div>
                    <div class="h-full" style="width: {{ $matchup['rightShare'] }}%; background-color: {{ $awayColor }}"></div>
                </div>
            </div>
        @endforeach
    </div>
</div>
