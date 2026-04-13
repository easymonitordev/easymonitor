<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $status_page_id
 * @property int $user_id
 * @property string $title
 * @property string|null $body
 * @property string $type 'incident' | 'maintenance'
 * @property string $status
 * @property string|null $severity minor | major | critical
 * @property array|null $affected_monitor_ids
 * @property \Illuminate\Support\Carbon|null $scheduled_for
 * @property \Illuminate\Support\Carbon|null $scheduled_until
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $resolved_at
 */
class StatusPageIncident extends Model
{
    protected $table = 'status_page_incidents';

    protected $fillable = [
        'status_page_id',
        'user_id',
        'title',
        'body',
        'type',
        'status',
        'severity',
        'affected_monitor_ids',
        'scheduled_for',
        'scheduled_until',
        'started_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'affected_monitor_ids' => 'array',
            'scheduled_for' => 'datetime',
            'scheduled_until' => 'datetime',
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function statusPage(): BelongsTo
    {
        return $this->belongsTo(StatusPage::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(StatusPageIncidentUpdate::class, 'incident_id')->latest();
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    public function isMaintenance(): bool
    {
        return $this->type === 'maintenance';
    }
}
