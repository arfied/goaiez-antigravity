<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where one `tenant_exports` row sits in its build (`28` §3.7).
 *
 * A `string` column cast to this enum, never a database enum — CLAUDE.md
 * §Critical rules and the convention test that enforces it.
 */
enum ExportStatus: string
{
    /** Row created, job dispatched, nothing written to storage yet. */
    case Queued = 'queued';

    /** The job is assembling the ZIP right now. */
    case Building = 'building';

    /** Built, uploaded, `expires_at` set. The only status a download reads. */
    case Ready = 'ready';

    /** Every retry was exhausted. `failure_reason` names why. */
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Waiting to start',
            self::Building => 'Building your download',
            self::Ready => 'Ready to download',
            self::Failed => 'Could not be built',
        };
    }
}
