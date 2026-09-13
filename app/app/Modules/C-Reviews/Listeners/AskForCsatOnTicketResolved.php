<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Listeners;

use App\Modules\CReviews\Events\CsatRequested;
use App\Modules\CSms\Events\SendRequested;
use App\Modules\X121\Actions\EntityReadAction;
use App\Modules\X181\Actions\QaMarketingSuppressionCheckAction;
use App\Modules\X181\Actions\QaTicketReadAction;
use App\Modules\X181\Events\TicketResolved;
use Illuminate\Support\Facades\Event;

/**
 * G20-05 · CSAT on resolve (R245).
 *
 * Plan :32254 guards it: "suppressed on any conversation with an open escalation".
 * In this lane an escalation is an open qa_ticket, so another open ticket for the
 * same person suppresses the ask (P-205, X-181's registered check).
 *
 * The stamp is written last and records that a send was REQUESTED. Delivery is
 * C-Sms's, and this listener cannot observe it.
 */
final class AskForCsatOnTicketResolved
{
    private const MESSAGE_CLASS = 'marketing';

    private const BODY = 'Your issue is resolved. How did we do? Reply 1 (poor) to 5 (great).';

    public function handle(TicketResolved $event): void
    {
        if ($event->personId === null) {
            return;
        }

        if (app(QaMarketingSuppressionCheckAction::class)->isGrowSuppressed($event->businessId, $event->personId)) {
            return;
        }

        $person = app(EntityReadAction::class)->handle('people', $event->personId, $event->businessId);
        if ($person === null || empty($person['phone'])) {
            return;
        }

        Event::dispatch(new SendRequested(
            businessId: $event->businessId,
            compositionId: $event->ticketId,
            recipientPhone: $person['phone'],
            messageClass: self::MESSAGE_CLASS,
            body: self::BODY,
            segmentsCount: 1
        ));

        CsatRequested::dispatch($event->businessId, $event->ticketId, $event->personId);

        app(QaTicketReadAction::class)
            ->findById($event->businessId, $event->ticketId)
            ->update(['csat_requested_at' => now()]);
    }
}
