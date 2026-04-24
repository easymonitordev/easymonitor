<?php

namespace Database\Factories;

use App\Enums\NotificationChannelType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\NotificationChannel>
 */
class NotificationChannelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => NotificationChannelType::Email,
            'config' => [],
            'is_active' => true,
            'is_default' => false,
        ];
    }

    public function email(): static
    {
        return $this->state(fn () => [
            'type' => NotificationChannelType::Email,
            'config' => [],
        ]);
    }

    public function pushover(?string $userKey = null, ?string $device = null): static
    {
        return $this->state(fn () => [
            'type' => NotificationChannelType::Pushover,
            'config' => array_filter([
                'user_key' => $userKey ?? str_repeat('a', 30),
                'device' => $device,
            ], fn ($value) => $value !== null),
        ]);
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
