<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Monitor;
use App\Notifications\CertificateExpiringSoon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Daily job that alerts on TLS certificates approaching expiry.
 *
 * Thresholds come from config (default 30/14/7 days). Each monitor is
 * alerted at most once per threshold: crossing a tighter threshold sends a
 * new alert, and a renewed certificate re-arms the sequence.
 */
class CheckCertificateExpiry implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out
     */
    public int $timeout = 300;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $thresholds = collect(config('easymonitor.certificates.expiry_alert_days'))
            ->map(fn ($days) => (int) $days)
            ->sort()
            ->values();

        if ($thresholds->isEmpty()) {
            return;
        }

        Monitor::query()
            ->whereNotNull('cert_expires_at')
            ->where('is_active', true)
            ->chunkById(100, function (Collection $monitors) use ($thresholds) {
                foreach ($monitors as $monitor) {
                    $this->evaluate($monitor, $thresholds);
                }
            });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>  $thresholds
     */
    private function evaluate(Monitor $monitor, \Illuminate\Support\Collection $thresholds): void
    {
        $days = $monitor->certDaysRemaining();

        if ($days === null) {
            return;
        }

        // Renewed certificate: expiry moved back above the alerted threshold.
        if ($monitor->cert_alerted_threshold_days !== null && $days > $monitor->cert_alerted_threshold_days) {
            $monitor->update(['cert_alerted_threshold_days' => null]);
        }

        // Already expired: the HTTPS checks themselves fail and alert as down.
        if ($days < 0) {
            return;
        }

        $crossed = $thresholds->first(fn (int $threshold) => $days <= $threshold);

        if ($crossed === null) {
            return;
        }

        if ($monitor->cert_alerted_threshold_days !== null && $monitor->cert_alerted_threshold_days <= $crossed) {
            return;
        }

        $channels = $monitor->alertChannels();

        if ($channels->isEmpty()) {
            Log::warning('Certificate expiry alert skipped — no active channels', [
                'monitor_id' => $monitor->id,
                'days_remaining' => $days,
            ]);

            return;
        }

        Notification::send($channels, new CertificateExpiringSoon($monitor, $days));

        $monitor->update(['cert_alerted_threshold_days' => $crossed]);

        Log::info('Certificate expiry alert sent', [
            'monitor_id' => $monitor->id,
            'days_remaining' => $days,
            'threshold' => $crossed,
        ]);
    }
}
