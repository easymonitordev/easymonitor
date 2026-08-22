<div class="w-full">
    <div class="mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('monitors.index') }}" wire:navigate class="btn btn-ghost btn-sm btn-circle">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold">{{ __('Add Monitor') }}</h2>
                <p class="text-base-content/70 mt-0.5 text-sm">{{ __('Add a new website or service to monitor') }}</p>
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
                        <span class="label-text font-medium">{{ __('Check Type') }}</span>
                    </label>
                    <div class="grid sm:grid-cols-3 gap-2">
                        @foreach (\App\Enums\CheckType::cases() as $type)
                            <label wire:key="check-type-{{ $type->value }}" class="cursor-pointer border border-base-300 rounded-lg p-3 flex items-start gap-3 hover:border-primary {{ $checkType === $type->value ? 'border-primary bg-primary/5' : '' }}">
                                <input type="radio" wire:model.live="checkType" value="{{ $type->value }}" class="radio radio-primary radio-sm mt-0.5" />
                                <div>
                                    <div class="font-medium text-sm">{{ __($type->label()) }}</div>
                                    <div class="text-xs text-base-content/60 mt-0.5">{{ __($type->description()) }}</div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

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
                    <div class="label pb-0">
                        <span class="label-text-alt text-base-content/50">{{ __('A friendly name to identify this monitor') }}</span>
                    </div>
                    @error('name')
                        <div class="label pb-0">
                            <span class="label-text-alt text-error">{{ $message }}</span>
                        </div>
                    @enderror
                </div>

                @if ($checkType === 'tcp')
                    <div class="grid grid-cols-3 gap-3">
                        <div class="form-control col-span-2">
                            <label class="label pb-1">
                                <span class="label-text font-medium">{{ __('Host') }}</span>
                            </label>
                            <input
                                type="text"
                                wire:model="tcpHost"
                                required
                                class="input input-bordered w-full rounded-lg @error('tcpHost') input-error @enderror"
                                placeholder="db.example.com"
                            />
                            @error('tcpHost')
                                <div class="label pb-0">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </div>
                            @enderror
                        </div>
                        <div class="form-control">
                            <label class="label pb-1">
                                <span class="label-text font-medium">{{ __('Port') }}</span>
                            </label>
                            <input
                                type="number"
                                wire:model="tcpPort"
                                required
                                min="1"
                                max="65535"
                                class="input input-bordered w-full rounded-lg @error('tcpPort') input-error @enderror"
                                placeholder="5432"
                            />
                            @error('tcpPort')
                                <div class="label pb-0">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </div>
                            @enderror
                        </div>
                        <div class="label pb-0 col-span-3 pt-0">
                            <span class="label-text-alt text-base-content/50">{{ __('The check succeeds when a TCP connection to host:port is established.') }}</span>
                        </div>
                    </div>
                @else
                    <div class="form-control">
                        <label class="label pb-1">
                            <span class="label-text font-medium">{{ $checkType === 'icmp' ? __('Host') : __('URL') }}</span>
                        </label>
                        <input
                            type="text"
                            wire:model="url"
                            required
                            class="input input-bordered w-full rounded-lg @error('url') input-error @enderror"
                            placeholder="{{ $checkType === 'icmp' ? '1.1.1.1' : 'https://example.com' }}"
                        />
                        <div class="label pb-0">
                            <span class="label-text-alt text-base-content/50">
                                {{ $checkType === 'icmp' ? __('Hostname or IP address — no scheme or path.') : __('Full URL including https://') }}
                            </span>
                        </div>
                        @error('url')
                            <div class="label pb-0">
                                <span class="label-text-alt text-error">{{ $message }}</span>
                            </div>
                        @enderror
                    </div>
                @endif

                @if ($checkType === 'http')
                    <div class="form-control">
                        <label class="label pb-1">
                            <span class="label-text font-medium">{{ __('Response Assertion') }}</span>
                            <span class="label-text-alt text-base-content/50">{{ __('Optional') }}</span>
                        </label>
                        <select wire:model.live="assertionType" class="select select-bordered w-full rounded-lg @error('assertionType') select-error @enderror">
                            @foreach (\App\Enums\AssertionType::cases() as $assertion)
                                <option value="{{ $assertion->value }}">{{ __($assertion->label()) }}</option>
                            @endforeach
                        </select>
                        @if ($assertionType !== 'none')
                            <input
                                type="text"
                                wire:model="assertionValue"
                                class="input input-bordered w-full rounded-lg mt-2 @error('assertionValue') input-error @enderror"
                                placeholder="{{ __('Keyword or phrase') }}"
                            />
                            @error('assertionValue')
                                <div class="label pb-0">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </div>
                            @enderror
                        @endif
                        <div class="label pb-0">
                            <span class="label-text-alt text-base-content/50">{{ __('Case-sensitive match against the first 1 MB of the response body. Probes without assertion support evaluate only the status code until upgraded.') }}</span>
                        </div>
                    </div>
                @endif
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
                    <label class="label cursor-pointer justify-start gap-4 py-3">
                        <input type="checkbox" wire:model="isActive" class="toggle toggle-success" />
                        <div>
                            <span class="label-text font-medium">{{ __('Start monitoring immediately') }}</span>
                            <p class="text-xs text-base-content/50 mt-0.5">{{ __('When enabled, the first check runs right after saving') }}</p>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        <!-- Notifications -->
        <div class="card bg-base-100 border border-base-300 mb-4">
            <div class="card-body gap-4">
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-base-content/50">{{ __('Notifications') }}</h3>
                    <p class="text-xs text-base-content/60 mt-1">
                        {{ __('Choose which channels to alert on down/recovery.') }}
                        <a href="{{ route('settings.notifications') }}" wire:navigate class="link link-primary">{{ __('Manage channels') }}</a>
                    </p>
                </div>

                @forelse ($channels as $channel)
                    <label wire:key="channel-opt-{{ $channel->id }}" class="label cursor-pointer justify-start gap-3 py-1">
                        <input type="checkbox"
                               value="{{ $channel->id }}"
                               wire:model="notificationChannelIds"
                               class="checkbox checkbox-primary checkbox-sm" />
                        <span class="label-text">
                            {{ $channel->type->label() }}@if ($channel->label)<span class="text-base-content/60"> — {{ $channel->label }}</span>@endif
                        </span>
                        @if ($channel->is_default)
                            <span class="badge badge-ghost badge-sm">{{ __('Default') }}</span>
                        @endif
                        @if (! $channel->isConfigured())
                            <span class="badge badge-warning badge-sm">{{ __('Not configured') }}</span>
                        @endif
                    </label>
                @empty
                    <p class="text-xs text-base-content/60">{{ __('No active channels. Configure one in your notification settings.') }}</p>
                @endforelse
            </div>
        </div>

        <!-- Organization -->
        <div class="card bg-base-100 border border-base-300 mb-4">
            <div class="card-body gap-5">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-base-content/50">{{ __('Organization') }}</h3>

                <div class="form-control">
                    <label class="label pb-1">
                        <span class="label-text font-medium">{{ __('Project') }}</span>
                        <span class="label-text-alt text-base-content/50">{{ __('Optional') }}</span>
                    </label>
                    <select wire:model.live="projectId" class="select select-bordered w-full rounded-lg @error('projectId') select-error @enderror">
                        <option value="">{{ __('No project — standalone monitor') }}</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}{{ $project->team_id ? ' (' . ($project->team?->name ?? 'team') . ')' : '' }}</option>
                        @endforeach
                    </select>
                    <div class="label pb-0">
                        <span class="label-text-alt text-base-content/50">
                            @if ($projectId)
                                {{ __('Ownership inherits from the project (team or owner).') }}
                            @else
                                <a href="{{ route('projects.create') }}" wire:navigate class="link link-primary">{{ __('Create a new project') }}</a>
                            @endif
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Team Assignment (only when no project) -->
        @if ($teams->count() > 0 && !$projectId)
            <div class="card bg-base-100 border border-base-300 mb-4">
                <div class="card-body gap-5">
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-base-content/50">{{ __('Ownership') }}</h3>

                    <div class="form-control">
                        <label class="label pb-1">
                            <span class="label-text font-medium">{{ __('Team') }}</span>
                            <span class="label-text-alt text-base-content/50">{{ __('Optional') }}</span>
                        </label>
                        <select wire:model="teamId" class="select select-bordered w-full rounded-lg @error('teamId') select-error @enderror">
                            <option value="">{{ __('Personal — only visible to you') }}</option>
                            @foreach ($teams as $team)
                                <option value="{{ $team->id }}">{{ $team->name }}</option>
                            @endforeach
                        </select>
                        @error('teamId')
                            <div class="label pb-0">
                                <span class="label-text-alt text-error">{{ $message }}</span>
                            </div>
                        @enderror
                    </div>
                </div>
            </div>
        @endif

        <!-- Actions -->
        <div class="flex gap-3 justify-end pt-2">
            <a href="{{ route('monitors.index') }}" wire:navigate class="btn btn-ghost">
                {{ __('Cancel') }}
            </a>
            <button type="submit" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ __('Add Monitor') }}
            </button>
        </div>
    </form>
</div>
