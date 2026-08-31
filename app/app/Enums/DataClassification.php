<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a business's data must be handled.
 *
 * Drives the hardest boundary in the system after tenancy: a Phi business routes
 * to a separate schema, a separate role, and a separate KMS key, its form values
 * stay `schema_only` until a BAA is executed, and its data never reaches L3
 * (`29` §2 rule 24).
 *
 * A `string` column cast to this enum, never a database enum — see
 * CLAUDE.md §Critical rules and the convention test that enforces it.
 */
enum DataClassification: string
{
    /**
     * The default. Ordinary personal information.
     */
    case Pii = 'pii';

    /**
     * Protected health information. Everything above applies, and the collector
     * drops form values server-side until the BAA exists.
     */
    case Phi = 'phi';

    /**
     * No personal information at all. Reserved for internal and demo tenants;
     * never assumed for a real business.
     */
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Pii => 'Standard',
            self::Phi => 'Health information',
            self::None => 'No personal data',
        };
    }

    /**
     * Whether this classification requires the PHI handling path.
     */
    public function requiresPhiIsolation(): bool
    {
        return $this === self::Phi;
    }
}
