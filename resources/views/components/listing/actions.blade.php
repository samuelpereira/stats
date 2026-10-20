@props([
    'editHref',
    'deleteRecordId' => null,
])

<div {{ $attributes->class(['flex items-center justify-end gap-2']) }}>
    {{ $slot }}

    <flux:dropdown position="bottom" align="end">
        <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" :aria-label="__('Actions')" />
        <flux:menu>
            <flux:menu.item :href="$editHref" icon="pencil" wire:navigate>
                {{ __('Edit') }}
            </flux:menu.item>
            @if ($deleteRecordId !== null)
                <flux:menu.item
                    as="button"
                    type="button"
                    wire:click="confirmDelete('{{ $deleteRecordId }}')"
                    icon="trash"
                    variant="danger"
                >
                    {{ __('Delete') }}
                </flux:menu.item>
            @endif
        </flux:menu>
    </flux:dropdown>
</div>
