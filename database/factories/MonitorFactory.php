<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Monitor>
 */
class MonitorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'team_id' => null,
            'name' => fake()->domainName(),
            'url' => fake()->url(),
            'is_active' => true,
            'status' => 'pending',
            'check_interval' => 60,
            'last_checked_at' => null,
            'last_error' => null,
            'consecutive_failures' => 0,
            'failure_threshold' => 3,
        ];
    }

    /**
     * Indicate that the monitor belongs to a team
     */
    public function forTeam(\App\Models\Team $team): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => $team->id,
            'user_id' => $team->owner_id,
        ]);
    }

    /**
     * Indicate that the monitor is up
     */
    public function up(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'up',
            'last_checked_at' => now(),
            'last_error' => null,
        ]);
    }

    /**
     * Indicate that the monitor is down
     */
    public function down(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'down',
            'last_checked_at' => now(),
            'last_error' => 'Connection timeout',
        ]);
    }

    /**
     * Indicate that the monitor is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
