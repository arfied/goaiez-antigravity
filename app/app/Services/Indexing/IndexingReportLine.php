<?php

declare(strict_types=1);

namespace App\Services\Indexing;

use App\Enums\IndexingEngine;
use App\Enums\IndexingRefusal;
use App\Enums\IndexingStatus;
use Illuminate\Support\Carbon;

/**
 * One `indexing_submissions` row, as an operator reads it.
 *
 * ⛔ **THIS TYPE EXISTS SO THAT SOMETHING READS THE TABLE, AND THAT IS NOT A
 * FORMALITY.** A row nothing reads is 272 with a green suite (1222), and slice K
 * found the same thing from the other end this week — four warehouse marts with
 * no reader anywhere in `app/`. The rows this slice writes are mostly refusals
 * naming machinery that does not exist yet, so a table nobody rendered would
 * have been a log file with row-level security on it.
 *
 * ⚠️ **STAFF-FACING AND NOT OWNER-FACING, DELIBERATELY.** Most of these
 * sentences name unbuilt parts of our own product; *"your website cannot host
 * our key file"* is a support ticket about something a business owner cannot act
 * on, and `CLAUDE.md`'s first tiebreaker is less support surface. When the
 * plugin lands and IndexNow genuinely submits, an owner-facing line is a
 * different and much shorter sentence.
 *
 * ⛔ **NEVER THE WORD "INDEXED".** `BUILD-PLAN` §2.11.4: this harness can prove
 * submission and a recorded response, never indexing — and IndexNow says the
 * same of its own 200: *"The HTTP 200 response code only indicates that the
 * search engine has received your URL."*
 */
final readonly class IndexingReportLine
{
    public function __construct(
        public IndexingEngine $engine,
        public string $url,
        public IndexingStatus $status,
        public ?IndexingRefusal $reason,
        public ?Carbon $attemptedAt,
    ) {}

    /**
     * The line an operator reads.
     *
     * ⚠️ **THE REFUSAL'S OWN SENTENCE WINS WHENEVER THERE IS ONE**, because
     * *"refused"* alone is the least useful word available: every refusal here
     * names a specific missing thing, and collapsing nine of them into one word
     * is what makes an operator open a database console.
     */
    public function staff(): string
    {
        if ($this->reason !== null) {
            return $this->reason->staff();
        }

        return match ($this->status) {
            IndexingStatus::InPlace => 'Already announced — this site\'s robots.txt names a sitemap.',
            IndexingStatus::Submitted => 'Accepted by the search engines. Accepted is not indexed.',
            IndexingStatus::Pending => 'Received; the engine is still checking the key file.',
            IndexingStatus::Rejected => 'The search engine refused this submission. See the recorded response.',
            IndexingStatus::Failed => 'The call did not complete or we were asked to slow down. It will be tried again.',
            // Unreachable by construction — `Indexing::record()` refuses a
            // refused status with no reason — and stated rather than left to a
            // match error, because a match without a total arm is a 500 on a
            // staff screen.
            IndexingStatus::Refused => 'Refused, with no reason recorded. That is a defect; see the row.',
        };
    }
}
