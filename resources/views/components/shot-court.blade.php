@props([
    'made' => true,
])

@php
    $courtWidth = \App\Support\ShotCourt::Width;
    $courtHeight = \App\Support\ShotCourt::Height;
@endphp

<div
    data-shot-court
    wire:ignore
    x-data="{
        marker: null,
        submitting: false,
        serverMade: @js($made),
        made: @js($made),
        init() {
            this.$watch('$store.collectQueue.choosingShot', (open) => {
                this.marker = null;

                if (! open) {
                    this.made = this.serverMade;

                    return;
                }

                this.made = ! String(this.$store.collectQueue?.shotType() ?? '').includes('missed');
            });
        },
        courtWidth: @js($courtWidth),
        courtHeight: @js($courtHeight),
        markerPositionStyle() {
            if (! this.marker) {
                return '';
            }

            const left = (this.marker.x / this.courtWidth) * 100;
            const top = (this.marker.y / this.courtHeight) * 100;

            return `left: ${left}%; top: ${top}%;`;
        },
        async mark(event) {
            if (this.submitting) {
                return;
            }

            const svg = this.$refs.court;

            if (! svg) {
                return;
            }

            const point = svg.createSVGPoint();
            point.x = event.clientX;
            point.y = event.clientY;
            const matrix = svg.getScreenCTM();

            if (! matrix) {
                return;
            }

            const location = point.matrixTransform(matrix.inverse());
            const x = Math.round(location.x);
            const y = Math.round(location.y);

            const queue = this.$store.collectQueue;

            if (! queue?.choosingShot) {
                return;
            }

            if (! queue.allowsShot(queue.shotType(), x, y)) {
                queue.shotError = true;
                this.marker = null;

                return;
            }

            queue.shotError = false;
            this.marker = { x, y };

            await new Promise((resolve) => window.setTimeout(resolve, 500));

            this.submitting = true;

            try {
                await queue.recordShot(x, y);
            } finally {
                this.submitting = false;

                if (queue.choosingShot) {
                    this.marker = null;
                }
            }
        },
    }"
    class="relative w-full"
>
    <svg
        x-ref="court"
        viewBox="0 0 {{ $courtWidth }} {{ $courtHeight }}"
        xmlns="http://www.w3.org/2000/svg"
        class="mx-auto block w-full max-w-xl cursor-crosshair touch-none select-none"
        :class="{ 'cursor-wait': submitting }"
        role="img"
        aria-label="{{ __('Half court') }}"
        @click.prevent="mark($event)"
    >
        <rect width="{{ $courtWidth }}" height="{{ $courtHeight }}" fill="#ffffff" />
        <x-half-court-markings stroke="#4a4867" three-point-stroke="#4a4867" />
    </svg>

    <div
        class="pointer-events-none absolute inset-0 mx-auto w-full max-w-xl"
        aria-hidden="true"
    >
        <span
            x-show="marker && made"
            x-cloak
            x-transition:enter="transition ease-out duration-75"
            x-transition:enter-start="scale-50 opacity-0"
            x-transition:enter-end="scale-100 opacity-100"
            class="absolute z-10 size-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-green-600 ring-1 ring-white"
            x-bind:style="markerPositionStyle()"
        ></span>
        <span
            x-show="marker && ! made"
            x-cloak
            x-transition:enter="transition ease-out duration-75"
            x-transition:enter-start="scale-50 opacity-0"
            x-transition:enter-end="scale-100 opacity-100"
            class="absolute z-10 size-2.5 -translate-x-1/2 -translate-y-1/2 text-red-600"
            x-bind:style="markerPositionStyle()"
        >
            <svg viewBox="0 0 24 24" class="size-2.5 drop-shadow-sm" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round">
                <line x1="6" y1="6" x2="18" y2="18" />
                <line x1="18" y1="6" x2="6" y2="18" />
            </svg>
        </span>
    </div>
</div>
