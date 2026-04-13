<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen antialiased">
        <div class="flex min-h-screen flex-col items-center justify-center gap-6 p-6 md:p-10 bg-base-200">
            <div class="flex w-full max-w-md flex-col gap-6">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <span class="flex h-12 w-12 items-center justify-center rounded-lg bg-primary text-primary-content">
                        <x-app-logo-icon class="size-7" />
                    </span>

                    <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                </a>

                <div class="flex flex-col gap-6">
                    <div class="card bg-base-100 shadow-xl">
                        <div class="card-body">{{ $slot }}</div>
                    </div>
                </div>
            </div>
        </div>
        @livewireScripts
    </body>
</html>
