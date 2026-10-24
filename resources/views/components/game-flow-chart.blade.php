@props([
    /** @var array{points: list<array{x: float, home: int, away: int}>, markers: list<array{x: float, label: string}>, lineups: list<array{x: float, players: list<array{id: int, number: int|null, name: string, label: string}>}>, maxX: float, maxScore: int} */
    'flow',
    'homeColor',
    'awayColor',
    'homeName',
    'awayName',
    'lineupColor',
])

@php
    $width = 1000;
    $height = 420;
    $padL = 52;
    $padR = 28;
    $padT = 28;
    $padB = 56;
    $plotW = $width - $padL - $padR;
    $plotH = $height - $padT - $padB;
    $maxX = max((float) $flow['maxX'], 1.0);
    $maxScore = max((int) $flow['maxScore'], 1);
    $maxY = (int) (ceil($maxScore / 10) * 10);
    if ($maxY < 10) {
        $maxY = 10;
    }

    $mapX = static fn (float $x): float => $padL + ($x / $maxX) * $plotW;
    $mapY = static fn (int $score): float => $padT + $plotH - ($score / $maxY) * $plotH;
    $axisLeftPercent = ($padL / $width) * 100;
    $axisWidthPercent = ($plotW / $width) * 100;

    $homePath = [];
    $awayPath = [];

    foreach ($flow['points'] as $point) {
        $x = $mapX((float) $point['x']);
        $homePath[] = round($x, 2).','.round($mapY((int) $point['home']), 2);
        $awayPath[] = round($x, 2).','.round($mapY((int) $point['away']), 2);
    }

    $yTicks = [];
    $step = max(10, (int) ($maxY / 5));
    for ($tick = 0; $tick <= $maxY; $tick += $step) {
        $yTicks[] = $tick;
    }
    if ($yTicks[array_key_last($yTicks)] !== $maxY) {
        $yTicks[] = $maxY;
    }

    $lineups = $flow['lineups'] ?? [];
@endphp

<div
    {{ $attributes->merge(['class' => 'w-full']) }}
    x-data="{ openLineup: null }"
    @click.outside="openLineup = null"
>
    <div class="mb-3 flex flex-wrap items-center justify-center gap-4 text-sm">
        <span class="inline-flex items-center gap-2 font-medium text-zinc-800">
            <span class="size-2.5 rounded-full" style="background-color: {{ $homeColor }}"></span>
            {{ $homeName }}
        </span>
        <span class="inline-flex items-center gap-2 font-medium text-zinc-800">
            <span class="size-2.5 rounded-full" style="background-color: {{ $awayColor }}"></span>
            {{ $awayName }}
        </span>
    </div>

    <svg
        viewBox="0 0 {{ $width }} {{ $height }}"
        xmlns="http://www.w3.org/2000/svg"
        class="mx-auto block w-full"
        role="img"
        aria-label="{{ __('Game flow') }}"
    >
        <rect x="0" y="0" width="{{ $width }}" height="{{ $height }}" fill="#ffffff" />

        @foreach ($yTicks as $tick)
            @php($y = $mapY($tick))
            <line
                x1="{{ $padL }}"
                y1="{{ $y }}"
                x2="{{ $width - $padR }}"
                y2="{{ $y }}"
                stroke="#e4e4e7"
                stroke-width="1"
            />
            <text
                x="{{ $padL - 10 }}"
                y="{{ $y + 4 }}"
                text-anchor="end"
                class="fill-zinc-400"
                font-size="14"
                font-family="ui-sans-serif, system-ui, sans-serif"
            >{{ $tick }}</text>
        @endforeach

        @foreach ($flow['markers'] as $marker)
            @php($x = $mapX((float) $marker['x']))
            <line
                x1="{{ $x }}"
                y1="{{ $padT }}"
                x2="{{ $x }}"
                y2="{{ $padT + $plotH }}"
                stroke="#d4d4d8"
                stroke-width="1"
                stroke-dasharray="4 4"
            />
            <text
                x="{{ $x }}"
                y="{{ $height - 18 }}"
                text-anchor="middle"
                class="fill-zinc-500"
                font-size="14"
                font-weight="600"
                font-family="ui-sans-serif, system-ui, sans-serif"
            >{{ $marker['label'] }}</text>
        @endforeach

        <polyline
            fill="none"
            stroke="{{ $awayColor }}"
            stroke-width="3"
            stroke-linejoin="round"
            stroke-linecap="round"
            points="{{ implode(' ', $awayPath) }}"
        />
        <polyline
            fill="none"
            stroke="{{ $homeColor }}"
            stroke-width="3"
            stroke-linejoin="round"
            stroke-linecap="round"
            points="{{ implode(' ', $homePath) }}"
        />

        @foreach ($flow['points'] as $index => $point)
            @if ($index > 0)
                @php($previous = $flow['points'][$index - 1])
                @php($x = $mapX((float) $point['x']))
                @if ((int) $point['home'] !== (int) $previous['home'])
                    <circle cx="{{ $x }}" cy="{{ $mapY((int) $point['home']) }}" r="4.5" fill="{{ $homeColor }}" stroke="#ffffff" stroke-width="1.5" />
                @endif
                @if ((int) $point['away'] !== (int) $previous['away'])
                    <circle cx="{{ $x }}" cy="{{ $mapY((int) $point['away']) }}" r="4.5" fill="{{ $awayColor }}" stroke="#ffffff" stroke-width="1.5" />
                @endif
            @endif
        @endforeach
    </svg>

    @if ($lineups !== [])
        <div class="relative mt-1" style="margin-left: {{ $axisLeftPercent }}%; width: {{ $axisWidthPercent }}%;">
            <p class="mb-2 text-center text-xs text-zinc-500">{{ __('Tap a marker to see who was on court') }}</p>
            <div class="relative h-10">
                @foreach ($lineups as $index => $lineup)
                    @php($leftPercent = (min((float) $lineup['x'], $maxX) / $maxX) * 100)
                    <div class="absolute top-0 -translate-x-1/2" style="left: {{ $leftPercent }}%">
                        <button
                            type="button"
                            class="flex flex-col items-center gap-1 rounded-md px-1 py-0.5 text-zinc-700 hover:bg-zinc-100"
                            @click.stop="openLineup = openLineup === {{ $index }} ? null : {{ $index }}"
                            :aria-expanded="openLineup === {{ $index }} ? 'true' : 'false'"
                            aria-label="{{ __('On court') }}"
                        >
                            <span
                                class="block size-2.5 rotate-45 border border-white shadow-sm"
                                style="background-color: {{ $lineupColor }}"
                            ></span>
                            <span class="text-[10px] font-semibold tabular-nums text-zinc-500">
                                {{ $index === 0 ? __('Starting five') : __('sub') }}
                            </span>
                        </button>

                        <div
                            x-show="openLineup === {{ $index }}"
                            x-cloak
                            class="absolute bottom-full left-1/2 z-20 mb-2 w-40 -translate-x-1/2 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-left shadow-lg"
                            @click.stop
                        >
                            <p class="mb-1 text-[11px] font-semibold tracking-wide text-zinc-500 uppercase">{{ __('On court') }}</p>
                            <ul class="space-y-0.5">
                                @foreach ($lineup['players'] as $player)
                                    <li class="truncate text-xs font-medium text-zinc-900">{{ $player['label'] }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
