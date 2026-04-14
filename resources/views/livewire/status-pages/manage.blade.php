<div class="w-full">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-6 gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('status-pages.index') }}" wire:navigate class="btn btn-ghost btn-sm btn-circle">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-2xl font-bold">{{ $statusPage->name }}</h2>
                    @if ($statusPage->visibility === 'public')
                        <span class="badge badge-success badge-sm">{{ __('Public') }}</span>
                    @elseif ($statusPage->visibility === 'unlisted')
                        <span class="badge badge-warning badge-sm">{{ __('Unlisted') }}</span>
                    @else
                        <span class="badge badge-ghost badge-sm">{{ __('Private') }}</span>
                    @endif
                </div>
                <p class="text-sm text-base-content/50 mt-0.5">/status/{{ $statusPage->slug }}</p>
            </div>
        </div>

        <div class="flex gap-2">
            @if ($statusPage->visibility === 'public')
                <a href="{{ $statusPage->publicUrl() }}" target="_blank" class="btn btn-ghost btn-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                    </svg>
                    {{ __('View Live') }}
                </a>
            @elseif ($statusPage->visibility === 'unlisted')
                <button onclick="navigator.clipboard.writeText('{{ $statusPage->publicUrl() }}'); this.innerText='Copied!'" class="btn btn-ghost btn-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                    </svg>
                    {{ __('Copy Secret Link') }}
                </button>
            @endif
        </div>
    </div>

    <x-alert-success />
    <x-alert-error />

    <!-- Tabs -->
    <div role="tablist" class="tabs tabs-bordered mb-6">
        <a role="tab" class="tab @if($tab === 'overview') tab-active @endif" wire:click="$set('tab', 'overview')">{{ __('Overview') }}</a>
        <a role="tab" class="tab @if($tab === 'items') tab-active @endif" wire:click="$set('tab', 'items')">{{ __('Items') }} <span class="badge badge-sm ml-2">{{ $items->count() }}</span></a>
        <a role="tab" class="tab @if($tab === 'incidents') tab-active @endif" wire:click="$set('tab', 'incidents')">{{ __('Incidents') }} <span class="badge badge-sm ml-2">{{ $incidents->count() }}</span></a>
        <a role="tab" class="tab @if($tab === 'branding') tab-active @endif" wire:click="$set('tab', 'branding')">{{ __('Branding') }}</a>
        <a role="tab" class="tab @if($tab === 'settings') tab-active @endif" wire:click="$set('tab', 'settings')">{{ __('Settings') }}</a>
    </div>

    {{-- ============ OVERVIEW TAB ============ --}}
    @if ($tab === 'overview')
        @php
            $sections = $statusPage->resolveSections();
            $allMonitors = $sections->flatMap(fn ($s) => $s['monitors']);
            $totalMon = $allMonitors->count();
            $upMon = $allMonitors->where('status', 'up')->count();
            $downMon = $allMonitors->where('status', 'down')->count();
            $aggregate = $statusPage->aggregateStatus();
        @endphp

        <div class="grid gap-4 md:grid-cols-4 mb-6">
            <div class="stats bg-base-100 border border-base-300 rounded-xl">
                <div class="stat">
                    <div class="stat-title">{{ __('Status') }}</div>
                    <div class="stat-value text-2xl @if($aggregate==='operational') text-success @elseif($aggregate==='degraded') text-warning @elseif($aggregate==='outage') text-error @endif">
                        {{ ucfirst($aggregate) }}
                    </div>
                </div>
            </div>
            <div class="stats bg-base-100 border border-base-300 rounded-xl">
                <div class="stat">
                    <div class="stat-title">{{ __('Monitors Shown') }}</div>
                    <div class="stat-value text-2xl">{{ $upMon }}<span class="text-base-content/30 text-lg">/{{ $totalMon }}</span></div>
                    <div class="stat-desc">{{ __('up') }}</div>
                </div>
            </div>
            <div class="stats bg-base-100 border border-base-300 rounded-xl">
                <div class="stat">
                    <div class="stat-title">{{ __('Active Incidents') }}</div>
                    <div class="stat-value text-2xl @if($incidents->where('resolved_at', null)->count() > 0) text-error @endif">
                        {{ $incidents->where('resolved_at', null)->where('type', 'incident')->count() }}
                    </div>
                </div>
            </div>
            <div class="stats bg-base-100 border border-base-300 rounded-xl">
                <div class="stat">
                    <div class="stat-title">{{ __('Items') }}</div>
                    <div class="stat-value text-2xl">{{ $items->count() }}</div>
                    <div class="stat-desc">{{ __('projects + monitors') }}</div>
                </div>
            </div>
        </div>

        @if ($items->isEmpty())
            <div class="card bg-base-100 border border-base-300">
                <div class="card-body text-center py-12">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-base-content/20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    <h3 class="text-lg font-semibold mt-3">{{ __('No items yet') }}</h3>
                    <p class="text-base-content/70 mt-2">{{ __('Add projects or individual monitors to show on this page.') }}</p>
                    <button wire:click="$set('tab', 'items')" class="btn btn-primary btn-sm mt-4 inline-flex">
                        {{ __('Add Items') }}
                    </button>
                </div>
            </div>
        @else
            <div class="card bg-base-100 border border-base-300">
                <div class="card-body">
                    <h3 class="card-title text-lg mb-4">{{ __('What visitors will see') }}</h3>
                    @foreach ($sections as $section)
                        <div class="mb-4">
                            <h4 class="font-medium mb-2 flex items-center gap-2">
                                @if ($section['project']?->color)
                                    <div class="w-2 h-2 rounded-full" style="background: {{ $section['project']->color }}"></div>
                                @endif
                                {{ $section['label'] }}
                            </h4>
                            <div class="space-y-1">
                                @foreach ($section['monitors'] as $monitor)
                                    <div class="flex items-center gap-3 px-3 py-2 rounded-lg bg-base-200/50" wire:key="overview-mon-{{ $monitor->id }}">
                                        @if ($monitor->status === 'up')
                                            <div class="w-2 h-2 rounded-full bg-success shrink-0"></div>
                                        @elseif ($monitor->status === 'down')
                                            <div class="w-2 h-2 rounded-full bg-error animate-pulse shrink-0"></div>
                                        @else
                                            <div class="w-2 h-2 rounded-full bg-base-content/30 shrink-0"></div>
                                        @endif
                                        <span class="text-sm font-medium truncate flex-1">{{ $monitor->name }}</span>
                                        <span class="text-xs text-base-content/50 capitalize">{{ $monitor->status }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif

    {{-- ============ ITEMS TAB ============ --}}
    @if ($tab === 'items')
        <div class="card bg-base-100 border border-base-300 mb-4">
            <div class="card-body">
                <h3 class="card-title text-lg mb-4">{{ __('Add Item') }}</h3>

                <div class="form-control mb-3">
                    <div role="tablist" class="tabs tabs-boxed w-fit">
                        <a role="tab" class="tab @if($addItemType==='project') tab-active @endif" wire:click="$set('addItemType', 'project')">{{ __('Project') }}</a>
                        <a role="tab" class="tab @if($addItemType==='monitor') tab-active @endif" wire:click="$set('addItemType', 'monitor')">{{ __('Single Monitor') }}</a>
                    </div>
                </div>

                <div class="flex gap-2">
                    @if ($addItemType === 'project')
                        <select wire:model="addProjectId" class="select select-bordered flex-1 rounded-lg">
                            <option value="">{{ __('Select a project') }}</option>
                            @foreach ($availableProjects as $project)
                                <option value="{{ $project->id }}">{{ $project->name }}</option>
                            @endforeach
                        </select>
                    @else
                        <select wire:model="addMonitorId" class="select select-bordered flex-1 rounded-lg">
                            <option value="">{{ __('Select a monitor') }}</option>
                            @foreach ($availableMonitors as $monitor)
                                <option value="{{ $monitor->id }}">{{ $monitor->name }}</option>
                            @endforeach
                        </select>
                    @endif
                    <button wire:click="addItem" class="btn btn-primary">{{ __('Add') }}</button>
                </div>
                <div class="text-xs text-base-content/50 mt-2">
                    @if ($addItemType === 'project')
                        {{ __('All active monitors in the project will appear. You can hide individual monitors below.') }}
                    @else
                        {{ __('A single monitor that will appear on this page.') }}
                    @endif
                </div>
            </div>
        </div>

        <div class="card bg-base-100 border border-base-300">
            <div class="card-body">
                <h3 class="card-title text-lg mb-4">{{ __('Items on this page') }} ({{ $items->count() }})</h3>

                @if ($items->isEmpty())
                    <p class="text-sm text-base-content/50 text-center py-8">{{ __('No items added yet.') }}</p>
                @else
                    <div class="space-y-3">
                        @foreach ($items as $item)
                            <div class="border border-base-300 rounded-lg p-3" wire:key="item-{{ $item->id }}">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        @if ($item->type === 'project')
                                            <span class="badge badge-primary badge-sm">{{ __('Project') }}</span>
                                            <span class="font-medium">{{ $item->project?->name ?? __('(deleted)') }}</span>
                                        @else
                                            <span class="badge badge-ghost badge-sm">{{ __('Monitor') }}</span>
                                            <span class="font-medium">{{ $item->monitor?->name ?? __('(deleted)') }}</span>
                                        @endif
                                    </div>
                                    <button wire:click="removeItem({{ $item->id }})" wire:confirm="{{ __('Remove this item from the page?') }}" class="btn btn-ghost btn-sm text-error">
                                        {{ __('Remove') }}
                                    </button>
                                </div>

                                @if ($item->type === 'project' && $item->project)
                                    <div class="mt-2 pl-3 border-l-2 border-base-300">
                                        <div class="text-xs text-base-content/50 mb-2">{{ __('Toggle visibility of monitors in this project:') }}</div>
                                        <div class="space-y-1">
                                            @foreach ($item->project->monitors as $mon)
                                                @php $hidden = in_array($mon->id, $excludedMonitorIds); @endphp
                                                <label class="flex items-center gap-2 cursor-pointer hover:bg-base-200 rounded p-1">
                                                    <input type="checkbox" {{ $hidden ? '' : 'checked' }}
                                                           wire:click="toggleMonitorVisibility({{ $mon->id }})"
                                                           class="checkbox checkbox-sm checkbox-success" />
                                                    <span class="text-sm {{ $hidden ? 'line-through text-base-content/40' : '' }}">{{ $mon->name }}</span>
                                                    @if ($hidden)
                                                        <span class="badge badge-ghost badge-xs">{{ __('Hidden') }}</span>
                                                    @endif
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- ============ INCIDENTS TAB ============ --}}
    @if ($tab === 'incidents')
        <div class="flex justify-end mb-4">
            <a href="{{ route('status-pages.incidents.create', $statusPage) }}" wire:navigate class="btn btn-primary btn-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ __('New Post') }}
            </a>
        </div>

        @if ($incidents->isEmpty())
            <div class="card bg-base-100 border border-base-300">
                <div class="card-body text-center py-12">
                    <p class="text-base-content/50">{{ __('No incidents or maintenance posts yet.') }}</p>
                </div>
            </div>
        @else
            <div class="card bg-base-100 border border-base-300">
                <div class="card-body">
                    <div class="space-y-3">
                        @foreach ($incidents as $incident)
                            <a href="{{ route('status-pages.incidents.edit', [$statusPage, $incident]) }}" wire:navigate
                               class="block border border-base-300 rounded-lg p-3 hover:border-base-content/20 transition" wire:key="inc-{{ $incident->id }}">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        @if ($incident->type === 'maintenance')
                                            <span class="badge badge-info badge-sm">{{ __('Maintenance') }}</span>
                                        @else
                                            <span class="badge {{ $incident->resolved_at ? 'badge-ghost' : 'badge-error' }} badge-sm">{{ __('Incident') }}</span>
                                        @endif
                                        <span class="font-medium">{{ $incident->title }}</span>
                                        <span class="badge badge-ghost badge-sm capitalize">{{ str_replace('_', ' ', $incident->status) }}</span>
                                    </div>
                                    <div class="text-xs text-base-content/50">
                                        {{ $incident->created_at->diffForHumans() }}
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    @endif

    {{-- ============ BRANDING TAB ============ --}}
    @if ($tab === 'branding')
        <form wire:submit="saveBranding" class="max-w-2xl">
            <!-- Logo -->
            <div class="card bg-base-100 border border-base-300 mb-4">
                <div class="card-body gap-4">
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-base-content/50">{{ __('Logo') }}</h3>

                    @if ($statusPage->logo_path)
                        <div class="flex items-center gap-4">
                            <img src="{{ $statusPage->logoUrl() }}" alt="Logo" class="h-16 w-auto rounded border border-base-300 p-2 bg-base-200" />
                            <button type="button" wire:click="removeLogo" wire:confirm="{{ __('Remove logo?') }}" class="btn btn-ghost btn-sm text-error">
                                {{ __('Remove') }}
                            </button>
                        </div>
                    @endif

                    <div class="form-control">
                        <label class="label pb-1">
                            <span class="label-text font-medium">{{ $statusPage->logo_path ? __('Replace logo') : __('Upload logo') }}</span>
                        </label>
                        <input type="file" wire:model="logo" accept="image/png,image/jpeg,image/svg+xml,image/webp" class="file-input file-input-bordered w-full rounded-lg" />
                        <div class="label pb-0">
                            <span class="label-text-alt text-base-content/50">{{ __('PNG, JPG, SVG or WebP. Max 2MB.') }}</span>
                        </div>
                        @error('logo')<div class="label pb-0"><span class="label-text-alt text-error">{{ $message }}</span></div>@enderror

                        @if ($logo)
                            <div class="mt-3 p-3 border border-base-300 rounded">
                                <div class="text-xs text-base-content/50 mb-2">{{ __('Preview:') }}</div>
                                <img src="{{ $logo->temporaryUrl() }}" class="h-16 w-auto" />
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Theme -->
            <div class="card bg-base-100 border border-base-300 mb-4">
                <div class="card-body gap-4">
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-base-content/50">{{ __('Theme') }}</h3>
                    <p class="text-xs text-base-content/50">{{ __('Choose a DaisyUI theme that matches your brand. The theme applies to the public status page only.') }}</p>

                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                        @foreach ($availableThemes as $themeId => $themeLabel)
                            <label class="cursor-pointer" data-theme="{{ $themeId }}">
                                <input type="radio" wire:model="theme" value="{{ $themeId }}" class="hidden peer" />
                                <div class="rounded-lg border-2 border-base-300 peer-checked:border-primary p-3 bg-base-100 transition" data-theme="{{ $themeId }}">
                                    <div class="flex gap-1 mb-2">
                                        <div class="w-4 h-4 rounded-full bg-primary"></div>
                                        <div class="w-4 h-4 rounded-full bg-secondary"></div>
                                        <div class="w-4 h-4 rounded-full bg-accent"></div>
                                        <div class="w-4 h-4 rounded-full bg-neutral"></div>
                                    </div>
                                    <div class="text-xs font-medium text-base-content">{{ $themeLabel }}</div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    @error('theme')<div class="text-xs text-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <!-- Custom CSS -->
            <div class="card bg-base-100 border border-base-300 mb-4">
                <div class="card-body gap-4">
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-base-content/50">{{ __('Custom Theme / CSS') }}</h3>
                    <p class="text-xs text-base-content/50">
                        {{ __('Paste the full output from the') }}
                        <a href="https://daisyui.com/theme-generator/" target="_blank" class="link link-primary">{{ __('DaisyUI theme generator') }}</a>
                        {{ __('— including the @plugin "daisyui/theme" wrapper. The page will use whatever theme name you set in the block.') }}
                    </p>
                    <p class="text-xs text-base-content/50">
                        {{ __('You can also add plain CSS rules to tweak specific elements.') }}
                    </p>

                    <div class="form-control">
                        <textarea wire:model="customCss" rows="12"
                            class="textarea textarea-bordered w-full font-mono text-xs rounded-lg @error('customCss') textarea-error @enderror"
                            placeholder='@plugin "daisyui/theme" {&#10;  name: "mytheme";&#10;  --color-primary: oklch(70% 0.20 250);&#10;  --color-base-100: #ffffff;&#10;}'></textarea>
                        <div class="label pb-0">
                            <span class="label-text-alt text-base-content/50">{{ __('Up to 50,000 characters.') }}</span>
                        </div>
                        @error('customCss')<div class="label pb-0"><span class="label-text-alt text-error">{{ $message }}</span></div>@enderror
                    </div>
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="btn btn-primary">{{ __('Save Branding') }}</button>
            </div>
        </form>
    @endif

    {{-- ============ SETTINGS TAB ============ --}}
    @if ($tab === 'settings')
        <form wire:submit="saveSettings" class="max-w-2xl">
            <div class="card bg-base-100 border border-base-300 mb-4">
                <div class="card-body gap-5">
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-base-content/50">{{ __('Page details') }}</h3>

                    <div class="form-control">
                        <label class="label pb-1"><span class="label-text font-medium">{{ __('Name') }}</span></label>
                        <input type="text" wire:model="name" class="input input-bordered w-full rounded-lg @error('name') input-error @enderror" />
                        @error('name')<div class="label pb-0"><span class="label-text-alt text-error">{{ $message }}</span></div>@enderror
                    </div>

                    <div class="form-control">
                        <label class="label pb-1"><span class="label-text font-medium">{{ __('URL Slug') }}</span></label>
                        <div class="join w-full">
                            <span class="join-item btn btn-disabled no-animation bg-base-200 border-base-300 text-base-content/60 px-3">/status/</span>
                            <input type="text" wire:model="slug" class="input input-bordered join-item w-full @error('slug') input-error @enderror" />
                        </div>
                        @error('slug')<div class="label pb-0"><span class="label-text-alt text-error">{{ $message }}</span></div>@enderror
                    </div>

                    <div class="form-control">
                        <label class="label pb-1">
                            <span class="label-text font-medium">{{ __('Description') }}</span>
                            <span class="label-text-alt text-base-content/50">{{ __('Optional') }}</span>
                        </label>
                        <textarea wire:model="description" rows="2" class="textarea textarea-bordered w-full rounded-lg"></textarea>
                    </div>

                    <div class="form-control">
                        <label class="label pb-1">
                            <span class="label-text font-medium">{{ __('Footer Text') }}</span>
                            <span class="label-text-alt text-base-content/50">{{ __('Optional') }}</span>
                        </label>
                        <textarea wire:model="footerText" rows="2" class="textarea textarea-bordered w-full rounded-lg" placeholder="{{ __('© 2026 Your Company') }}"></textarea>
                    </div>
                </div>
            </div>

            <div class="card bg-base-100 border border-base-300 mb-4">
                <div class="card-body gap-5">
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-base-content/50">{{ __('Visibility') }}</h3>

                    <div class="form-control gap-2">
                        <label class="label cursor-pointer justify-start gap-3 py-2">
                            <input type="radio" wire:model="visibility" value="public" class="radio radio-primary" />
                            <div class="flex-1">
                                <div class="font-medium">{{ __('Public') }}</div>
                                <div class="text-xs text-base-content/50">{{ __('Anyone with the URL can view') }}</div>
                            </div>
                        </label>
                        <label class="label cursor-pointer justify-start gap-3 py-2">
                            <input type="radio" wire:model="visibility" value="unlisted" class="radio radio-primary" />
                            <div class="flex-1">
                                <div class="font-medium">{{ __('Unlisted') }}</div>
                                <div class="text-xs text-base-content/50">{{ __('Requires a secret link to access') }}</div>
                            </div>
                        </label>
                        <label class="label cursor-pointer justify-start gap-3 py-2">
                            <input type="radio" wire:model="visibility" value="private" class="radio radio-primary" />
                            <div class="flex-1">
                                <div class="font-medium">{{ __('Private') }}</div>
                                <div class="text-xs text-base-content/50">{{ __('Only you and your team members can view') }}</div>
                            </div>
                        </label>
                    </div>

                    @if ($statusPage->visibility === 'unlisted' && $statusPage->access_key)
                        <div class="alert">
                            <div class="flex-1">
                                <div class="font-medium text-sm mb-1">{{ __('Secret Link') }}</div>
                                <code class="text-xs break-all">{{ $statusPage->publicUrl() }}</code>
                            </div>
                            <button type="button" wire:click="regenerateAccessKey" wire:confirm="{{ __('Regenerate? The current secret link will stop working.') }}" class="btn btn-ghost btn-sm">
                                {{ __('Regenerate') }}
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            <div class="flex justify-between items-center pt-2">
                <button type="button" wire:click="deleteStatusPage" wire:confirm="{{ __('Delete this status page? This cannot be undone.') }}" class="btn btn-ghost text-error">
                    {{ __('Delete Status Page') }}
                </button>
                <button type="submit" class="btn btn-primary">
                    {{ __('Save Changes') }}
                </button>
            </div>
        </form>

        <!-- Custom Domain (separate form) -->
        <div class="card bg-base-100 border border-base-300 mt-6 max-w-2xl">
            <div class="card-body gap-4">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-base-content/50">{{ __('Custom Domain') }}</h3>
                <p class="text-xs text-base-content/50">{{ __('Serve this status page from your own domain (e.g. status.example.com). HTTPS is provisioned automatically once the domain is verified.') }}</p>

                <form wire:submit="saveCustomDomain" class="space-y-3">
                    <div class="form-control">
                        <label class="label pb-1"><span class="label-text font-medium">{{ __('Domain') }}</span></label>
                        <div class="flex gap-2">
                            <input type="text" wire:model="customDomain"
                                class="input input-bordered flex-1 rounded-lg @error('customDomain') input-error @enderror"
                                placeholder="status.example.com" />
                            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                            @if ($statusPage->custom_domain)
                                <button type="button" wire:click="removeCustomDomain" wire:confirm="{{ __('Remove custom domain?') }}" class="btn btn-ghost text-error">
                                    {{ __('Remove') }}
                                </button>
                            @endif
                        </div>
                        @error('customDomain')<div class="label pb-0"><span class="label-text-alt text-error">{{ $message }}</span></div>@enderror
                    </div>
                </form>

                @if ($statusPage->custom_domain)
                    <div class="divider my-2"></div>

                    @if ($statusPage->isDomainVerified())
                        <div class="alert alert-success">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 stroke-current shrink-0" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <div>
                                <div class="font-medium">{{ __('Domain verified') }} · {{ $statusPage->domain_verified_at->diffForHumans() }}</div>
                                <div class="text-sm">
                                    <a href="https://{{ $statusPage->custom_domain }}" target="_blank" class="link">https://{{ $statusPage->custom_domain }}</a>
                                </div>
                            </div>
                        </div>
                    @else
                        <div>
                            <div class="font-medium text-sm mb-2">{{ __('Step 1 — Add a TXT record at your DNS provider:') }}</div>
                            <div class="bg-base-200 rounded-lg p-3 mb-3 text-xs font-mono space-y-1">
                                <div><span class="text-base-content/50">{{ __('Type:') }}</span> TXT</div>
                                <div><span class="text-base-content/50">{{ __('Host:') }}</span> {{ $statusPage->domainVerificationHost() }}</div>
                                <div class="break-all"><span class="text-base-content/50">{{ __('Value:') }}</span> {{ $statusPage->domainVerificationToken() }}</div>
                            </div>

                            <div class="font-medium text-sm mb-2">{{ __('Step 2 — Point your domain to this server:') }}</div>
                            <div class="bg-base-200 rounded-lg p-3 mb-3 text-xs font-mono space-y-1">
                                <div><span class="text-base-content/50">{{ __('Type:') }}</span> CNAME</div>
                                <div><span class="text-base-content/50">{{ __('Host:') }}</span> {{ $statusPage->custom_domain }}</div>
                                <div><span class="text-base-content/50">{{ __('Value:') }}</span> {{ request()->getHost() }}</div>
                            </div>

                            <div class="font-medium text-sm mb-2">{{ __('Step 3 — Click verify (DNS may take a few minutes to propagate):') }}</div>
                            <button type="button" wire:click="verifyCustomDomain" class="btn btn-primary btn-sm">
                                {{ __('Verify Domain') }}
                            </button>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    @endif
</div>
