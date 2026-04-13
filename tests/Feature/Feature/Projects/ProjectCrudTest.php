<?php

use App\Livewire\Projects\Create;
use App\Livewire\Projects\Edit;
use App\Livewire\Projects\Index;
use App\Livewire\Projects\Show;
use App\Models\Monitor;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

test('guests cannot access projects pages', function () {
    $project = Project::factory()->create();

    $this->get(route('projects.index'))->assertRedirect(route('login'));
    $this->get(route('projects.create'))->assertRedirect(route('login'));
    $this->get(route('projects.edit', $project))->assertRedirect(route('login'));
    $this->get(route('projects.show', $project))->assertRedirect(route('login'));
});

test('projects index page is displayed', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Index::class)->assertSuccessful();
});

test('user can create a personal project', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Create::class)
        ->set('name', 'ConvertHub')
        ->set('description', 'Main site + APIs')
        ->call('save')
        ->assertRedirect(route('projects.index'));

    $this->assertDatabaseHas('projects', [
        'user_id' => $user->id,
        'team_id' => null,
        'name' => 'ConvertHub',
    ]);
});

test('project name is required', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Create::class)
        ->set('name', '')
        ->call('save')
        ->assertHasErrors('name');
});

test('team owner can create team project', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $user->id]);
    $this->actingAs($user);

    Livewire::test(Create::class)
        ->set('name', 'Team Project')
        ->set('teamId', $team->id)
        ->call('save')
        ->assertRedirect(route('projects.index'));

    $this->assertDatabaseHas('projects', [
        'team_id' => $team->id,
        'name' => 'Team Project',
    ]);
});

test('user can edit own project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    Livewire::test(Edit::class, ['project' => $project])
        ->set('name', 'Renamed')
        ->call('save')
        ->assertRedirect(route('projects.show', $project));

    $this->assertDatabaseHas('projects', [
        'id' => $project->id,
        'name' => 'Renamed',
    ]);
});

test('user cannot edit another users project', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($intruder);

    Livewire::test(Edit::class, ['project' => $project])->assertForbidden();
});

test('team admin can edit team project', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->users()->attach($admin->id, ['role' => 'admin']);
    $project = Project::factory()->forTeam($team)->create();

    $this->actingAs($admin);

    Livewire::test(Edit::class, ['project' => $project])
        ->set('name', 'Edited by admin')
        ->call('save');

    $this->assertDatabaseHas('projects', [
        'id' => $project->id,
        'name' => 'Edited by admin',
    ]);
});

test('team member cannot edit team project', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->users()->attach($member->id, ['role' => 'member']);
    $project = Project::factory()->forTeam($team)->create();

    $this->actingAs($member);

    Livewire::test(Edit::class, ['project' => $project])->assertForbidden();
});

test('deleting a project unassigns its monitors', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $monitor = Monitor::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
    ]);

    $this->actingAs($user);

    Livewire::test(Index::class)->call('delete', $project->id);

    $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    $this->assertDatabaseHas('monitors', [
        'id' => $monitor->id,
        'project_id' => null,
    ]);
});

test('user can view own project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id, 'name' => 'My Project']);

    $this->actingAs($user);

    Livewire::test(Show::class, ['project' => $project])
        ->assertSuccessful()
        ->assertSee('My Project');
});

test('user cannot view another users personal project', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($intruder);

    Livewire::test(Show::class, ['project' => $project])->assertForbidden();
});

test('team member can view team project', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->users()->attach($member->id, ['role' => 'member']);
    $project = Project::factory()->forTeam($team)->create();

    $this->actingAs($member);

    Livewire::test(Show::class, ['project' => $project])->assertSuccessful();
});

test('project aggregate status is operational when all monitors up', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    Monitor::factory()->up()->count(3)->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
    ]);

    expect($project->fresh()->aggregateStatus())->toBe('operational');
});

test('project aggregate status is outage when all monitors down', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    Monitor::factory()->down()->count(2)->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
    ]);

    expect($project->fresh()->aggregateStatus())->toBe('outage');
});

test('project aggregate status is degraded when some down', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    Monitor::factory()->up()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
    ]);
    Monitor::factory()->down()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
    ]);

    expect($project->fresh()->aggregateStatus())->toBe('degraded');
});

test('monitor assigned to team project inherits team access', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->users()->attach($member->id, ['role' => 'member']);
    $project = Project::factory()->forTeam($team)->create();
    $monitor = Monitor::factory()->create([
        'user_id' => $owner->id,
        'team_id' => null,
        'project_id' => $project->id,
    ]);

    // Team member should be able to view the monitor via project team
    expect($member->can('view', $monitor->fresh()))->toBeTrue();
});

test('monitor outside project falls back to team_id for access', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->users()->attach($member->id, ['role' => 'member']);
    $monitor = Monitor::factory()->forTeam($team)->create();

    expect($member->can('view', $monitor))->toBeTrue();
});
