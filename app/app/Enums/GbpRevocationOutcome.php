<?php

declare(strict_types=1);

namespace App\Enums;

use App\Exceptions\GbpRequestFailed;
use App\Services\Gbp\GbpConnections;

/**
 * What one attempt to end an owed Google grant found out.
 *
 * Two cases and not three: {@see GbpRequestFailed::disabled()}
 * — the integration switched off mid-incident — is a genuine failure to
 * revoke rather than a third outcome, so it is {@see self::Failed} with
 * `reason` carrying `integration_disabled`, exactly as `RevokeOwedGbpGrants`
 * already treats it: 4888(c)'s own words, "the client refused to run" is not
 * "the grant is gone".
 */
enum GbpRevocationOutcome: string
{
    /**
     * Zernio accepted the request to disconnect the account.
     *
     * ⚠️ **NOT "the grant is confirmed ended"** — 4888(c) records the limit
     * plainly: every test here fakes Zernio's API, and a 200 to
     * `DELETE /v1/accounts/{id}` is *assumed* to end the grant rather than
     * verified against a real account watched at Google afterwards. This case
     * name, and every sentence rendered from it, says what this application
     * observed and nothing more.
     */
    case Revoked = 'revoked';

    /**
     * Zernio refused the request, or could not be reached at all.
     *
     * The binding this attempt was about is left exactly as it was — see
     * {@see GbpConnections}'s order-is-the-point rule — so a
     * row with this outcome always has a live obligation sitting beside it.
     */
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Revoked => 'Revoked',
            self::Failed => 'Failed',
        };
    }
}
