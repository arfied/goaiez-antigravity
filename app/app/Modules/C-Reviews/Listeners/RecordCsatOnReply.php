<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Listeners;

use App\Modules\CReviews\Models\CsatAnswer;
use App\Modules\CSms\Events\MessageReceived;
use App\Modules\CReviews\Actions\QaTicketAction;
use App\Modules\X181\Actions\QaTicketReadAction;

final class RecordCsatOnReply
{
    public function handle(MessageReceived $event): void
    {
        if ($event->personId === null) {
            return;
        }

        $body = trim($event->body ?? '');
        
        if (!in_array($body, ['1', '2', '3', '4', '5'], true)) {
            return;
        }

        $ticket = app(QaTicketReadAction::class)->latestAwaitingCsatForPerson($event->businessId, $event->personId);

        if ($ticket === null) {
            return;
        }

        $score = (int) $body;

        CsatAnswer::query()->create([
            'qa_ticket_id' => $ticket->id,
            'score' => $score,
            'body' => $body,
            'is_valid' => true,
            'received_at' => $event->receivedAt,
        ]);

        app(QaTicketAction::class)->receiveCsat($event->businessId, $ticket->id, $score);
    }
}
