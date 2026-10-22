<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="admin-surface min-h-screen dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="admin-sidebar border-e border-amber-900/10 dark:border-zinc-700">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    @can('viewAnyStaff', \App\Models\User::class)
                        <flux:sidebar.item icon="shield-check" :href="route('administrators.index')" :current="request()->routeIs('administrators.*')" wire:navigate>
                            {{ __('Administrators') }}
                        </flux:sidebar.item>
                    @endcan
                    @can(\App\Enums\PermissionName::CreateClubs->value)
                        <flux:sidebar.item icon="building-office-2" :href="route('clubs.index')" :current="request()->routeIs('clubs.*')" wire:navigate>
                            {{ __('Clubs') }}
                        </flux:sidebar.item>
                    @endcan
                    @can(\App\Enums\PermissionName::CreateUsers->value)
                        <flux:sidebar.item icon="users" :href="route('users.index')" :current="request()->routeIs('users.*')" wire:navigate>
                            {{ __('Users') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Competition')" class="grid">
                    @can(\App\Enums\PermissionName::CreateClubs->value)
                        <flux:sidebar.item icon="calendar" :href="route('seasons.index')" :current="request()->routeIs('seasons.*')" wire:navigate>
                            {{ __('Seasons') }}
                        </flux:sidebar.item>
                    @endcan
                    @can(\App\Enums\PermissionName::CreateUsers->value)
                        <flux:sidebar.item icon="user-group" :href="route('teams.index')" :current="request()->routeIs('teams.*')" wire:navigate>
                            {{ __('Teams') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="identification" :href="route('players.index')" :current="request()->routeIs('players.*')" wire:navigate>
                            {{ __('Players') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="trophy" :href="route('games.index')" :current="request()->routeIs('games.*')" wire:navigate>
                            {{ __('Games') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('collectAny', \App\Models\Game::class)
                        @cannot('viewAny', \App\Models\Game::class)
                            <flux:sidebar.item icon="trophy" :href="route('statistics.index')" :current="request()->routeIs('statistics.*')" wire:navigate>
                                {{ __('Games') }}
                            </flux:sidebar.item>
                        @endcannot
                    @endcan
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
