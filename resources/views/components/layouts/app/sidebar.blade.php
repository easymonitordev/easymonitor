<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen">
        <div class="drawer lg:drawer-open">
            <input id="main-drawer" type="checkbox" class="drawer-toggle" />
            <div class="drawer-content flex flex-col">
                <!-- Mobile Header -->
                <div class="navbar bg-base-300 lg:hidden">
                    <div class="flex-none">
                        <label for="main-drawer" class="btn btn-square btn-ghost">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-5 h-5 stroke-current">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                        </label>
                    </div>
                    <div class="flex-1">
                        <a href="{{ route('dashboard') }}" class="btn btn-ghost text-xl" wire:navigate>
                            <x-app-logo />
                        </a>
                    </div>
                    <div class="flex-none">
                        <div class="dropdown dropdown-end">
                            <div tabindex="0" role="button" class="btn btn-ghost btn-circle avatar">
                                <div class="w-10 rounded-full bg-neutral flex items-center justify-center">
                                    <span class="text-sm font-semibold">{{ auth()->user()->initials() }}</span>
                                </div>
                            </div>
                            <ul tabindex="0" class="menu menu-sm dropdown-content mt-3 z-[1] p-2 shadow bg-base-100 rounded-box w-52">
                                <li class="menu-title">
                                    <div class="flex flex-col">
                                        <span class="font-semibold">{{ auth()->user()->name }}</span>
                                        <span class="text-xs opacity-70">{{ auth()->user()->email }}</span>
                                    </div>
                                </li>
                                <li><a href="{{ route('settings.profile') }}" wire:navigate>{{ __('Settings') }}</a></li>
                                <li>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="w-full text-left">{{ __('Log Out') }}</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Page Content -->
                {{ $slot }}
            </div>

            <!-- Sidebar -->
            <div class="drawer-side">
                <label for="main-drawer" aria-label="close sidebar" class="drawer-overlay"></label>
                <div class="menu p-4 w-80 min-h-full bg-base-200 flex flex-col">
                    <!-- Logo -->
                    <a href="{{ route('dashboard') }}" class="mb-6 flex items-center space-x-2" wire:navigate>
                        <x-app-logo />
                    </a>

                    <!-- Navigation -->
                    <div class="flex-1">
                        <ul class="menu w-full p-0 gap-0.5">
                            <li>
                                <a href="{{ route('dashboard') }}"
                                   class="@if(request()->routeIs('dashboard')) menu-active @endif"
                                   wire:navigate>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                                    </svg>
                                    {{ __('Dashboard') }}
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('monitors.index') }}"
                                   class="@if(request()->routeIs('monitors.*')) menu-active @endif"
                                   wire:navigate>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                    </svg>
                                    {{ __('Monitors') }}
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('projects.index') }}"
                                   class="@if(request()->routeIs('projects.*')) menu-active @endif"
                                   wire:navigate>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                    </svg>
                                    {{ __('Projects') }}
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('status-pages.index') }}"
                                   class="@if(request()->routeIs('status-pages.*')) menu-active @endif"
                                   wire:navigate>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a4 4 0 014-4h6m0 0l-3-3m3 3l-3 3m-9 4H5a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v6" />
                                    </svg>
                                    {{ __('Status Pages') }}
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('teams.index') }}"
                                   class="@if(request()->routeIs('teams.*')) menu-active @endif"
                                   wire:navigate>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    {{ __('Teams') }}
                                </a>
                            </li>
                        </ul>
                    </div>

                    <!-- Desktop User Menu -->
                    <div class="hidden lg:block mt-auto">
                        <div class="divider"></div>
                        <div class="dropdown dropdown-top">
                            <div tabindex="0" role="button" class="btn btn-ghost w-full justify-start">
                                <div class="avatar avatar-placeholder">
                                    <div class="bg-neutral text-neutral-content rounded-full w-10">
                                        <span class="text-sm">{{ auth()->user()->initials() }}</span>
                                    </div>
                                </div>
                                <div class="flex flex-col items-start flex-1">
                                    <span class="text-sm font-semibold truncate w-full text-left">{{ auth()->user()->name }}</span>
                                    <span class="text-xs opacity-70 truncate w-full text-left">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                            <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52 mb-2">
                                <li><a href="{{ route('settings.profile') }}" wire:navigate>{{ __('Settings') }}</a></li>
                                <li>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="w-full text-left">{{ __('Log Out') }}</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @livewireScripts
    </body>
</html>
