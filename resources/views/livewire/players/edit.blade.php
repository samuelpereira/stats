<div class="flex h-full w-full flex-1 flex-col gap-4">
    <flux:heading size="xl">{{ __('Edit player') }}</flux:heading>

    <form wire:submit="save" class="w-full max-w-lg space-y-6">
        <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus />

        <flux:select wire:model="teamId" :label="__('Team')" required>
            <flux:select.option value="">{{ __('Select a team') }}</flux:select.option>
            @foreach ($this->teams as $team)
                <flux:select.option :value="$team->id">
                    {{ $team->name }} — {{ $team->club->name }} ({{ $team->season->name }})
                </flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model="number" :label="__('Number')" type="number" min="0" max="99" />

        <flux:input wire:model="position" :label="__('Position')" type="text" />

        <flux:input wire:model="dateOfBirth" :label="__('Date of birth')" type="date" />

        <flux:input wire:model="fpbId" :label="__('FPB ID')" type="text" />

        <flux:input wire:model="order" :label="__('Order')" type="number" min="0" required />

        @if ($player->photo_url)
            <img src="{{ $player->photo_url }}" alt="" class="h-24 w-24 rounded-lg object-cover" />
        @endif

        <flux:input wire:model="photo" :label="__('Photo')" type="file" accept="image/*" />

        <flux:switch wire:model="enabled" :label="__('Enabled')" />

        <div class="flex items-center gap-4">
            <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>

            <flux:button :href="$this->playersIndexUrl()" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>
