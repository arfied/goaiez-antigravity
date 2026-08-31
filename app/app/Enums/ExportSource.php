<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who asked for one `tenant_exports` row (`28` §3.7 vs `28` §9.5's ops tool).
 *
 * A `string` column cast to this enum, never a database enum — CLAUDE.md
 * §Critical rules and the convention test that enforces it.
 */
enum ExportSource: string
{
    /** The owner's own "Download my data" button — never gated, never delayed. */
    case Owner = 'owner';

    /**
     * Ops Console, on the tenant's behalf, through the data-request queue's
     * existing second-person approval — `28` §9.7's Fix Toolbox is not built,
     * so this is the approval surface that stands in for it (decision 1823).
     */
    case Ops = 'ops';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Requested by the owner',
            self::Ops => 'Requested by support',
        };
    }
}
