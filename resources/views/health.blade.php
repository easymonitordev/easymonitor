<!DOCTYPE html>
<html lang="en" data-theme="business">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Health · {{ config('app.name', 'EasyMonitor') }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml" />
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css'])
    <meta name="robots" content="noindex" />
</head>
<body class="min-h-screen bg-base-200 p-6 md:p-10">
    <div class="max-w-3xl mx-auto">

        <div class="mb-6">
            <h1 class="text-2xl font-bold">{{ config('app.name', 'EasyMonitor') }} · System Health</h1>
            <p class="text-sm text-base-content/60 mt-1">
                {{ $timestamp }}
            </p>
        </div>

        <!-- Overall status banner -->
        <div class="rounded-xl p-4 mb-6 border-2 @if($status === 'ok') bg-success/10 border-success/40 @else bg-error/10 border-error/40 @endif">
            <div class="flex items-center gap-3">
                @if ($status === 'ok')
                    <div class="w-3 h-3 rounded-full bg-success"></div>
                    <span class="font-semibold text-success">All systems operational</span>
                @else
                    <div class="w-3 h-3 rounded-full bg-error animate-pulse"></div>
                    <span class="font-semibold text-error">One or more components need attention</span>
                @endif
            </div>
        </div>

        <!-- Components -->
        <div class="card bg-base-100 border border-base-300 mb-6">
            <div class="card-body p-4 md:p-6">
                <h2 class="font-semibold mb-3">Components</h2>
                <div class="space-y-2">
                    @foreach ($components as $name => $component)
                        <div class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-base-200/40">
                            @if ($component['status'] === 'ok')
                                <div class="w-2.5 h-2.5 rounded-full bg-success shrink-0"></div>
                            @else
                                <div class="w-2.5 h-2.5 rounded-full bg-error animate-pulse shrink-0"></div>
                            @endif
                            <span class="font-medium capitalize">{{ str_replace('_', ' ', $name) }}</span>
                            <span class="text-sm text-base-content/60 flex-1 truncate">{{ $component['detail'] }}</span>
                            @if ($component['status'] === 'ok')
                                <span class="text-xs font-medium text-success">OK</span>
                            @else
                                <span class="text-xs font-medium text-error">FAIL</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid gap-3 md:grid-cols-3 mb-6">
            <div class="card bg-base-100 border border-base-300">
                <div class="card-body p-4">
                    <div class="text-xs text-base-content/60 uppercase tracking-wider">Active Monitors</div>
                    <div class="text-2xl font-bold mt-1">{{ $stats['active_monitors'] ?? '—' }}</div>
                </div>
            </div>
            <div class="card bg-base-100 border border-base-300">
                <div class="card-body p-4">
                    <div class="text-xs text-base-content/60 uppercase tracking-wider">Active Probes</div>
                    <div class="text-2xl font-bold mt-1">{{ $stats['active_probes'] ?? '—' }}</div>
                </div>
            </div>
            <div class="card bg-base-100 border border-base-300">
                <div class="card-body p-4">
                    <div class="text-xs text-base-content/60 uppercase tracking-wider">Total Monitors</div>
                    <div class="text-2xl font-bold mt-1">{{ $stats['total_monitors'] ?? '—' }}</div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-xs text-base-content/50 text-center">
            For JSON output: <code class="bg-base-100 px-2 py-0.5 rounded">curl -H "Accept: application/json" {{ url('/healthz') }}</code>
            <br />
            See <a href="https://github.com/easymonitordev/easymonitor/blob/main/TROUBLESHOOTING.md" class="link">TROUBLESHOOTING.md</a> for remediation steps.
        </div>
    </div>
</body>
</html>
