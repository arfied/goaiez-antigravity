<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ActuationTier;
use RuntimeException;

/**
 * Something asked to act through a rung of the actuation ladder that has no
 * implementation — decision 219's rule, transplanted.
 *
 * ⛔ **THE ALTERNATIVE IS A SILENT DOWNGRADE AND IT IS WORSE THAN A CRASH.**
 * Falling back to T4 advisory would make *"this site needs a tier we have not
 * built"* indistinguishable from *"there was nothing to do here"*, and `41`
 * Part 1 turns that difference into two different things the owner is told: the
 * first is an upgrade offer, the second is silence. A tenant on a T0 or T2 site
 * would sit quietly receiving nothing while every screen reported a healthy
 * system — which is the shape of every writerless control `CLAUDE.md` opens
 * with.
 *
 * ⚠️ **NOT A FEATURE FLAG AND NEVER CAUGHT TO PRODUCE A FALLBACK**, on
 * {@see UnbuiltPatch}'s terms. A caught "unbuilt tier" is the downgrade this
 * type exists to prevent, written one indirection away.
 */
final class UnbuiltActuationTier extends RuntimeException
{
    public static function for(ActuationTier $tier): self
    {
        return new self(sprintf(
            'Actuation tier [%s] has no implementation in this build, so nothing can act through it. '
            .'Only t1_plugin, t3_pixel and t4_advisory are reachable today. Do not catch this to fall '
            .'back to advisory: a silent downgrade makes "needs a stronger tier" and "nothing to do" '
            .'the same outcome (decision 219).',
            $tier->value,
        ));
    }
}
