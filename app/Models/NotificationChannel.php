<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationChannelType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\Pushover\PushoverReceiver;

class NotificationChannel extends Model
{
    /** @use HasFactory<\Database\Factories\NotificationChannelFactory> */
    use HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'config',
        'is_active',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => NotificationChannelType::class,
            'config' => 'array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function monitors(): BelongsToMany
    {
        return $this->belongsToMany(Monitor::class);
    }

    /**
     * Route mail notifications to the owning user's email address.
     */
    public function routeNotificationForMail(): ?string
    {
        return $this->user?->email;
    }

    /**
     * Route Pushover notifications using the user_key (and optional device)
     * stored in this channel's config payload.
     */
    public function routeNotificationForPushover(): ?PushoverReceiver
    {
        $userKey = $this->config['user_key'] ?? null;

        if (! is_string($userKey) || $userKey === '') {
            return null;
        }

        $receiver = PushoverReceiver::withUserKey($userKey);

        $device = $this->config['device'] ?? null;

        if (is_string($device) && $device !== '') {
            $receiver->toDevice($device);
        }

        return $receiver;
    }

    /**
     * Whether the channel has the configuration it needs to send.
     */
    public function isConfigured(): bool
    {
        return match ($this->type) {
            NotificationChannelType::Email => filled($this->user?->email),
            NotificationChannelType::Pushover => filled($this->config['user_key'] ?? null),
        };
    }
}
