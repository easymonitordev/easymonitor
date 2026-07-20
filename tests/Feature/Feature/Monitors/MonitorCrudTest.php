<?php

use App\Livewire\Monitors\Create;
use App\Livewire\Monitors\Edit;
use App\Livewire\Monitors\Index;
use App\Models\Monitor;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

test('guests cannot access monitors pages', function () {
    $monitor = Monitor::factory()->create();

    $this->get(route('monitors.index'))->assertRedirect(route('login'));
    $this->get(route('monitors.create'))->assertRedirect(route('login'));
    $this->get(route('monitors.edit', $monitor))->assertRedirect(route('login'));
});

test('monitors index page is displayed', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->assertSuccessful();
});

test('user can create a monitor', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(Create::class)
        ->set('teamId', null)
        ->set('name', 'My Website')
        ->set('url', 'https://example.com')
        ->set('checkInterval', 120)
        ->set('isActive', true)
        ->call('save')
        ->assertRedirect(route('monitors.index'));

    $this->assertDatabaseHas('monitors', [
        'user_id' => $user->id,
        'team_id' => null,
        'name' => 'My Website',
        'url' => 'https://example.com',
        'check_interval' => 120,
        'is_active' => true,
    ]);
});

test('monitor name is required when creating', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(Create::class)
        ->set('teamId', $team->id)
        ->set('name', '')
        ->set('url', 'https://example.com')
        ->call('save')
        ->assertHasErrors('name');
});

test('monitor url is required and must be valid', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(Create::class)
        ->set('teamId', $team->id)
        ->set('name', 'My Website')
        ->set('url', '')
        ->call('save')
        ->assertHasErrors('url');

    Livewire::test(Create::class)
        ->set('teamId', $team->id)
        ->set('name', 'My Website')
        ->set('url', 'not-a-valid-url')
        ->call('save')
        ->assertHasErrors('url');
});

test('check interval must be between 30 and 3600', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(Create::class)
        ->set('teamId', $team->id)
        ->set('name', 'My Website')
        ->set('url', 'https://example.com')
        ->set('checkInterval', 20)
        ->call('save')
        ->assertHasErrors('checkInterval');

    Livewire::test(Create::class)
        ->set('teamId', $team->id)
        ->set('name', 'My Website')
        ->set('url', 'https://example.com')
        ->set('checkInterval', 5000)
        ->call('save')
        ->assertHasErrors('checkInterval');
});

test('team owner can edit monitor', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $user->id]);
    $monitor = Monitor::factory()->forTeam($team)->create();

    $this->actingAs($user);

    Livewire::test(Edit::class, ['monitor' => $monitor])
        ->set('name', 'Updated Name')
        ->set('url', 'https://updated.com')
        ->call('save')
        ->assertRedirect(route('monitors.show', $monitor));

    $this->assertDatabaseHas('monitors', [
        'id' => $monitor->id,
        'name' => 'Updated Name',
        'url' => 'https://updated.com',
    ]);
});

test('team admin can edit monitor', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->users()->attach($admin->id, ['role' => 'admin']);
    $monitor = Monitor::factory()->forTeam($team)->create();

    $this->actingAs($admin);

    Livewire::test(Edit::class, ['monitor' => $monitor])
        ->set('name', 'Updated by Admin')
        ->call('save')
        ->assertRedirect(route('monitors.show', $monitor));

    $this->assertDatabaseHas('monitors', [
        'id' => $monitor->id,
        'name' => 'Updated by Admin',
    ]);
});

test('team member cannot edit monitor', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->users()->attach($member->id, ['role' => 'member']);
    $monitor = Monitor::factory()->forTeam($team)->create();

    $this->actingAs($member);

    Livewire::test(Edit::class, ['monitor' => $monitor])
        ->assertForbidden();
});

test('team owner can delete monitor', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $user->id]);
    $monitor = Monitor::factory()->forTeam($team)->create();

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('delete', $monitor->id);

    $this->assertDatabaseMissing('monitors', [
        'id' => $monitor->id,
    ]);
});

test('team admin can delete monitor', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->users()->attach($admin->id, ['role' => 'admin']);
    $monitor = Monitor::factory()->forTeam($team)->create();

    $this->actingAs($admin);

    Livewire::test(Index::class)
        ->call('delete', $monitor->id);

    $this->assertDatabaseMissing('monitors', [
        'id' => $monitor->id,
    ]);
});

test('team member cannot delete monitor', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->users()->attach($member->id, ['role' => 'member']);
    $monitor = Monitor::factory()->forTeam($team)->create();

    $this->actingAs($member);

    Livewire::test(Index::class)
        ->call('delete', $monitor->id)
        ->assertForbidden();

    $this->assertDatabaseHas('monitors', [
        'id' => $monitor->id,
    ]);
});

