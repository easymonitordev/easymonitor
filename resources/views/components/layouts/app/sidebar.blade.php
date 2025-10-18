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
                        <div class="mb-4">
                            <h3 class="menu-title">{{ __('Platform') }}</h3>
                            <ul>
                                <li>
                                    <a href="{{ route('dashboard') }}"
                                       class="@if(request()->routeIs('dashboard')) active @endif"
                                       wire:navigate>
                                        {{ __('Dashboard') }}
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <div class="divider"></div>

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
