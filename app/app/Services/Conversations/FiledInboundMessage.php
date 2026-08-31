<?php

declare(strict_types=1);

namespace App\Services\Conversations;

use App\Models\Conversation;

/**
 * An inbound message that has been filed, as the writer hands it on.
 *
 * ## ⛔ WHY THE THREE TRAVEL TOGETHER
 *
 * {@see InboundThreading::thread()} used to answer with the thread alone and
 * throw away the `Message` row {@see ConversationThreads::recordInbound()}
 * had just returned. Its one caller then passed the thread and the raw webhook
 * text on as **two independent arguments**, and nothing anywhere guaranteed
 * that the second was a message on the first.
 *
 * ⛔ **THAT MATTERED BECAUSE THE MISSING PIECE WAS THE ROW'S IDENTITY** (8720).
 * `AnswerAgentTurnJob` had no way to name the message it was answering, so it
 * carried the **words** instead — a member of the public's sentence, in
 * cleartext, on a queue payload, in a table with no row-level security that no
 * erasure reaches. The row already existed, under RLS, cascade-deleted with its
 * tenant; the payload was a second copy of it in the one store that survives an
 * erasure request. What this object adds is the **id**, which is what makes the
 * second copy unnecessary.
 *
 * ⚠️ **THE BODY IS STILL CARRIED, AND IT IS NOT A CONTRADICTION.** This object
 * lives for the length of one webhook request and is never serialised: nothing
 * queues it, nothing broadcasts it and nothing stores it. Its readers — the
 * empty check and skill 9's urgent-term match — are both synchronous and both
 * inside that request. **The rule this slice is about is the queue payload, not
 * the process.**
 */
final readonly class FiledInboundMessage
{
    /**
     * @param  Conversation  $thread  The thread the message was filed on.
     * @param  int  $messageId  The `messages` row {@see ConversationThreads::recordInbound()}
     *                          wrote. ⛔ **THE WRITER'S OWN ANSWER, NEVER A
     *                          LOOK-UP.** Deriving it later as *"the latest
     *                          inbound message on this thread"* is an inference,
     *                          and `AnswerAgentTurnJob::idempotencyKey()` already
     *                          records what a derived identity costs on this
     *                          path: two callers deriving it independently
     *                          eventually derive it differently, and the day
     *                          they do the customer is answered twice or not at
     *                          all.
     * @param  string  $body  The message, trimmed — the same string that was
     *                        written to the row.
     */
    public function __construct(
        public Conversation $thread,
        public int $messageId,
        public string $body,
    ) {}
}
