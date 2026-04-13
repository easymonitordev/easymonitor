<?php

use App\Livewire\Auth\Register;
use App\Models\User;
use Livewire\Livewire;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $response = Livewire::test(Register::class)
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register');

    $response
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('first user can register even when registration is disabled', function () {
    config(['auth.registration_enabled' => false]);

    expect(User::count())->toBe(0);

    Livewire::test(Register::class)
        ->set('name', 'First User')
        ->set('email', 'first@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->assertHasNoErrors();

    $this->assertAuthenticated();
});

test('register page returns 404 when disabled and a user already exists', function () {
    config(['auth.registration_enabled' => false]);
    User::factory()->create();

    $this->get('/register')->assertNotFound();
});

test('register submit returns 404 when disabled and a user already exists', function () {
    config(['auth.registration_enabled' => false]);
    User::factory()->create();

    Livewire::test(Register::class)->assertStatus(404);
});

test('register page is accessible when enabled and users exist', function () {
    config(['auth.registration_enabled' => true]);
    User::factory()->create();

    $this->get('/register')->assertSuccessful();
});
