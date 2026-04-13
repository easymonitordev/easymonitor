<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="business">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ config('app.name', 'EasyMonitor') }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml" />
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-base-200 flex items-center justify-center p-6">
    <div class="max-w-lg w-full text-center">
        <div class="flex justify-center mb-6">
            <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary text-primary-content">
                <x-app-logo-icon class="size-9" />
            </span>
        </div>

        <h1 class="text-4xl font-bold mb-3">{{ config('app.name', 'EasyMonitor') }}</h1>

        <p class="text-lg text-base-content/70 mb-8">
            {{ __('Open-source uptime & performance monitoring.') }}
        </p>

        <div class="flex flex-col sm:flex-row gap-3 justify-center mb-12">
            @auth
                <a href="{{ route('dashboard') }}" wire:navigate class="btn btn-primary rounded-lg">
                    {{ __('Open Dashboard') }}
                </a>
            @else
                <a href="{{ route('login') }}" wire:navigate class="btn btn-primary rounded-lg">
                    {{ __('Sign In') }}
                </a>
                @if (\App\Models\User::registrationAllowed())
                    <a href="{{ route('register') }}" wire:navigate class="btn btn-ghost rounded-lg">
                        {{ __('Create Account') }}
                    </a>
                @endif
            @endauth
        </div>

        <div class="text-sm text-base-content/40">
            {{ __('Self-hosted · Multi-region probes · Public status pages') }}
        </div>
    </div>
</body>
</html>
