<div class="w-full" wire:poll.30s>
    <div class="mb-6">
        <h2 class="text-2xl font-bold">{{ __('Projects') }}</h2>
        <p class="text-base-content/70 mt-1">{{ __('Organize monitors into projects') }}</p>
    </div>

    <x-alert-success />
    <x-alert-error />

    <div class="flex flex-col sm:flex-row gap-4 justify-between mb-6">
        <div class="flex gap-2">
            <div role="tablist" class="tabs tabs-boxed">
                <a role="tab" class="tab @if($filter === 'my') tab-active @endif" wire:click="$set('filter', 'my')">
                    {{ __('My Projects') }}
                </a>
                @if ($teams->count() > 0)
                    <a role="tab" class="tab @if($filter === 'team') tab-active @endif" wire:click="$set('filter', 'team')">
                        {{ __('Team Projects') }}
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

        <div class="flex items-end">
            <a href="{{ route('projects.create') }}" wire:navigate class="btn btn-primary rounded-lg">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ __('New Project') }}
            </a>
        </div>
    </div>

    @if ($projects->isEmpty())
        <div class="card bg-base-100 border border-base-300">
            <div class="card-body text-center py-12">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-base-content/20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                </svg>
                <h3 class="text-lg font-semibold mt-4">{{ __('No projects yet') }}</h3>
                <p class="text-base-content/70 mt-2">{{ __('Projects help you group related monitors — like a site and its APIs.') }}</p>
                <div class="mt-6">
                    <a href="{{ route('projects.create') }}" wire:navigate class="btn btn-primary rounded-lg">
                        {{ __('Create Project') }}
                    </a>
                </div>
            </div>
        </div>
    @else
        <div class="grid gap-3">
            @foreach ($projects as $project)
                <div class="card bg-base-100 border border-base-300 hover:border-base-content/20 transition-colors" wire:key="project-{{ $project->id }}">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between gap-4">
                            <a href="{{ route('projects.show', $project) }}" wire:navigate class="flex-1 min-w-0 group flex items-center gap-3">
                                @if ($project->color)
                                    <div class="w-3 h-3 rounded-full shrink-0" style="background: {{ $project->color }}"></div>
                                @else
                                    <div class="w-3 h-3 rounded-full bg-base-content/30 shrink-0"></div>
                                @endif
                                <div class="min-w-0">
                                    <div class="font-semibold group-hover:underline truncate">{{ $project->name }}</div>
                                    @if ($project->description)
                                        <div class="text-sm text-base-content/50 truncate">{{ $project->description }}</div>
                                    @endif
                                </div>
                            </a>

                            <div class="flex items-center gap-6 text-sm text-base-content/60 shrink-0">
                                <div class="text-right">
                                    <div class="font-medium text-base-content">{{ $project->monitors_count }}</div>
                                    <div class="text-xs text-base-content/40">{{ trans_choice('monitor|monitors', $project->monitors_count) }}</div>
                                </div>
                            </div>

                            <div class="dropdown dropdown-end shrink-0">
                                <div tabindex="0" role="button" class="btn btn-ghost btn-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                                    </svg>
                                </div>
                                <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box z-[1] w-52 p-2 shadow border border-base-300">
                                    <li>
                                        <a href="{{ route('projects.show', $project) }}" wire:navigate>
                                            {{ __('View') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('projects.edit', $project) }}" wire:navigate>
                                            {{ __('Edit') }}
                                        </a>
                                    </li>
                                    <li>
                                        <button wire:click="delete({{ $project->id }})" wire:confirm="{{ __('Delete this project? Monitors will be unassigned but not deleted.') }}" class="text-error">
                                            {{ __('Delete') }}
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
