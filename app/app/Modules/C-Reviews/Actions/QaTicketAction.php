<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Actions;

use App\Modules\CReviews\Events\CsatRequested;
use App\Modules\CReviews\Models\QaSetting;
use App\Modules\CReviews\Models\ReviewRequest;
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

            $ticket = app(\App\Modules\X181\Actions\QaTicketCreateAction::class)->handle(
                businessId: $businessId,
                personId: $req->customer_id,
                subject: 'Low review rating triage',
                description: 'Review rating was '.$req->rating.' stars.',
                reviewRequestId: $req->id,
                slaHours: (int) $slaHours
            );

            return [
                'review_request_id' => $req->id,
                'ticket_status' => 'open_sla_24h',
                'rating' => $req->rating,
            ];
        });
    }

    public function resolve(int $businessId, int $ticketId): void
    {
        $ticket = DB::table('qa_tickets')->where('business_id', $businessId)->where('id', $ticketId)->first();
        if (!$ticket) throw new \Exception("Not found");

        if ($ticket->status !== 'resolved') {
            app(\App\Modules\X181\Actions\QaTicketResolveAction::class)->handle($businessId, $ticketId, 'Resolved');

            CsatRequested::dispatch(
                $businessId,
                $ticket->id,
                $ticket->person_id
            );
        }
    }

    public function receiveCsat(int $businessId, int $ticketId, int $score): void
    {
        $ticket = DB::table('qa_tickets')->where('business_id', $businessId)->where('id', $ticketId)->first();
        if (!$ticket) throw new \Exception("Not found");

        // TEST ANCHOR: receiving a CSAT of 1 on a resolved ticket sets it back to open and marks reopened_at
        if ($score === 1 && $ticket->status === 'resolved') {
            app(\App\Modules\X181\Actions\QaTicketReopenAction::class)->handle($businessId, $ticketId, 'Reopened due to low CSAT');
        }
    }
}
