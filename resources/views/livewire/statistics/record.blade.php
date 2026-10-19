@php
    $pendingStat = $pendingType ? \App\Enums\StatType::from($pendingType) : null;
@endphp

<div data-statistics-record x-init="$store.collectQueue.bind($wire)">
@if (! $this->lineupReady)
    <div wire:key="lineup-setup" class="flex h-dvh min-h-0 flex-col bg-zinc-100 text-zinc-900">
        @if ($editingSquad || ! $this->squadReady)
            <header class="shrink-0 border-b border-zinc-200 bg-white">
                <div class="mx-auto grid w-full grid-cols-3 items-center px-4 py-3">
                    <button type="button" wire:click="leaveSquadSelection" class="inline-flex items-center gap-2 text-sm font-medium text-zinc-900">
                        <flux:icon name="arrow-left" class="size-4" />
                        {{ __('Back') }}
                    </button>
                    <h1 class="truncate text-center text-sm font-medium sm:text-base">{{ __('Players in this game') }}</h1>
                    <span></span>
                </div>
            </header>
            <p class="px-4 py-3 text-center text-sm text-zinc-500">{{ __('Choose at least 5 players.') }}</p>
        @else
            <header class="shrink-0 border-b border-zinc-200 bg-white">
                <div class="mx-auto grid w-full grid-cols-3 items-center px-4 py-3">
                    <button type="button" wire:click="changeLineup" class="inline-flex items-center gap-2 text-sm font-medium text-zinc-900">
                        <flux:icon name="arrow-left" class="size-4" />
                        {{ __('Back') }}
                    </button>
                    <h1 class="truncate text-center text-sm font-medium sm:text-base">{{ __('Starting five') }}</h1>
                    <span></span>
                </div>
            </header>
            <p class="px-4 py-3 text-center text-sm text-zinc-500">{{ __('Choose the 5 players who start.') }}</p>
        @endif
        @error('squad')
            <p class="px-4 text-center text-xs text-red-700">{{ $message }}</p>
        @enderror
        @error('starters')
            <p class="px-4 text-center text-xs text-red-700">{{ $message }}</p>
        @enderror
        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-3 sm:px-6">
            <div class="flex flex-col gap-2">
                @foreach (($editingSquad || ! $this->squadReady) ? $this->players : $this->attachedPlayers as $player)
                    <button
                        type="button"
                        wire:key="setup-player-{{ $player->id }}"
                        wire:click="{{ ($editingSquad || ! $this->squadReady) ? 'toggleSquadPlayer' : 'toggleStarter' }}({{ $player->id }})"
                        @class([
                            'flex items-center gap-3 rounded-lg border px-3 py-2 text-left',
                            'border-zinc-900 bg-amber-100' => in_array($player->id, ($editingSquad || ! $this->squadReady) ? $squadIds : $starterIds, true),
                            'border-zinc-300 bg-white hover:border-zinc-900' => ! in_array($player->id, ($editingSquad || ! $this->squadReady) ? $squadIds : $starterIds, true),
                        ])
                    >
                        <span class="pointer-events-none flex size-10 shrink-0 items-center justify-center rounded-md border border-zinc-300 text-sm font-semibold">{{ $player->number ?? '—' }}</span>
                        <span class="min-w-0 flex-1 truncate text-sm">{{ $player->name }}</span>
                    </button>
                @endforeach
            </div>
        </div>
        <div class="flex items-center justify-end gap-3 border-t border-zinc-200 px-3 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
            @if ($editingSquad || ! $this->squadReady)
                <button type="button" wire:click="saveSquad" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white">{{ __('Continue') }}</button>
            @else
                <button type="button" wire:click="saveStarters" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white">{{ __('Confirm') }}</button>
            @endif
        </div>
    </div>
@elseif ($configuringGame)
    <div wire:key="game-settings" class="flex h-dvh min-h-0 flex-col bg-zinc-100 text-zinc-900">
        <header class="shrink-0 border-b border-zinc-200 bg-white">
            <div class="mx-auto grid w-full grid-cols-3 items-center px-4 py-3">
                <button type="button" wire:click="$set('configuringGame', false)" class="inline-flex items-center gap-2 text-sm font-medium text-zinc-900">
                    <flux:icon name="arrow-left" class="size-4" />
                    {{ __('Back') }}
                </button>
                <h1 class="truncate text-center text-sm font-medium sm:text-base">{{ __('Configuration') }}</h1>
                <span></span>
            </div>
        </header>
        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-3 sm:px-6">
            @php
                $homeSideName = $this->game->home_away === \App\Enums\HomeAway::Home ? $teamName : $opponentName;
                $awaySideName = $this->game->home_away === \App\Enums\HomeAway::Home ? $opponentName : $teamName;
            @endphp
            <div class="grid grid-cols-1 items-start gap-6 md:grid-cols-2">
                <div class="mx-auto flex w-full flex-col gap-3 md:w-4/5">
                    <label>
                        <span class="text-sm text-zinc-500">{{ __('Period (minutes)') }}</span>
                        <input wire:model="quarterMinutes" type="number" min="1" max="99" inputmode="numeric" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-center text-lg tabular-nums" />
                        @error('quarterMinutes')
                            <span class="mt-1 block text-xs text-red-700">{{ $message }}</span>
                        @enderror
                    </label>
                    <label>
                        <span class="text-sm text-zinc-500">{{ __('Overtime (minutes)') }}</span>
                        <input wire:model="overtimeMinutes" type="number" min="1" max="99" inputmode="numeric" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-center text-lg tabular-nums" />
                        @error('overtimeMinutes')
                            <span class="mt-1 block text-xs text-red-700">{{ $message }}</span>
                        @enderror
                    </label>
                    <label class="flex items-start gap-3 rounded-lg border border-zinc-300 bg-white px-3 py-3">
                        <input wire:model="markShotLocation" type="checkbox" class="mt-1 size-4 rounded border-zinc-300" />
                        <span>
                            <span class="block text-sm font-medium">{{ __('Mark shot location on court') }}</span>
                            <span class="mt-0.5 block text-xs text-zinc-500">{{ __('After 2P or 3P, tap the half court where the shot was taken.') }}</span>
                        </span>
                    </label>
                </div>
                <div class="mx-auto flex w-full flex-col items-center gap-4 md:w-4/5">
                    <div class="w-full">
                        <p class="truncate text-center text-sm text-zinc-500">{{ $homeSideName }} · {{ __('At home') }}</p>
                        <div class="mt-2 grid w-full gap-2" style="grid-template-columns: repeat(8, minmax(0, 1fr));">
                            @foreach (\App\Enums\TeamColor::cases() as $color)
                                <button
                                    type="button"
                                    wire:key="home-color-{{ $color->value }}"
                                    wire:click="$set('homeColor', '{{ $color->value }}')"
                                    aria-label="{{ __($color->label()) }}"
                                    aria-pressed="{{ $homeColor === $color->value ? 'true' : 'false' }}"
                                    class="rounded-full border border-zinc-300"
                                    @style([
                                        'aspect-ratio: 1',
                                        'width: 100%',
                                        'max-width: 2rem',
                                        'justify-self: center',
                                        'background-color: '.$color->hex(),
                                        'box-shadow: 0 0 0 2px #fff, 0 0 0 4px #18181b' => $homeColor === $color->value,
                                    ])
                                ></button>
                            @endforeach
                        </div>
                        @error('homeColor')
                            <span class="mt-1 block text-center text-xs text-red-700">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="w-full">
                        <p class="truncate text-center text-sm text-zinc-500">{{ $awaySideName }} · {{ __('Away') }}</p>
                        <div class="mt-2 grid w-full gap-2" style="grid-template-columns: repeat(8, minmax(0, 1fr));">
                            @foreach (\App\Enums\TeamColor::cases() as $color)
                                <button
                                    type="button"
                                    wire:key="away-color-{{ $color->value }}"
                                    wire:click="$set('awayColor', '{{ $color->value }}')"
                                    aria-label="{{ __($color->label()) }}"
                                    aria-pressed="{{ $awayColor === $color->value ? 'true' : 'false' }}"
                                    class="rounded-full border border-zinc-300"
                                    @style([
                                        'aspect-ratio: 1',
                                        'width: 100%',
                                        'max-width: 2rem',
                                        'justify-self: center',
                                        'background-color: '.$color->hex(),
                                        'box-shadow: 0 0 0 2px #fff, 0 0 0 4px #18181b' => $awayColor === $color->value,
                                    ])
                                ></button>
                            @endforeach
                        </div>
                        @error('awayColor')
                            <span class="mt-1 block text-center text-xs text-red-700">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </div>
            @error('settingsSquad')
                <p class="px-4 pt-3 text-center text-xs text-red-700">{{ $message }}</p>
            @enderror
            @php
                $courtIds = $this->lineup['court'];
                $protectedIds = $this->protectedSquadIds;
                $settingsOnCourt = $this->players->filter(fn ($player) => in_array($player->id, $courtIds, true));
                $settingsOthers = $this->players->reject(fn ($player) => in_array($player->id, $courtIds, true));
            @endphp
            <div class="mt-4 flex flex-col gap-2 border-t border-zinc-200 pt-4">
                @foreach ($settingsOnCourt as $player)
                    <div wire:key="settings-court-{{ $player->id }}" class="flex items-center gap-3 rounded-lg border border-zinc-900 bg-amber-100 px-3 py-2">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-md border border-zinc-300 text-sm font-semibold">{{ $player->number ?? '—' }}</span>
                        <span class="min-w-0 flex-1 truncate text-sm">{{ $player->name }}</span>
                        <span class="shrink-0 text-[11px] font-medium text-zinc-500">{{ __('On court') }}</span>
                    </div>
                @endforeach
            </div>
            @if ($settingsOthers->isNotEmpty())
                <div class="mt-4 flex flex-col gap-2 border-t border-zinc-200 pt-3">
                    @foreach ($settingsOthers as $player)
                        @if (in_array($player->id, $protectedIds, true))
                            <div wire:key="settings-kept-{{ $player->id }}" class="flex items-center gap-3 rounded-lg border border-zinc-900 bg-amber-100 px-3 py-2">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-md border border-zinc-300 text-sm font-semibold">{{ $player->number ?? '—' }}</span>
                                <span class="min-w-0 flex-1 truncate text-sm">{{ $player->name }}</span>
                            </div>
                        @else
                            <button
                                type="button"
                                wire:key="settings-player-{{ $player->id }}"
                                wire:click="toggleSettingsPlayer({{ $player->id }})"
                                @class([
                                    'flex items-center gap-3 rounded-lg border px-3 py-2 text-left',
                                    'border-zinc-900 bg-amber-100' => in_array($player->id, $settingsSquadIds, true),
                                    'border-zinc-300 bg-white hover:border-zinc-900' => ! in_array($player->id, $settingsSquadIds, true),
                                ])
                            >
                                <span class="pointer-events-none flex size-10 shrink-0 items-center justify-center rounded-md border border-zinc-300 text-sm font-semibold">{{ $player->number ?? '—' }}</span>
                                <span class="min-w-0 flex-1 truncate text-sm">{{ $player->name }}</span>
                            </button>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
        <div class="flex items-center justify-end gap-3 border-t border-zinc-200 px-3 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
            <button type="button" wire:click="saveGameSettings" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white">{{ __('Save') }}</button>
        </div>
    </div>
