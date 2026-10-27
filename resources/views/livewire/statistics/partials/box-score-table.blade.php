@props([
    'boxScore',
    'teamName',
    'opponentName' => null,
    'showGp' => false,
    'showPlusMinus' => true,
    'wireKeyPrefix' => 'box',
])

<div class="overflow-x-auto">
    <table class="box-score-table w-max min-w-full text-left text-xs">
        <thead>
            <tr class="text-xs font-medium tracking-wide text-zinc-500 uppercase">
                <th class="sticky left-0 z-10 bg-white w-44 whitespace-nowrap py-2 pr-3 pl-4 font-medium sm:pl-6">{{ __('Player') }}</th>
                @if ($showGp)
                    <th class="whitespace-nowrap px-2 py-2 text-center font-medium">{{ __('GP') }}</th>
                @endif
                <th class="whitespace-nowrap px-2 py-2 text-center font-medium">{{ __('Min') }}</th>
                <th class="whitespace-nowrap px-2 py-2 text-right font-medium">{{ __('Pts') }}</th>
                <th class="min-w-14 whitespace-nowrap px-2 py-2 text-right font-medium">{{ __('2P') }}</th>
                <th class="whitespace-nowrap px-2 py-2 text-center font-medium">{{ __('%') }}</th>
                <th class="min-w-14 whitespace-nowrap px-2 py-2 text-right font-medium">{{ __('3P') }}</th>
                <th class="whitespace-nowrap px-2 py-2 text-center font-medium">{{ __('%') }}</th>
                <th class="min-w-14 whitespace-nowrap px-2 py-2 text-right font-medium">{{ __('FT') }}</th>
                <th class="whitespace-nowrap px-2 py-2 text-center font-medium">{{ __('%') }}</th>
                <th class="whitespace-nowrap px-2 py-2 text-center font-medium">{{ __('DR') }}</th>
                <th class="whitespace-nowrap px-2 py-2 text-center font-medium">{{ __('OR') }}</th>
                <th class="whitespace-nowrap px-2 py-2 text-center font-medium">{{ __('TR') }}</th>
                <th class="whitespace-nowrap px-2 py-2 text-center font-medium">{{ __('AST') }}</th>
                <th class="whitespace-nowrap px-2 py-2 text-center font-medium">{{ __('BLK') }}</th>
                <th class="whitespace-nowrap px-2 py-2 text-center font-medium">{{ __('STL') }}</th>
                <th class="whitespace-nowrap px-2 py-2 text-center font-medium">{{ __('TO') }}</th>
                @if ($showPlusMinus)
                    <th class="whitespace-nowrap px-2 py-2 text-center font-medium">{{ __('+/-') }}</th>
                @endif
                <th class="whitespace-nowrap px-2 py-2 text-center font-medium">{{ __('PF') }}</th>
                <th class="whitespace-nowrap px-2 py-2 text-center font-medium">{{ __('TF') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($boxScore['players'] as $player)
                <tr wire:key="{{ $wireKeyPrefix }}-{{ $player['id'] }}">
                    <td class="sticky left-0 z-10 bg-white w-44 whitespace-nowrap py-2 pr-3 pl-4 sm:pl-6">
                        <span class="font-medium tabular-nums">{{ $player['number'] ?? '—' }}</span>
                        <span class="ml-2">{{ $player['name'] }}</span>
                    </td>
                    @if ($showGp)
                        <td class="px-2 py-2 text-center tabular-nums">{{ $player['gp'] ?? 0 }}</td>
                    @endif
                    <td class="px-2 py-2 text-center tabular-nums">{{ $player['minutes'] }}</td>
                    <td class="px-2 py-2 text-right font-semibold tabular-nums">{{ $player['points'] }}</td>
                    <td class="min-w-14 whitespace-nowrap px-2 py-2 text-right tabular-nums">{{ $player['twoMade'] }}-{{ $player['twoAttempts'] }}</td>
                    <td class="px-2 py-2 text-center tabular-nums">{{ $player['twoAttempts'] > 0 ? round(100 * $player['twoMade'] / $player['twoAttempts']).'%' : '—' }}</td>
                    <td class="min-w-14 whitespace-nowrap px-2 py-2 text-right tabular-nums">{{ $player['threeMade'] }}-{{ $player['threeAttempts'] }}</td>
                    <td class="px-2 py-2 text-center tabular-nums">{{ $player['threeAttempts'] > 0 ? round(100 * $player['threeMade'] / $player['threeAttempts']).'%' : '—' }}</td>
                    <td class="min-w-14 whitespace-nowrap px-2 py-2 text-right tabular-nums">{{ $player['freeMade'] }}-{{ $player['freeAttempts'] }}</td>
                    <td class="px-2 py-2 text-center tabular-nums">{{ $player['freeAttempts'] > 0 ? round(100 * $player['freeMade'] / $player['freeAttempts']).'%' : '—' }}</td>
                    <td class="px-2 py-2 text-center tabular-nums">{{ $player['defensiveRebounds'] }}</td>
                    <td class="px-2 py-2 text-center tabular-nums">{{ $player['offensiveRebounds'] }}</td>
                    <td class="px-2 py-2 text-center tabular-nums">{{ $player['offensiveRebounds'] + $player['defensiveRebounds'] }}</td>
                    <td class="px-2 py-2 text-center tabular-nums">{{ $player['assists'] }}</td>
                    <td class="px-2 py-2 text-center tabular-nums">{{ $player['blocks'] }}</td>
                    <td class="px-2 py-2 text-center tabular-nums">{{ $player['steals'] }}</td>
                    <td class="px-2 py-2 text-center tabular-nums">{{ $player['turnovers'] }}</td>
                    @if ($showPlusMinus)
                        <td class="px-2 py-2 text-center tabular-nums">{{ $player['plusMinus'] > 0 ? '+'.$player['plusMinus'] : $player['plusMinus'] }}</td>
                    @endif
                    <td class="px-2 py-2 text-center tabular-nums">{{ $player['fouls'] }}</td>
                    <td class="px-2 py-2 text-center tabular-nums">{{ $player['technicalFouls'] }}</td>
                </tr>
            @endforeach
            <tr class="font-semibold">
                <td class="sticky left-0 z-10 bg-white w-44 whitespace-nowrap py-2 pr-3 pl-4 sm:pl-6">{{ $teamName }}</td>
                @if ($showGp)
                    <td class="px-2 py-2 text-center tabular-nums">{{ $boxScore['team']['gp'] ?? '—' }}</td>
                @endif
                <td class="px-2 py-2 text-center text-zinc-400">—</td>
                <td class="min-w-14 whitespace-nowrap px-2 py-2 text-right tabular-nums">{{ $boxScore['team']['points'] }}</td>
                <td class="min-w-14 whitespace-nowrap px-2 py-2 text-right tabular-nums">{{ $boxScore['team']['twoMade'] }}-{{ $boxScore['team']['twoAttempts'] }}</td>
                <td class="px-2 py-2 text-center tabular-nums">{{ $boxScore['team']['twoAttempts'] > 0 ? round(100 * $boxScore['team']['twoMade'] / $boxScore['team']['twoAttempts']).'%' : '—' }}</td>
                <td class="min-w-14 whitespace-nowrap px-2 py-2 text-right tabular-nums">{{ $boxScore['team']['threeMade'] }}-{{ $boxScore['team']['threeAttempts'] }}</td>
                <td class="px-2 py-2 text-center tabular-nums">{{ $boxScore['team']['threeAttempts'] > 0 ? round(100 * $boxScore['team']['threeMade'] / $boxScore['team']['threeAttempts']).'%' : '—' }}</td>
                <td class="min-w-14 whitespace-nowrap px-2 py-2 text-right tabular-nums">{{ $boxScore['team']['freeMade'] }}-{{ $boxScore['team']['freeAttempts'] }}</td>
                <td class="px-2 py-2 text-center tabular-nums">{{ $boxScore['team']['freeAttempts'] > 0 ? round(100 * $boxScore['team']['freeMade'] / $boxScore['team']['freeAttempts']).'%' : '—' }}</td>
                <td class="px-2 py-2 text-center tabular-nums">{{ $boxScore['team']['defensiveRebounds'] }}</td>
                <td class="px-2 py-2 text-center tabular-nums">{{ $boxScore['team']['offensiveRebounds'] }}</td>
                <td class="px-2 py-2 text-center tabular-nums">{{ $boxScore['team']['offensiveRebounds'] + $boxScore['team']['defensiveRebounds'] }}</td>
                <td class="px-2 py-2 text-center tabular-nums">{{ $boxScore['team']['assists'] }}</td>
                <td class="px-2 py-2 text-center tabular-nums">{{ $boxScore['team']['blocks'] }}</td>
                <td class="px-2 py-2 text-center tabular-nums">{{ $boxScore['team']['steals'] }}</td>
                <td class="px-2 py-2 text-center tabular-nums">{{ $boxScore['team']['turnovers'] }}</td>
                @if ($showPlusMinus)
                    <td class="px-2 py-2 text-center text-zinc-400">—</td>
                @endif
                <td class="px-2 py-2 text-center tabular-nums">{{ $boxScore['team']['fouls'] }}</td>
                <td class="px-2 py-2 text-center tabular-nums">{{ $boxScore['team']['technicalFouls'] }}</td>
            </tr>
            @if ($opponentName !== null)
                <tr class="font-semibold">
                    <td class="sticky left-0 z-10 bg-white w-44 whitespace-nowrap py-2 pr-3 pl-4 sm:pl-6">{{ $opponentName }}</td>
                    @if ($showGp)
                        <td class="px-2 py-2 text-center text-zinc-400">—</td>
                    @endif
                    <td class="px-2 py-2 text-center text-zinc-400">—</td>
                    <td class="min-w-14 whitespace-nowrap px-2 py-2 text-right tabular-nums">{{ $boxScore['opponent']['points'] }}</td>
                    <td class="min-w-14 whitespace-nowrap px-2 py-2 text-right tabular-nums">{{ $boxScore['opponent']['twoMade'] }}-{{ $boxScore['opponent']['twoAttempts'] }}</td>
                    <td class="px-2 py-2 text-center tabular-nums">{{ $boxScore['opponent']['twoAttempts'] > 0 ? round(100 * $boxScore['opponent']['twoMade'] / $boxScore['opponent']['twoAttempts']).'%' : '—' }}</td>
                    <td class="min-w-14 whitespace-nowrap px-2 py-2 text-right tabular-nums">{{ $boxScore['opponent']['threeMade'] }}-{{ $boxScore['opponent']['threeAttempts'] }}</td>
                    <td class="px-2 py-2 text-center tabular-nums">{{ $boxScore['opponent']['threeAttempts'] > 0 ? round(100 * $boxScore['opponent']['threeMade'] / $boxScore['opponent']['threeAttempts']).'%' : '—' }}</td>
                    <td class="min-w-14 whitespace-nowrap px-2 py-2 text-right tabular-nums">{{ $boxScore['opponent']['freeMade'] }}-{{ $boxScore['opponent']['freeAttempts'] }}</td>
                    <td class="px-2 py-2 text-center tabular-nums">{{ $boxScore['opponent']['freeAttempts'] > 0 ? round(100 * $boxScore['opponent']['freeMade'] / $boxScore['opponent']['freeAttempts']).'%' : '—' }}</td>
                    <td class="px-2 py-2 text-center tabular-nums">{{ $boxScore['opponent']['defensiveRebounds'] }}</td>
                    <td class="px-2 py-2 text-center tabular-nums">{{ $boxScore['opponent']['offensiveRebounds'] }}</td>
                    <td class="px-2 py-2 text-center tabular-nums">{{ $boxScore['opponent']['offensiveRebounds'] + $boxScore['opponent']['defensiveRebounds'] }}</td>
                    <td class="px-2 py-2 text-center tabular-nums">{{ $boxScore['opponent']['assists'] }}</td>
                    <td class="px-2 py-2 text-center tabular-nums">{{ $boxScore['opponent']['blocks'] }}</td>
                    <td class="px-2 py-2 text-center tabular-nums">{{ $boxScore['opponent']['steals'] }}</td>
                    <td class="px-2 py-2 text-center tabular-nums">{{ $boxScore['opponent']['turnovers'] }}</td>
                    @if ($showPlusMinus)
                        <td class="px-2 py-2 text-center text-zinc-400">—</td>
                    @endif
                    <td class="px-2 py-2 text-center tabular-nums">{{ $boxScore['opponent']['fouls'] }}</td>
                    <td class="px-2 py-2 text-center tabular-nums">{{ $boxScore['opponent']['technicalFouls'] }}</td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
