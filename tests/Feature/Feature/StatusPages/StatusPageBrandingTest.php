<?php

use App\Livewire\StatusPages\Manage;
use App\Models\StatusPage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('user can set the theme', function () {
    $user = User::factory()->create();
    $sp = StatusPage::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(Manage::class, ['statusPage' => $sp])
        ->set('theme', 'dracula')
        ->call('saveBranding');

    expect($sp->fresh()->theme)->toBe('dracula');
});

test('invalid theme is rejected', function () {
    $user = User::factory()->create();
    $sp = StatusPage::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(Manage::class, ['statusPage' => $sp])
        ->set('theme', 'made-up-theme')
        ->call('saveBranding')
        ->assertHasErrors('theme');
});

test('user can save custom css', function () {
    $user = User::factory()->create();
    $sp = StatusPage::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $css = ':root { --p: 250 80% 60%; }';

    Livewire::test(Manage::class, ['statusPage' => $sp])
        ->set('customCss', $css)
        ->call('saveBranding');

    expect($sp->fresh()->custom_css)->toBe($css);
});

test('custom css is rendered on public page', function () {
    $user = User::factory()->create();
    $css = '.my-custom-class-xyz { color: red; }';
    $sp = StatusPage::factory()->create([
        'user_id' => $user->id,
        'visibility' => 'public',
        'custom_css' => $css,
    ]);

    $this->get(route('public.status', $sp->slug))
        ->assertSuccessful()
        ->assertSee('.my-custom-class-xyz', false);
});

test('theme attribute is rendered on public page', function () {
    $user = User::factory()->create();
    $sp = StatusPage::factory()->create([
        'user_id' => $user->id,
        'visibility' => 'public',
        'theme' => 'dracula',
    ]);

    $this->get(route('public.status', $sp->slug))
        ->assertSee('data-theme="dracula"', false);
});

test('user can upload a logo', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $sp = StatusPage::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $file = UploadedFile::fake()->image('logo.png', 200, 100);

    Livewire::test(Manage::class, ['statusPage' => $sp])
        ->set('logo', $file)
        ->call('saveBranding');

    $sp->refresh();
    expect($sp->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($sp->logo_path);
});

test('non-image file is rejected', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $sp = StatusPage::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $file = UploadedFile::fake()->create('not-image.pdf', 100, 'application/pdf');

    Livewire::test(Manage::class, ['statusPage' => $sp])
        ->set('logo', $file)
        ->call('saveBranding')
        ->assertHasErrors('logo');
});

test('logo larger than 2MB is rejected', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $sp = StatusPage::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $file = UploadedFile::fake()->image('huge.png')->size(3000); // 3MB

    Livewire::test(Manage::class, ['statusPage' => $sp])
        ->set('logo', $file)
        ->call('saveBranding')
        ->assertHasErrors('logo');
});

test('user can remove logo', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $sp = StatusPage::factory()->create([
        'user_id' => $user->id,
        'logo_path' => 'status-page-logos/test.png',
    ]);
    Storage::disk('public')->put('status-page-logos/test.png', 'fake content');

    $this->actingAs($user);

    Livewire::test(Manage::class, ['statusPage' => $sp])->call('removeLogo');

    expect($sp->fresh()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing('status-page-logos/test.png');
});

test('replacing logo deletes old file', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $oldPath = 'status-page-logos/old.png';
    Storage::disk('public')->put($oldPath, 'old content');
    $sp = StatusPage::factory()->create([
        'user_id' => $user->id,
        'logo_path' => $oldPath,
    ]);

    $this->actingAs($user);

    $newFile = UploadedFile::fake()->image('new.png');

    Livewire::test(Manage::class, ['statusPage' => $sp])
        ->set('logo', $newFile)
        ->call('saveBranding');

    Storage::disk('public')->assertMissing($oldPath);
});
