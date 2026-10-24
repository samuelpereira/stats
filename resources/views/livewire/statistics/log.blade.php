@php
    $playerFilters = $playerFilters ?? [];
    $actionFilters = $actionFilters ?? [];
@endphp
<div
    x-data="{
        playerFilter: '',
        actionFilter: '',
        matches(line) {
            if (line.actionKey === 'quarter_started' || line.actionKey === 'quarter_ended') {
                return true;
            }

            const playerOk = this.playerFilter === ''
                || (Array.isArray(line.playerKeys) && line.playerKeys.includes(this.playerFilter));
            const actionOk = this.actionFilter === '' || line.actionKey === this.actionFilter;

            return playerOk && actionOk;
        },
        isPeriodStart(line) {
            return line.actionKey === 'quarter_started';
        },
        isPeriodEnd(line) {
            return line.actionKey === 'quarter_ended';
        },
        isPeriod(line) {
            return this.isPeriodStart(line) || this.isPeriodEnd(line);
        },
    }"
    class="space-y-3"
>
    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
        <label class="block">
            <span class="sr-only">{{ __('Player') }}</span>
            <select
                x-model="playerFilter"
                class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900"
            >
                <option value="">{{ __('All players') }}</option>
                @foreach ($playerFilters as $option)
                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="sr-only">{{ __('Action') }}</span>
            <select
                x-model="actionFilter"
                class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900"
            >
                <option value="">{{ __('All actions') }}</option>
                @foreach ($actionFilters as $option)
                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <table class="w-full table-fixed text-left text-sm">
        <thead>
            <tr class="border-b border-zinc-200 text-xs font-medium tracking-wide text-zinc-500 uppercase">
                <th class="w-16 px-2 py-2 font-medium">{{ __('Clock') }}</th>
                <th class="px-2 py-2 font-medium">{{ __('Player') }}</th>
                <th class="px-2 py-2 text-right font-medium">{{ __('Action') }}</th>
            </tr>
        </thead>
        <tbody>
            <template x-for="line in $store.collectQueue.lines" :key="'pending-' + line.id">
                <tbody x-show="matches(line)">
                    <tr x-show="isPeriod(line)">
                        <td colspan="3" class="border-0 px-0 py-1.5">
                            <div
                                class="flex items-center gap-3 rounded-lg border px-3 py-2"
                                :class="isPeriodStart(line)
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                    : 'border-rose-200 bg-rose-50 text-rose-800'"
                            >
                                <span class="w-16 shrink-0 leading-tight">
                                    <span class="block tabular-nums font-semibold" x-text="line.clock"></span>
                                    <span class="block text-xs tabular-nums opacity-80" x-text="line.score"></span>
                                </span>
                                <span class="min-w-0 flex-1"></span>
                                <span class="font-semibold" x-text="line.label"></span>
                            </div>
                        </td>
                    </tr>
                    <tr
                        x-show="! isPeriod(line)"
                        class="border-b border-zinc-300 bg-zinc-200 align-top last:border-b-0"
                        :class="line.editable ? 'cursor-pointer' : ''"
                        @click="if (line.editable) $store.collectQueue.editPending(line.id)"
                    >
                        <td class="px-2 py-2 leading-tight" :style="line.side === 'home' ? 'box-shadow: inset 4px 0 0 {{ $homeColor }}' : ''">
                            <p class="tabular-nums text-zinc-500" x-text="line.clock"></p>
                            <p class="text-xs tabular-nums text-zinc-400" x-text="line.score"></p>
                        </td>
                        <td class="max-w-0 px-2 py-2">
                            <template x-if="line.playerLines.length > 0">
                                <div class="leading-tight">
                                    <template x-for="playerLine in line.playerLines" :key="playerLine.name">
                                        <p class="truncate font-medium" :class="playerLine.enters ? 'text-green-700' : 'text-red-700'" x-text="playerLine.name"></p>
                                    </template>
                                </div>
                            </template>
                            <p x-show="line.playerLines.length === 0" class="truncate" x-text="line.player"></p>
                        </td>
                        <td class="px-2 py-2 text-right" :style="line.side === 'away' ? 'box-shadow: inset -4px 0 0 {{ $awayColor }}' : ''">
                            <p class="font-medium" :class="line.missed ? 'text-zinc-400' : 'text-zinc-900'" x-text="line.label"></p>
                        </td>
                    </tr>
                </tbody>
            </template>
            @foreach ($lines as $line)
                @php($isPeriodStart = ($line['actionKey'] ?? '') === 'quarter_started')
                @php($isPeriodEnd = ($line['actionKey'] ?? '') === 'quarter_ended')
                @if ($isPeriodStart || $isPeriodEnd)
                    <tr
                        wire:key="{{ $keyPrefix }}-period-{{ $line['id'] }}"
                        x-show="matches(@js($line))"
                    >
                        <td colspan="3" class="border-0 px-0 py-1.5">
                            <div @class([
                                'flex items-center gap-3 rounded-lg border px-3 py-2',
                                'border-emerald-200 bg-emerald-50 text-emerald-800' => $isPeriodStart,
                                'border-rose-200 bg-rose-50 text-rose-800' => $isPeriodEnd,
                            ])>
                                <span class="w-16 shrink-0 leading-tight">
                                    <span class="block tabular-nums font-semibold">{{ $line['clock'] }}</span>
                                    <span class="block text-xs tabular-nums opacity-80">{{ $line['score'] }}</span>
                                </span>
                                <span class="min-w-0 flex-1"></span>
                                <span class="font-semibold">{{ __($line['label']) }}</span>
                            </div>
                        </td>
                    </tr>
                @else
                    <tr
                        wire:key="{{ $keyPrefix }}-{{ $line['id'] }}"
                        x-show="matches(@js($line))"
                        @class([
                            'border-b border-zinc-100 align-top last:border-b-0',
                            'cursor-pointer' => $line['editable'],
                        ])
                        @if ($line['editable']) wire:click="editAction({{ $line['id'] }})" @endif
                    >
                        <td class="px-2 py-2 leading-tight" @style(['box-shadow: inset 4px 0 0 '.$homeColor => $line['side'] === 'home'])>
                            <p class="tabular-nums text-zinc-500">{{ $line['clock'] }}</p>
                            <p class="text-xs tabular-nums text-zinc-400">{{ $line['score'] }}</p>
                        </td>
                        <td class="max-w-0 px-2 py-2">
                            @if ($line['playerLines'] !== [])
                                <div class="leading-tight">
                                    @foreach ($line['playerLines'] as $playerLine)
                                        <p @class(['truncate font-medium', 'text-red-700' => ! $playerLine['enters'], 'text-green-700' => $playerLine['enters']])>{{ $playerLine['name'] }}</p>
                                    @endforeach
                                </div>
                            @else
                                <p class="truncate">{{ $line['player'] }}</p>
                            @endif
                        </td>
                        <td class="px-2 py-2 text-right" @style(['box-shadow: inset -4px 0 0 '.$awayColor => $line['side'] === 'away'])>
                            <p @class(['font-medium', 'text-zinc-400' => $line['missed'], 'text-zinc-900' => ! $line['missed']])>{{ __($line['label']) }}</p>
                            @if ($line['detail'] !== '')
                                <p class="text-xs text-zinc-500">{{ $line['detail'] }}</p>
                            @endif
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</div>
