<?php

use App\Livewire\Teams\ManageMembers;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

test('manage members page is displayed for team owner', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner);

    $this->get("/teams/{$team->id}/members")->assertOk();
});

test('manage members page is displayed for team admin', function () {
    $admin = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($admin->id, ['role' => 'admin']);

    $this->actingAs($admin);

    $this->get("/teams/{$team->id}/members")->assertOk();
});

test('team member cannot view manage members page', function () {
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($member->id, ['role' => 'member']);

    $this->actingAs($member);

    $this->get("/teams/{$team->id}/members")->assertForbidden();
});

test('non-members cannot access manage members page', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $this->actingAs($user);

    $this->get("/teams/{$team->id}/members")->assertForbidden();
});

test('team owner can add members', function () {
    $owner = User::factory()->create();
    $newMember = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner);

    Livewire::test(ManageMembers::class, ['team' => $team])
        ->set('email', $newMember->email)
        ->set('role', 'member')
        ->call('addMember')
        ->assertHasNoErrors();

    expect($team->hasUser($newMember))->toBeTrue();
    expect($team->userRole($newMember))->toBe('member');
});

test('team admin can add members', function () {
    $admin = User::factory()->create();
    $newMember = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($admin->id, ['role' => 'admin']);

    $this->actingAs($admin);

    Livewire::test(ManageMembers::class, ['team' => $team])
        ->set('email', $newMember->email)
        ->set('role', 'admin')
        ->call('addMember')
        ->assertHasNoErrors();

    expect($team->hasUser($newMember))->toBeTrue();
    expect($team->userRole($newMember))->toBe('admin');
});

test('team member cannot add members', function () {
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($member->id, ['role' => 'member']);

    $this->actingAs($member);

    // Member cannot even access the manage members page
    $this->get("/teams/{$team->id}/members")->assertForbidden();
});

test('cannot add existing member to team', function () {
    $owner = User::factory()->create();
    $existingMember = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->users()->attach($existingMember->id, ['role' => 'member']);

    $this->actingAs($owner);

    Livewire::test(ManageMembers::class, ['team' => $team])
        ->set('email', $existingMember->email)
        ->set('role', 'member')
        ->call('addMember')
        ->assertHasErrors(['email']);
});

test('cannot add team owner as member', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner);

    Livewire::test(ManageMembers::class, ['team' => $team])
        ->set('email', $owner->email)
        ->set('role', 'member')
        ->call('addMember')
        ->assertHasErrors(['email']);
});

test('email must exist in users table when adding member', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner);

    Livewire::test(ManageMembers::class, ['team' => $team])
        ->set('email', 'nonexistent@example.com')
        ->set('role', 'member')
        ->call('addMember')
        ->assertHasErrors(['email']);
});

test('team owner can remove members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->users()->attach($member->id, ['role' => 'member']);

    $this->actingAs($owner);

    Livewire::test(ManageMembers::class, ['team' => $team])
        ->call('removeMember', $member->id);

    expect($team->hasUser($member))->toBeFalse();
});

test('team admin can remove members', function () {
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($admin->id, ['role' => 'admin']);
    $team->users()->attach($member->id, ['role' => 'member']);

    $this->actingAs($admin);

    Livewire::test(ManageMembers::class, ['team' => $team])
        ->call('removeMember', $member->id);

    expect($team->hasUser($member))->toBeFalse();
});

test('team member cannot remove members', function () {
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($member->id, ['role' => 'member']);

    $this->actingAs($member);

    // Member cannot even access the manage members page
    $this->get("/teams/{$team->id}/members")->assertForbidden();
});

test('cannot remove team owner', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner);

    Livewire::test(ManageMembers::class, ['team' => $team])
        ->call('removeMember', $owner->id);

    expect($team->owner_id)->toBe($owner->id);
});

test('team owner can update member roles', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->users()->attach($member->id, ['role' => 'member']);

    $this->actingAs($owner);

    Livewire::test(ManageMembers::class, ['team' => $team])
        ->call('updateRole', $member->id, 'admin');

    expect($team->userRole($member))->toBe('admin');
});

test('team admin can update member roles', function () {
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($admin->id, ['role' => 'admin']);
    $team->users()->attach($member->id, ['role' => 'member']);

    $this->actingAs($admin);

    Livewire::test(ManageMembers::class, ['team' => $team])
        ->call('updateRole', $member->id, 'admin');

    expect($team->userRole($member))->toBe('admin');
});

test('cannot change team owner role', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner);

    Livewire::test(ManageMembers::class, ['team' => $team])
        ->call('updateRole', $owner->id, 'member');

    expect($team->owner_id)->toBe($owner->id);
});
