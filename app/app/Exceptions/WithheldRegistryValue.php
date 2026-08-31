<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when code asks the Defaults Registry for a number the owner has not
 * set.
 *
 * ⚠️ **THIS IS A FEATURE, AND IT IS THE WHOLE REASON THE MANIFEST NAMES ITS
 * GAPS.** `CLAUDE.md`: *"One number is still unset and may not be guessed …
 * It fails closed"*. ⚠️ **That sentence read *"two numbers"* until the AI credit
 * grant was answered at 3412, and this docblock went on quoting the old count.**
 * The
 * conservative default for a *price* is not a smaller number — it is refusing to
 * quote one. A registry that answered "0" would silently make the Limited tier
 * free, and a registry that answered "$25" would silently make decision 153's
 * rejected proportion into policy.
 *
 * The message names the decision that left the value open, so whoever hits this
 * can go and read it rather than reaching for a plausible figure.
 *
 * ## If you are here because this threw
 *
 * The fix is not a fallback. It is either the owner setting the number — after
 * which it moves from `DefaultsManifest::withheld()` into the seeds — or the
 * calling feature not shipping until they do.
 */
final class WithheldRegistryValue extends RuntimeException
{
    public static function for(string $path, string $reason): self
    {
        return new self(
            "The registry value `{$path}` is deliberately unset and may not be guessed. {$reason}"
        );
    }
}
