<?php

declare(strict_types=1);

namespace App\Jobs\MonitoringEngine;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Keeps exactly one chain of a self-requeuing monitoring job alive.
 *
 * Every chain carries a loop id, and only the chain whose id matches the
 * cached current id keeps requeueing; any other chain stops at its next run.
 * Without this, each extra dispatch (a Horizon restart, a retried job) left
 * one more chain running forever, because a chain that lost the lock still
 * requeued itself.
 *
 * Using classes must accept the loop id as their first constructor argument
 * and store it in a public ?string $loopId property.
 */
trait RunsAsSingleLoop
{
    /**
     * The cache key that holds the id of the current chain
     */
    abstract protected static function loopKey(): string;

    /**
     * Start a new chain and retire every other chain of this job
     */
    public static function startLoop(): void
    {
        $loopId = (string) Str::uuid();

        Cache::forever(static::loopKey(), $loopId);

        dispatch(new static($loopId));
    }

    /**
     * Whether this job belongs to the current chain
     *
     * When no chain is registered (first run after an upgrade, or the cache
     * was flushed), the first job to ask adopts the loop, so monitoring
     * never stops just because the id was lost.
     */
    protected function isCurrentLoop(): bool
    {
        $this->loopId ??= (string) Str::uuid();

        Cache::add(static::loopKey(), $this->loopId);

        if (Cache::get(static::loopKey()) === $this->loopId) {
            return true;
        }

        Log::info(static::class.': stopping a superseded loop.');

        return false;
    }
}
