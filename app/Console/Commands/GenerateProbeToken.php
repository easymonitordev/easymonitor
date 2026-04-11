<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ProbeNodeService;
use Illuminate\Console\Command;

use function Laravel\Prompts\text;
use function Laravel\Prompts\info;
use function Laravel\Prompts\error;
use function Laravel\Prompts\confirm;

/**
 * Generate JWT tokens for probe nodes
 *
 * This command creates JWT tokens that probe nodes use to authenticate
 * when connecting to the monitoring system via Redis Streams.
 */
class GenerateProbeToken extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'probe:generate-token
                            {--node-id= : The unique identifier for the probe node}
                            {--tags= : Comma-separated list of tags for the probe}
                            {--expires= : Number of days until token expires (default: 365)}';

    /**
     * The console command description.
     */
    protected $description = 'Generate a JWT token for a probe node';

    /**
     * Execute the console command.
     */
    public function handle(ProbeNodeService $probeService): int
    {
        // Check if JWT_SECRET is configured
        if (empty(config('app.jwt_secret'))) {
            if (! $this->option('no-interaction')) {
                error('JWT_SECRET is not configured in .env file');
                info('Please add JWT_SECRET to your .env file with a secure random string');
            }

            return self::FAILURE;
        }

        // Get node ID
        $nodeId = $this->option('node-id');
        if (! $nodeId) {
            if ($this->option('no-interaction')) {
                $nodeId = 'local-node-1'; // Default for non-interactive mode
            } else {
                $nodeId = text(
                    label: 'Node ID',
                    placeholder: 'my-probe-node-1',
                    required: true,
                    validate: fn (string $value) => strlen($value) < 3
                        ? 'Node ID must be at least 3 characters'
                        : null
                );
            }
        }

        // Get tags
        $tagsInput = $this->option('tags');
        $tags = $tagsInput ? explode(',', $tagsInput) : [];

        if (empty($tags) && ! $this->option('no-interaction')) {
            $tagsStr = text(
                label: 'Tags (optional, comma-separated)',
                placeholder: 'us-east-1,production',
                required: false
            );

            if ($tagsStr) {
                $tags = array_map('trim', explode(',', $tagsStr));
            }
        }

        // Get expiration
        $expires = (int) ($this->option('expires') ?? 365);

        // Generate token
        try {
            $token = $probeService->generateToken($nodeId, $tags, $expires);

            // Non-interactive mode: just update .env silently
            if ($this->option('no-interaction')) {
                $this->updateEnvFile($nodeId, $token);

                return self::SUCCESS;
            }

            // Interactive mode: show details
            $this->newLine();
            info('JWT Token generated successfully!');
            $this->newLine();

            $this->components->twoColumnDetail('<fg=green>Node ID</>', $nodeId);
            if (! empty($tags)) {
                $this->components->twoColumnDetail('<fg=green>Tags</>', implode(', ', $tags));
            }
            $this->components->twoColumnDetail('<fg=green>Expires in</>', "{$expires} days");

            $this->newLine();
            $this->line('<fg=yellow>Token:</>');
            $this->line($token);
            $this->newLine();

            $this->components->info('Add this token to your probe node configuration:');
            $this->line("NODE_ID={$nodeId}");
            $this->line("JWT_TOKEN={$token}");
            $this->line("REDIS_URL=redis://redis:6379/0  # Use rediss:// for TLS");
            $this->newLine();
            $this->components->warn('Note: JWT_SECRET stays on the server - probes only need the token!');

            $this->newLine();

            if (confirm('Would you like to update .env with this token?', default: false)) {
                $this->updateEnvFile($nodeId, $token);
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            if (! $this->option('no-interaction')) {
                error('Failed to generate token: '.$e->getMessage());
            }

            return self::FAILURE;
        }
    }

    /**
     * Update the .env file with the probe configuration
     */
    private function updateEnvFile(string $nodeId, string $token): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            if (! $this->option('no-interaction')) {
                error('.env file not found');
            }

            return;
        }

        $envContent = file_get_contents($envPath);

        // Update or add PROBE_NODE_ID
        if (str_contains($envContent, 'PROBE_NODE_ID=')) {
            $envContent = preg_replace(
                '/PROBE_NODE_ID=.*/',
                "PROBE_NODE_ID={$nodeId}",
                $envContent
            );
        } else {
            $envContent .= "\nPROBE_NODE_ID={$nodeId}";
        }

        // Update or add PROBE_JWT_TOKEN
        if (str_contains($envContent, 'PROBE_JWT_TOKEN=')) {
            $envContent = preg_replace(
                '/PROBE_JWT_TOKEN=.*/',
                "PROBE_JWT_TOKEN={$token}",
                $envContent
            );
        } else {
            $envContent .= "\nPROBE_JWT_TOKEN={$token}";
        }

        file_put_contents($envPath, $envContent);

        if (! $this->option('no-interaction')) {
            info('.env file updated successfully!');
            info('Run "docker compose restart probe" to apply changes');
        }
    }
}