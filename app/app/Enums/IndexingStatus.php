<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What happened to one indexing attempt — `indexing_submissions.status`.
 *
 * ⚠️ **SIX CASES BECAUSE THEY ARE SIX DIFFERENT SENTENCES**, and three of the
 * distinctions are load-bearing rather than tidy:
 *
 * - **{@see self::Refused} is us and {@see self::Rejected} is them.** Collapsing
 *   the two would make *"we chose not to send this"* and *"a search engine said
 *   no"* the same row, and today almost every row is the first — so the
 *   collapsed version would read as a platform of rejections.
 * - **{@see self::Rejected} is permanent and {@see self::Failed} is not.** A 403
 *   means the key file is wrong and retrying changes nothing; a 429 or a
 *   timeout is the queue's to absorb.
 * - **{@see self::InPlace} is not {@see self::Submitted}.** A `Sitemap:` line
 *   already in a site's `robots.txt` is a submission route that is *working* and
 *   that this application did not create. Recording it as `submitted` would
 *   claim an act we did not perform, on the one path where WordPress core does
 *   the work for us (see `App\Services\Indexing\SitemapAnnouncement`).
 */
enum IndexingStatus: string
{
    /**
     * The submission route is already in place and nothing needed doing.
     * `submitted_at` stays null — there was no request.
     */
    case InPlace = 'in_place';

    /**
     * The engine accepted the request. IndexNow's HTTP 200: *"URL submitted
     * successfully"*.
     *
     * ⛔ **ACCEPTED IS NOT INDEXED**, and IndexNow's own documentation says so in
     * the same breath: *"The HTTP 200 response code only indicates that the
     * search engine has received your URL."* Nothing in this application may
     * report that a page was indexed (`BUILD-PLAN` §2.11.4's *"two claims this
     * harness cannot make"*).
     */
    case Submitted = 'submitted';

    /**
     * IndexNow's HTTP 202: *"URL received. IndexNow key validation pending."*
     * The FAQ says to expect it on a first request.
     */
    case Pending = 'pending';

    /**
     * This application declined to send. `reason` says why and is never null on
     * this arm.
     */
    case Refused = 'refused';

    /**
     * The engine said no, and saying it again will not help — IndexNow's 400,
     * 403 and 422.
     */
    case Rejected = 'rejected';

    /**
     * The call did not complete, or the engine asked us to slow down — a 429, a
     * 5xx or a connection failure. Retryable.
     */
    case Failed = 'failed';

    /**
     * Whether a request actually reached an engine and was taken.
     */
    public function wasAccepted(): bool
    {
        return match ($this) {
            self::Submitted, self::Pending => true,
            self::InPlace, self::Refused, self::Rejected, self::Failed => false,
        };
    }
}
