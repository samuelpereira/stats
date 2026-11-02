@props(['game'])

<div class="flex min-w-0 items-center text-zinc-600">
    <div class="min-w-0 flex-1 overflow-hidden">
        @if ($game->home_away === \App\Enums\HomeAway::Home)
            <x-games.side :name="$game->team->name" :photo-url="$game->team->photo_url" emphasis logo-last :justify-end-on-mobile="false" />
        @else
            <x-games.side :name="$game->opponent" :photo-url="$game->opponent_photo_url" logo-last :justify-end-on-mobile="false" />
        @endif
    </div>
    <span class="shrink-0 px-1.5 text-zinc-400">·</span>
    <div class="min-w-0 flex-1 overflow-hidden">
        @if ($game->home_away === \App\Enums\HomeAway::Away)
            <x-games.side :name="$game->team->name" :photo-url="$game->team->photo_url" emphasis :justify-end-on-mobile="false" />
        @else
            <x-games.side :name="$game->opponent" :photo-url="$game->opponent_photo_url" :justify-end-on-mobile="false" />
        @endif
    </div>
</div>
<p class="mt-1 text-center text-xs text-zinc-500">
    {{ $game->date_time->format('d/m/Y H:i') }}
    @if ($game->score())
        <span class="text-zinc-400">·</span>
        <span class="font-bold text-zinc-900">{{ $game->score() }}</span>
    @endif
</p>
