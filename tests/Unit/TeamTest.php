<?php

use App\Models\Team;
use App\Models\User;

test('team belongs to an owner', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $user->id]);

    expect($team->owner)->toBeInstanceOf(User::class);
    expect($team->owner->id)->toBe($user->id);
});

test('team can have multiple users', function () {
    $team = Team::factory()->create();
    $users = User::factory()->count(3)->create();

    $team->users()->attach($users->pluck('id'), ['role' => 'member']);

    expect($team->users)->toHaveCount(3);
});

test('team can identify its owner', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);

    expect($team->isOwner($owner))->toBeTrue();
    expect($team->isOwner($otherUser))->toBeFalse();
});

test('team can check if user exists', function () {
    $team = Team::factory()->create();
    $member = User::factory()->create();
    $nonMember = User::factory()->create();

    $team->users()->attach($member->id, ['role' => 'member']);

    expect($team->hasUser($member))->toBeTrue();
    expect($team->hasUser($nonMember))->toBeFalse();
});

test('team can get user role', function () {
    $team = Team::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $nonMember = User::factory()->create();

    $team->users()->attach($admin->id, ['role' => 'admin']);
    $team->users()->attach($member->id, ['role' => 'member']);

    expect($team->userRole($admin))->toBe('admin');
    expect($team->userRole($member))->toBe('member');
    expect($team->userRole($nonMember))->toBeNull();
});

test('team can filter members by role', function () {
    $team = Team::factory()->create();
    $admins = User::factory()->count(2)->create();
    $members = User::factory()->count(3)->create();

    foreach ($admins as $admin) {
        $team->users()->attach($admin->id, ['role' => 'admin']);
    }

    foreach ($members as $member) {
        $team->users()->attach($member->id, ['role' => 'member']);
    }

    expect($team->admins()->count())->toBe(2);
    expect($team->members()->count())->toBe(3);
});
