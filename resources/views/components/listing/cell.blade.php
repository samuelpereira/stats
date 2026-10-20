@props([
    'label' => null,
    'primary' => false,
    'actions' => false,
])

@php
    $dataLabel = $primary ? 'primary' : ($actions ? 'actions' : $label);
@endphp

<td
    @if ($dataLabel !== null)
        data-label="{{ $dataLabel }}"
    @endif
    {{ $attributes->class([
        'px-4 py-4',
        'font-semibold' => $primary,
        'text-zinc-600' => ! $primary && ! $actions,
        'sm:text-right' => $actions,
    ]) }}
>
    @if ($actions)
        {{ $slot }}
    @elseif ($primary)
        <div class="min-w-0 max-w-full truncate">{{ $slot }}</div>
    @else
        <div class="min-w-0 flex-1 overflow-hidden break-words max-sm:text-end">{{ $slot }}</div>
    @endif
</td>
