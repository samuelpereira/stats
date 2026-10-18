@props([
    'paginator',
])

@if ($paginator->hasPages())
    <flux:pagination :paginator="$paginator" class="px-4 pb-3" />
@endif
