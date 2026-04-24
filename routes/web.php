<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\PublicStatusPageController;
use App\Livewire\Dashboard;
use App\Livewire\Monitors\Create as MonitorsCreate;
use App\Livewire\Monitors\Edit as MonitorsEdit;
use App\Livewire\Monitors\Index as MonitorsIndex;
use App\Livewire\Monitors\Show as MonitorsShow;
use App\Livewire\Projects\Create as ProjectsCreate;
use App\Livewire\Projects\Edit as ProjectsEdit;
use App\Livewire\Projects\Index as ProjectsIndex;
use App\Livewire\Projects\Show as ProjectsShow;
use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Notifications as SettingsNotifications;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use App\Livewire\Settings\TwoFactor;
use App\Livewire\StatusPages\Create as StatusPagesCreate;
use App\Livewire\StatusPages\Incidents\Create as StatusPageIncidentCreate;
use App\Livewire\StatusPages\Incidents\Edit as StatusPageIncidentEdit;
use App\Livewire\StatusPages\Index as StatusPagesIndex;
use App\Livewire\StatusPages\Manage as StatusPagesManage;
use App\Livewire\Teams\Create;
use App\Livewire\Teams\Edit;
use App\Livewire\Teams\Index;
use App\Livewire\Teams\ManageMembers;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

// Caddy on-demand TLS ask endpoint
Route::get('/caddy/ask', [PublicStatusPageController::class, 'caddyAsk'])->name('caddy.ask');

// Home — if Host matches a verified custom_domain, render the status page;
// otherwise show the welcome page.
Route::get('/', function (\Illuminate\Http\Request $request) {
    $host = strtolower($request->getHost());

    if (\App\Models\StatusPage::where('custom_domain', $host)->whereNotNull('domain_verified_at')->exists()) {
        return app(PublicStatusPageController::class)->showByDomain($request);
    }

    return view('welcome');
})->name('home');

// Public status page (unauthenticated, slug-based)
Route::get('/status/{slug}', [PublicStatusPageController::class, 'show'])->name('public.status');

Route::get('dashboard', Dashboard::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    // App-level health dashboard (DB, Redis, monitoring loop, probes).
    // Returns JSON if the client asks for it, rich HTML for browsers.
    Route::get('/healthz', HealthController::class)->name('healthz');

    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', Profile::class)->name('settings.profile');
    Route::get('settings/password', Password::class)->name('settings.password');
    Route::get('settings/notifications', SettingsNotifications::class)->name('settings.notifications');
    Route::get('settings/appearance', Appearance::class)->name('settings.appearance');

    Route::get('settings/two-factor', TwoFactor::class)
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');

    Route::prefix('teams')->name('teams.')->group(function () {
        Route::get('/', Index::class)->name('index');
        Route::get('/create', Create::class)->name('create');
        Route::get('/{team}/edit', Edit::class)->name('edit');
        Route::get('/{team}/members', ManageMembers::class)->name('members');
    });

    Route::prefix('projects')->name('projects.')->group(function () {
        Route::get('/', ProjectsIndex::class)->name('index');
        Route::get('/create', ProjectsCreate::class)->name('create');
        Route::get('/{project}', ProjectsShow::class)->name('show');
        Route::get('/{project}/edit', ProjectsEdit::class)->name('edit');
    });

    Route::prefix('status-pages')->name('status-pages.')->group(function () {
        Route::get('/', StatusPagesIndex::class)->name('index');
        Route::get('/create', StatusPagesCreate::class)->name('create');
        Route::get('/{statusPage}', StatusPagesManage::class)->name('manage');
        Route::get('/{statusPage}/incidents/create', StatusPageIncidentCreate::class)->name('incidents.create');
        Route::get('/{statusPage}/incidents/{incident}/edit', StatusPageIncidentEdit::class)->name('incidents.edit');
    });

    Route::prefix('monitors')->name('monitors.')->group(function () {
        Route::get('/', MonitorsIndex::class)->name('index');
        Route::get('/create', MonitorsCreate::class)->name('create');
        Route::get('/{monitor}', MonitorsShow::class)->name('show');
        Route::get('/{monitor}/edit', MonitorsEdit::class)->name('edit');
    });
});

require __DIR__.'/auth.php';
