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

    public function slack(?string $webhookUrl = null, ?string $label = null): static
    {
        return $this->state(fn () => [
            'type' => NotificationChannelType::Slack,
            'label' => $label ?? '#alerts',
            'config' => [
                'webhook_url' => $webhookUrl ?? 'https://hooks.slack.com/services/T000/B000/XXXXXXXXXXXXXXXXXXXXXXXX',
            ],
        ]);
    }

    public function discord(?string $webhookUrl = null, ?string $label = null): static
    {
        return $this->state(fn () => [
            'type' => NotificationChannelType::Discord,
            'label' => $label ?? '#alerts',
            'config' => [
                'webhook_url' => $webhookUrl ?? 'https://discord.com/api/webhooks/0/aaaaaaaaaaaa',
            ],
        ]);
    }

    public function telegram(?string $botToken = null, ?string $chatId = null, ?string $label = null): static
    {
        return $this->state(fn () => [
            'type' => NotificationChannelType::Telegram,
            'label' => $label ?? 'Ops group',
            'config' => [
                'bot_token' => $botToken ?? '123456789:'.str_repeat('A', 35),
                'chat_id' => $chatId ?? '-100123456789',
            ],
        ]);
    }

    public function webhook(?string $url = null, ?string $label = null, ?string $secret = null): static
    {
        return $this->state(fn () => [
            'type' => NotificationChannelType::Webhook,
            'label' => $label ?? 'PagerDuty',
            'config' => [
                'url' => $url ?? 'https://example.com/hooks/easymonitor',
                'secret' => $secret ?? str_repeat('s', 64),
            ],
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
