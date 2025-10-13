<?php

use App\Livewire\Auth\Login;
use App\Models\User;
use Laravel\Fortify\Features;
use Livewire\Livewire;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $response = Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login');

    $response
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $response = Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'wrong-password')
        ->call('login');

    $response->assertHasErrors('email');

    $this->assertGuest();
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->create();

    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $response = Livewire::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login');

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $response->assertRedirect('/');

    $this->assertGuest();
});