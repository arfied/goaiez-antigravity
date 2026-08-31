<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\LegalCanon;
use RuntimeException;

/**
 * Thrown when a sentence counsel owns has not been set, and something was about
 * to send without it.
 *
 * ⚠️ **IT REFUSES RATHER THAN DEGRADING, WHICH IS THE OPPOSITE OF WHAT MOST OF
 * THIS CODEBASE DOES WITH A MISSING FIGURE.** An exhausted balance degrades to
 * `null` and never throws (2904); an unset retention period deletes nothing
 * (4942). Neither of those makes a false statement to a member of the public.
 * These two do: a review invite without L-3's footer is a marketing text with no
 * stated way to stop it, and a rung without R39's guarantee is a promise
 * silently withdrawn from somebody who was told it applied.
 *
 * ⚠️ **THE MESSAGE NAMES THE KEY AND NEVER GUESSES THE WORDS.** It is read by
 * an operator, so it says what to set; it is never shown to a customer, because
 * the send it interrupts does not happen.
 *
 * @see LegalCanon
 */
final class LegalCanonUnavailable extends RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function because(string $key, string $what): self
    {
        return new self(sprintf(
            'The registry key `%s` has no value, so %s cannot be composed and nothing was sent. '
            .'These are counsel\'s words and this application will not invent them: set the row '
            .'through the Ops settings editor, or run the legal seed that supplies it. Sending '
            .'without it would make a statement to somebody else\'s customer that nobody wrote.',
            $key,
            $what,
        ));
    }
}