@elseif ($substituting)
    <div wire:key="substitution" class="flex h-dvh min-h-0 flex-col bg-zinc-100 text-zinc-900">
        <header class="shrink-0 border-b border-zinc-200 bg-white">
            <div class="mx-auto grid w-full grid-cols-3 items-center px-4 py-3">
                <button type="button" wire:click="cancel" class="inline-flex items-center gap-2 text-sm font-medium text-zinc-900">
                    <flux:icon name="arrow-left" class="size-4" />
                    {{ __('Back') }}
                </button>
                <h1 class="truncate text-center text-sm font-medium sm:text-base">{{ __('Substitution') }}</h1>
                <span></span>
            </div>
        </header>
        <p class="px-4 text-center text-sm text-zinc-500">{{ __('Mark 5 players.') }}</p>
        @error('court')
            <p class="px-4 pt-1 text-center text-xs text-red-700">{{ $message }}</p>
        @enderror
        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-3 sm:px-6">
            @foreach (['court' => $this->courtPlayers, 'bench' => $this->benchPlayers] as $group => $players)
                @continue($players->isEmpty())
                <div @class(['mt-4 border-t border-zinc-200 pt-3' => $group === 'bench'])>
                    <div class="flex flex-col gap-2 sm:grid sm:grid-cols-2 sm:content-start sm:gap-3 lg:grid-cols-3">
                        @foreach ($players as $player)
                            <button
                                type="button"
                                wire:key="sub-{{ $group }}-{{ $player->id }}"
                                wire:click="toggleCourtSelection({{ $player->id }})"
                                @class([
                                    'flex w-full items-center gap-3 rounded-lg border px-3 py-3 text-left sm:rounded-xl sm:py-4',
                                    'border-zinc-900 bg-amber-100' => in_array($player->id, $courtSelection, true),
                                    'border-zinc-300 bg-white hover:border-zinc-900' => ! in_array($player->id, $courtSelection, true),
                                ])
                            >
                                <span class="pointer-events-none flex size-10 shrink-0 items-center justify-center rounded-full border border-zinc-900 bg-zinc-900 text-base font-bold tabular-nums text-white sm:size-14 sm:text-lg">{{ $player->number ?? '—' }}</span>
                                <span class="min-w-0 flex-1 truncate text-sm font-semibold sm:text-base">{{ $player->name }}</span>
                                @php($personalFouls = $this->fouls['players'][$player->id] ?? 0)
                                @if ($personalFouls > 0)
                                    <span class="flex shrink-0 items-center gap-1">
                                        @for ($mark = 0; $mark < $personalFouls; $mark++)
                                            <span @class([
                                                'size-2.5 rounded-sm',
                                                'bg-green-700' => $personalFouls <= 3,
                                                'bg-orange-600' => $personalFouls === 4,
                                                'bg-red-700' => $personalFouls >= 5,
                                            ])></span>
                                        @endfor
                                    </span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
        <div class="flex justify-end border-t border-zinc-200 px-3 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
            <button type="button" x-on:click="$wire.confirmSubstitutions($store.gameClock?.formatted ?? null)" wire:loading.attr="disabled" wire:target="confirmSubstitutions" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white">{{ __('Confirm') }}</button>
        </div>
    </div>
