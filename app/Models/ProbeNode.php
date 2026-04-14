<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A probe that has reported results to the server.
 *
 * Auto-registered by the ResultConsumer on first result. Used to compute
 * quorum denominator: majority of "active" probes must agree for a monitor
 * status transition to occur.
 *
 * @property int $id
 * @property string $node_id
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 * @property bool $active
 */
class ProbeNode extends Model
{
    /** @use HasFactory<\Database\Factories\ProbeNodeFactory> */
    use HasFactory;

    /**
     * A probe that hasn't reported in this many seconds is considered stale
     * and excluded from quorum calculations.
     */
    public const ACTIVE_WINDOW_SECONDS = 120;

    protected $fillable = [
        'node_id',
        'last_seen_at',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    /**
     * Upsert a probe from a received result (called by ResultConsumer).
     */
    public static function recordSeen(string $nodeId): self
    {
        return self::updateOrCreate(
            ['node_id' => $nodeId],
            ['last_seen_at' => now(), 'active' => true],
        );
    }

    /**
     * Count probes considered active for quorum purposes.
     *
     * Active = flagged active AND reported within ACTIVE_WINDOW_SECONDS.
     */
    public static function activeCount(): int
    {
        return self::query()
            ->where('active', true)
            ->where('last_seen_at', '>=', now()->subSeconds(self::ACTIVE_WINDOW_SECONDS))
            ->count();
    }
}
