@props([
    'name',
    'photoUrl' => null,
    'emphasis' => false,
])

<div {{ $attributes->class(['flex min-w-0 items-center gap-2']) }}>
    <span class="relative flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-full border border-zinc-200 bg-zinc-50">
        @if ($photoUrl)
            <img src="{{ $photoUrl }}" alt="" class="absolute inset-0 size-full object-cover" />
        @else
            <flux:icon name="user-group" class="size-4 text-zinc-400" />
        @endif
    </span>
    <span @class([
        'truncate',
        'font-bold text-zinc-900' => $emphasis,
    ])>{{ $name }}</span>
</div>