@else
<div class="hidden" data-collect-baseline>@json($collectQueue)</div>
<div wire:key="stat-pad" class="flex h-dvh min-h-0 flex-col overflow-hidden bg-zinc-100 text-zinc-900 collect-desktop:hidden">
    @include('livewire.statistics.partials.scoreboard', ['bindGameClock' => true])

    <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
        <div x-show="! $store.collectPane || $store.collectPane.name === 'scoring'" class="flex min-h-0 flex-1 flex-col overflow-hidden">
            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-2">
            <div class="grid grid-cols-2 grid-rows-7 gap-3 portrait:grid-rows-7 portrait:gap-3">
                @foreach ([
                    [\App\Enums\StatType::TwoPointMade, '2P', 'made'],
                    [\App\Enums\StatType::TwoPointMissed, '2P', 'missed'],
                    [\App\Enums\StatType::ThreePointMade, '3P', 'made'],
                    [\App\Enums\StatType::ThreePointMissed, '3P', 'missed'],
                    [\App\Enums\StatType::FreeThrowMade, '1P', 'made'],
                    [\App\Enums\StatType::FreeThrowMissed, '1P', 'missed'],
                    [\App\Enums\StatType::DefensiveRebound, \App\Enums\StatType::DefensiveRebound->buttonLines(), 'plain'],
                    [\App\Enums\StatType::OffensiveRebound, \App\Enums\StatType::OffensiveRebound->buttonLines(), 'plain'],
                    [\App\Enums\StatType::Turnover, \App\Enums\StatType::Turnover->buttonLines(), 'plain'],
                    [\App\Enums\StatType::Steal, \App\Enums\StatType::Steal->buttonLines(), 'plain'],
                    [\App\Enums\StatType::Assist, 'Assist', 'plain'],
                    [\App\Enums\StatType::Block, \App\Enums\StatType::Block->buttonLines(), 'plain'],
                    [\App\Enums\StatType::Substitution, $substitutionLabel, 'alert'],
                    [\App\Enums\StatType::Foul, 'Foul', 'plain'],
                ] as [$stat, $statLabel, $tone])
                    <button
                        type="button"
                        x-on:click="if ('{{ $stat->value }}' === 'foul' || '{{ $stat->value }}' === 'substitution') $store.gameClock.pause(); $store.collectQueue.select('{{ $stat->value }}')"
                        x-bind:disabled="! $store.collectQueue.statEnabled('{{ $stat->value }}')"
                        x-bind:class="{ 'opacity-40': ! $store.collectQueue.statEnabled('{{ $stat->value }}'), 'ring-2 ring-zinc-900 ring-inset': $store.collectQueue.pendingType === '{{ $stat->value }}' }"
                        @class([
                            'flex min-h-0 flex-col items-center justify-center gap-1 rounded-lg border bg-white px-1 py-2 text-sm font-semibold tracking-wide leading-none shadow-sm portrait:h-[50px] portrait:min-h-[50px] portrait:max-h-[50px] portrait:gap-0.5 portrait:px-2 portrait:py-1',
                            'border-green-700 text-green-700' => $tone === 'made',
                            'border-red-700 text-red-700' => $tone === 'missed',
                            'border-red-700 text-zinc-900' => $tone === 'alert',
                            'border-zinc-300 text-zinc-900' => $tone === 'plain',
                        ])
                    >@foreach ((array) $statLabel as $line)@continue(__($line) === '')<span>{{ __($line) }}</span>@endforeach</button>
                @endforeach
            </div>
            </div>
        </div>

        <div x-cloak x-show="$store.collectPane.name === 'roster'" class="min-h-0 flex-1 space-y-2 overflow-y-auto overscroll-contain px-3 py-3">
            @foreach ($this->squad as $player)
                @php($played = $this->lineup['seconds'][$player->id] ?? 0)
                @php($onCourt = in_array($player->id, $this->lineup['court'], true))
                <div wire:key="mobile-player-{{ $player->id }}" class="flex items-center gap-3 rounded-lg border bg-white px-3 py-2" :class="$store.collectQueue.onCourt({{ $player->id }}) ? 'border-zinc-900' : 'border-zinc-200'">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-md border border-zinc-300 text-sm font-semibold">{{ $player->number ?? '—' }}</span>
                    <span class="min-w-0 flex-1 truncate text-sm">{{ $player->name }}</span>
                    <span x-cloak x-show="$store.collectQueue.onCourt({{ $player->id }})" class="shrink-0 text-[11px] font-medium text-zinc-500">{{ __('On court') }}</span>
                    <span class="shrink-0 text-xs font-medium tabular-nums text-zinc-500">{{ intdiv($played, 60) }}:{{ sprintf('%02d', $played % 60) }}</span>
                    @php($personalFouls = $this->fouls['players'][$player->id] ?? 0)
                    <span class="shrink-0 text-sm font-medium tabular-nums" :class="$store.collectQueue.foulTone($store.collectQueue.foulCount({{ $player->id }}))"><span x-text="$store.collectQueue.foulCount({{ $player->id }})">{{ $personalFouls }}</span> {{ __('FP') }}</span>
                </div>
            @endforeach
        </div>

        <div x-cloak x-show="$store.collectPane.name === 'record'" class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-4">
            @include('livewire.statistics.log', ['lines' => $this->lines, 'keyPrefix' => 'mobile-line', 'homeColor' => $this->game->home_color->hex(), 'awayColor' => $this->game->away_color->hex()])
        </div>

        <div class="mt-auto shrink-0 border-t border-zinc-200 bg-white pb-[max(0.5rem,env(safe-area-inset-bottom))]">
            <div class="grid grid-cols-3 border-b border-zinc-100">
                <button
                    type="button"
                    @click="$store.collectPane.name = 'scoring'"
                    class="border-t-2 py-2.5 text-sm font-medium"
                    :class="$store.collectPane.name === 'scoring' || ! $store.collectPane.name ? 'border-zinc-900 text-zinc-900' : 'border-transparent text-zinc-500'"
                >{{ __('Scoring') }}</button>
                <button
                    type="button"
                    @click="$store.collectPane.name = 'roster'"
                    class="border-t-2 py-2.5 text-sm font-medium"
                    :class="$store.collectPane.name === 'roster' ? 'border-zinc-900 text-zinc-900' : 'border-transparent text-zinc-500'"
                >{{ __('Roster') }}</button>
                <button
                    type="button"
                    @click="$store.collectPane.name = 'record'"
                    class="border-t-2 py-2.5 text-sm font-medium"
                    :class="$store.collectPane.name === 'record' ? 'border-zinc-900 text-zinc-900' : 'border-transparent text-zinc-500'"
                >{{ __('Game log') }}</button>
            </div>
            <div class="flex items-center justify-center gap-3 p-2">
                <button type="button" wire:click="attemptExit" x-bind:disabled="! $store.collectQueue.online" class="flex size-12 items-center justify-center rounded-xl bg-zinc-100 text-zinc-700 shadow-sm disabled:text-zinc-400" aria-label="{{ __('Back') }}">
                    <flux:icon name="arrow-left" class="size-5" />
                </button>
                <button type="button" x-bind:disabled="! $store.collectQueue.online" x-on:click="window.location.assign(@js(route('statistics.show', ['game' => $this->game, 'from' => 'record'])))" class="flex size-12 items-center justify-center rounded-xl bg-zinc-100 text-zinc-700 shadow-sm disabled:text-zinc-400" aria-label="{{ __('Statistics') }}">
                    <flux:icon name="chart-bar" class="size-5" />
                </button>
                <button type="button" wire:click="openGameSettings" x-bind:disabled="! $store.collectQueue.online" class="flex size-12 items-center justify-center rounded-xl bg-zinc-100 text-zinc-700 shadow-sm disabled:text-zinc-400" aria-label="{{ __('Game settings') }}">
                    <flux:icon name="cog-6-tooth" class="size-5" />
                </button>
                <button type="button" x-on:click="$store.collectQueue.undo()" x-bind:disabled="! $store.collectQueue.canUndo()" x-bind:class="$store.collectQueue.canUndo() ? 'text-zinc-700' : 'text-zinc-500'" class="flex size-12 items-center justify-center rounded-xl bg-zinc-100 shadow-sm" aria-label="{{ __('Undo') }}">
                    <flux:icon name="arrow-uturn-left" class="size-5" />
                </button>
            </div>
        </div>
    </div>
</div>

