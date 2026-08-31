<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What the daily self-audit found (`16` §15.3).
 *
 * ⛔ **TWO OF THE DOCUMENT'S FIVE LINES, AND THE OTHER THREE ARE NAMED IN
 * `ContentSelfAudit`'s DOCBLOCK RATHER THAN DECLARED HERE AS DEAD CASES.** An
 * enum case nothing constructs is anticipated vocabulary — `OutreachChannel::Sms`
 * shipped that way and `AutopilotActionType::ContentPublished` sat uncalled from
 * Stage 0 until this slice. A case that can never be filed reads to the next
 * reader as a check that runs.
 */
enum ContentAuditFinding: string
{
    /**
     * A published page with no Search Console impressions in its window.
     *
     * ⚠️ **THE WINDOW IS NINETY DAYS AND THAT IS THE DOCUMENT'S FIGURE** — `16`
     * §15.3: *"Flag pages with zero impressions after 90 days → prune or
     * merge"*. A page published last week has no impressions because Google has
     * not finished with it, and reporting that as a finding is 229's false
     * statement.
     */
    case ZeroImpressions = 'zero_impressions';

    /**
     * Two or more published pages on one location that are near-duplicates of
     * each other — `16` §15.1's city×service matrix, found after the fact.
     *
     * ⚠️ **THE GATE ALREADY REFUSES THIS ONE PAGE AT A TIME** (5567). This is
     * the corpus-level version: pages that each cleared the gate against the
     * corpus as it stood, and now sit close to each other.
     */
    case NearDuplicateCluster = 'near_duplicate_cluster';
}
