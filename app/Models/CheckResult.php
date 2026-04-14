<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a single check result from a probe node
 *
 * Check results are stored in a TimescaleDB hypertable for efficient
 * time-series queries and automatic data retention policies.
 *
 * @property int $id
 * @property int $monitor_id
 * @property string $node_id
 * @property bool $is_up
 * @property int|null $response_time_ms
 * @property int|null $status_code
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class CheckResult extends Model
{
    /** @use HasFactory<\Database\Factories\CheckResultFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'monitor_id',
        'node_id',
        'round_id',
        'is_up',
        'response_time_ms',
        'status_code',
        'error_message',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_up' => 'boolean',
            'response_time_ms' => 'integer',
            'status_code' => 'integer',
        ];
    }

    /**
     * Get the monitor that owns this check result
     */
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    /**
     * Check if this result indicates the monitor is up
     */
    public function isUp(): bool
    {
        return $this->is_up;
    }

    /**
     * Check if this result indicates the monitor is down
     */
    public function isDown(): bool
    {
        return ! $this->is_up;
    }
}
