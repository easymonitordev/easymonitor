<?php

namespace Tests;

use App\Services\HostResolver;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        }

        $this->fakeDns();
    }

    /**
     * Resolve hostnames without the network: IP literals resolve to
     * themselves, listed hosts to their given addresses, and every other
     * host to a public address
     *
     * @param  array<string, list<string>>  $hosts
     */
    protected function fakeDns(array $hosts = []): void
    {
        $this->app->instance(HostResolver::class, new class($hosts) extends HostResolver
        {
            /**
             * @param  array<string, list<string>>  $hosts
             */
            public function __construct(private array $hosts) {}

            public function resolve(string $host): array
            {
                if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
                    return [$host];
                }

                return $this->hosts[$host] ?? ['93.184.215.14'];
            }
        });
    }
}
