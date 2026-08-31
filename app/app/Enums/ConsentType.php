<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The strength of a consent record (DATA-MODEL §5.6).
 *
 * ExpressWritten is what the TCPA requires before a marketing text. Express
 * covers transactional contact. The distinction is not decorative — it decides
 * which messages a record can authorise, and row 4's composer will read it.
 */
enum ConsentType: string
{
    case Express = 'express';
    case ExpressWritten = 'express_written';

    /**
     * ⚠️ **NOT CONSENT FROM THE PERSON. A TENANT'S CLAIM ABOUT THEM.**
     *
     * Added for decision 549, which approved reactivation messaging to a
     * tenant's uploaded customer list on the basis that the business has an
     * existing relationship with those people. **The person on that list gave us
     * nothing**; the tenant attested on their behalf, and
     * `CustomerImport` holds the attestation.
     *
     * It is a third case rather than a reuse of `ExpressWritten`, and that is
     * the load-bearing part. `ExpressWritten` means the person themselves gave
     * prior express written consent — the thing the TCPA actually requires
     * before a marketing text — and writing it here would put a **false record**
     * into the one table whose entire job is to be true. A compliance export
     * claiming express written consent from somebody who never gave it is worse
     * in a dispute than one that says plainly what happened, because the first
     * is a misstatement we authored and the second is a position we can defend
     * or concede on its own terms.
     *
     * So the honest label also protects the owner's position rather than
     * undermining it: it keeps the claim exactly as strong as it really is, and
     * lets row 4's composer treat it differently on purpose rather than by
     * accident.
     */
    case TenantAttested = 'tenant_attested';
}
