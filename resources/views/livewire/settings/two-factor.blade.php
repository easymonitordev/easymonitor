<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout
        :heading="__('Two Factor Authentication')"
        :subheading="__('Manage your two-factor authentication settings')"
    >
        <div class="flex flex-col w-full mx-auto space-y-6 text-sm" wire:cloak>
            @if ($twoFactorEnabled)
                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="badge badge-success">{{ __('Enabled') }}</div>
                    </div>

                    <p class="text-base-content">
                        {{ __('With two-factor authentication enabled, you will be prompted for a secure, random pin during login, which you can retrieve from the TOTP-supported application on your phone.') }}
                    </p>

                    <livewire:settings.two-factor.recovery-codes :$requiresConfirmation/>

                    <div class="flex justify-start">
                        <button
                            class="btn btn-error rounded-lg"
                            wire:click="disable"
                        >
                            {{ __('Disable 2FA') }}
                        </button>
                    </div>
                </div>
            @else
                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="badge badge-error">{{ __('Disabled') }}</div>
                    </div>

                    <p class="text-base-content/70">
                        {{ __('When you enable two-factor authentication, you will be prompted for a secure pin during login. This pin can be retrieved from a TOTP-supported application on your phone.') }}
                    </p>

                    <button
                        class="btn btn-primary rounded-lg"
                        wire:click="enable"
                    >
                        {{ __('Enable 2FA') }}
                    </button>
                </div>
            @endif
        </div>
    </x-settings.layout>

    <div
        class="modal"
        x-data="{ open: @entangle('showModal') }"
        x-show="open"
        :class="{ 'modal-open': open }"
        x-cloak
    >
        <div class="modal-box max-w-md">
            <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2" @click="open = false">✕</button>

            <div class="space-y-6">
                <div class="flex flex-col items-center space-y-4">
                    <div class="p-0.5 w-auto rounded-full border border-base-300 bg-base-100 shadow-sm">
                        <div class="p-2.5 rounded-full border border-base-300 overflow-hidden bg-base-200 relative">
                            <div class="flex items-stretch absolute inset-0 w-full h-full divide-x [&>div]:flex-1 divide-base-300 justify-around opacity-50">
                                @for ($i = 1; $i <= 5; $i++)
                                    <div></div>
                                @endfor
                            </div>

                            <div class="flex flex-col items-stretch absolute w-full h-full divide-y [&>div]:flex-1 inset-0 divide-base-300 justify-around opacity-50">
                                @for ($i = 1; $i <= 5; $i++)
                                    <div></div>
                                @endfor
                            </div>

                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="relative z-20 w-12 h-12">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75zM6.75 16.5h.75v.75h-.75v-.75zM16.5 6.75h.75v.75h-.75v-.75zM13.5 13.5h.75v.75h-.75v-.75zM13.5 19.5h.75v.75h-.75v-.75zM19.5 13.5h.75v.75h-.75v-.75zM19.5 19.5h.75v.75h-.75v-.75zM16.5 16.5h.75v.75h-.75v-.75z" />
                            </svg>
                        </div>
                    </div>

                    <div class="space-y-2 text-center">
                        <h3 class="text-lg font-bold">{{ $this->modalConfig['title'] }}</h3>
                        <p class="text-base-content/70">{{ $this->modalConfig['description'] }}</p>
                    </div>
                </div>

            @if ($showVerificationStep)
                <div class="space-y-6">
                    <div class="flex flex-col items-center space-y-3">
                        <x-input-otp
                            :digits="6"
                            name="code"
                            wire:model="code"
                            autocomplete="one-time-code"
                        />
                        @error('code')
                            <div class="text-error text-sm text-center">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div class="flex items-center space-x-3">
                        <button
                            class="btn btn-ghost flex-1 rounded-lg"
                            wire:click="resetVerification"
                        >
                            {{ __('Back') }}
                        </button>

                        <button
                            class="btn btn-primary flex-1 rounded-lg"
                            wire:click="confirmTwoFactor"
                            x-bind:disabled="$wire.code.length < 6"
                        >
                            {{ __('Confirm') }}
                        </button>
                    </div>
                </div>
            @else
                @error('setupData')
                    <div role="alert" class="alert alert-error">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0 stroke-current" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ $message }}</span>
                    </div>
                @enderror

                <div class="flex justify-center">
                    <div class="relative w-64 overflow-hidden border rounded-lg border-base-300 aspect-square">
                        @empty($qrCodeSvg)
                            <div class="absolute inset-0 flex items-center justify-center bg-base-100 animate-pulse">
                                <span class="loading loading-spinner loading-lg"></span>
                            </div>
                        @else
                            <div class="flex items-center justify-center h-full p-4">
                                {!! $qrCodeSvg !!}
                            </div>
                        @endempty
                    </div>
                </div>

                <div>
                    <button
                        class="btn btn-primary w-full rounded-lg"
                        @if($errors->has('setupData')) disabled @endif
                        wire:click="showVerificationIfNecessary"
                    >
                        {{ $this->modalConfig['buttonText'] }}
                    </button>
                </div>

                <div class="space-y-4">
                    <div class="relative flex items-center justify-center w-full">
                        <div class="absolute inset-0 w-full h-px top-1/2 bg-base-300"></div>
                        <span class="relative px-2 text-sm bg-base-100 text-base-content/70">
                            {{ __('or, enter the code manually') }}
                        </span>
                    </div>

                    <div
                        class="flex items-center space-x-2"
                        x-data="{
                            copied: false,
                            async copy() {
                                try {
                                    await navigator.clipboard.writeText('{{ $manualSetupKey }}');
                                    this.copied = true;
                                    setTimeout(() => this.copied = false, 1500);
                                } catch (e) {
                                    console.warn('Could not copy to clipboard');
                                }
                            }
                        }"
                    >
                        <div class="flex items-stretch w-full border rounded-xl border-base-300">
                            @empty($manualSetupKey)
                                <div class="flex items-center justify-center w-full p-3 bg-base-200">
                                    <span class="loading loading-spinner loading-sm"></span>
                                </div>
                            @else
                                <input
                                    type="text"
                                    readonly
                                    value="{{ $manualSetupKey }}"
                                    class="w-full p-3 bg-transparent outline-none text-base-content"
                                />

                                <button
                                    @click="copy()"
                                    class="px-3 transition-colors border-l cursor-pointer border-base-300 hover:bg-base-200"
                                >
                                    <svg x-show="!copied" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    <svg x-show="copied" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </button>
                            @endempty
                        </div>
                    </div>
                </div>
            @endif
        </div>
        </div>
        <div class="modal-backdrop" @click="open = false"></div>
    </div>
</section>
