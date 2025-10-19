<?php

use App\Livewire\Monitors\Create as MonitorsCreate;
use App\Livewire\Monitors\Edit as MonitorsEdit;
use App\Livewire\Monitors\Index as MonitorsIndex;
use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use App\Livewire\Settings\TwoFactor;
use App\Livewire\Teams\Create;
use App\Livewire\Teams\Edit;
use App\Livewire\Teams\Index;
use App\Livewire\Teams\ManageMembers;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', Profile::class)->name('settings.profile');
    Route::get('settings/password', Password::class)->name('settings.password');
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

    Route::prefix('monitors')->name('monitors.')->group(function () {
        Route::get('/', MonitorsIndex::class)->name('index');
        Route::get('/create', MonitorsCreate::class)->name('create');
        Route::get('/{monitor}/edit', MonitorsEdit::class)->name('edit');
    });
});

require __DIR__.'/auth.php';
