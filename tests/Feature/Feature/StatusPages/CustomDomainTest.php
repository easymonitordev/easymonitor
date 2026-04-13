<?php

use App\Livewire\StatusPages\Manage;
use App\Models\StatusPage;
use App\Models\User;
use Livewire\Livewire;

test('user can save a custom domain', function () {
    $user = User::factory()->create();
    $sp = StatusPage::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(Manage::class, ['statusPage' => $sp])
        ->set('customDomain', 'status.example.com')
        ->call('saveCustomDomain');

    expect($sp->fresh()->custom_domain)->toBe('status.example.com');
});

test('invalid domain format is rejected', function () {
    $user = User::factory()->create();
    $sp = StatusPage::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(Manage::class, ['statusPage' => $sp])
        ->set('customDomain', 'not a valid domain')
        ->call('saveCustomDomain')
        ->assertHasErrors('customDomain');
});

test('domain must be unique', function () {
    StatusPage::factory()->create(['custom_domain' => 'taken.example.com']);
    $user = User::factory()->create();
    $sp = StatusPage::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(Manage::class, ['statusPage' => $sp])
        ->set('customDomain', 'taken.example.com')
        ->call('saveCustomDomain')
        ->assertHasErrors('customDomain');
});

test('changing domain resets verification', function () {
    $user = User::factory()->create();
    $sp = StatusPage::factory()->create([
        'user_id' => $user->id,
        'custom_domain' => 'old.example.com',
        'domain_verified_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test(Manage::class, ['statusPage' => $sp])
        ->set('customDomain', 'new.example.com')
        ->call('saveCustomDomain');

    $fresh = $sp->fresh();
    expect($fresh->custom_domain)->toBe('new.example.com');
    expect($fresh->domain_verified_at)->toBeNull();
});

test('verification token is deterministic per domain', function () {
    $user = User::factory()->create();
    $sp = StatusPage::factory()->create([
        'user_id' => $user->id,
        'custom_domain' => 'a.example.com',
    ]);

    $tokenA = $sp->domainVerificationToken();
    $sp->update(['custom_domain' => 'b.example.com']);
    $tokenB = $sp->fresh()->domainVerificationToken();

    expect($tokenA)->not->toBe($tokenB);
    expect($tokenA)->toStartWith('easymonitor-verify-');
});

test('verification host is prefixed with _easymonitor-verify', function () {
    $sp = StatusPage::factory()->create(['custom_domain' => 'status.example.com']);

    expect($sp->domainVerificationHost())->toBe('_easymonitor-verify.status.example.com');
});

test('removing custom domain clears both fields', function () {
    $user = User::factory()->create();
    $sp = StatusPage::factory()->create([
        'user_id' => $user->id,
        'custom_domain' => 'gone.example.com',
        'domain_verified_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test(Manage::class, ['statusPage' => $sp])->call('removeCustomDomain');

    $fresh = $sp->fresh();
    expect($fresh->custom_domain)->toBeNull();
    expect($fresh->domain_verified_at)->toBeNull();
});

test('caddy ask returns 200 for verified custom domain', function () {
    StatusPage::factory()->create([
        'custom_domain' => 'verified.example.com',
        'domain_verified_at' => now(),
    ]);

    $this->get(route('caddy.ask', ['domain' => 'verified.example.com']))->assertSuccessful();
});

test('caddy ask returns 404 for unverified custom domain', function () {
    StatusPage::factory()->create([
        'custom_domain' => 'pending.example.com',
        'domain_verified_at' => null,
    ]);

    $this->get(route('caddy.ask', ['domain' => 'pending.example.com']))->assertNotFound();
});

test('caddy ask returns 404 for unknown domain', function () {
    $this->get(route('caddy.ask', ['domain' => 'never-heard-of.example.com']))->assertNotFound();
});

test('caddy ask returns 400 when domain query is missing', function () {
    $this->get(route('caddy.ask'))->assertStatus(400);
});

test('home route renders status page when host matches verified custom_domain', function () {
    $sp = StatusPage::factory()->create([
        'custom_domain' => 'mydomain.test',
        'domain_verified_at' => now(),
        'name' => 'Custom Domain Page',
    ]);

    $response = $this->withHeaders(['Host' => 'mydomain.test'])->get('http://mydomain.test/');

    $response->assertSuccessful()->assertSee('Custom Domain Page');
});

test('home route renders welcome when host does not match a verified domain', function () {
    StatusPage::factory()->create([
        'custom_domain' => 'unverified.test',
        'domain_verified_at' => null,
    ]);

    // Use the default test host so we hit the welcome page.
    $this->get('/')->assertSuccessful();
});

test('private status page on custom domain still requires auth', function () {
    $owner = User::factory()->create();
    $sp = StatusPage::factory()->private()->create([
        'user_id' => $owner->id,
        'custom_domain' => 'private.test',
        'domain_verified_at' => now(),
    ]);

    // Unauthenticated visit
    $response = $this->withHeaders(['Host' => 'private.test'])->get('http://private.test/');
    $response->assertNotFound();

    // Owner can see it
    $this->actingAs($owner);
    $response = $this->withHeaders(['Host' => 'private.test'])->get('http://private.test/');
    $response->assertSuccessful();
});
