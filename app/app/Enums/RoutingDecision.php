<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What routing decided about one first-party review (`17` FPR-03).
 *
 * FOUR CASES BECAUSE TWO OF THEM CO-OCCUR. Invitation and triage are separate
 * questions asked of the same rating, and the thresholds that answer them run
 * in opposite directions and come from different tables. A 3-star customer at a
 * business with Trustpilot enabled is above Trustpilot's forced 0 and at or
 * below the default triage threshold of 3 — so they are invited *and* triaged,
 * and `17` FPR-04b names exactly that pair as an acceptance criterion.
 *
 * ⚠️ IT IS RARER THAN THIS DOCBLOCK SAID UNTIL 2026-08-12, AND THE CORRECTION IS
 * WORTH KEEPING. It claimed InvitedAndTriaged was what a 1-star produced,
 * because `autopilot_settings.gating_ack_at` was null for every tenant and no
 * invite threshold could be applied at all. Decisions 2074 and 2660 removed the
 * acknowledgement and the column, so a 1-star at a location on the seeded
 * threshold of 4 is now Triaged and nothing else. The pair still co-occurs
 * wherever the two thresholds overlap — a 3-star with Trustpilot enabled is
 * above its forced 0 and at or below the default triage threshold of 3 — which
 * is the case `17` FPR-04b names as an acceptance criterion.
 *
 * NoAction IS THE DEFAULT STATE, NOT AN ERROR. TenantProvisioner seeds all three
 * destinations disabled (decision 312), so a fresh tenant's 5-star review is
 * routed nowhere until somebody enables one — and Google cannot be enabled until
 * a place id is confirmed. A reader who treats this case as a bug will go
 * looking for a fault that is not there.
 *
 * A PHP ENUM OVER A `string` COLUMN, never a database enum: this vocabulary is
 * exactly the kind that churns, and Postgres enum values can never be dropped or
 * reordered once added.
 */
enum RoutingDecision: string
{
    case Invited = 'invited';
    case Triaged = 'triaged';
    case InvitedAndTriaged = 'invited_and_triaged';
    case NoAction = 'no_action';

    /**
     * Whether at least one destination was offered.
     */
    public function invited(): bool
    {
        return $this === self::Invited || $this === self::InvitedAndTriaged;
    }

    /**
     * Whether a recovery conversation was opened.
     */
    public function triaged(): bool
    {
        return $this === self::Triaged || $this === self::InvitedAndTriaged;
    }
}
