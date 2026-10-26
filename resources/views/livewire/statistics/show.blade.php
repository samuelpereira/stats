<div class="admin-surface flex h-dvh min-h-0 flex-col text-zinc-900">
    <header class="shrink-0 border-b border-zinc-200 bg-white">
        <div @class(['mx-auto grid w-full grid-cols-3 items-center px-4 py-3', 'max-w-5xl' => $shared])>
        @if ($shared)
            <span></span>
        @else
            <a href="{{ $backUrl }}" wire:navigate class="inline-flex items-center gap-2 text-sm font-medium text-zinc-900">
                <flux:icon name="arrow-left" class="size-4" />
                {{ __('Back') }}
            </a>
        @endif
        <h1 class="truncate text-center text-sm font-medium sm:text-base">{{ __('Statistics') }}</h1>
        @if ($shared)
            <span></span>
        @else
            <div class="flex justify-end">
                <button type="button" wire:click="openShare" class="inline-flex size-9 items-center justify-center rounded-lg border border-zinc-300 text-zinc-800 hover:border-zinc-900" aria-label="{{ __('Share') }}">
                    <flux:icon name="share" class="size-4" />
                </button>
            </div>
        @endif
        </div>
    </header>

    <div class="min-h-0 flex-1 overflow-y-auto">
        <div @class(['mx-auto w-full px-4 py-4 sm:px-6', 'max-w-5xl' => $shared])>
        @if ($shared)
        <div class="rounded-lg border border-zinc-200 bg-white px-4 py-6 shadow-sm">
            <p class="text-center text-sm text-zinc-500">{{ $gameDate }}</p>
            <div class="mt-5 flex items-start justify-center gap-4 sm:gap-8">
                <div class="flex w-28 flex-col items-center gap-2 text-center sm:w-36">
                    <span class="flex size-16 items-center justify-center overflow-hidden rounded-full border border-zinc-300 bg-zinc-100 text-sm font-semibold tracking-wide text-zinc-700">
                        @if ($homeLogoUrl)
                            <img src="{{ $homeLogoUrl }}" alt="" class="size-full object-cover" />
                        @else
                            {{ $homeInitials }}
                        @endif
                    </span>
                    <span class="text-sm font-medium">{{ $homeName }}</span>
                </div>
                <div class="flex items-center gap-4 pt-4 sm:gap-8">
                    <span class="text-4xl font-semibold tabular-nums">{{ $homeTotal }}</span>
                    <span class="text-4xl font-semibold tabular-nums">{{ $awayTotal }}</span>
                </div>
                <div class="flex w-28 flex-col items-center gap-2 text-center sm:w-36">
                    <span class="flex size-16 items-center justify-center overflow-hidden rounded-full border border-zinc-300 bg-zinc-100 text-sm font-semibold tracking-wide text-zinc-700">
                        @if ($awayLogoUrl)
                            <img src="{{ $awayLogoUrl }}" alt="" class="size-full object-cover" />
                        @else
                            {{ $awayInitials }}
                        @endif
                    </span>
                    <span class="text-sm font-medium">{{ $awayName }}</span>
                </div>
            </div>
        </div>
        @endif

        <div @class(['w-full rounded-lg border border-zinc-200 bg-white shadow-sm', 'mt-4' => $shared])>
            <div class="border-b border-zinc-200 px-4 py-3 sm:px-6">
                <h2 class="text-base font-semibold">{{ __('Breakdown') }}</h2>
                <p class="text-sm text-zinc-500">{{ __('Stat breakdown by time period') }}</p>
            </div>
            <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-zinc-200 text-xs font-medium tracking-wide text-zinc-500 uppercase">
                        <th class="py-2 pr-3 pl-4 sm:pl-6"></th>
                        @foreach ($periods as $period)
                            <th class="px-2 py-2 text-center font-medium">{{ __($period['label']) }}</th>
                        @endforeach
                        <th class="bg-zinc-50 px-2 py-2 text-center font-medium">{{ __('T') }}</th>
                        <th class="px-2 py-2 text-center font-medium">{{ __('F') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-zinc-100">
                        <td class="py-3 pr-3 pl-4 sm:pl-6">
                            <div class="flex min-w-0 items-center gap-3">
                                <span
                                    class="flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-full border border-zinc-300 bg-zinc-100 text-[10px] font-semibold tracking-wide text-zinc-700"
                                    title="{{ $homeName }}"
                                >
                                    @if ($homeLogoUrl)
                                        <img src="{{ $homeLogoUrl }}" alt="" class="size-full object-cover" />
                                    @else
                                        {{ $homeInitials }}
                                    @endif
                                </span>
                                <span class="hidden truncate font-medium sm:inline">{{ $homeName }}</span>
                            </div>
                        </td>
                        @foreach ($periods as $period)
                            <td class="px-2 py-3 text-center tabular-nums">{{ $period['home'] }}</td>
                        @endforeach
                        <td class="bg-zinc-50 px-2 py-3 text-center font-bold tabular-nums">{{ $homeTotal }}</td>
                        <td class="px-2 py-3 text-center tabular-nums">{{ $homePeriodFouls }}</td>
                    </tr>
                    <tr>
                        <td class="py-3 pr-3 pl-4 sm:pl-6">
                            <div class="flex min-w-0 items-center gap-3">
                                <span
                                    class="flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-full border border-zinc-300 bg-zinc-100 text-[10px] font-semibold tracking-wide text-zinc-700"
                                    title="{{ $awayName }}"
                                >
                                    @if ($awayLogoUrl)
                                        <img src="{{ $awayLogoUrl }}" alt="" class="size-full object-cover" />
                                    @else
                                        {{ $awayInitials }}
                                    @endif
                                </span>
                                <span class="hidden truncate font-medium sm:inline">{{ $awayName }}</span>
                            </div>
                        </td>
                        @foreach ($periods as $period)
                            <td class="px-2 py-3 text-center tabular-nums">{{ $period['away'] }}</td>
                        @endforeach
                        <td class="bg-zinc-50 px-2 py-3 text-center font-bold tabular-nums">{{ $awayTotal }}</td>
                        <td class="px-2 py-3 text-center tabular-nums">{{ $awayPeriodFouls }}</td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>

        @unless ($shared || $this->game->status === \App\Enums\GameStatus::Played)
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white shadow-sm">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-zinc-200 text-xs font-medium tracking-wide text-zinc-500 uppercase">
                                <th class="px-3 py-2 text-left font-medium">{{ __('On court') }}</th>
                                <th class="px-2 py-2 text-center font-medium">{{ __('Min') }}</th>
                                <th class="px-2 py-2 text-center font-medium">{{ __('Pts') }}</th>
                                <th class="px-2 py-2 text-center font-medium">{{ __('PF') }}</th>
                                <th class="px-2 py-2 text-center font-medium">{{ __('TF') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($courtPlayers as $player)
                                <tr wire:key="court-{{ $player['id'] }}" class="border-b border-zinc-100 last:border-0">
                                    <td class="px-3 py-2">
                                        <span class="font-medium tabular-nums">{{ $player['number'] ?? '—' }}</span>
                                        <span class="ml-2">{{ $player['name'] }}</span>
                                    </td>
                                    <td class="px-2 py-2 text-center tabular-nums">{{ $player['minutes'] }}</td>
                                    <td class="px-2 py-2 text-center font-semibold tabular-nums">{{ $player['points'] }}</td>
                                    <td class="px-2 py-2 text-center tabular-nums">{{ $player['fouls'] }}</td>
                                    <td class="px-2 py-2 text-center tabular-nums">{{ $player['technicalFouls'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-3 text-center text-zinc-500">—</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white shadow-sm">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-zinc-200 text-xs font-medium tracking-wide text-zinc-500 uppercase">
                                <th class="px-3 py-2 text-left font-medium">{{ __('Bench') }}</th>
                                <th class="px-2 py-2 text-center font-medium">{{ __('Min') }}</th>
                                <th class="px-2 py-2 text-center font-medium">{{ __('Pts') }}</th>
                                <th class="px-2 py-2 text-center font-medium">{{ __('PF') }}</th>
                                <th class="px-2 py-2 text-center font-medium">{{ __('TF') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($benchPlayers as $player)
                                <tr wire:key="bench-{{ $player['id'] }}" class="border-b border-zinc-100 last:border-0">
                                    <td class="px-3 py-2">
                                        <span class="font-medium tabular-nums">{{ $player['number'] ?? '—' }}</span>
                                        <span class="ml-2">{{ $player['name'] }}</span>
                                    </td>
                                    <td class="px-2 py-2 text-center tabular-nums">{{ $player['minutes'] }}</td>
                                    <td class="px-2 py-2 text-center font-semibold tabular-nums">{{ $player['points'] }}</td>
                                    <td class="px-2 py-2 text-center tabular-nums">{{ $player['fouls'] }}</td>
                                    <td class="px-2 py-2 text-center tabular-nums">{{ $player['technicalFouls'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-3 text-center text-zinc-500">—</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endunless

        <div class="mt-4 rounded-lg border border-zinc-200 bg-white shadow-sm">
            <div class="border-b border-zinc-200 px-4 py-3 sm:px-6">
                <h2 class="text-base font-semibold">{{ __('Box score') }}</h2>
            </div>
            @include('livewire.statistics.partials.box-score-table', [
                'boxScore' => $boxScore,
                'teamName' => $teamName,
                'opponentName' => $opponentName,
            ])
        </div>

        @include('livewire.statistics.partials.matchup')

        @if ($gameFlow !== null)
            <div class="mt-4 rounded-lg border border-zinc-200 bg-white px-4 py-4 shadow-sm sm:px-6">
                <div class="mb-2">
                    <h2 class="text-base font-semibold">{{ __('Game flow') }}</h2>
                    <p class="text-sm text-zinc-500">{{ __('Score progress through the game') }}</p>
                </div>
                <x-game-flow-chart
                    :flow="$gameFlow"
                    :home-color="$homeColor"
                    :away-color="$awayColor"
                    :home-name="$homeName"
                    :away-name="$awayName"
                />
            </div>
        @endif

        @if (count($lineupStats) > 0)
            @php
                $lineupColumns = [
                    'seconds' => __('Min'),
                    'points' => __('Pts'),
                    'opponentPoints' => __('Opp Pts'),
                    'twoMade' => __('2P'),
                    'threeMade' => __('3P'),
                    'freeMade' => __('FT'),
                    'plusMinus' => __('+/-'),
                ];
            @endphp
            <div class="mt-4 rounded-lg border border-zinc-200 bg-white shadow-sm">
                <div class="border-b border-zinc-200 px-4 py-3 sm:px-6">
                    <h2 class="text-base font-semibold">{{ __('Lineups') }}</h2>
                    <p class="text-sm text-zinc-500">{{ __('Time and production of each five on court') }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-max min-w-full text-xs">
                        <thead>
                            <tr class="border-b border-zinc-200 text-xs font-medium tracking-wide text-zinc-500 uppercase">
                                <th class="sticky left-0 z-10 bg-white px-3 py-2 text-left font-medium sm:px-6">{{ __('Court five') }}</th>
                                @foreach ($lineupColumns as $column => $label)
                                    <th class="px-1 py-2 text-center font-medium">
                                        <button
                                            type="button"
                                            wire:click="sortLineupsBy('{{ $column }}')"
                                            @class([
                                                'min-w-11 whitespace-nowrap rounded-md px-2 py-1 uppercase',
                                                'bg-zinc-900 text-white' => $lineupSort === $column,
                                                'hover:bg-zinc-100' => $lineupSort !== $column,
                                            ])
                                        >{{ $label }}</button>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lineupStats as $line)
                                <tr wire:key="lineup-{{ collect($line['players'])->pluck('id')->implode('-') }}" class="border-b border-zinc-100 last:border-0">
                                    <td class="sticky left-0 z-10 bg-white px-3 py-2 sm:px-6">
                                        <div class="flex gap-1">
                                            @foreach ($line['players'] as $player)
                                                <span
                                                    class="flex h-7 min-w-7 items-center justify-center rounded-md bg-amber-500 px-1 text-xs font-bold tabular-nums text-white"
                                                    title="{{ $player['label'] }}"
                                                >{{ $player['number'] ?? mb_substr($player['name'], 0, 2) }}</span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td @class(['px-2 py-2 text-center tabular-nums', 'bg-zinc-100' => $lineupSort === 'seconds'])>{{ $line['minutes'] }}</td>
                                    <td @class(['px-2 py-2 text-center font-semibold tabular-nums', 'bg-zinc-100' => $lineupSort === 'points'])>{{ $line['points'] }}</td>
                                    <td @class(['px-2 py-2 text-center tabular-nums', 'bg-zinc-100' => $lineupSort === 'opponentPoints'])>{{ $line['opponentPoints'] }}</td>
                                    <td @class(['whitespace-nowrap px-2 py-2 text-center tabular-nums', 'bg-zinc-100' => $lineupSort === 'twoMade'])>{{ $line['twoMade'] }}/{{ $line['twoAttempts'] }}</td>
                                    <td @class(['whitespace-nowrap px-2 py-2 text-center tabular-nums', 'bg-zinc-100' => $lineupSort === 'threeMade'])>{{ $line['threeMade'] }}/{{ $line['threeAttempts'] }}</td>
                                    <td @class(['whitespace-nowrap px-2 py-2 text-center tabular-nums', 'bg-zinc-100' => $lineupSort === 'freeMade'])>{{ $line['freeMade'] }}/{{ $line['freeAttempts'] }}</td>
                                    <td @class([
                                        'px-2 py-2 text-center font-semibold tabular-nums',
                                        'text-green-700' => $line['plusMinus'] > 0,
                                        'text-red-700' => $line['plusMinus'] < 0,
                                        'bg-zinc-100' => $lineupSort === 'plusMinus',
                                    ])>{{ $line['plusMinus'] > 0 ? '+'.$line['plusMinus'] : $line['plusMinus'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if (count($shotChartsByPeriod) > 0)
            <div class="mt-4 rounded-lg border border-zinc-200 bg-white px-4 py-4 shadow-sm sm:px-6">
                <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold">{{ __('Shot Charts') }}</h2>
                        <p class="text-sm text-zinc-500">{{ __('Court locations for shot makes and misses') }}</p>
                    </div>
                    <div class="flex items-center gap-4 text-xs text-zinc-500">
                        <span class="inline-flex items-center gap-2">
                            <svg viewBox="0 0 12 12" class="size-3 shrink-0" aria-hidden="true">
                                <circle cx="6" cy="6" r="4" fill="#16a34a" />
                            </svg>
                            {{ __('Made') }}
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <svg viewBox="0 0 12 12" class="size-3 shrink-0" aria-hidden="true">
                                <path d="M2.5 2.5 L9.5 9.5 M9.5 2.5 L2.5 9.5" fill="none" stroke="#dc2626" stroke-width="1.75" stroke-linecap="round" />
                            </svg>
                            {{ __('Missed') }}
                        </span>
                    </div>
                </div>
                <div class="mb-4 sm:hidden">
                    <flux:select wire:model.live="mobileShotChartFilter">
                        <flux:select.option value="home|team">{{ $homeName }}</flux:select.option>
                        @foreach ($homeShotChartPlayers as $player)
                            <flux:select.option value="home|player-{{ $player['id'] }}">
                                #{{ $player['number'] ?? '—' }} {{ $player['name'] }} · {{ $homeName }}
                            </flux:select.option>
                        @endforeach
                        <flux:select.option value="away|team">{{ $awayName }}</flux:select.option>
                        @foreach ($awayShotChartPlayers as $player)
                            <flux:select.option value="away|player-{{ $player['id'] }}">
                                #{{ $player['number'] ?? '—' }} {{ $player['name'] }} · {{ $awayName }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <div class="mb-4 hidden gap-4 sm:grid sm:grid-cols-2">
                    <flux:select wire:model.live="homeShotFilter">
                        <flux:select.option value="team">{{ $homeName }}</flux:select.option>
                        @foreach ($homeShotChartPlayers as $player)
                            <flux:select.option value="player-{{ $player['id'] }}">
                                #{{ $player['number'] ?? '—' }} {{ $player['name'] }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="awayShotFilter">
                        <flux:select.option value="team">{{ $awayName }}</flux:select.option>
                        @foreach ($awayShotChartPlayers as $player)
                            <flux:select.option value="player-{{ $player['id'] }}">
                                #{{ $player['number'] ?? '—' }} {{ $player['name'] }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <div class="space-y-6">
                    @foreach ($shotChartsByPeriod as $periodChart)
                        <div wire:key="shot-chart-period-{{ $periodChart['quarter'] }}-{{ $homeShotFilter }}-{{ $awayShotFilter }}-{{ $mobileShotChartFilter }}">
                            <h3 class="mb-3 text-center text-sm font-semibold tracking-wide text-zinc-700 uppercase">{{ __($periodChart['label']) }}</h3>
                            <div class="sm:hidden">
                                <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-3">
                                    <div class="mx-auto w-full max-w-md">
                                        <x-shot-chart :shots="$mobileShotChartSide === 'home' ? $periodChart['home'] : $periodChart['away']" />
                                    </div>
                                </div>
                            </div>
                            <div class="hidden gap-4 sm:grid sm:grid-cols-2">
                                <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-3">
                                    <div class="mx-auto w-[62.5%]">
                                        <x-shot-chart :shots="$periodChart['home']" />
                                    </div>
                                </div>
                                <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-3">
                                    <div class="mx-auto w-[62.5%]">
                                        <x-shot-chart :shots="$periodChart['away']" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
        </div>
    </div>

    @unless ($shared)
        <flux:modal wire:model="sharing" class="max-w-sm !bg-white">
            <div class="space-y-4">
                <div>
                    <h2 class="text-base font-semibold">{{ __('Share statistics') }}</h2>
                    <p class="mt-1 text-sm text-zinc-500">{{ __('Anyone with this link can view the statistics.') }}</p>
                </div>
                @if ($shareUrl)
                    <input type="text" readonly value="{{ $shareUrl }}" class="w-full rounded-lg border border-zinc-300 bg-zinc-50 px-3 py-2 text-sm text-zinc-800" />
                    <div class="flex flex-wrap justify-end gap-2">
                        <button type="button" wire:click="revokeShare" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-800">{{ __('Stop sharing') }}</button>
                        <button type="button" wire:click="rotateShare" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-800">{{ __('New link') }}</button>
                        <button
                            type="button"
                            class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white"
                            x-data
                            x-on:click="
                                const url = @js($shareUrl);
                                const copied = () => $wire.copiedShareLink();
                                const copy = () => navigator.clipboard.writeText(url).then(copied);
                                if (! navigator.share) {
                                    copy();
                                    return;
                                }
                                navigator.share({ title: @js(__('Statistics')), url }).catch((error) => {
                                    if (error && error.name !== 'AbortError') {
                                        copy();
                                    }
                                });
                            "
                        >{{ __('Share') }}</button>
                    </div>
                @endif
            </div>
        </flux:modal>
    @endunless
</div>
