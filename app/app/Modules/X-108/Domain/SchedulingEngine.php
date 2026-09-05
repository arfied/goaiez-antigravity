<?php

declare(strict_types=1);

namespace App\Modules\X108\Domain;

use App\Modules\X108\Events\AppointmentBooked;
use App\Modules\X108\Events\SlotLocked;
use App\Modules\X108\Models\Appointment;
use App\Modules\X108\Models\SlotLock;
use App\Modules\X108\Models\AvailabilityRule;
use App\Modules\X108\Models\Waitlist;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class SchedulingEngine
{
    /**
     * Request availability with strict window adherence and VIP member priority (TEST ANCHOR).
     */
    public function getAvailableSlots(int $businessId, string $date, bool $isMember = false): array
    {
        $baseDate = Carbon::parse($date)->startOfDay();

        // Standard potential slots: 09:00, 11:00, 14:00, 16:00
        $allSlotHours = [9, 11, 14, 16];

        // Check booked appointments and active slot locks
        $bookedHours = Appointment::where('business_id', $businessId)
            ->whereDate('start_time', $baseDate->toDateString())
            ->where('status', '!=', 'cancelled')
            ->pluck('start_time')
            ->map(fn ($t) => Carbon::parse($t)->hour)
            ->toArray();

        $lockedHours = SlotLock::where('business_id', $businessId)
            ->where('expires_at', '>', now())
            ->pluck('slot_start')
            ->map(fn ($t) => Carbon::parse($t)->hour)
            ->toArray();

        $unavailable = array_merge($bookedHours, $lockedHours);

        $blackouts = AvailabilityRule::where('business_id', $businessId)
            ->where('is_blackout', true)
            ->where('day_of_week', $baseDate->dayOfWeekIso)
            ->get();

        $availableSlots = [];
        foreach ($allSlotHours as $hour) {
            if (in_array($hour, $unavailable, true) || $this->isBlackedOut($hour, $blackouts)) {
                continue;
            }
            $start = $baseDate->copy()->setHour($hour)->setMinute(0);
            $end = $start->copy()->addHours(2);
            $availableSlots[] = [
                'start_time' => $start->toIso8601String(),
                'end_time' => $end->toIso8601String(),
                'formatted_window' => $start->format('g:i A').' - '.$end->format('g:i A'),
                'is_vip_reserved' => ($hour === 9), // 09:00 AM reserved for members
            ];
        }

        // VIP Member Priority Rule (TEST ANCHOR):
        // If not a member, VIP exclusive earliest slot (09:00) is held for members; non-members get 11:00 onwards
        if (! $isMember) {
            $offeredSlots = array_values(array_filter($availableSlots, fn ($s) => ! $s['is_vip_reserved']));
        } else {
            $offeredSlots = $availableSlots; // Members get all earliest available slots
        }

        return [
            'date' => $baseDate->toDateString(),
            'is_member' => $isMember,
            'offered_slots' => $offeredSlots,
            'slots_count' => count($offeredSlots),
        ];
    }

    private function isBlackedOut(int $hour, Collection $blackouts): bool
    {
        foreach ($blackouts as $rule) {
            $start = Carbon::parse($rule->start_time)->hour;
            $end = Carbon::parse($rule->end_time)->hour;

            if ($hour >= $start && $hour < $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lock slot for 10 minutes during booking.
     */
    public function lockSlot(int $businessId, string $slotStart, string $slotEnd, string $sessionId): SlotLock
    {
        $lock = SlotLock::create([
            'business_id' => $businessId,
            'slot_start' => Carbon::parse($slotStart),
            'slot_end' => Carbon::parse($slotEnd),
            'locked_for_session' => $sessionId,
            'expires_at' => now()->addMinutes(10),
        ]);

        Event::dispatch(new SlotLocked(
            businessId: $businessId,
            slotStart: $slotStart,
            slotEnd: $slotEnd,
            sessionId: $sessionId
        ));

        return $lock;
    }

    /**
     * Book appointment and generate conference link (G18-27).
     */
    public function book(
        int $businessId,
        string $serviceName,
        string $startTime,
        string $endTime,
        bool $isMember = false,
        ?int $customerId = null
    ): Appointment {
        return DB::transaction(function () use ($businessId, $serviceName, $startTime, $endTime, $isMember, $customerId) {
            $apt = Appointment::create([
                'business_id' => $businessId,
                'customer_id' => $customerId,
                'service_name' => $serviceName,
                'start_time' => Carbon::parse($startTime),
                'end_time' => Carbon::parse($endTime),
                'is_member' => $isMember,
                'status' => 'booked',
                'conference_link' => 'https://meet.goaiez.com/room-'.rand(1000, 9999),
            ]);

            Event::dispatch(new AppointmentBooked(
                businessId: $businessId,
                appointmentId: $apt->id,
                serviceName: $serviceName,
                startTime: $startTime,
                isMember: $isMember
            ));

            return $apt;
        });
    }

    /**
     * Cancel appointment and backfill from waitlist (G19-01).
     */
    public function cancel(int $businessId, int $appointmentId): array
    {
        return DB::transaction(function () use ($businessId, $appointmentId) {
            $apt = Appointment::where('business_id', $businessId)->findOrFail($appointmentId);
            $apt->update(['status' => 'cancelled']);

            // Auto-backfill from waitlist (G19-01)
            $waitlistEntry = Waitlist::where('business_id', $businessId)
                ->where('service_name', $apt->service_name)
                ->where('status', 'pending')
                ->orderBy('is_member', 'desc')
                ->orderBy('id', 'asc')
                ->first();

            $backfilled = false;
            if ($waitlistEntry) {
                $waitlistEntry->update(['status' => 'offered']);
                $backfilled = true;
            }

            return [
                'appointment_id' => $apt->id,
                'status' => 'cancelled',
                'backfill_offered' => $backfilled,
                'waitlist_id' => $waitlistEntry?->id,
            ];
        });
    }

    public function joinWaitlist(
        int $businessId,
        string $customerName,
        string $customerPhone,
        string $serviceName,
        string $preferredDate,
        bool $isMember = false
    ): Waitlist {
        return Waitlist::create([
            'business_id' => $businessId,
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'service_name' => $serviceName,
            'preferred_date' => $preferredDate,
            'is_member' => $isMember,
            'status' => 'pending',
        ]);
    }
}
