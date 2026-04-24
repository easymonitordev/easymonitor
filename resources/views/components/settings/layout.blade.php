<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <ul class="menu bg-base-200 rounded-box">
            <li>
                <a href="{{ route('settings.profile') }}"
                   class="@if(request()->routeIs('settings.profile')) active @endif"
                   wire:navigate>
                    {{ __('Profile') }}
                </a>
            </li>
            <li>
                <a href="{{ route('settings.password') }}"
                   class="@if(request()->routeIs('settings.password')) active @endif"
                   wire:navigate>
                    {{ __('Password') }}
                </a>
            </li>
            @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                <li>
                    <a href="{{ route('two-factor.show') }}"
                       class="@if(request()->routeIs('two-factor.show')) active @endif"
                       wire:navigate>
                        {{ __('Two-Factor Auth') }}
                    </a>
                </li>
            @endif
            <li>
                <a href="{{ route('settings.notifications') }}"
                   class="@if(request()->routeIs('settings.notifications')) active @endif"
                   wire:navigate>
                    {{ __('Notifications') }}
                </a>
            </li>
            <li>
                <a href="{{ route('settings.appearance') }}"
                   class="@if(request()->routeIs('settings.appearance')) active @endif"
                   wire:navigate>
                    {{ __('Appearance') }}
                </a>
            </li>
        </ul>
    </div>

    <div class="divider md:hidden"></div>

    <div class="flex-1 self-stretch max-md:pt-6">
        <div class="mb-4">
            <h1 class="text-2xl font-bold">{{ $heading ?? '' }}</h1>
            <p class="text-base-content/70 mt-1">{{ $subheading ?? '' }}</p>
        </div>

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
