<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900">{{ __('Dashboard') }}</h1>
        <p class="mt-1 text-sm text-zinc-500">
            {{ __('Welcome back, :name.', ['name' => auth()->user()->name]) }}
            @if ($this->currentSeason)
                <span class="text-zinc-400">·</span>
                {{ __('Current season') }}: <span class="font-medium text-zinc-700">{{ $this->currentSeason->name }}</span>
            @endif
        </p>
    </div>

    @if ($this->summaryCards !== [])
        <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
            @foreach ($this->summaryCards as $card)
                @if ($card['href'])
                    <a
                        href="{{ $card['href'] }}"
                        wire:navigate
                        class="rounded-xl border border-zinc-200 bg-white px-4 py-4 shadow-xs transition hover:border-zinc-300 hover:shadow-sm"
                    >
                        <p class="text-sm font-medium text-zinc-500">{{ $card['label'] }}</p>
                        <p class="mt-2 text-3xl font-semibold tabular-nums text-zinc-900">{{ $card['value'] }}</p>
                    </a>
                @else
                    <div class="rounded-xl border border-zinc-200 bg-white px-4 py-4 shadow-xs">
                        <p class="text-sm font-medium text-zinc-500">{{ $card['label'] }}</p>
                        <p class="mt-2 text-3xl font-semibold tabular-nums text-zinc-900">{{ $card['value'] }}</p>
                    </div>
                @endif
            @endforeach
        </div>
    @endif

    @if ($this->upcomingGames->isNotEmpty() || $this->recentGames->isNotEmpty())
        <div class="grid gap-4 lg:grid-cols-2">
            @if ($this->upcomingGames->isNotEmpty())
                <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs">
                    <div class="border-b border-zinc-200 px-4 py-3 sm:px-6">
                        <h2 class="text-base font-semibold text-zinc-900">{{ __('Upcoming games') }}</h2>
                    </div>
                    <ul class="divide-y divide-zinc-100">
                        @foreach ($this->upcomingGames as $game)
                            <li wire:key="upcoming-{{ $game->uuid }}" class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                <div class="min-w-0 w-full flex-1">
                                    <x-games.matchup :game="$game" />
                                </div>
                                <x-statistics.game-actions :game="$game" from="dashboard" class="shrink-0" />
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($this->recentGames->isNotEmpty())
                <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs">
                    <div class="border-b border-zinc-200 px-4 py-3 sm:px-6">
                        <h2 class="text-base font-semibold text-zinc-900">{{ __('Recent games') }}</h2>
                    </div>
                    <ul class="divide-y divide-zinc-100">
                        @foreach ($this->recentGames as $game)
                            <li wire:key="recent-{{ $game->uuid }}" class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                <div class="min-w-0 w-full flex-1">
                                    <x-games.matchup :game="$game" />
                                </div>
                                <x-statistics.game-actions :game="$game" from="dashboard" class="shrink-0" />
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    @elseif ($this->canCollectStatistics())
        <div class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50 px-4 py-8 text-center">
            <p class="text-sm text-zinc-600">{{ __('No games scheduled yet for the current season.') }}</p>
            <a href="{{ route('statistics.index') }}" wire:navigate class="mt-3 inline-flex text-sm font-medium text-zinc-900 hover:underline">
                {{ __('Games') }}
            </a>
        </div>
    @endif
</div>