<div wire:key="stat-pad-desktop" class="hidden h-dvh overflow-hidden bg-zinc-100 text-zinc-900 collect-desktop:grid collect-desktop:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)_minmax(0,1fr)]">
    <section class="flex min-h-0 flex-col border-r border-zinc-200 p-2">
        <div class="grid h-4/5 min-h-0 grid-cols-2 grid-rows-7 gap-3">
        @foreach ([
            [\App\Enums\StatType::TwoPointMade, '2P', 'made'],
            [\App\Enums\StatType::TwoPointMissed, '2P', 'missed'],
            [\App\Enums\StatType::ThreePointMade, '3P', 'made'],
            [\App\Enums\StatType::ThreePointMissed, '3P', 'missed'],
            [\App\Enums\StatType::FreeThrowMade, '1P', 'made'],
            [\App\Enums\StatType::FreeThrowMissed, '1P', 'missed'],
            [\App\Enums\StatType::DefensiveRebound, \App\Enums\StatType::DefensiveRebound->buttonLines(), 'plain'],
            [\App\Enums\StatType::OffensiveRebound, \App\Enums\StatType::OffensiveRebound->buttonLines(), 'plain'],
            [\App\Enums\StatType::Turnover, \App\Enums\StatType::Turnover->buttonLines(), 'plain'],
            [\App\Enums\StatType::Steal, \App\Enums\StatType::Steal->buttonLines(), 'plain'],
            [\App\Enums\StatType::Assist, 'Assist', 'plain'],
            [\App\Enums\StatType::Block, \App\Enums\StatType::Block->buttonLines(), 'plain'],
            [\App\Enums\StatType::Substitution, $substitutionLabel, 'alert'],
            [\App\Enums\StatType::Foul, 'Foul', 'plain'],
        ] as [$stat, $statLabel, $tone])
            <button
                type="button"
                x-on:click="if ('{{ $stat->value }}' === 'foul' || '{{ $stat->value }}' === 'substitution') $store.gameClock.pause(); $store.collectQueue.select('{{ $stat->value }}')"
                x-bind:disabled="! $store.collectQueue.statEnabled('{{ $stat->value }}')"
                x-bind:class="{ 'opacity-40': ! $store.collectQueue.statEnabled('{{ $stat->value }}'), 'ring-2 ring-zinc-900 ring-inset': $store.collectQueue.pendingType === '{{ $stat->value }}' }"
                @class([
                    'flex flex-col items-center justify-center gap-1 rounded-lg border bg-white text-sm font-semibold tracking-wide leading-none shadow-sm',
                    'border-green-700 text-green-700' => $tone === 'made',
                    'border-red-700 text-red-700' => $tone === 'missed',
                    'border-red-700 text-zinc-900' => $tone === 'alert',
                    'border-zinc-300 text-zinc-900' => $tone === 'plain',
                ])
            >@foreach ((array) $statLabel as $line)@continue(__($line) === '')<span>{{ __($line) }}</span>@endforeach</button>
        @endforeach
        </div>
        <div class="flex min-h-0 flex-1 items-end justify-center gap-3">
            <button type="button" wire:click="attemptExit" x-bind:disabled="! $store.collectQueue.online" class="flex size-12 items-center justify-center rounded-xl bg-white text-zinc-700 shadow-sm disabled:text-zinc-400" aria-label="{{ __('Back') }}">
                <flux:icon name="arrow-left" class="size-5" />
            </button>
            <button type="button" x-bind:disabled="! $store.collectQueue.online" x-on:click="window.location.assign(@js(route('statistics.show', ['game' => $this->game, 'from' => 'record'])))" class="flex size-12 items-center justify-center rounded-xl bg-white text-zinc-700 shadow-sm disabled:text-zinc-400" aria-label="{{ __('Statistics') }}">
                <flux:icon name="chart-bar" class="size-5" />
            </button>
            <button type="button" wire:click="openGameSettings" x-bind:disabled="! $store.collectQueue.online" class="flex size-12 items-center justify-center rounded-xl bg-white text-zinc-700 shadow-sm disabled:text-zinc-400" aria-label="{{ __('Game settings') }}">
                <flux:icon name="cog-6-tooth" class="size-5" />
            </button>
            <button type="button" x-on:click="$store.collectQueue.undo()" x-bind:disabled="! $store.collectQueue.canUndo()" x-bind:class="$store.collectQueue.canUndo() ? 'text-zinc-700' : 'text-zinc-500'" class="flex size-12 items-center justify-center rounded-xl bg-white shadow-sm" aria-label="{{ __('Undo') }}">
                <flux:icon name="arrow-uturn-left" class="size-5" />
            </button>
        </div>
    </section>

    <section class="flex min-h-0 flex-col border-r border-zinc-200">
        @include('livewire.statistics.partials.scoreboard', ['bindGameClock' => true])

        <div class="flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto px-4 py-4">
            @php($seenCourt = false)
            @php($benchStarted = false)
            @foreach ($this->squad as $player)
                @php($played = $this->lineup['seconds'][$player->id] ?? 0)
                @php($onCourt = in_array($player->id, $this->lineup['court'], true))
                @php($firstBench = $seenCourt && ! $onCourt && ! $benchStarted)
                @php($seenCourt = $seenCourt || $onCourt)
                @php($benchStarted = $benchStarted || ! $onCourt)
                <div wire:key="lineup-{{ $player->id }}" @class(['mt-2' => $firstBench, 'flex items-center gap-3 rounded-lg border bg-white px-3 py-2']) :class="$store.collectQueue.onCourt({{ $player->id }}) ? 'border-zinc-900' : 'border-zinc-200'">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-md border border-zinc-300 text-sm font-semibold">
                        {{ $player->number ?? '—' }}
                    </span>
                    <span class="min-w-0 flex-1 truncate text-sm">{{ $player->name }}</span>
                    <span x-cloak x-show="$store.collectQueue.onCourt({{ $player->id }})" class="shrink-0 text-[11px] font-medium text-zinc-500">{{ __('On court') }}</span>
                    <span class="shrink-0 text-xs font-medium tabular-nums text-zinc-500">{{ intdiv($played, 60) }}:{{ sprintf('%02d', $played % 60) }}</span>
                    @php($personalFouls = $this->fouls['players'][$player->id] ?? 0)
                    <span class="shrink-0 text-sm font-medium tabular-nums" :class="$store.collectQueue.foulTone($store.collectQueue.foulCount({{ $player->id }}))"><span x-text="$store.collectQueue.foulCount({{ $player->id }})">{{ $personalFouls }}</span> {{ __('FP') }}</span>
                </div>
            @endforeach
        </div>
    </section>

    <section class="min-h-0 overflow-y-auto overscroll-contain px-3 py-4">
        @include('livewire.statistics.log', ['lines' => $this->lines, 'keyPrefix' => 'desktop-line', 'homeColor' => $this->game->home_color->hex(), 'awayColor' => $this->game->away_color->hex()])
    </section>
</div>

<div x-cloak x-show="$store.collectQueue.choosingPlayer" style="z-index: 80" class="fixed inset-0 flex h-dvh min-h-0 flex-col bg-white text-zinc-900">
    <header class="shrink-0 border-b border-zinc-200 bg-white">
        <div class="mx-auto grid w-full grid-cols-3 items-center px-4 py-3">
            <button type="button" x-on:click="$store.collectQueue.closePicker()" class="inline-flex items-center gap-2 text-sm font-medium text-zinc-900">
                <flux:icon name="arrow-left" class="size-4" />
                {{ __('Back') }}
            </button>
            <h1 class="truncate text-center text-sm font-medium sm:text-base" x-text="$store.collectQueue.pickerTitle()"></h1>
            <span></span>
        </div>
    </header>
    <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-3 sm:px-6">
        <div class="flex flex-col gap-2 sm:grid sm:grid-cols-2 sm:content-start sm:gap-3 lg:grid-cols-3 xl:grid-cols-4">
            <template x-for="player in $store.collectQueue.courtPlayers()" :key="player.id">
                <button type="button" x-on:click="$store.collectQueue.recordPlayer(player.id)" class="flex w-full items-center gap-3 rounded-lg border border-zinc-300 bg-white px-3 py-3 text-left hover:border-zinc-900 sm:rounded-xl sm:py-4">
                    <span class="pointer-events-none flex size-10 shrink-0 items-center justify-center rounded-full border border-zinc-900 bg-zinc-900 text-base font-bold tabular-nums text-white sm:size-14 sm:text-lg" x-text="player.number ?? '—'"></span>
                    <span class="min-w-0 flex-1 truncate text-sm font-semibold sm:text-base" x-text="player.name"></span>
                    <span x-cloak x-show="$store.collectQueue.pendingType === 'foul' && $store.collectQueue.foulCount(player.id) > 0" class="flex shrink-0 items-center gap-1">
                        <template x-for="mark in $store.collectQueue.foulMarks(player.id)" :key="mark">
                            <span
                                class="size-2.5 rounded-sm"
                                :class="{
                                    'bg-green-700': $store.collectQueue.foulCount(player.id) <= 3,
                                    'bg-orange-600': $store.collectQueue.foulCount(player.id) === 4,
                                    'bg-red-700': $store.collectQueue.foulCount(player.id) >= 5,
                                }"
                            ></span>
                        </template>
                    </span>
                </button>
            </template>
        </div>
        <div class="mt-4 border-t border-zinc-200 pt-3">
            <div class="flex flex-col gap-2 sm:grid sm:grid-cols-2 sm:content-start sm:gap-3 lg:grid-cols-3 xl:grid-cols-4">
                <button type="button" x-on:click="$store.collectQueue.recordOpponent()" class="flex w-full items-center gap-3 rounded-lg border border-zinc-300 bg-white px-3 py-3 text-left hover:border-zinc-900 sm:rounded-xl sm:py-4">
                    @include('livewire.statistics.partials.opponent-mark')
                    <span class="min-w-0 flex-1 truncate text-sm font-semibold sm:text-base">{{ $opponentName }}</span>
                </button>
            </div>
        </div>
    </div>
</div>

