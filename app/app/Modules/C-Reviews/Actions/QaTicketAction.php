<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Actions;

use App\Modules\CReviews\Models\QaSetting;
use App\Modules\CReviews\Models\ReviewRequest;
use App\Modules\X181\Actions\QaTicketCreateAction;
use App\Modules\X181\Actions\QaTicketReadAction;
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

            $ticket = app(QaTicketCreateAction::class)->handle(
                $businessId,
                $req->customer_id,
                'Low review rating triage',
                'Review rating was '.$req->rating.' stars.',
                $req->id,
                (int) $slaHours
            );

            return [
                'review_request_id' => $req->id,
                'ticket_status' => 'open_sla_24h',
                'rating' => $req->rating,
            ];
        });
    }

    public function receiveCsat(int $businessId, int $ticketId, int $score): void
    {
        $ticket = app(QaTicketReadAction::class)->findById($businessId, $ticketId);

        // TEST ANCHOR: receiving a CSAT of 1 on a resolved ticket sets it back to open and marks reopened_at
        if ($score === 1 && $ticket->status === 'resolved') {
            $ticket->update([
                'status' => 'open',
                'reopened_at' => now(),
            ]);
        }
    }
}
