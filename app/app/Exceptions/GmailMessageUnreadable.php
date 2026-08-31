<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Jobs\PollSupportMailboxJob;
use App\Services\Mail\GmailInbox;
use App\Services\Support\SupportMailbox;
use RuntimeException;

/**
 * Gmail refused to hand over one message that its own history record named.
 *
 * ⛔ **THE POINT OF THIS TYPE IS THAT THE ALTERNATIVE WAS `null`, AND A `null`
 * HERE MEANT *"NOTHING TO RECORD"*** (9480–9499). {@see SupportMailbox::fetch()}
 * and {@see GmailInbox::recipientsOf()} each answered a vendor failure with the
 * same empty value they answer three ordinary conditions with — a message
 * deleted since the history record, a sender we cannot parse, a message with no
 * plain-text part — and the callers went on to **advance the bookmark past it**.
 * A `users.messages.get` that 429s or 503s therefore destroyed the message
 * permanently, silently, and with no row, log line or counter anywhere: on the
 * support path, a run in which every read failed logged **nothing at all**,
 * because `PollSupportMailboxJob::report()` returns early when its two counts
 * are zero and a swallowed message incremented neither.
 *
 * ⚠️ **A 404 IS DELIBERATELY NOT THIS.** Both classes' docblocks argue the
 * `null` for the case where *"the message may have been deleted between the
 * history record and this call, which is ordinary rather than hostile"* — that
 * argument is correct and is untouched. What was wrong was its **population**:
 * the code tested `failed()`, which is every status from 400 up. The swallow is
 * now narrowed to the case the paragraph was written about, and 404-is-special
 * is already this vendor's own idiom here — {@see GmailHistoryUnavailable} is
 * built on the same status meaning something specific.
 *
 * ⛔ **THE CALLERS CATCH IT PER MESSAGE AND HOLD THE BOOKMARK.** Not letting it
 * out of the loop is deliberate: one message nobody can read must not cost the
 * message beside it in the same batch, which is what both classes already
 * promise. Not advancing the bookmark is what makes the loss recoverable — the
 * next run re-reads the range, and on the support path
 * `support_messages (business_id, external_ref)` absorbs the repeats, which is
 * the property {@see PollSupportMailboxJob}'s own docblock already claimed and
 * did not have on this arm.
 */
final class GmailMessageUnreadable extends RuntimeException
{
    private function __construct(string $message, public readonly int $status)
    {
        parent::__construct($message);
    }

    /**
     * ⚠️ **NO BODY IN THE MESSAGE.** A Gmail error body can quote the request,
     * and this string reaches `failed_jobs` and the log — `GmailApiClient` and
     * both history reads refuse the same thing for the same reason.
     */
    public static function status(int $status): self
    {
        return new self(
            'Gmail refused a message read with HTTP '.$status.'. The mailbox bookmark is held '
            .'where it is so the next run re-reads this range; advancing past a message we '
            .'never read would lose it permanently.',
            $status,
        );
    }
}