<div x-cloak x-show="$store.collectQueue.choosingShot" style="z-index: 80" class="fixed inset-0 flex h-dvh min-h-0 flex-col bg-white text-zinc-900">
    <header class="shrink-0 border-b border-zinc-200 bg-white">
        <div class="mx-auto grid w-full grid-cols-3 items-center px-4 py-3">
            <button type="button" x-on:click="$store.collectQueue.cancelShot()" class="inline-flex items-center gap-2 text-sm font-medium text-zinc-900">
                <flux:icon name="arrow-left" class="size-4" />
                {{ __('Back') }}
            </button>
            <h1 class="truncate text-center text-sm font-medium sm:text-base" x-text="$store.collectQueue.pickerTitle()"></h1>
            <span></span>
        </div>
    </header>
    <p x-cloak x-show="$store.collectQueue.shotError" class="px-4 text-center text-sm text-red-600">{{ __('Mark the shot inside the 2-point area for 2P, or outside for 3P.') }}</p>
    <div class="mt-4 flex flex-1 flex-col justify-start px-4 sm:px-6">
        <x-shot-court />
    </div>
</div>

<div x-cloak x-show="$store.collectQueue.choosingTimeout" style="z-index: 80; background-color: rgb(24 24 27 / 0.4)" class="fixed inset-0 flex items-center justify-center p-4">
    <div class="w-full max-w-sm space-y-4 rounded-xl bg-white p-6 text-zinc-900">
        <div class="text-center">
            <h2 class="text-base font-semibold">{{ __('Record timeout') }}</h2>
            <p class="mt-2 text-sm text-zinc-500">{{ __('Select which team called the timeout') }}</p>
        </div>
        <div class="space-y-3">
            <button type="button" x-on:click="$store.collectQueue.recordTimeout('home')" class="flex w-full items-center justify-between gap-3 rounded-lg border border-zinc-300 px-4 py-3 text-left hover:border-zinc-900">
                <span class="min-w-0 truncate text-sm font-medium">{{ $this->game->home_away === \App\Enums\HomeAway::Home ? $teamName : $opponentName }}</span>
                <span class="shrink-0 text-xs text-zinc-500">{{ __('At home') }}</span>
            </button>
            <button type="button" x-on:click="$store.collectQueue.recordTimeout('away')" class="flex w-full items-center justify-between gap-3 rounded-lg border border-zinc-300 px-4 py-3 text-left hover:border-zinc-900">
                <span class="min-w-0 truncate text-sm font-medium">{{ $this->game->home_away === \App\Enums\HomeAway::Home ? $opponentName : $teamName }}</span>
                <span class="shrink-0 text-xs text-zinc-500">{{ __('Away') }}</span>
            </button>
        </div>
        <div class="flex justify-end">
            <button type="button" x-on:click="$store.collectQueue.choosingTimeout = false" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-800">{{ __('Cancel') }}</button>
        </div>
    </div>
</div>

<div x-cloak x-show="$store.collectQueue.substituting" style="z-index: 80" class="fixed inset-0 flex h-dvh min-h-0 flex-col bg-zinc-100 text-zinc-900">
    <header class="shrink-0 border-b border-zinc-200 bg-white">
        <div class="mx-auto grid w-full grid-cols-3 items-center px-4 py-3">
            <button type="button" x-on:click="$store.collectQueue.substituting = false" class="inline-flex items-center gap-2 text-sm font-medium text-zinc-900">
                <flux:icon name="arrow-left" class="size-4" />
                {{ __('Back') }}
            </button>
            <h1 class="truncate text-center text-sm font-medium sm:text-base">{{ __('Substitution') }}</h1>
            <span></span>
        </div>
    </header>
    <p class="px-4 text-center text-sm text-zinc-500">{{ __('Mark 5 players.') }}</p>
    <p x-cloak x-show="$store.collectQueue.courtError" class="px-4 pt-1 text-center text-xs text-red-700">{{ __('Mark 5 players.') }}</p>
    <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-3 sm:px-6">
        <div class="flex flex-col gap-2 sm:grid sm:grid-cols-2 sm:content-start sm:gap-3 lg:grid-cols-3">
            <template x-for="player in $store.collectQueue.courtPlayers()" :key="'sub-court-' + player.id">
                <button type="button" x-on:click="$store.collectQueue.toggleCourt(player.id)" class="flex w-full items-center gap-3 rounded-lg border px-3 py-3 text-left sm:rounded-xl sm:py-4" :class="$store.collectQueue.courtSelection.includes(player.id) ? 'border-zinc-900 bg-amber-100' : 'border-zinc-300 bg-white'">
                    <span class="pointer-events-none flex size-10 shrink-0 items-center justify-center rounded-full border border-zinc-900 bg-zinc-900 text-base font-bold tabular-nums text-white sm:size-14 sm:text-lg" x-text="player.number ?? '—'"></span>
                    <span class="min-w-0 flex-1 truncate text-sm font-semibold sm:text-base" x-text="player.name"></span>
                    <span x-cloak x-show="$store.collectQueue.foulCount(player.id) > 0" class="flex shrink-0 items-center gap-1">
                        <template x-for="mark in $store.collectQueue.foulMarks(player.id)" :key="mark">
                            <span
                                class="size-2.5 rounded-sm"
                                :class="{
                                    'bg-green-700': $store.collectQueue.foulCount(player.id) <= 3,
                                    'bg-orange-600': $store.collectQueue.foulCount(player.id) === 4,
                                    'bg-red-700': $store.collectQueue.foulCount(player.id) >= 5,
                                }"
                            ></span>
                        </template>
                    </span>
                </button>
            </template>
        </div>
        <div x-cloak x-show="$store.collectQueue.benchPlayers().length > 0" class="mt-4 border-t border-zinc-200 pt-3">
            <div class="flex flex-col gap-2 sm:grid sm:grid-cols-2 sm:content-start sm:gap-3 lg:grid-cols-3">
                <template x-for="player in $store.collectQueue.benchPlayers()" :key="'sub-bench-' + player.id">
                    <button type="button" x-on:click="$store.collectQueue.toggleCourt(player.id)" class="flex w-full items-center gap-3 rounded-lg border px-3 py-3 text-left sm:rounded-xl sm:py-4" :class="$store.collectQueue.courtSelection.includes(player.id) ? 'border-zinc-900 bg-amber-100' : 'border-zinc-300 bg-white'">
                        <span class="pointer-events-none flex size-10 shrink-0 items-center justify-center rounded-full border border-zinc-900 bg-zinc-900 text-base font-bold tabular-nums text-white sm:size-14 sm:text-lg" x-text="player.number ?? '—'"></span>
                        <span class="min-w-0 flex-1 truncate text-sm font-semibold sm:text-base" x-text="player.name"></span>
                        <span x-cloak x-show="$store.collectQueue.foulCount(player.id) > 0" class="flex shrink-0 items-center gap-1">
                            <template x-for="mark in $store.collectQueue.foulMarks(player.id)" :key="mark">
                                <span
                                    class="size-2.5 rounded-sm"
                                    :class="{
                                        'bg-green-700': $store.collectQueue.foulCount(player.id) <= 3,
                                        'bg-orange-600': $store.collectQueue.foulCount(player.id) === 4,
                                        'bg-red-700': $store.collectQueue.foulCount(player.id) >= 5,
                                    }"
                                ></span>
                            </template>
                        </span>
                    </button>
                </template>
            </div>
        </div>
    </div>
    <div class="flex justify-end border-t border-zinc-200 px-3 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
        <button type="button" x-on:click="$store.collectQueue.confirmSubstitution()" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white">{{ __('Confirm') }}</button>
    </div>
</div>

<div x-cloak x-show="$store.collectQueue.confirmingQuarterEnd" style="z-index: 80; background-color: rgb(24 24 27 / 0.4)" class="fixed inset-0 flex items-center justify-center p-4">
    <div class="w-full max-w-sm space-y-6 rounded-xl bg-white p-6 text-zinc-900">
        <div class="text-center">
            <h2 class="text-base font-semibold">{{ $quarterTitle }}</h2>
            <p class="mt-2 text-sm text-zinc-600" x-text="$store.collectQueue.endPeriodQuestion()"></p>
            <p class="mt-3 text-sm font-medium text-red-700">{{ __('You cannot undo this action.') }}</p>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
            <button type="button" x-on:click="$store.collectQueue.confirmingQuarterEnd = false" class="w-full rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-800 sm:w-auto">{{ __('Cancel') }}</button>
            <button type="button" x-on:click="$store.collectQueue.confirmEndQuarter()" class="w-full rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white sm:w-auto">{{ __('Confirm') }}</button>
        </div>
    </div>
