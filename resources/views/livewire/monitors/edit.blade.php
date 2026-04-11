<div class="w-full">
    <div class="mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('monitors.show', $monitor) }}" wire:navigate class="btn btn-ghost btn-sm btn-circle">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold">{{ __('Edit Monitor') }}</h2>
                <p class="text-base-content/70 mt-0.5 text-sm">{{ $monitor->url }}</p>
            </div>
        </div>
    </div>

    <form wire:submit="save" class="max-w-2xl mx-auto">
        <!-- Name & URL -->
        <div class="card bg-base-100 border border-base-300 mb-4">
            <div class="card-body gap-5">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-base-content/50">{{ __('What to monitor') }}</h3>

                <div class="form-control">
                    <label class="label pb-1">
                        <span class="label-text font-medium">{{ __('Display Name') }}</span>
                    </label>
                    <input
                        type="text"
                        wire:model="name"
                        required
                        autofocus
                        class="input input-bordered w-full rounded-lg @error('name') input-error @enderror"
                        placeholder="{{ __('My Website') }}"
                    />
                    @error('name')
                        <div class="label pb-0">
                            <span class="label-text-alt text-error">{{ $message }}</span>
                        </div>
                    @enderror
                </div>

                <div class="form-control">
                    <label class="label pb-1">
                        <span class="label-text font-medium">{{ __('URL') }}</span>
                    </label>
                    <input
                        type="url"
                        wire:model="url"
                        required
                        class="input input-bordered w-full rounded-lg @error('url') input-error @enderror"
                        placeholder="https://example.com"
                    />
                    @error('url')
                        <div class="label pb-0">
                            <span class="label-text-alt text-error">{{ $message }}</span>
                        </div>
                    @enderror
                </div>
            </div>
        </div>

        <!-- Check Settings -->
        <div class="card bg-base-100 border border-base-300 mb-4">
            <div class="card-body gap-5">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-base-content/50">{{ __('Check settings') }}</h3>

                <div class="form-control">
                    <label class="label pb-1">
                        <span class="label-text font-medium">{{ __('Check Interval') }}</span>
                    </label>
                    <div class="flex items-center gap-4">
                        <input
                            type="range"
                            wire:model.live="checkInterval"
                            min="30"
                            max="3600"
                            step="30"
                            class="range range-sm range-primary flex-1"
                        />
                        <div class="badge badge-lg font-mono min-w-20 justify-center rounded-lg">
                            @if ($checkInterval >= 60)
                                {{ floor($checkInterval / 60) }}m{{ $checkInterval % 60 > 0 ? ' ' . ($checkInterval % 60) . 's' : '' }}
                            @else
                                {{ $checkInterval }}s
                            @endif
                        </div>
                    </div>
                    <div class="label pb-0">
                        <span class="label-text-alt text-base-content/50">{{ __('How often to check this monitor (30s to 1 hour)') }}</span>
                    </div>
                    @error('checkInterval')
                        <div class="label pb-0">
                            <span class="label-text-alt text-error">{{ $message }}</span>
                        </div>
                    @enderror
                </div>

                <div class="form-control">
                    <label class="label pb-1">
                        <span class="label-text font-medium">{{ __('Failure Threshold') }}</span>
                    </label>
                    <div class="flex items-center gap-4">
                        <input
                            type="range"
                            wire:model.live="failureThreshold"
                            min="1"
                            max="10"
                            step="1"
                            class="range range-sm range-primary flex-1"
                        />
                        <div class="badge badge-lg font-mono min-w-20 justify-center rounded-lg">
                            {{ $failureThreshold }} {{ trans_choice('fail|fails', $failureThreshold) }}
                        </div>
                    </div>
                    <div class="label pb-0">
                        <span class="label-text-alt text-base-content/50">{{ __('Number of consecutive failures before marking as down and alerting') }}</span>
                    </div>
                    @error('failureThreshold')
                        <div class="label pb-0">
                            <span class="label-text-alt text-error">{{ $message }}</span>
                        </div>
                    @enderror
                </div>

                <div class="form-control">
                    <label class="label cursor-pointer justify-start gap-4 py-3">
                        <input type="checkbox" wire:model="isActive" class="toggle toggle-success" />
                        <div>
                            <span class="label-text font-medium">{{ __('Monitoring active') }}</span>
                            <p class="text-xs text-base-content/50 mt-0.5">{{ __('Pause or resume checks for this monitor') }}</p>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex gap-3 justify-end pt-2">
            <a href="{{ route('monitors.show', $monitor) }}" wire:navigate class="btn btn-ghost">
                {{ __('Cancel') }}
            </a>
            <button type="submit" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                {{ __('Save Changes') }}
            </button>
        </div>
    </form>
</div>
