<div class="w-full">
    <div class="mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('status-pages.manage', ['statusPage' => $statusPage, 'tab' => 'incidents']) }}" wire:navigate class="btn btn-ghost btn-sm btn-circle">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold">{{ __('New Post') }}</h2>
                <p class="text-base-content/70 mt-0.5 text-sm">{{ $statusPage->name }}</p>
            </div>
        </div>
    </div>

    <form wire:submit="save" class="max-w-2xl mx-auto">
        <div class="card bg-base-100 border border-base-300 mb-4">
            <div class="card-body gap-5">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-base-content/50">{{ __('Type') }}</h3>

                <div class="form-control gap-2">
                    <label class="label cursor-pointer justify-start gap-3 py-2">
                        <input type="radio" wire:model.live="type" value="incident" class="radio radio-primary" />
                        <div class="flex-1">
                            <div class="font-medium">{{ __('Incident') }}</div>
                            <div class="text-xs text-base-content/50">{{ __('Something is broken right now') }}</div>
                        </div>
                    </label>
                    <label class="label cursor-pointer justify-start gap-3 py-2">
                        <input type="radio" wire:model.live="type" value="maintenance" class="radio radio-primary" />
                        <div class="flex-1">
                            <div class="font-medium">{{ __('Scheduled Maintenance') }}</div>
                            <div class="text-xs text-base-content/50">{{ __('Planned downtime or work') }}</div>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 border border-base-300 mb-4">
            <div class="card-body gap-5">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-base-content/50">{{ __('Details') }}</h3>

                <div class="form-control">
                    <label class="label pb-1"><span class="label-text font-medium">{{ __('Title') }}</span></label>
                    <input type="text" wire:model="title" required autofocus
                        class="input input-bordered w-full rounded-lg @error('title') input-error @enderror"
                        placeholder="{{ $type === 'maintenance' ? __('Database upgrade window') : __('API requests failing') }}" />
                    @error('title')<div class="label pb-0"><span class="label-text-alt text-error">{{ $message }}</span></div>@enderror
                </div>

                <div class="form-control">
                    <label class="label pb-1">
                        <span class="label-text font-medium">{{ __('Description') }}</span>
                        <span class="label-text-alt text-base-content/50">{{ __('Markdown supported') }}</span>
                    </label>
                    <textarea wire:model="body" rows="5"
                        class="textarea textarea-bordered w-full rounded-lg @error('body') textarea-error @enderror"
                        placeholder="{{ __('What is happening, when, what is the impact, what is being done...') }}"></textarea>
                </div>

                <div class="form-control">
                    <label class="label pb-1"><span class="label-text font-medium">{{ __('Status') }}</span></label>
                    <select wire:model="status" class="select select-bordered w-full rounded-lg">
                        @if ($type === 'incident')
                            <option value="investigating">{{ __('Investigating') }}</option>
                            <option value="identified">{{ __('Identified') }}</option>
                            <option value="monitoring">{{ __('Monitoring') }}</option>
                            <option value="resolved">{{ __('Resolved') }}</option>
                        @else
                            <option value="scheduled">{{ __('Scheduled') }}</option>
                            <option value="in_progress">{{ __('In Progress') }}</option>
                            <option value="completed">{{ __('Completed') }}</option>
                        @endif
                    </select>
                </div>

                @if ($type === 'incident')
                    <div class="form-control">
                        <label class="label pb-1"><span class="label-text font-medium">{{ __('Severity') }}</span></label>
                        <select wire:model="severity" class="select select-bordered w-full rounded-lg">
                            <option value="minor">{{ __('Minor') }}</option>
                            <option value="major">{{ __('Major') }}</option>
                            <option value="critical">{{ __('Critical') }}</option>
                        </select>
                    </div>
                @endif

                @if ($type === 'maintenance')
                    <div class="grid grid-cols-2 gap-3">
                        <div class="form-control">
                            <label class="label pb-1"><span class="label-text font-medium">{{ __('Scheduled Start') }}</span></label>
                            <input type="datetime-local" wire:model="scheduledFor" class="input input-bordered w-full rounded-lg" />
                        </div>
                        <div class="form-control">
                            <label class="label pb-1"><span class="label-text font-medium">{{ __('Scheduled End') }}</span></label>
                            <input type="datetime-local" wire:model="scheduledUntil" class="input input-bordered w-full rounded-lg" />
                        </div>
                    </div>
                @endif

                @if ($availableMonitors->isNotEmpty())
                    <div class="form-control">
                        <label class="label pb-1">
                            <span class="label-text font-medium">{{ __('Affected Monitors') }}</span>
                            <span class="label-text-alt text-base-content/50">{{ __('Optional') }}</span>
                        </label>
                        <div class="space-y-1 max-h-40 overflow-y-auto border border-base-300 rounded-lg p-2">
                            @foreach ($availableMonitors as $monitor)
                                <label class="flex items-center gap-2 p-1 hover:bg-base-200 rounded cursor-pointer">
                                    <input type="checkbox" wire:model="affectedMonitorIds" value="{{ $monitor->id }}" class="checkbox checkbox-sm checkbox-primary" />
                                    <span class="text-sm">{{ $monitor->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="flex gap-3 justify-end pt-2">
            <a href="{{ route('status-pages.manage', ['statusPage' => $statusPage, 'tab' => 'incidents']) }}" wire:navigate class="btn btn-ghost">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('Publish') }}</button>
        </div>
    </form>
</div>