</div>

<div x-cloak x-show="$store.collectQueue.editingPendingId !== null" style="z-index: 80" class="fixed inset-0 flex h-dvh min-h-0 flex-col bg-white text-zinc-900">
    <header class="shrink-0 border-b border-zinc-200 bg-white">
        <div class="mx-auto grid w-full grid-cols-3 items-center px-4 py-3">
            <button type="button" x-on:click="$store.collectQueue.editingPendingId = null" class="inline-flex items-center gap-2 text-sm font-medium text-zinc-900">
                <flux:icon name="arrow-left" class="size-4" />
                {{ __('Back') }}
            </button>
            <h1 class="truncate text-center text-sm font-medium sm:text-base">{{ __('Edit') }}</h1>
            <span></span>
        </div>
    </header>
    <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-3 sm:px-6">
        <div class="mx-auto max-w-xs">
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-zinc-700">{{ __('Clock') }}</span>
                <input type="text" inputmode="numeric" maxlength="5" x-model="$store.collectQueue.editingEventClock" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-center text-lg font-semibold tabular-nums text-zinc-900" aria-label="{{ __('Clock') }}" />
            </label>
        </div>
        <div class="mx-auto mt-6 grid max-w-md grid-cols-2 gap-2" x-show="$store.collectQueue.editableTypesForEdit().length > 1">
            <template x-for="stat in $store.collectQueue.editableTypesForEdit()" :key="stat.value">
                <button type="button" x-on:click="$store.collectQueue.editingType = stat.value" class="w-full rounded-lg border bg-white px-3 py-2 text-sm font-medium" :class="{ 'border-green-700 text-green-700': stat.tone === 'made', 'border-red-700 text-red-700': stat.tone === 'missed', 'border-zinc-300 text-zinc-900': stat.tone === 'plain', 'ring-2 ring-zinc-900 ring-inset': $store.collectQueue.editingType === stat.value }" x-text="stat.label"></button>
            </template>
        </div>
        <div class="mt-6 flex flex-col gap-2 sm:grid sm:grid-cols-2 sm:content-start sm:gap-3 lg:grid-cols-3 xl:grid-cols-4">
            <template x-for="player in $store.collectQueue.baseline?.roster ?? []" :key="'edit-' + player.id">
                <button type="button" x-on:click="$store.collectQueue.editingPlayerId = player.id; $store.collectQueue.editingForOpponent = false" class="flex w-full items-center gap-3 rounded-lg border px-3 py-3 text-left sm:rounded-xl sm:py-4" :class="! $store.collectQueue.editingForOpponent && $store.collectQueue.editingPlayerId === player.id ? 'border-zinc-900 bg-amber-100' : 'border-zinc-300 bg-white'">
                    <span class="pointer-events-none flex size-10 shrink-0 items-center justify-center rounded-full border border-zinc-900 bg-zinc-900 text-base font-bold tabular-nums text-white sm:size-14 sm:text-lg" x-text="player.number ?? '—'"></span>
                    <span class="min-w-0 flex-1 truncate text-sm font-semibold sm:text-base" x-text="player.name"></span>
                </button>
            </template>
            <button type="button" x-on:click="$store.collectQueue.editingPlayerId = null; $store.collectQueue.editingForOpponent = true" class="flex w-full items-center gap-3 rounded-lg border px-3 py-3 text-left sm:rounded-xl sm:py-4" :class="$store.collectQueue.editingForOpponent ? 'border-zinc-900 bg-amber-100' : 'border-zinc-300 bg-white'">
                <span class="min-w-0 flex-1 truncate text-sm font-semibold sm:text-base">{{ $opponentName }}</span>
            </button>
        </div>
    </div>
    <div class="flex shrink-0 justify-end border-t border-zinc-200 px-3 py-3 sm:px-6">
        <button type="button" x-on:click="$store.collectQueue.savePendingEdit()" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white">{{ __('Confirm') }}</button>
    </div>
</div>
@endif

<flux:modal wire:model="choosingTimeout" :closable="false" class="max-w-sm !bg-white">
    @php($timeoutAtHome = $this->game->home_away === \App\Enums\HomeAway::Home)
    <div class="space-y-4 text-zinc-900">
        <div class="text-center">
            <h2 class="text-base font-semibold">{{ __('Record timeout') }}</h2>
            <p class="mt-2 text-sm text-zinc-500">{{ __('Select which team called the timeout') }}</p>
        </div>
        <div class="space-y-3">
            <button type="button" x-on:click="$wire.recordTimeout('home', $store.gameClock?.formatted ?? null)" wire:loading.attr="disabled" wire:target="recordTimeout" class="flex w-full items-center justify-between gap-3 rounded-lg border border-zinc-300 px-4 py-3 text-left hover:border-zinc-900">
                <span class="min-w-0 truncate text-sm font-medium">{{ $timeoutAtHome ? $teamName : $opponentName }}</span>
                <span class="shrink-0 text-xs text-zinc-500">{{ __('At home') }}</span>
            </button>
            <button type="button" x-on:click="$wire.recordTimeout('away', $store.gameClock?.formatted ?? null)" wire:loading.attr="disabled" wire:target="recordTimeout" class="flex w-full items-center justify-between gap-3 rounded-lg border border-zinc-300 px-4 py-3 text-left hover:border-zinc-900">
                <span class="min-w-0 truncate text-sm font-medium">{{ $timeoutAtHome ? $opponentName : $teamName }}</span>
                <span class="shrink-0 text-xs text-zinc-500">{{ __('Away') }}</span>
            </button>
        </div>
        <div class="flex justify-end">
            <button type="button" wire:click="cancelChoosingTimeout" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-800">{{ __('Cancel') }}</button>
        </div>
    </div>
</flux:modal>