test('user can toggle their own monitor status', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->create([
        'user_id' => $user->id,
        'is_active' => true,
    ]);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('toggleActive', $monitor->id);

    $this->assertDatabaseHas('monitors', [
        'id' => $monitor->id,
        'is_active' => false,
    ]);
});

test('user can create an icmp monitor with a hostname', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(Create::class)
        ->set('name', 'Cloudflare DNS')
        ->set('checkType', 'icmp')
        ->set('url', '1.1.1.1')
        ->set('checkInterval', 60)
        ->call('save')
        ->assertRedirect(route('monitors.index'));

    $this->assertDatabaseHas('monitors', [
        'user_id' => $user->id,
        'name' => 'Cloudflare DNS',
        'check_type' => 'icmp',
        'url' => '1.1.1.1',
    ]);
});

test('check type defaults to http on create', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Create::class)->assertSet('checkType', 'http');
});

test('icmp monitor rejects values with a scheme', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Create::class)
        ->set('name', 'Bad')
        ->set('checkType', 'icmp')
        ->set('url', 'https://example.com')
        ->call('save')
        ->assertHasErrors('url');
});

test('icmp monitor rejects values with a path', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Create::class)
        ->set('name', 'Bad')
        ->set('checkType', 'icmp')
        ->set('url', 'example.com/path')
        ->call('save')
        ->assertHasErrors('url');
});

test('http monitor still requires a valid url', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Create::class)
        ->set('name', 'Bad')
        ->set('checkType', 'http')
        ->set('url', 'example.com')
        ->call('save')
        ->assertHasErrors('url');
});

test('user can switch a monitor from http to icmp', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $user->id, 'url' => 'https://example.com']);

    $this->actingAs($user);

    Livewire::test(Edit::class, ['monitor' => $monitor])
        ->set('checkType', 'icmp')
        ->set('url', '8.8.8.8')
        ->call('save')
        ->assertRedirect(route('monitors.show', $monitor));

    $this->assertDatabaseHas('monitors', [
        'id' => $monitor->id,
        'check_type' => 'icmp',
        'url' => '8.8.8.8',
    ]);
});

test('monitors index shows personal monitors', function () {
    $user = User::factory()->create();
    $monitor1 = Monitor::factory()->create(['user_id' => $user->id, 'name' => 'Monitor 1']);
    $monitor2 = Monitor::factory()->create(['user_id' => $user->id, 'name' => 'Monitor 2']);
    $otherMonitor = Monitor::factory()->create(); // Different user

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('filter', 'my')
        ->assertSee('Monitor 1')
        ->assertSee('Monitor 2')
        ->assertDontSee($otherMonitor->name);
});

test('user can create a tcp monitor with host and port', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(Create::class)
        ->set('name', 'Postgres')
        ->set('checkType', 'tcp')
        ->set('tcpHost', 'db.example.com')
        ->set('tcpPort', 5432)
        ->set('checkInterval', 60)
        ->call('save')
        ->assertRedirect(route('monitors.index'));

    $this->assertDatabaseHas('monitors', [
        'user_id' => $user->id,
        'name' => 'Postgres',
        'check_type' => 'tcp',
        'url' => 'db.example.com:5432',
    ]);
});

test('tcp monitor validation rejects bad hosts and ports', function (string $host, mixed $port, string $errorField) {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Create::class)
        ->set('name', 'Bad TCP')
        ->set('checkType', 'tcp')
        ->set('tcpHost', $host)
        ->set('tcpPort', $port)
        ->call('save')
        ->assertHasErrors($errorField);
})->with([
    'missing host' => ['', 5432, 'tcpHost'],
    'host with scheme' => ['tcp://db.example.com', 5432, 'tcpHost'],
    'host with embedded port' => ['db.example.com:5432', 5432, 'tcpHost'],
    'port zero' => ['db.example.com', 0, 'tcpPort'],
    'port too large' => ['db.example.com', 70000, 'tcpPort'],
    'missing port' => ['db.example.com', null, 'tcpPort'],
]);

test('editing a tcp monitor splits and recombines host and port', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->tcp()->create([
        'user_id' => $user->id,
        'url' => 'db.example.com:5432',
    ]);

    $this->actingAs($user);

    Livewire::test(Edit::class, ['monitor' => $monitor])
        ->assertSet('tcpHost', 'db.example.com')
        ->assertSet('tcpPort', 5432)
        ->set('tcpPort', 6432)
        ->call('save')
        ->assertRedirect(route('monitors.show', $monitor));

    expect($monitor->fresh()->url)->toBe('db.example.com:6432');
});

test('switching a monitor from tcp to http requires a url', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->tcp()->create([
        'user_id' => $user->id,
        'url' => 'db.example.com:5432',
    ]);

    $this->actingAs($user);

    Livewire::test(Edit::class, ['monitor' => $monitor])
        ->set('checkType', 'http')
        ->set('url', 'db.example.com:5432')
        ->call('save')
        ->assertHasErrors('url');
});
