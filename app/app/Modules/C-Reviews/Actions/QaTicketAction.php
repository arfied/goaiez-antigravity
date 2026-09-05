<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Actions;

use App\Modules\CReviews\Events\CsatRequested;
use App\Modules\CReviews\Models\QaSetting;
use App\Modules\CReviews\Models\ReviewRequest;
use App\Modules\X181\Models\QaTicket;
use Illuminate\Support\Facades\DB;

final class QaTicketAction
{
    public function handle(int $businessId, int $reviewRequestId): array
    {
        return DB::transaction(function () use ($businessId, $reviewRequestId) {
            $req = ReviewRequest::where('business_id', $businessId)->findOrFail($reviewRequestId);
            $req->update(['status' => 'triaged_internal']);

            $setting = QaSetting::where('business_id', $businessId)->first();
            $slaHours = $setting ? ((int) $setting->sla_hours) : 48;

            $ticket = QaTicket::create([
                'business_id' => $businessId,
                'person_id' => $req->customer_id,
                'review_request_id' => $req->id,
                'subject' => 'Low review rating triage',
                'description' => 'Review rating was '.$req->rating.' stars.',
                'status' => 'open',
                'arrived_at' => now(),
                'sla_due_at' => now()->addHours((int) $slaHours),
            ]);

            return [
                'review_request_id' => $req->id,
                'ticket_status' => 'open_sla_24h',
                'rating' => $req->rating,
            ];
        });
    }

    public function resolve(int $businessId, int $ticketId): void
    {
        $ticket = QaTicket::where('business_id', $businessId)->findOrFail($ticketId);

        if ($ticket->status !== 'resolved') {
            $ticket->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'csat_requested_at' => now(),
            ]);

            CsatRequested::dispatch(
                $businessId,
                $ticket->id,
                $ticket->person_id
            );
        }
    }

    public function receiveCsat(int $businessId, int $ticketId, int $score): void
    {
        $ticket = QaTicket::where('business_id', $businessId)->findOrFail($ticketId);

        $updates = ['csat_score' => $score];

        // TEST ANCHOR: receiving a CSAT of 1 on a resolved ticket sets it back to open and marks reopened_at
        if ($score === 1 && $ticket->status === 'resolved') {
            $updates['status'] = 'open';
            $updates['reopened_at'] = now();
        }

        $ticket->update($updates);
    }
}
