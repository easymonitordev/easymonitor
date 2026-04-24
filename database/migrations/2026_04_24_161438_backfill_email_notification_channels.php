<?php

use App\Enums\NotificationChannelType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('users')->orderBy('id')->each(function (object $user) use ($now): void {
            $channelId = DB::table('notification_channels')->insertGetId([
                'user_id' => $user->id,
                'type' => NotificationChannelType::Email->value,
                'config' => json_encode([]),
                'is_active' => true,
                'is_default' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $monitorIds = DB::table('monitors')->where('user_id', $user->id)->pluck('id');

            foreach ($monitorIds as $monitorId) {
                DB::table('monitor_notification_channel')->insert([
                    'monitor_id' => $monitorId,
                    'notification_channel_id' => $channelId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        // Data backfill — leave rows in place on rollback. The schema migrations
        // above will drop the tables entirely.
    }
};
