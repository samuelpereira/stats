<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-dvh bg-zinc-100 text-zinc-900 antialiased">
        <div class="mx-auto flex min-h-dvh w-full max-w-lg flex-col px-6 py-8 sm:py-10">
            <main class="flex flex-1 flex-col items-center justify-center gap-8 py-10 text-center">
                <div class="flex flex-col items-center gap-4">
                    <x-app-logo-icon class="size-[5.25rem]" />
                    <div class="space-y-2">
                        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900">
                            {{ config('app.name') }}
                        </h1>
                        <p class="max-w-sm text-sm leading-relaxed text-zinc-600">
                            {{ __('Collect live stats, box scores, and team reports in one place.') }}
                        </p>
                    </div>
                </div>

                @auth
                    <a
                        href="{{ route('dashboard') }}"
                        wire:navigate
                        class="inline-flex items-center justify-center rounded-lg bg-zinc-900 px-6 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-zinc-800"
                    >
                        {{ __('Go to dashboard') }}
                    </a>
                @else
                    @if (Route::has('login'))
                        <div class="flex flex-col items-center gap-3">
                            <a
                                href="{{ route('login') }}"
                                wire:navigate
                                class="inline-flex items-center justify-center rounded-lg bg-zinc-900 px-6 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-zinc-800"
                            >
                                {{ __('Log in') }}
                            </a>
                        </div>
                    @endif
                @endauth
            </main>
        </div>
    </body>
</html>