<flux:modal wire:model="choosingPlayer" :closable="false" class="statistics-collect-modal !bg-white">
    @if ($pendingStat)
        <div class="flex h-dvh min-h-0 flex-col text-zinc-900">
            @include('livewire.statistics.partials.collect-modal-header', ['title' => __($pendingStat->pickerTitle()), 'cancelAction' => 'cancel'])
            @error('clock')
                <p class="px-4 text-center text-xs text-zinc-500">{{ $message }}</p>
            @enderror
            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-3 sm:px-6">
                <div class="flex flex-col gap-2 sm:grid sm:grid-cols-2 sm:content-start sm:gap-3 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($this->courtPlayers as $player)
                        <button
                            type="button"
                            wire:key="player-{{ $player->id }}"
                            x-on:click="$wire.record({{ $player->id }}, $store.gameClock?.formatted ?? null)"
                            wire:loading.attr="disabled"
                            wire:target="record, recordOpponent, recordShotLocation"
                            class="flex w-full items-center gap-3 rounded-lg border border-zinc-300 bg-white px-3 py-3 text-left hover:border-zinc-900 sm:rounded-xl sm:py-4"
                        >
                            <span class="pointer-events-none flex size-10 shrink-0 items-center justify-center rounded-full border border-zinc-900 bg-zinc-900 text-base font-bold tabular-nums text-white sm:size-14 sm:text-lg">{{ $player->number ?? '—' }}</span>
                            <span class="min-w-0 flex-1 truncate text-sm font-semibold sm:text-base">{{ $player->name }}</span>
                            @if ($pendingStat === \App\Enums\StatType::Foul)
                                @php($personalFouls = $this->fouls['players'][$player->id] ?? 0)
                                @if ($personalFouls > 0)
                                    <span class="flex shrink-0 items-center gap-1">
                                        @for ($mark = 0; $mark < $personalFouls; $mark++)
                                            <span @class([
                                                'size-2.5 rounded-sm',
                                                'bg-green-700' => $personalFouls <= 3,
                                                'bg-orange-600' => $personalFouls === 4,
                                                'bg-red-700' => $personalFouls >= 5,
                                            ])></span>
                                        @endfor
                                    </span>
                                @endif
                            @endif
                        </button>
                    @endforeach
                </div>
                <div class="mt-4 border-t border-zinc-200 pt-3">
                    <div class="flex flex-col gap-2 sm:grid sm:grid-cols-2 sm:content-start sm:gap-3 lg:grid-cols-3 xl:grid-cols-4">
                        <button
                            type="button"
                            x-on:click="$wire.recordOpponent($store.gameClock?.formatted ?? null)"
                            wire:loading.attr="disabled"
                            wire:target="record, recordOpponent, recordShotLocation"
                            class="flex w-full items-center gap-3 rounded-lg border border-zinc-300 bg-white px-3 py-3 text-left hover:border-zinc-900 sm:rounded-xl sm:py-4"
                        >
                            @include('livewire.statistics.partials.opponent-mark')
                            <span class="min-w-0 flex-1 truncate text-sm font-semibold sm:text-base">{{ $opponentName }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</flux:modal>

<flux:modal wire:model="choosingShotLocation" :closable="false" class="statistics-collect-modal !bg-white">
    @if ($pendingStat?->isFieldGoal())
        <div class="flex h-dvh min-h-0 flex-col text-zinc-900">
            @include('livewire.statistics.partials.collect-modal-header', ['title' => __($pendingStat->pickerTitle()), 'cancelAction' => 'cancelShotLocation'])
            @error('shotLocation')
                <p class="px-4 text-center text-sm text-red-600">{{ $message }}</p>
            @enderror
            <div class="mt-4 flex flex-1 flex-col justify-start px-4 sm:px-6" wire:key="shot-court-{{ $pendingType }}">
                <x-shot-court :made="$pendingStat->isSolid()" />
            </div>
        </div>
    @endif
</flux:modal>

@if ($this->lineupReady)
<flux:modal wire:model="editingAction" :closable="false" class="statistics-collect-modal !bg-white">
    @php($editingSubstitution = $editingType === \App\Enums\StatType::Substitution->value)
    @php($editingTimeout = $editingType === \App\Enums\StatType::Timeout->value)
    <div class="flex h-dvh min-h-0 flex-col text-zinc-900">
        @include('livewire.statistics.partials.collect-modal-header', ['title' => __('Edit'), 'cancelAction' => 'cancelEditingAction'])
        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-3 sm:px-6">
            <div class="mx-auto max-w-xs">
                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-zinc-700">{{ __('Clock') }}</span>
                    <input
                        type="text"
                        inputmode="numeric"
                        maxlength="5"
                        wire:model="editingEventClock"
                        class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-center text-lg font-semibold tabular-nums text-zinc-900"
                        aria-label="{{ __('Clock') }}"
                    />
                </label>
                @error('editingEventClock')
                    <p class="mt-1 text-center text-xs text-red-700">{{ $message }}</p>
                @enderror
            </div>

            @if ($editingTimeout)
                <div class="mt-6 flex flex-col gap-2 sm:mx-auto sm:max-w-md">
                    <button
                        type="button"
                        wire:click="selectEditingTeam"
                        @class([
                            'flex w-full items-center gap-3 rounded-lg border px-3 py-3 text-left sm:rounded-xl sm:py-4',
                            'border-zinc-900 bg-amber-100' => ! $editingForOpponent,
                            'border-zinc-300 bg-white hover:border-zinc-900' => $editingForOpponent,
                        ])
                    >
                        <span class="min-w-0 flex-1 truncate text-sm font-semibold sm:text-base">{{ $teamName }}</span>
                    </button>
                    <button
                        type="button"
                        wire:click="selectEditingOpponent"
                        @class([
                            'flex w-full items-center gap-3 rounded-lg border px-3 py-3 text-left sm:rounded-xl sm:py-4',
                            'border-zinc-900 bg-amber-100' => $editingForOpponent,
                            'border-zinc-300 bg-white hover:border-zinc-900' => ! $editingForOpponent,
                        ])
                    >
                        @include('livewire.statistics.partials.opponent-mark')
                        <span class="min-w-0 flex-1 truncate text-sm font-semibold sm:text-base">{{ $opponentName }}</span>
                    </button>
                </div>
            @elseif ($editingSubstitution)
                @php($incomingPlayer = $this->attachedPlayers->firstWhere('id', $editingPlayerId))
                @php($outgoingPlayer = $this->attachedPlayers->firstWhere('id', $editingOutgoingPlayerId))
                <div class="mt-6 space-y-4 sm:mx-auto sm:max-w-md">
                    @if ($incomingPlayer)
                        <div class="flex w-full items-center gap-3 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-3 sm:rounded-xl sm:py-4">
                            <span class="pointer-events-none flex size-10 shrink-0 items-center justify-center rounded-full border border-zinc-900 bg-zinc-900 text-base font-bold tabular-nums text-white sm:size-14 sm:text-lg">{{ $incomingPlayer->number ?? '—' }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-xs font-medium text-green-700">{{ __('Comes in') }}</span>
                                <span class="block truncate text-sm font-semibold sm:text-base">{{ $incomingPlayer->name }}</span>
                            </span>
                        </div>
                    @endif
                    @if ($outgoingPlayer)
                        <div class="flex w-full items-center gap-3 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-3 sm:rounded-xl sm:py-4">
                            <span class="pointer-events-none flex size-10 shrink-0 items-center justify-center rounded-full border border-zinc-900 bg-zinc-900 text-base font-bold tabular-nums text-white sm:size-14 sm:text-lg">{{ $outgoingPlayer->number ?? '—' }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-xs font-medium text-red-700">{{ __('Goes out') }}</span>
                                <span class="block truncate text-sm font-semibold sm:text-base">{{ $outgoingPlayer->name }}</span>
                            </span>
                        </div>
                    @endif
                </div>
            @else
                @if (count($this->editingTypeChoices) > 1)
                    <div class="mx-auto mt-6 grid max-w-md grid-cols-2 gap-2">
                        @foreach ($this->editingTypeChoices as $choice)
                            @php($stat = $choice['stat'])
                            <button
                                type="button"
                                wire:key="edit-type-{{ $stat->value }}"
                                wire:click="selectEditingType('{{ $stat->value }}')"
                                @class([
                                    'w-full rounded-lg border bg-white px-3 py-2 text-sm font-medium',
                                    'border-green-700 text-green-700' => $choice['tone'] === 'made',
                                    'border-red-700 text-red-700' => $choice['tone'] === 'missed',
                                    'border-zinc-300 text-zinc-900' => $choice['tone'] === 'plain',
                                    'ring-2 ring-zinc-900 ring-inset' => $editingType === $stat->value,
                                ])
                            >{{ __($stat->logLabel()) }}</button>
                        @endforeach
                    </div>
                @endif
                <div class="mt-6 flex flex-col gap-2 sm:grid sm:grid-cols-2 sm:content-start sm:gap-3 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($this->attachedPlayers as $player)
                        <button
                            type="button"
                            wire:key="edit-player-{{ $player->id }}"
                            wire:click="selectEditingPlayer({{ $player->id }})"
                            @class([
                                'flex w-full items-center gap-3 rounded-lg border px-3 py-3 text-left sm:rounded-xl sm:py-4',
                                'border-zinc-900 bg-amber-100' => ! $editingForOpponent && $editingPlayerId === $player->id,
                                'border-zinc-300 bg-white hover:border-zinc-900' => $editingForOpponent || $editingPlayerId !== $player->id,
                            ])
                        >
                            <span class="pointer-events-none flex size-10 shrink-0 items-center justify-center rounded-full border border-zinc-900 bg-zinc-900 text-base font-bold tabular-nums text-white sm:size-14 sm:text-lg">{{ $player->number ?? '—' }}</span>
                            <span class="min-w-0 flex-1 truncate text-sm font-semibold sm:text-base">{{ $player->name }}</span>
                        </button>
                    @endforeach
                    <button
                        type="button"
                        wire:click="selectEditingOpponent"
                        @class([
                            'flex w-full items-center gap-3 rounded-lg border px-3 py-3 text-left sm:rounded-xl sm:py-4',
                            'border-zinc-900 bg-amber-100' => $editingForOpponent,
                            'border-zinc-300 bg-white hover:border-zinc-900' => ! $editingForOpponent,
                        ])
                    >
                        @include('livewire.statistics.partials.opponent-mark')
                        <span class="min-w-0 flex-1 truncate text-sm font-semibold sm:text-base">{{ $opponentName }}</span>
                    </button>
                </div>
            @endif
        </div>
        <div class="flex shrink-0 justify-end border-t border-zinc-200 px-3 py-3 sm:px-6">
            <button type="button" x-on:click="$store.collectQueue.saveConfirmedEdit()" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white">{{ __('Confirm') }}</button>
        </div>
    </div>
</flux:modal>
@endif

<flux:modal wire:model="confirmingQuarterEnd" :closable="false" class="max-w-sm !bg-white">
    <div class="space-y-6 text-zinc-900">
        <div class="text-center">
            <h2 class="text-base font-semibold">{{ $quarterTitle }}</h2>
            <p class="mt-2 text-sm text-zinc-600">{{ $endPeriodQuestion }}</p>
            <p class="mt-3 text-sm font-medium text-red-700">{{ __('You cannot undo this action.') }}</p>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
            <button type="button" wire:click="cancelConfirmingQuarterEnd" class="w-full rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-800 sm:w-auto">{{ __('Cancel') }}</button>
            <button type="button" wire:click="endQuarter" class="w-full rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white sm:w-auto">{{ __('Confirm') }}</button>
        </div>
    </div>
</flux:modal>

<flux:modal wire:model="confirmingExit" :closable="false" class="max-w-sm !bg-white">
    <div class="space-y-6 text-zinc-900">
        <div class="text-center">
            <h2 class="text-base font-semibold">{{ __('Leave game?') }}</h2>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
            <button type="button" wire:click="cancelConfirmingExit" class="w-full rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-800 sm:w-auto">{{ __('Cancel') }}</button>
            <button type="button" wire:click="confirmExit" class="w-full rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white sm:w-auto">{{ __('Leave') }}</button>
        </div>
    </div>
</flux:modal>

<flux:modal wire:model="confirmingGameFinish" :closable="false" class="max-w-sm !bg-white">
    <div class="space-y-6 text-zinc-900">
        <div class="text-center">
            <h2 class="text-base font-semibold">{{ __('Finish game?') }}</h2>
            <p class="mt-3 text-sm text-zinc-600">{{ __('Mark this game as played and save the final score from the collected stats?') }}</p>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
            <button type="button" wire:click="leaveWithoutFinishing" class="w-full rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-800 sm:w-auto">{{ __('Leave without finishing') }}</button>
            <button type="button" x-on:click="$store.collectQueue.finishGame()" class="w-full rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white sm:w-auto">{{ __('Finish game') }}</button>
        </div>
    </div>
</flux:modal>
@once
    <script>
        {!! file_get_contents(resource_path('js/collect-queue.js')) !!}
    </script>
    <script>
        (function () {
            const registerGameClockListeners = () => {
                if (window.__gameClockListenersRegistered) {
                    return;
                }

                window.__gameClockListenersRegistered = true;

                document.addEventListener('livewire:navigated', () => {
                    requestAnimationFrame(() => {
                        window.ensureGameClockStore?.();
                        window.Alpine.store('gameClock')?.syncFromStatisticsRecord();
                    });
                });
            };

            const createGameClockStore = () => {
                if (! window.Alpine?.store('collectPane')) {
                    window.Alpine.store('collectPane', { name: 'scoring' });
                }

                if (window.Alpine.store('gameClock')) {
                    return;
                }

                window.Alpine.store('gameClock', {
                componentId: null,
                wire: null,
                remaining: 600,
                formatted: '10:00',
                running: false,
                timer: null,
                bind(wire) {
                    const id = wire.__instance.id;

                    if (this.componentId !== id) {
                        this.stop();
                        this.componentId = id;
                        this.wire = wire;
                    }

                    const parsed = this.parse(wire.clock);
                    this.remaining = parsed ?? 600;
                    this.formatted = this.format(this.remaining);
                },
                syncFromStatisticsRecord() {
                    const root = document.querySelector('[data-statistics-record]')?.closest('[wire\\:id]');
                    const id = root?.getAttribute('wire:id');

                    if (! id) {
                        return;
                    }

                    const component = window.Livewire?.find?.(id);

                    if (component?.$wire) {
                        this.bind(component.$wire);
                    }
                },
                toggle() {
                    if (this.running) {
                        this.pause();

                        return;
                    }

                    this.start();
                },
                press() {
                    if (this.wire === null || ! this.alive()) {
                        this.syncFromStatisticsRecord();
                    }

                    const wire = this.wire;

                    if (wire === null) {
                        return;
                    }

                    const queue = window.Alpine.store('collectQueue');
                    const awaitingQuarterStart = queue?.local?.awaitingQuarterStart ?? wire.awaitingQuarterStart;
                    const quarterEnded = queue?.local?.quarterEnded ?? wire.quarterEnded;

                    if (awaitingQuarterStart) {
                        queue?.startQuarter();

                        return;
                    }

                    if (! this.running && this.remaining === 0 && ! quarterEnded) {
                        queue?.askToEndQuarter();

                        return;
                    }

                    if (! this.running) {
                        const queuedStart = queue?.recordQuarterStart(this.formatted) ?? Promise.resolve();
                        queuedStart.then(() => {
                            if (! this.running) {
                                this.start();
                            }
                        });

                        return;
                    }

                    this.pause();
                },
                start() {
                    if (this.running || this.remaining <= 0) {
                        return;
                    }

                    this.running = true;
                    this.timer = setInterval(() => this.tick(), 1000);
                },
                pause() {
                    this.stop();
                    this.publish();
                },
                stop() {
                    this.running = false;

                    if (this.timer !== null) {
                        clearInterval(this.timer);
                        this.timer = null;
                    }
                },
                adjust(seconds) {
                    this.remaining = Math.min((99 * 60) + 59, Math.max(0, this.remaining + seconds));
                    this.formatted = this.format(this.remaining);

                    if (this.remaining === 0) {
                        this.stop();
                    }

                    this.publish();
                },
                tick() {
                    if (! this.alive()) {
                        this.stop();

                        return;
                    }

                    if (this.remaining <= 1) {
                        this.remaining = 0;
                        this.formatted = this.format(0);
                        this.stop();
                        this.publish();

                        return;
                    }

                    this.remaining -= 1;
                    this.formatted = this.format(this.remaining);
                },
                apply(value) {
                    this.stop();
                    this.setFrom(value, false);
                },
                hold() {
                    this.stop();
                    this.remaining = 0;
                    this.formatted = '--:--';
                },
                begin(value) {
                    this.stop();
                    this.setFrom(value ?? '10:00', false);
                    this.start();
                },
                setFrom(value, publish = true) {
                    const parsed = this.parse(value);

                    if (parsed === null) {
                        return;
                    }

                    this.remaining = parsed;
                    this.formatted = this.format(parsed);

                    if (this.remaining === 0) {
                        this.stop();
                    }

                    if (publish) {
                        this.publish();
                    }
                },
                parse(value) {
                    if (typeof value !== 'string' || ! /^\d{1,2}:\d{2}$/.test(value)) {
                        return null;
                    }

                    const [minutes, seconds] = value.split(':').map((part) => Number(part));

                    if (minutes > 99 || seconds > 59) {
                        return null;
                    }

                    return (minutes * 60) + seconds;
                },
                format(total) {
                    const minutes = Math.floor(total / 60);
                    const seconds = total % 60;

                    return String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
                },
                alive() {
                    return this.componentId !== null && document.querySelector(`[wire\\:id="${this.componentId}"]`) !== null;
                },
                publish() {
                    if (! this.alive()) {
                        return;
                    }

                    this.wire?.$set('clock', this.formatted, false);
                },
            });

                registerGameClockListeners();
            };

            window.ensureGameClockStore = () => {
                if (typeof window.Alpine === 'undefined') {
                    document.addEventListener('alpine:init', createGameClockStore, { once: true });

                    return;
                }

                createGameClockStore();
            };

            window.ensureGameClockStore();
        })();
    </script>
@endonce
@script
    <script>
        const bindGameClock = () => {
            window.ensureGameClockStore?.();
            window.Alpine.store('gameClock')?.bind($wire);
        };

        bindGameClock();

        document.addEventListener('livewire:navigated', () => {
            requestAnimationFrame(bindGameClock);
        });
    </script>
@endscript
<div
    x-cloak
    x-show="$store.collectQueue.pendingCount() > 0"
    class="pointer-events-none fixed inset-x-0 bottom-6 z-[70] flex justify-center"
>
    <p class="rounded-full bg-zinc-900 px-4 py-2 text-sm font-medium text-white shadow">
        <span x-show="$store.collectQueue.online">{{ __('Saving…') }}</span>
        <span x-cloak x-show="! $store.collectQueue.online">{{ __('Offline') }}</span>
    </p>
</div>
</div>
