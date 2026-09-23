<?php

declare(strict_types=1);

namespace App\Modules\X193\Actions;

use App\Models\AutomationRun;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class HoldsListAction
{
    public function handle(int $businessId, int $days): array
    {
        $since = Carbon::now()->subDays($days);

        $holds = [];

        // 1. notification_holds rows
        $notificationHolds = DB::table('notification_holds')
            ->where('business_id', $businessId)
            ->where('created_at', '>=', $since)
            ->get();

        foreach ($notificationHolds as $hold) {
            $holds[] = [
                'what' => $hold->caller_type,
                'why' => 'quiet_hours', // from the brief: what was held (caller type / job class), why (reason), since, until, source
                'since' => $hold->created_at,
                'until' => $hold->held_until,
                'source' => $hold->source,
            ];
        }

        // 2. AutomationRun rows whose output carries held = true
        $runs = AutomationRun::where('business_id', $businessId)
            ->where('started_at', '>=', $since)
            ->whereJsonContains('output->held', true)
            ->get();

        foreach ($runs as $run) {
            $output = $run->output ?? [];
            $holds[] = [
                'what' => $run->automation_key,
                'why' => $output['reason'] ?? 'unknown',
                'since' => \Carbon\Carbon::parse($run->started_at)->toDateTimeString(),
                'until' => $output['window'] ?? null,
                'source' => 'autopilot',
            ];
        }

        // Sort them by since DESC
        usort($holds, function ($a, $b) {
            return Carbon::parse($b['since']) <=> Carbon::parse($a['since']);
        });

        return $holds;
    }
}
