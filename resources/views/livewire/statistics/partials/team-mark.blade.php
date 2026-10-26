<span @class([
    'pointer-events-none relative flex shrink-0 items-center justify-center overflow-hidden rounded-full border border-zinc-900 bg-white',
    'size-5 sm:size-7' => $small ?? false,
    'size-10 sm:size-14' => ! ($small ?? false),
])>
    @if ($this->game->team->photo_url)
        <img src="{{ $this->game->team->photo_url }}" alt="" class="absolute inset-0 size-full object-cover" />
    @else
        <flux:icon name="user-group" @class(['size-3 sm:size-4' => $small ?? false, 'size-5 sm:size-7' => ! ($small ?? false)]) />
    @endif
</span>
