@props([
    'game',
    'from' => 'statistics',
])

<div {{ $attributes->class(['flex items-center justify-end gap-2']) }}>
    @can('collect', $game)
        @if ($game->hasCollectedStatistics())
            <a href="{{ route('statistics.show', ['game' => $game, 'from' => $from]) }}" wire:navigate class="inline-flex size-9 items-center justify-center rounded-lg border border-zinc-300 text-zinc-800 hover:border-zinc-900" aria-label="{{ __('Statistics') }}">
                <flux:icon name="chart-bar" class="size-4" />
            </a>
        @endif
        <a href="{{ route('statistics.record', $game) }}" wire:navigate class="inline-flex size-9 items-center justify-center rounded-lg border border-zinc-300 text-zinc-800 hover:border-zinc-900" aria-label="{{ __('Collect') }}">
            <flux:icon name="clipboard-document-list" class="size-4" />
        </a>
    @endcan
</div>
