@props([
    'boxScore',
    'gamesHistory',
    'matchupHistory',
])

<div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs">
    <div class="border-b border-zinc-200 px-4 py-3 sm:px-6">
        <h2 class="text-base font-semibold">{{ __('Season Box Score') }}</h2>
    </div>
    @include('livewire.statistics.partials.box-score-table', [
        'boxScore' => $boxScore,
        'teamName' => $this->team->name,
        'opponentName' => __('Opponents'),
        'showGp' => true,
        'wireKeyPrefix' => 'season-box',
    ])
</div>

<div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs">
    <div class="border-b border-zinc-200 px-4 py-3 sm:px-6">
        <h2 class="text-base font-semibold">{{ __('Games history') }}</h2>
    </div>
    @if ($gamesHistory->isEmpty())
        <p class="px-4 py-8 text-center text-sm text-zinc-500 sm:px-6">{{ __('No played games yet.') }}</p>
    @else
        <ul class="divide-y divide-zinc-100">
            @foreach ($gamesHistory as $game)
                <li wire:key="season-game-{{ $game->uuid }}" class="flex items-center gap-3 px-4 py-3 sm:px-6">
                    <span class="relative flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-zinc-200 bg-zinc-50">
                        @if ($game->opponent_photo_url)
                            <img src="{{ $game->opponent_photo_url }}" alt="" class="absolute inset-0 size-full object-cover" />
                        @else
                            <flux:icon name="user-group" class="size-5 text-zinc-500" />
                        @endif
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-zinc-900">{{ $game->opponent }}</p>
                        <p class="text-sm text-zinc-500">
                            <span @class([
                                'font-medium',
                                'text-green-700' => $game->resultLabel() === 'Win',
                                'text-red-700' => $game->resultLabel() === 'Loss',
                                'text-zinc-600' => $game->resultLabel() === 'Tie',
                            ])>{{ __($game->resultLabel()) }}</span>
                            @if ($game->score())
                                <span class="text-zinc-400">·</span>
                                <span class="tabular-nums">{{ $game->score() }}</span>
                            @endif
                        </p>
                    </div>
                    <p class="shrink-0 text-sm tabular-nums text-zinc-500">{{ $game->date_time->format('d/m/Y') }}</p>
                </li>
            @endforeach
        </ul>
    @endif
</div>

<div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs">
    <div class="border-b border-zinc-200 px-4 py-3 sm:px-6">
        <h2 class="text-base font-semibold">{{ __('Matchup history') }}</h2>
    </div>
    @if ($matchupHistory === [])
        <p class="px-4 py-8 text-center text-sm text-zinc-500 sm:px-6">{{ __('No matchups yet.') }}</p>
    @else
        <ul class="divide-y divide-zinc-100">
            @foreach ($matchupHistory as $matchup)
                <li wire:key="matchup-{{ $matchup['opponent'] }}" class="flex items-center justify-between gap-3 px-4 py-3 sm:px-6">
                    <p class="min-w-0 truncate font-medium text-zinc-900">{{ $matchup['opponent'] }}</p>
                    <p class="shrink-0 text-sm font-semibold tabular-nums text-zinc-900">{{ $matchup['record'] }}</p>
                </li>
            @endforeach
        </ul>
    @endif
</div>
