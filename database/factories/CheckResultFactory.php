<?php

namespace Database\Factories;

use App\Models\Monitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CheckResult>
 */
class CheckResultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'monitor_id' => Monitor::factory(),
            'node_id' => 'local-node-1',
            'is_up' => true,
            'response_time_ms' => fake()->numberBetween(50, 500),
            'status_code' => 200,
            'error_message' => null,
        ];
    }

    /**
     * Indicate that the check result is down
     */
    public function down(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_up' => false,
            'response_time_ms' => null,
            'status_code' => 0,
            'error_message' => fake()->randomElement([
                'Connection timeout',
                'Connection refused',
                'DNS resolution failed',
                'SSL certificate error',
            ]),
        ]);
    }

    /**
     * Indicate a slow response
     */
    public function slow(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_up' => true,
            'response_time_ms' => fake()->numberBetween(1000, 5000),
            'status_code' => 200,
        ]);
    }
}
