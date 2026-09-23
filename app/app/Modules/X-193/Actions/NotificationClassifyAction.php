<?php

declare(strict_types=1);

namespace App\Modules\X193\Actions;

use App\Modules\X193\Events\NotificationClassified;
use App\Modules\X193\Models\NotificationClass;
use Carbon\Carbon;
use App\Services\Config\DefaultsRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class NotificationClassifyAction
{
    /**
     * Classifies notification based on CALLER type (never reading the message body: TEST ANCHOR, P-062).
     */
    public function handle(
        int $businessId,
        string $callerType,
        ?Carbon $sendTime = null
    ): array {
        $time = $sendTime ?? Carbon::now();
        $hour = (int) $time->format('H'); // 00 - 23

        // Fetch or infer classification based STRICTLY on callerType (G10-38, P-062)
        $notifClass = NotificationClass::firstOrCreate(
            ['business_id' => $businessId, 'caller_type' => $callerType],
            [
                'classification' => match (true) {
                    str_contains($callerType, 'dunning') || str_contains($callerType, 'account') || str_contains($callerType, 'billing') => 'account',
                    str_contains($callerType, 'missed_call') || str_contains($callerType, 'chat') || str_contains($callerType, 'alert') => 'operational',
                    default => 'marketing',
                },
                'respects_quiet_hours' => match (true) {
                    str_contains($callerType, 'dunning') || str_contains($callerType, 'account') || str_contains($callerType, 'missed_call') || str_contains($callerType, 'chat') || str_contains($callerType, 'alert') => false,
                    default => true,
                },
                'quiet_hours_start' => app(\App\Services\Config\DefaultsRegistry::class)->int('notifications.quiet_hours.start'),
                'quiet_hours_end' => app(\App\Services\Config\DefaultsRegistry::class)->int('notifications.quiet_hours.end'),
            ]
        );

        $isQuietHours = false;
        if ($notifClass->quiet_hours_start > $notifClass->quiet_hours_end) {
            $isQuietHours = ($hour >= $notifClass->quiet_hours_start || $hour < $notifClass->quiet_hours_end);
        } else {
            $isQuietHours = ($hour >= $notifClass->quiet_hours_start && $hour < $notifClass->quiet_hours_end);
        }

        $classification = $notifClass->classification;
        $deliveryDecision = 'send_immediately';
        $heldUntil = null;

        // Marketing-class text during quiet hours holds until the window (TEST ANCHOR, G10-31)
        if ($notifClass->respects_quiet_hours && $isQuietHours) {
            $deliveryDecision = 'hold_until_window';
            $heldUntil = $time->copy()->hour($notifClass->quiet_hours_end)->minute(0)->second(0)->toIso8601String();

            DB::table('notification_holds')->insert([
                'business_id' => $businessId,
                'caller_type' => $callerType,
                'classification' => $classification,
                'held_until' => Carbon::parse($heldUntil)->toDateTimeString(),
                'source' => 'classify',
                'run_reference' => null,
                'created_at' => Carbon::now()->toDateTimeString(),
                'updated_at' => Carbon::now()->toDateTimeString(),
            ]);
        }

        Event::dispatch(new NotificationClassified(
            businessId: $businessId,
            callerType: $callerType,
            classification: $classification,
            deliveryDecision: $deliveryDecision
        ));

        return [
            'status' => 'classified',
            'caller_type' => $callerType,
            'classification' => $classification,
            'delivery_decision' => $deliveryDecision,
            'held_until' => $heldUntil,
            'respects_quiet_hours' => $notifClass->respects_quiet_hours,
        ];
    }
}
