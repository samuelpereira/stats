<div class="space-y-6">
    <flux:select wire:model="teamId" :label="__('Team')" required>
        <flux:select.option value="">{{ __('Select a team') }}</flux:select.option>
        @foreach ($this->teams as $team)
            <flux:select.option :value="$team->id">
                {{ $team->name }} — {{ $team->club->name }} ({{ $team->season->name }})
            </flux:select.option>
        @endforeach
    </flux:select>

    <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
        <div class="min-w-0 flex-1">
            <flux:input wire:model="fpbId" :label="__('FPB ID')" type="text" />
        </div>
        <flux:button
            type="button"
            wire:click="fetchFromFpb"
            wire:loading.attr="disabled"
            wire:target="fetchFromFpb"
        >
            <span wire:loading.remove wire:target="fetchFromFpb">{{ __('Fetch from FPB') }}</span>
            <span wire:loading wire:target="fetchFromFpb">{{ __('Importing…') }}</span>
        </flux:button>
    </div>

    <flux:input wire:model="opponent" :label="__('Opponent')" type="text" required />

    <flux:input wire:model="dateTime" :label="__('Date and time')" type="datetime-local" required />

    <flux:input wire:model="place" :label="__('Place')" type="text" />

    <flux:select wire:model="homeAway" :label="__('Home or away')" required>
        <flux:select.option value="">{{ __('Select home or away') }}</flux:select.option>
        @foreach (\App\Enums\HomeAway::cases() as $homeAway)
            <flux:select.option :value="$homeAway->value">{{ __($homeAway->label()) }}</flux:select.option>
        @endforeach
    </flux:select>

    <flux:input wire:model="competition" :label="__('Competition')" type="text" />

    <flux:input wire:model="journey" :label="__('Journey')" type="text" />

    <div class="grid gap-6 sm:grid-cols-2">
        <flux:input wire:model="homePoints" :label="__('Home points')" type="number" min="0" max="999" />
        <flux:input wire:model="awayPoints" :label="__('Away points')" type="number" min="0" max="999" />
    </div>

    <flux:select wire:model="status" :label="__('Status')" required>
        <flux:select.option value="">{{ __('Select a status') }}</flux:select.option>
        @foreach (\App\Enums\GameStatus::cases() as $status)
            <flux:select.option :value="$status->value">{{ __($status->label()) }}</flux:select.option>
        @endforeach
    </flux:select>
</div>
