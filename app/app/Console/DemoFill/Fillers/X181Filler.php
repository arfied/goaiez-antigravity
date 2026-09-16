<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\CReviews\Models\ReviewRequest;
use App\Modules\X181\Models\QaTicket;
use App\Modules\CReviews\Models\CsatAnswer;
use App\Modules\X121\Models\Person;

class X181Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-181';
    }

    public function fill(Business $business): int
    {
        if (QaTicket::where('business_id', $business->id)->where('subject', 'like', self::MARKER . '%')->exists()) {
            return 0;
        }

        $person = Person::firstOrCreate([
            'business_id' => $business->id,
            'first_name' => self::MARKER . 'Dana',
        ], [
            'last_name' => 'Customer',
            'phone' => '+15125554601',
        ]);

        $twoStarReq = ReviewRequest::where('business_id', $business->id)
            ->where('review_text', 'like', self::MARKER . '%')
            ->where('rating', 2)
            ->first();

        $t1 = QaTicket::create([
            'business_id' => $business->id,
            'person_id' => $person->id,
            'subject' => self::MARKER . 'Tech arrived late',
            'status' => 'open',
            'arrived_at' => now(),
            'sla_due_at' => now()->addHours(4),
            'review_request_id' => $twoStarReq?->id,
        ]);

        $t2 = QaTicket::create([
            'business_id' => $business->id,
            'person_id' => $person->id,
            'subject' => self::MARKER . 'Invoice higher than the estimate',
            'status' => 'open',
            'arrived_at' => now()->subHours(5),
            'sla_due_at' => now()->subHours(3),
        ]);

        $t3 = QaTicket::create([
            'business_id' => $business->id,
            'person_id' => $person->id,
            'subject' => self::MARKER . 'Callback never came',
            'status' => 'resolved',
            'arrived_at' => now()->subDays(3),
            'sla_due_at' => now()->subDays(2),
            'resolved_at' => now()->subDays(2),
            'resolution_notes' => self::MARKER . 'Called back, credited the callout fee',
            'csat_requested_at' => now()->subDays(2),
        ]);

        CsatAnswer::create([
            'business_id' => $business->id,
            'person_id' => $person->id,
            'qa_ticket_id' => $t3->id,
            'score' => 4,
            'body' => '4',
            'is_valid' => true,
            'received_at' => now()->subDay(),
        ]);

        return 3;
    }

    public function purge(Business $business): int
    {
        return QaTicket::where('business_id', $business->id)
            ->where('subject', 'like', self::MARKER . '%')
            ->delete();
    }
}
