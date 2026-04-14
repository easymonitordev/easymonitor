<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProbeNode>
 */
class ProbeNodeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'node_id' => 'probe-'.fake()->unique()->slug(2),
            'last_seen_at' => now(),
            'active' => true,
        ];
    }

    public function stale(): static
    {
        return $this->state(fn () => [
            'last_seen_at' => now()->subMinutes(10),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
