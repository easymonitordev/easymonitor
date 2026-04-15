<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $monitor_id
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon|null $ended_at
 * @property int|null $duration_seconds
 * @property string|null $error_message
 * @property int|null $status_code
 * @property string|null $trigger_node_id
 */
class Incident extends Model
{
    /** @use HasFactory<\Database\Factories\IncidentFactory> */
    use HasFactory;

    public const SEVERITY_DOWN = 'down';

    public const SEVERITY_DEGRADED = 'degraded';

    protected $fillable = [
        'monitor_id',
        'severity',
        'started_at',
        'ended_at',
        'duration_seconds',
        'error_message',
        'status_code',
        'trigger_node_id',
        'affected_node_ids',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_seconds' => 'integer',
            'status_code' => 'integer',
            'affected_node_ids' => 'array',
        ];
    }

    public function isDown(): bool
    {
        return $this->severity === self::SEVERITY_DOWN;
    }

    public function isDegraded(): bool
    {
        return $this->severity === self::SEVERITY_DEGRADED;
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    public function isOngoing(): bool
    {
        return $this->ended_at === null;
    }

    public function close(\DateTimeInterface $endedAt): void
    {
        $this->ended_at = $endedAt;
        $this->duration_seconds = max(0, $endedAt->getTimestamp() - $this->started_at->getTimestamp());
        $this->save();
    }
}
