<?php

use App\Livewire\StatusPages\Create;
use App\Livewire\StatusPages\Index;
use App\Livewire\StatusPages\Manage;
use App\Models\Monitor;
use App\Models\Project;
use App\Models\StatusPage;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected from admin status pages', function () {
    $sp = StatusPage::factory()->create();

    $this->get(route('status-pages.index'))->assertRedirect(route('login'));
    $this->get(route('status-pages.create'))->assertRedirect(route('login'));
    $this->get(route('status-pages.manage', $sp))->assertRedirect(route('login'));
});

test('user can create a public status page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Create::class)
        ->set('name', 'My Service')
        ->set('slug', 'my-service')
        ->set('visibility', 'public')
        ->call('save')
        ->assertRedirect();

    $this->assertDatabaseHas('status_pages', [
        'user_id' => $user->id,
        'slug' => 'my-service',
        'visibility' => 'public',
    ]);
});

test('creating an unlisted page generates an access key', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Create::class)
        ->set('name', 'Secret Page')
        ->set('slug', 'secret-page')
        ->set('visibility', 'unlisted')
        ->call('save');

    $sp = StatusPage::where('slug', 'secret-page')->first();
    expect($sp->access_key)->not->toBeEmpty()->and(strlen($sp->access_key))->toBeGreaterThan(20);
});

test('slug must be unique', function () {
    $user = User::factory()->create();
    StatusPage::factory()->create(['slug' => 'taken']);
    $this->actingAs($user);

    Livewire::test(Create::class)
        ->set('name', 'Other')
        ->set('slug', 'taken')
        ->call('save')
        ->assertHasErrors('slug');
});

test('user cannot manage another users status page', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $sp = StatusPage::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($intruder);

    Livewire::test(Manage::class, ['statusPage' => $sp])->assertForbidden();
});

test('team admin can manage team status page', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->users()->attach($admin->id, ['role' => 'admin']);
    $sp = StatusPage::factory()->forTeam($team)->create();

    $this->actingAs($admin);

    Livewire::test(Manage::class, ['statusPage' => $sp])
        ->assertSuccessful();
});

test('team member cannot edit team status page', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->users()->attach($member->id, ['role' => 'member']);
    $sp = StatusPage::factory()->forTeam($team)->create();

    $this->actingAs($member);

    Livewire::test(Manage::class, ['statusPage' => $sp])->assertForbidden();
});

test('user can add a project to status page', function () {
    $user = User::factory()->create();
    $sp = StatusPage::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create(['user_id' => $user->id]);
    Monitor::factory()->up()->create(['user_id' => $user->id, 'project_id' => $project->id]);

    $this->actingAs($user);

    Livewire::test(Manage::class, ['statusPage' => $sp])
        ->set('addItemType', 'project')
        ->set('addProjectId', $project->id)
        ->call('addItem');

    $this->assertDatabaseHas('status_page_items', [
        'status_page_id' => $sp->id,
        'type' => 'project',
        'project_id' => $project->id,
    ]);
});

test('user can add a single monitor to status page', function () {
    $user = User::factory()->create();
    $sp = StatusPage::factory()->create(['user_id' => $user->id]);
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(Manage::class, ['statusPage' => $sp])
        ->set('addItemType', 'monitor')
        ->set('addMonitorId', $monitor->id)
        ->call('addItem');

    $this->assertDatabaseHas('status_page_items', [
        'status_page_id' => $sp->id,
        'type' => 'monitor',
        'monitor_id' => $monitor->id,
    ]);
});

test('user can hide a monitor from status page', function () {
    $user = User::factory()->create();
    $sp = StatusPage::factory()->create(['user_id' => $user->id]);
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(Manage::class, ['statusPage' => $sp])
        ->call('toggleMonitorVisibility', $monitor->id);

    $this->assertDatabaseHas('status_page_excluded_monitors', [
        'status_page_id' => $sp->id,
        'monitor_id' => $monitor->id,
    ]);

    Livewire::test(Manage::class, ['statusPage' => $sp])
        ->call('toggleMonitorVisibility', $monitor->id);

    $this->assertDatabaseMissing('status_page_excluded_monitors', [
        'status_page_id' => $sp->id,
        'monitor_id' => $monitor->id,
    ]);
});

test('aggregate status reflects monitors in items', function () {
    $user = User::factory()->create();
    $sp = StatusPage::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create(['user_id' => $user->id]);

    Monitor::factory()->up()->count(2)->create(['user_id' => $user->id, 'project_id' => $project->id]);

    \App\Models\StatusPageItem::create([
        'status_page_id' => $sp->id,
        'type' => 'project',
        'project_id' => $project->id,
    ]);

    expect($sp->fresh()->aggregateStatus())->toBe('operational');

    Monitor::factory()->down()->create(['user_id' => $user->id, 'project_id' => $project->id]);

    expect($sp->fresh()->aggregateStatus())->toBe('degraded');
});

test('excluded monitors are not in resolved sections', function () {
    $user = User::factory()->create();
    $sp = StatusPage::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create(['user_id' => $user->id]);

    $visible = Monitor::factory()->up()->create(['user_id' => $user->id, 'project_id' => $project->id]);
    $hidden = Monitor::factory()->up()->create(['user_id' => $user->id, 'project_id' => $project->id]);

    \App\Models\StatusPageItem::create([
        'status_page_id' => $sp->id,
        'type' => 'project',
        'project_id' => $project->id,
    ]);
    $sp->excludedMonitors()->attach($hidden->id);

    $monitors = $sp->fresh()->resolveSections()->flatMap(fn ($s) => $s['monitors']);

    expect($monitors->pluck('id')->all())
        ->toContain($visible->id)
        ->not->toContain($hidden->id);
});

test('regenerating access key invalidates the old one', function () {
    $user = User::factory()->create();
    $sp = StatusPage::factory()->unlisted()->create(['user_id' => $user->id]);
    $oldKey = $sp->access_key;

    $this->actingAs($user);

    Livewire::test(Manage::class, ['statusPage' => $sp])->call('regenerateAccessKey');

    expect($sp->fresh()->access_key)->not->toBe($oldKey);
});

test('user can delete own status page', function () {
    $user = User::factory()->create();
    $sp = StatusPage::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(Index::class)->call('delete', $sp->id);

    $this->assertDatabaseMissing('status_pages', ['id' => $sp->id]);
});
