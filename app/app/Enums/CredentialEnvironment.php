<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which side of a vendor's account a credential opens (`38` Part 1's
 * "environment badge (live/test)").
 *
 * ⚠️ IT IS DECLARED BY THE OPERATOR AND CANNOT BE DERIVED, WHICH IS THE WHOLE
 * REASON IT IS A COLUMN. Some vendors prefix their test keys visibly — Stripe's
 * `sk_test_` is the famous one — and most do not: a Google Places key, an
 * Infobip key and an Anthropic key look identical whichever project they came
 * from. So there is nothing to infer from, and inferring from
 * `app()->environment()` would answer a different question entirely (where the
 * *code* is running, not which account the *key* opens).
 *
 * What it prevents is a test key sitting in production looking exactly like a
 * live one. That failure does not present as a credential problem: the vendor
 * accepts the call, does nothing real with it, and the missing outcome gets
 * blamed on our side for as long as it takes somebody to check.
 *
 * A `string` column cast to this enum, never a database enum (CLAUDE.md).
 */
enum CredentialEnvironment: string
{
    /** Real money, real messages, real customers. */
    case Live = 'live';

    /**
     * A vendor's sandbox. Also the correct value for the published dummy
     * credentials — Cloudflare's always-pass Turnstile pair is documented in
     * `config/credentials.php` and is a test credential in every sense.
     */
    case Test = 'test';

    /**
     * How this reads on the Ops screen.
     *
     * Outcome language (`22`): what the key opens, not what the column is called.
     */
    public function label(): string
    {
        return match ($this) {
            self::Live => 'Live account',
            self::Test => 'Test account',
        };
    }

    /**
     * Whether this environment is worth flagging on the credentials board.
     *
     * ⚠️ COLOUR IS NEVER THE SOLE INDICATOR (`22`), so this returns a predicate
     * the template pairs with the label above rather than a colour name. A test
     * key in production is worth noticing; it is not an error, because a test
     * key is exactly right on a staging box.
     */
    public function isNoteworthyInProduction(): bool
    {
        return $this === self::Test;
    }
}
