<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen">
        <div class="drawer">
            <input id="mobile-drawer" type="checkbox" class="drawer-toggle" />
            <div class="drawer-content flex flex-col">
                <!-- Top Navigation Bar -->
                <div class="navbar bg-base-200 border-b border-base-300">
                    <!-- Mobile menu toggle -->
                    <div class="flex-none lg:hidden">
                        <label for="mobile-drawer" class="btn btn-square btn-ghost">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-5 h-5 stroke-current">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                        </label>
                    </div>

                    <!-- Logo -->
                    <div class="flex-none">
                        <a href="{{ route('dashboard') }}" class="btn btn-ghost text-xl" wire:navigate>
                            <x-app-logo />
                        </a>
                    </div>

                    <!-- Desktop Navigation -->
                    <div class="flex-none hidden lg:flex">
                        <ul class="menu menu-horizontal px-1">
                            <li>
                                <a href="{{ route('dashboard') }}"
                                   class="@if(request()->routeIs('dashboard')) active @endif"
                                   wire:navigate>
                                    {{ __('Dashboard') }}
                                </a>
                            </li>
                        </ul>
                    </div>

                    <!-- Spacer -->
                    <div class="flex-1"></div>

                    <!-- Right side actions -->
                    <div class="flex-none gap-2">
                        <!-- Search button -->
                        <div class="tooltip tooltip-bottom" data-tip="{{ __('Search') }}">
                            <button class="btn btn-ghost btn-circle">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </button>
                        </div>

                        <!-- Repository link (desktop only) -->
                        <div class="tooltip tooltip-bottom hidden lg:flex" data-tip="{{ __('Repository') }}">
                            <a href="https://github.com/laravel/livewire-starter-kit" target="_blank" rel="noopener" class="btn btn-ghost btn-circle">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                </svg>
                            </a>
                        </div>

                        <!-- Documentation link (desktop only) -->
                        <div class="tooltip tooltip-bottom hidden lg:flex" data-tip="{{ __('Documentation') }}">
                            <a href="https://laravel.com/docs/starter-kits#livewire" target="_blank" rel="noopener" class="btn btn-ghost btn-circle">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                </svg>
                            </a>
                        </div>

                        <!-- User dropdown -->
                        <div class="dropdown dropdown-end">
                            <div tabindex="0" role="button" class="btn btn-ghost btn-circle avatar">
                                <div class="w-10 rounded-full bg-neutral flex items-center justify-center">
                                    <span class="text-sm font-semibold">{{ auth()->user()->initials() }}</span>
                                </div>
                            </div>
                            <ul tabindex="0" class="menu menu-sm dropdown-content mt-3 z-[1] p-2 shadow bg-base-100 rounded-box w-64">
                                <li class="menu-title">
                                    <div class="flex items-center gap-2 py-2">
                                        <div class="avatar placeholder">
                                            <div class="bg-neutral text-neutral-content rounded-lg w-8">
                                                <span class="text-xs">{{ auth()->user()->initials() }}</span>
                                            </div>
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="font-semibold text-sm truncate">{{ auth()->user()->name }}</span>
                                            <span class="text-xs opacity-70 truncate">{{ auth()->user()->email }}</span>
                                        </div>
                                    </div>
                                </li>
                                <li class="my-1"><hr /></li>
                                <li><a href="{{ route('settings.profile') }}" wire:navigate>{{ __('Settings') }}</a></li>
                                <li class="my-1"><hr /></li>
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

            <!-- Mobile Drawer -->
            <div class="drawer-side">
                <label for="mobile-drawer" aria-label="close sidebar" class="drawer-overlay"></label>
                <div class="menu p-4 w-80 min-h-full bg-base-200 flex flex-col">
                    <!-- Close button -->
                    <div class="flex justify-end mb-4">
                        <label for="mobile-drawer" class="btn btn-square btn-ghost">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </label>
                    </div>

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

                        <ul>
                            <li>
                                <a href="https://github.com/laravel/livewire-starter-kit" target="_blank" rel="noopener">
                                    {{ __('Repository') }}
                                </a>
                            </li>
                            <li>
                                <a href="https://laravel.com/docs/starter-kits#livewire" target="_blank" rel="noopener">
                                    {{ __('Documentation') }}
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        @livewireScripts
    </body>
</html>
