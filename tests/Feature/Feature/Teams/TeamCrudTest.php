<?php

use App\Livewire\Teams\Create;
use App\Livewire\Teams\Edit;
use App\Livewire\Teams\Index;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

test('guests cannot access teams pages', function () {
    $this->get('/teams')->assertRedirect('/login');
    $this->get('/teams/create')->assertRedirect('/login');
});

test('teams index page is displayed', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->get('/teams')->assertOk();
});

test('teams index shows owned teams', function () {
    $user = User::factory()->create();
    $ownedTeam = Team::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->assertSee($ownedTeam->name)
        ->assertSee('Teams You Own');
});

test('teams index shows member teams', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($user->id, ['role' => 'member']);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->assertSee($team->name)
        ->assertSee('Teams You Belong To');
});

test('user can create a team', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(Create::class)
        ->set('name', 'Test Team')
        ->set('description', 'Test Description')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect('/teams');

    expect(Team::where('name', 'Test Team')->exists())->toBeTrue();
    expect(Team::where('name', 'Test Team')->first()->owner_id)->toBe($user->id);
});

test('team name is required when creating', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(Create::class)
        ->set('name', '')
        ->set('description', 'Test Description')
        ->call('save')
        ->assertHasErrors(['name']);
});

test('team description is optional when creating', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(Create::class)
        ->set('name', 'Test Team')
        ->set('description', '')
        ->call('save')
        ->assertHasNoErrors();

    expect(Team::where('name', 'Test Team')->exists())->toBeTrue();
});

test('team owner can edit team', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(Edit::class, ['team' => $team])
        ->set('name', 'Updated Team Name')
        ->set('description', 'Updated Description')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect('/teams');

    expect($team->fresh()->name)->toBe('Updated Team Name');
    expect($team->fresh()->description)->toBe('Updated Description');
});

test('team admin can edit team', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($user->id, ['role' => 'admin']);

    $this->actingAs($user);

    Livewire::test(Edit::class, ['team' => $team])
        ->set('name', 'Updated by Admin')
        ->call('save')
        ->assertHasNoErrors();

    expect($team->fresh()->name)->toBe('Updated by Admin');
});

test('team member cannot edit team', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->attach($user->id, ['role' => 'member']);

    $this->actingAs($user);

    $this->get("/teams/{$team->id}/edit")->assertForbidden();
});

test('team owner can delete team', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('delete', $team->id);

    expect(Team::find($team->id))->toBeNull();
});

test('non-owner cannot delete team', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->users()->attach($user->id, ['role' => 'admin']);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('delete', $team->id)
        ->assertForbidden();

    expect(Team::find($team->id))->not->toBeNull();
});
