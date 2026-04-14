<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $statusPage->effectiveTheme() }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $statusPage->name }} — Status</title>
    <meta name="description" content="{{ $statusPage->description ?? 'Live system status' }}" />
    <link rel="icon" href="/favicon.svg" type="image/svg+xml" />
    <link rel="preconnect" href="https://fonts.bunny.net" />
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css'])
    @if ($statusPage->renderableCustomCss())
        <style>{!! $statusPage->renderableCustomCss() !!}</style>
    @endif
</head>
<body class="min-h-screen bg-base-200">
    <div class="max-w-4xl mx-auto p-6 md:p-10">
        <!-- Header -->
        <header class="mb-8">
            <div class="flex items-center gap-3 mb-2">
                @if ($statusPage->logoUrl())
                    <img src="{{ $statusPage->logoUrl() }}" alt="{{ $statusPage->name }}" class="h-10 w-auto" />
                @endif
                <h1 class="text-3xl font-bold">{{ $statusPage->name }}</h1>
            </div>
            @if ($statusPage->description)
                <p class="text-base-content/70">{{ $statusPage->description }}</p>
            @endif
        </header>

        <!-- Aggregate banner -->
        <div class="rounded-xl p-6 mb-6 border-2
            @if($aggregate === 'operational') bg-success/10 border-success/40
            @elseif($aggregate === 'degraded') bg-warning/10 border-warning/40
            @elseif($aggregate === 'outage') bg-error/10 border-error/40
            @else bg-base-100 border-base-300
            @endif">
            <div class="flex items-center gap-4">
                @if ($aggregate === 'operational')
                    <div class="w-4 h-4 rounded-full bg-success shrink-0"></div>
                    <div>
                        <h2 class="text-xl font-semibold text-success">{{ __('All systems operational') }}</h2>
                        <p class="text-sm text-base-content/70 mt-1">{{ __('Everything is running normally.') }}</p>
                    </div>
                @elseif ($aggregate === 'degraded')
                    <div class="w-4 h-4 rounded-full bg-warning shrink-0"></div>
                    <div>
                        <h2 class="text-xl font-semibold text-warning">{{ __('Some systems degraded') }}</h2>
                        <p class="text-sm text-base-content/70 mt-1">{{ __('We are aware and investigating.') }}</p>
                    </div>
                @elseif ($aggregate === 'outage')
                    <div class="w-4 h-4 rounded-full bg-error animate-pulse shrink-0"></div>
                    <div>
                        <h2 class="text-xl font-semibold text-error">{{ __('Major outage') }}</h2>
                        <p class="text-sm text-base-content/70 mt-1">{{ __('We are working to restore service.') }}</p>
                    </div>
                @else
                    <div class="w-4 h-4 rounded-full bg-base-content/30 shrink-0"></div>
                    <div>
                        <h2 class="text-xl font-semibold">{{ __('No data') }}</h2>
                        <p class="text-sm text-base-content/70 mt-1">{{ __('Status is being collected.') }}</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Active incidents -->
        @if ($activeIncidents->isNotEmpty())
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-3">{{ __('Active Incidents') }}</h3>
                <div class="space-y-3">
                    @foreach ($activeIncidents as $incident)
                        <div class="card bg-base-100 border-l-4 border-error border-y border-r border-base-300">
                            <div class="card-body">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="font-semibold">{{ $incident->title }}</h4>
                                    <span class="badge badge-error capitalize">{{ str_replace('_', ' ', $incident->status) }}</span>
                                </div>
                                @if ($incident->body)
                                    <p class="text-sm text-base-content/70">{{ $incident->body }}</p>
                                @endif
                                @if ($incident->updates->isNotEmpty())
                                    <div class="mt-3 pt-3 border-t border-base-200 space-y-2">
                                        @foreach ($incident->updates as $update)
                                            <div class="text-sm">
                                                <span class="badge badge-ghost badge-sm capitalize">{{ str_replace('_', ' ', $update->status_at_update) }}</span>
                                                <span class="text-base-content/50 text-xs ml-1">{{ $update->created_at->diffForHumans() }}</span>
                                                <p class="mt-1 ml-1 text-base-content/80">{{ $update->body }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="text-xs text-base-content/40 mt-2">{{ $incident->created_at->format('M d, Y H:i T') }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Upcoming maintenance -->
        @if ($upcomingMaintenance->isNotEmpty())
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-3">{{ __('Scheduled Maintenance') }}</h3>
                <div class="space-y-3">
                    @foreach ($upcomingMaintenance as $maintenance)
                        <div class="card bg-base-100 border-l-4 border-info border-y border-r border-base-300">
                            <div class="card-body">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="font-semibold">{{ $maintenance->title }}</h4>
                                    <span class="badge badge-info capitalize">{{ str_replace('_', ' ', $maintenance->status) }}</span>
                                </div>
                                @if ($maintenance->body)
                                    <p class="text-sm text-base-content/70 mb-2">{{ $maintenance->body }}</p>
                                @endif
                                @if ($maintenance->scheduled_for)
                                    <div class="text-sm text-base-content/60">
                                        <strong>{{ __('Scheduled') }}:</strong>
                                        {{ $maintenance->scheduled_for->format('M d, Y H:i T') }}
                                        @if ($maintenance->scheduled_until)
                                            – {{ $maintenance->scheduled_until->format('H:i T') }}
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Monitors -->
        <div class="card bg-base-100 border border-base-300 mb-8">
            <div class="card-body p-4 md:p-6">
                @if ($monitors->isEmpty())
                    <p class="text-base-content/50 text-center py-8">{{ __('No services configured yet.') }}</p>
                @else
                    <div class="divide-y divide-base-200">
                        @foreach ($monitors as $monitor)
                            @php
                                $stats = $monitorStats[$monitor->id] ?? ['uptime' => null, 'days' => []];
                            @endphp
                            <div class="py-4 first:pt-0 last:pb-0">
                                <!-- Header row: name + current status -->
                                <div class="flex items-center justify-between gap-3 mb-3">
                                    <div class="flex items-center gap-2 min-w-0 flex-1">
                                        @if ($monitor->status === 'up')
                                            <div class="w-2.5 h-2.5 rounded-full bg-success shrink-0"></div>
                                        @elseif ($monitor->status === 'down')
                                            <div class="w-2.5 h-2.5 rounded-full bg-error animate-pulse shrink-0"></div>
                                        @else
                                            <div class="w-2.5 h-2.5 rounded-full bg-base-content/30 shrink-0"></div>
                                        @endif
                                        <span class="font-medium truncate">{{ $monitor->name }}</span>
                                    </div>
                                    <div class="flex items-center gap-3 shrink-0">
                                        @if ($stats['uptime'] !== null)
                                            <span class="text-xs text-base-content/60">
                                                {{ $stats['uptime'] }}% {{ __('uptime') }}
                                            </span>
                                        @endif
                                        @if ($monitor->status === 'up')
                                            <span class="text-xs font-medium text-success">{{ __('Operational') }}</span>
                                        @elseif ($monitor->status === 'down')
                                            <span class="text-xs font-medium text-error">{{ __('Down') }}</span>
                                        @else
                                            <span class="text-xs text-base-content/50">{{ __('Pending') }}</span>
                                        @endif
                                    </div>
                                </div>

                                <!-- 60-tick uptime timeline -->
                                <div class="flex items-center gap-[5px] h-7" role="img" aria-label="{{ __('Uptime history') }}">
                                    @foreach ($stats['ticks'] as $tick)
                                        @php
                                            $color = match ($tick['status']) {
                                                'up' => 'bg-success',
                                                'down' => 'bg-error',
                                                'partial' => 'bg-warning',
                                                default => 'bg-base-content/10',
                                            };
                                        @endphp
                                        <div class="tooltip flex-1 h-full" data-tip="{{ $tick['tooltip'] }}">
                                            <div class="w-full h-full rounded-[2px] {{ $color }} hover:opacity-70 transition-opacity" style="max-width: 6px; margin: 0 auto;"></div>
                                        </div>
                                    @endforeach
                                </div>

                                <!-- Axis labels -->
                                <div class="flex justify-between mt-1.5 text-xs text-base-content/40">
                                    <span>{{ $stats['from_label'] ?? __('No data yet') }}</span>
                                    <span>{{ now()->format('H:i T') }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <!-- Recent resolved -->
        @if ($recentIncidents->isNotEmpty())
            <div class="mb-8">
                <h3 class="text-lg font-semibold mb-3">{{ __('Past Incidents') }}</h3>
                <div class="card bg-base-100 border border-base-300">
                    <div class="card-body p-4">
                        <div class="space-y-3">
                            @foreach ($recentIncidents as $incident)
                                <div class="flex items-start justify-between gap-2 pb-3 {{ ! $loop->last ? 'border-b border-base-200' : '' }}">
                                    <div class="min-w-0">
                                        <div class="font-medium text-sm">{{ $incident->title }}</div>
                                        <div class="text-xs text-base-content/50 mt-0.5">{{ __('Resolved') }} {{ $incident->resolved_at?->diffForHumans() }}</div>
                                    </div>
                                    <span class="badge badge-ghost badge-sm shrink-0">{{ ucfirst($incident->type) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Footer -->
        <footer class="text-center text-sm text-base-content/50 mt-12">
            @if ($statusPage->footer_text)
                <p class="mb-2">{{ $statusPage->footer_text }}</p>
            @endif
            <p>{{ __('Last updated') }} {{ now()->format('M d, Y H:i T') }}</p>
        </footer>
    </div>
</body>
</html>
