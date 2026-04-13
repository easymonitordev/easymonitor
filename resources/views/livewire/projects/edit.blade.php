<div class="w-full">
    <div class="mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('projects.show', $project) }}" wire:navigate class="btn btn-ghost btn-sm btn-circle">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold">{{ __('Edit Project') }}</h2>
                <p class="text-base-content/70 mt-0.5 text-sm">{{ $project->name }}</p>
            </div>
        </div>
    </div>

    <form wire:submit="save" class="max-w-2xl mx-auto">
        <div class="card bg-base-100 border border-base-300 mb-4">
            <div class="card-body gap-5">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-base-content/50">{{ __('Details') }}</h3>

                <div class="form-control">
                    <label class="label pb-1">
                        <span class="label-text font-medium">{{ __('Name') }}</span>
                    </label>
                    <input type="text" wire:model="name" required autofocus
                        class="input input-bordered w-full rounded-lg @error('name') input-error @enderror" />
                    @error('name')
                        <div class="label pb-0"><span class="label-text-alt text-error">{{ $message }}</span></div>
                    @enderror
                </div>

                <div class="form-control">
                    <label class="label pb-1">
                        <span class="label-text font-medium">{{ __('Description') }}</span>
                        <span class="label-text-alt text-base-content/50">{{ __('Optional') }}</span>
                    </label>
                    <textarea wire:model="description" rows="3"
                        class="textarea textarea-bordered w-full rounded-lg @error('description') textarea-error @enderror"></textarea>
                    @error('description')
                        <div class="label pb-0"><span class="label-text-alt text-error">{{ $message }}</span></div>
                    @enderror
                </div>

                <div class="form-control">
                    <label class="label pb-1">
                        <span class="label-text font-medium">{{ __('Color') }}</span>
                        <span class="label-text-alt text-base-content/50">{{ __('Optional') }}</span>
                    </label>
                    <div class="flex gap-2">
                        @foreach (['#3b82f6' => 'blue', '#10b981' => 'green', '#ef4444' => 'red', '#f59e0b' => 'amber', '#8b5cf6' => 'violet', '#ec4899' => 'pink'] as $hex => $label)
                            <button type="button" wire:click="$set('color', '{{ $hex }}')"
                                class="w-8 h-8 rounded-full border-2 {{ $color === $hex ? 'border-base-content ring-2 ring-offset-2 ring-base-content/20' : 'border-base-300' }}"
                                style="background: {{ $hex }}"
                                aria-label="{{ $label }}"></button>
                        @endforeach
                        <button type="button" wire:click="$set('color', null)"
                            class="w-8 h-8 rounded-full border-2 border-base-300 flex items-center justify-center {{ $color === null ? 'ring-2 ring-offset-2 ring-base-content/20' : '' }}"
                            aria-label="{{ __('No color') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-base-content/40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex gap-3 justify-end pt-2">
            <a href="{{ route('projects.show', $project) }}" wire:navigate class="btn btn-ghost">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                {{ __('Save Changes') }}
            </button>
        </div>
    </form>
</div>
