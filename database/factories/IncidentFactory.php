<?php

namespace Database\Factories;

use App\Models\Monitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Incident>
 */
class IncidentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = now()->subMinutes($this->faker->numberBetween(5, 120));

        return [
            'monitor_id' => Monitor::factory(),
            'severity' => \App\Models\Incident::SEVERITY_DOWN,
            'started_at' => $startedAt,
            'ended_at' => null,
            'duration_seconds' => null,
            'error_message' => 'Connection timeout',
            'status_code' => null,
            'trigger_node_id' => 'probe-'.$this->faker->randomLetter(),
            'affected_node_ids' => null,
        ];
    }

    public function degraded(): self
    {
        return $this->state(fn () => ['severity' => \App\Models\Incident::SEVERITY_DEGRADED]);
    }

    public function resolved(): self
    {
        return $this->state(function (array $attrs) {
            $ended = \Illuminate\Support\Carbon::parse($attrs['started_at'])->addMinutes($this->faker->numberBetween(1, 30));

            return [
                'ended_at' => $ended,
                'duration_seconds' => $ended->getTimestamp() - \Illuminate\Support\Carbon::parse($attrs['started_at'])->getTimestamp(),
            ];
        });
    }
}
