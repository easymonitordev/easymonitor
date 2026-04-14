<div class="w-full">
    <div class="mb-6">
        <h2 class="text-2xl font-bold">{{ __('Status Pages') }}</h2>
        <p class="text-base-content/70 mt-1">{{ __('Public-facing pages that show your service status') }}</p>
    </div>

    <x-alert-success />
    <x-alert-error />

    <div class="flex flex-col sm:flex-row gap-4 justify-between mb-6">
        <div class="flex gap-2">
            <div role="tablist" class="tabs tabs-boxed">
                <a role="tab" class="tab @if($filter === 'my') tab-active @endif" wire:click="$set('filter', 'my')">
                    {{ __('Mine') }}
                </a>
                @if ($teams->count() > 0)
                    <a role="tab" class="tab @if($filter === 'team') tab-active @endif" wire:click="$set('filter', 'team')">
                        {{ __('Team') }}
                    </a>
                @endif
            </div>

            @if ($filter === 'team' && $teams->count() > 0)
                <select wire:model.live="selectedTeamId" class="select select-bordered select-sm rounded-lg">
                    <option value="">{{ __('Select Team') }}</option>
                    @foreach ($teams as $team)
                        <option value="{{ $team->id }}">{{ $team->name }}</option>
                    @endforeach
                </select>
            @endif
        </div>

        <a href="{{ route('status-pages.create') }}" wire:navigate class="btn btn-primary rounded-lg">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            {{ __('New Status Page') }}
        </a>
    </div>

    @if ($statusPages->isEmpty())
        <div class="card bg-base-100 border border-base-300">
            <div class="card-body text-center py-12">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-base-content/20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <h3 class="text-lg font-semibold mt-4">{{ __('No status pages yet') }}</h3>
                <p class="text-base-content/70 mt-2">{{ __('Create a public-facing page to share your monitoring status with users.') }}</p>
                <div class="mt-6">
                    <a href="{{ route('status-pages.create') }}" wire:navigate class="btn btn-primary rounded-lg">
                        {{ __('Create Status Page') }}
                    </a>
                </div>
            </div>
        </div>
    @else
        <div class="grid gap-3">
            @foreach ($statusPages as $statusPage)
                <div class="card bg-base-100 border border-base-300 hover:border-base-content/20 transition-colors" wire:key="sp-{{ $statusPage->id }}">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between gap-4">
                            <a href="{{ route('status-pages.manage', $statusPage) }}" wire:navigate class="flex-1 min-w-0 group flex items-center gap-3">
                                @php $status = $statusPage->aggregateStatus(); @endphp
                                @if ($status === 'operational')
                                    <div class="w-3 h-3 rounded-full bg-success shrink-0"></div>
                                @elseif ($status === 'degraded')
                                    <div class="w-3 h-3 rounded-full bg-warning shrink-0"></div>
                                @elseif ($status === 'outage')
                                    <div class="w-3 h-3 rounded-full bg-error animate-pulse shrink-0"></div>
                                @else
                                    <div class="w-3 h-3 rounded-full bg-base-content/30 shrink-0"></div>
                                @endif

                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold group-hover:underline truncate">{{ $statusPage->name }}</span>
                                        @if ($statusPage->visibility === 'public')
                                            <span class="badge badge-success badge-sm">{{ __('Public') }}</span>
                                        @elseif ($statusPage->visibility === 'unlisted')
                                            <span class="badge badge-warning badge-sm">{{ __('Unlisted') }}</span>
                                        @else
                                            <span class="badge badge-ghost badge-sm">{{ __('Private') }}</span>
                                        @endif
                                    </div>
                                    <div class="text-sm text-base-content/50 truncate">/{{ $statusPage->slug }}</div>
                                </div>
                            </a>

                            <div class="flex items-center gap-2 shrink-0">
                                @if ($statusPage->visibility === 'public' || $statusPage->isDomainVerified())
                                    <a href="{{ $statusPage->publicUrl() }}" target="_blank" class="btn btn-ghost btn-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                        {{ __('View') }}
                                    </a>
                                @endif

                                <div class="dropdown dropdown-end">
                                    <div tabindex="0" role="button" class="btn btn-ghost btn-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                                        </svg>
                                    </div>
                                    <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box z-[1] w-52 p-2 shadow border border-base-300">
                                        <li>
                                            <a href="{{ route('status-pages.manage', $statusPage) }}" wire:navigate>
                                                {{ __('Manage') }}
                                            </a>
                                        </li>
                                        <li>
                                            <button wire:click="delete({{ $statusPage->id }})" wire:confirm="{{ __('Delete this status page?') }}" class="text-error">
                                                {{ __('Delete') }}
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
