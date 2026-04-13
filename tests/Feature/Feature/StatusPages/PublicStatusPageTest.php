<?php

use App\Models\Monitor;
use App\Models\Project;
use App\Models\StatusPage;
use App\Models\StatusPageItem;
use App\Models\Team;
use App\Models\User;

test('public status page is accessible without auth', function () {
    $sp = StatusPage::factory()->create([
        'visibility' => 'public',
        'name' => 'Public Service',
    ]);

    $this->get(route('public.status', $sp->slug))
        ->assertSuccessful()
        ->assertSee('Public Service');
});

test('non-existent slug returns 404', function () {
    $this->get(route('public.status', 'no-such-page'))->assertNotFound();
});

test('unlisted page without key returns 404', function () {
    $sp = StatusPage::factory()->unlisted()->create();

    $this->get(route('public.status', $sp->slug))->assertNotFound();
});

test('unlisted page with valid key is accessible', function () {
    $sp = StatusPage::factory()->unlisted()->create(['name' => 'Secret Page']);

    $this->get(route('public.status', $sp->slug).'?key='.$sp->access_key)
        ->assertSuccessful()
        ->assertSee('Secret Page');
});

test('unlisted page with wrong key returns 404', function () {
    $sp = StatusPage::factory()->unlisted()->create();

    $this->get(route('public.status', $sp->slug).'?key=wrong-key-here')->assertNotFound();
});

test('private page returns 404 to unauthenticated visitors', function () {
    $sp = StatusPage::factory()->private()->create();

    $this->get(route('public.status', $sp->slug))->assertNotFound();
});

test('private page returns 404 to other users', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $sp = StatusPage::factory()->private()->create(['user_id' => $owner->id]);

    $this->actingAs($intruder);

    $this->get(route('public.status', $sp->slug))->assertNotFound();
});

test('private page is accessible to owner', function () {
    $owner = User::factory()->create();
    $sp = StatusPage::factory()->private()->create([
        'user_id' => $owner->id,
        'name' => 'Private Page',
    ]);

    $this->actingAs($owner);

    $this->get(route('public.status', $sp->slug))
        ->assertSuccessful()
        ->assertSee('Private Page');
});

test('private team page is accessible to team member', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->users()->attach($member->id, ['role' => 'member']);
    $sp = StatusPage::factory()->private()->forTeam($team)->create();

    $this->actingAs($member);

    $this->get(route('public.status', $sp->slug))->assertSuccessful();
});

test('public page renders project monitors', function () {
    $user = User::factory()->create();
    $sp = StatusPage::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create(['user_id' => $user->id, 'name' => 'My Stack']);
    $monitor = Monitor::factory()->up()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'name' => 'Main API',
    ]);

    StatusPageItem::create([
        'status_page_id' => $sp->id,
        'type' => 'project',
        'project_id' => $project->id,
    ]);

    $this->get(route('public.status', $sp->slug))
        ->assertSuccessful()
        ->assertSee('Main API');
});

test('hidden monitor is not rendered', function () {
    $user = User::factory()->create();
    $sp = StatusPage::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create(['user_id' => $user->id]);

    $visible = Monitor::factory()->up()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'name' => 'Visible Monitor',
    ]);
    $hidden = Monitor::factory()->up()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'name' => 'Hidden Monitor',
    ]);

    StatusPageItem::create([
        'status_page_id' => $sp->id,
        'type' => 'project',
        'project_id' => $project->id,
    ]);
    $sp->excludedMonitors()->attach($hidden->id);

    $this->get(route('public.status', $sp->slug))
        ->assertSuccessful()
        ->assertSee('Visible Monitor')
        ->assertDontSee('Hidden Monitor');
});

test('public page renders aggregate operational state', function () {
    $user = User::factory()->create();
    $sp = StatusPage::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create(['user_id' => $user->id]);
    Monitor::factory()->up()->count(2)->create(['user_id' => $user->id, 'project_id' => $project->id]);

    StatusPageItem::create([
        'status_page_id' => $sp->id,
        'type' => 'project',
        'project_id' => $project->id,
    ]);

    $this->get(route('public.status', $sp->slug))
        ->assertSee('All systems operational');
});

test('public page renders aggregate outage state', function () {
    $user = User::factory()->create();
    $sp = StatusPage::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create(['user_id' => $user->id]);
    Monitor::factory()->down()->count(2)->create(['user_id' => $user->id, 'project_id' => $project->id]);

    StatusPageItem::create([
        'status_page_id' => $sp->id,
        'type' => 'project',
        'project_id' => $project->id,
    ]);

    $this->get(route('public.status', $sp->slug))
        ->assertSee('Major outage');
});
