<div>
    @if ($shared)
        <div class="flex h-dvh min-h-0 flex-col bg-zinc-100 text-zinc-900">
            <header class="shrink-0 border-b border-zinc-200 bg-white">
                <div class="mx-auto flex w-full max-w-5xl items-center justify-center px-4 py-3">
                    <h1 class="truncate text-center text-sm font-medium sm:text-base">{{ __('Season statistics') }}</h1>
                </div>
            </header>

            <div class="min-h-0 flex-1 overflow-y-auto">
                <div class="mx-auto flex w-full max-w-5xl flex-col gap-4 px-4 py-4 sm:px-6">
                    <div class="rounded-lg border border-zinc-200 bg-white px-4 py-6 shadow-sm">
                        <p class="text-center text-sm text-zinc-500">{{ $this->team->season->name }}</p>
                        <p class="mt-2 text-center text-lg font-semibold tracking-tight">{{ $this->team->name }}</p>
                    </div>

                    @include('livewire.teams.partials.season-statistics-sections', [
                        'boxScore' => $boxScore,
                        'gamesHistory' => $gamesHistory,
                        'matchupHistory' => $matchupHistory,
                    ])
                </div>
            </div>
        </div>
    @else
        <div class="-m-6 flex min-h-dvh flex-col gap-6 bg-white p-6 text-zinc-900 lg:-m-8 lg:p-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <a href="{{ route('teams.index') }}" wire:navigate class="inline-flex items-center gap-2 text-sm font-medium text-zinc-600 hover:text-zinc-900">
                        <flux:icon name="arrow-left" class="size-4" />
                        {{ __('Back') }}
                    </a>
                    <h1 class="mt-3 text-2xl font-semibold tracking-tight">{{ __('Season statistics') }}</h1>
                    <p class="mt-1 text-sm text-zinc-500">{{ $this->team->name }} · {{ $this->team->season->name }}</p>
                </div>
                <div class="flex justify-end sm:pt-10">
                    <button type="button" wire:click="openShare" class="inline-flex size-9 items-center justify-center rounded-lg border border-zinc-300 text-zinc-800 hover:border-zinc-900" aria-label="{{ __('Share') }}">
                        <flux:icon name="share" class="size-4" />
                    </button>
                </div>
            </div>

            @include('livewire.teams.partials.season-statistics-sections', [
                'boxScore' => $boxScore,
                'gamesHistory' => $gamesHistory,
                'matchupHistory' => $matchupHistory,
            ])

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
                                    navigator.share({ title: @js(__('Season statistics')), url }).catch((error) => {
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
        </div>
    @endif
</div>
