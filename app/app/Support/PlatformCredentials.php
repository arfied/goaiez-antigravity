<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Config\CredentialStore;
use RuntimeException;

/**
 * The one place platform-level vendor credentials are read.
 *
 * THIS CLASS IS A SEAM, AND THE STORE BEHIND IT NOW EXISTS. Doc `38` Part 1
 * (its D-149) rules that "environment variables are bootstrap seeds only. At
 * runtime, every vendor credential resolves from the encrypted
 * `platform_credentials` store". That store landed with the Credentials Manager;
 * `App\Services\Config\CredentialStore` owns it, and this class delegates.
 *
 * ✅ **THE SEAM'S PROMISE WAS KEPT, AND IT IS WORTH RECORDING THAT IT WAS.** The
 * version of this docblock written in row 2 slice B said: "When CFG1 arrives,
 * `get()` grows a lookup against `platform_credentials` with config as the
 * fallback, and **no call site changes**. That is the entire point." Every
 * caller — the Places client, the Turnstile verifier, both AI provider clients —
 * is untouched by the slice that built the store. `BUILD-PLAN` §4.4's argument
 * for paying for a seam early ("the seam costs nothing now and is the whole cost
 * later") is the rare one that can be checked afterwards, and it held.
 *
 * The `.env` fallback is not a leftover. D-149 calls env vars bootstrap seeds,
 * and the seed keeps working for two situations that are not edge cases: a fresh
 * install where nobody has opened Ops yet, and an `APP_KEY` rotation, which
 * makes every ciphertext in the store unreadable at once.
 *
 * NOT FOR TENANT CREDENTIALS. A tenant's OAuth tokens live in the vault behind
 * TokenService, encrypted per connection and scoped to a business. This is for
 * credentials that are ours — one key, no tenant, used on paths where no tenant
 * is even resolved yet.
 */
final class PlatformCredentials
{
    /**
     * A required credential, or a failure that names what is missing.
     *
     * Throws rather than returning null on purpose. A missing vendor key is a
     * deployment fault, and the alternative — an empty string reaching an HTTP
     * client — produces a 401 from the vendor and a support ticket that blames
     * the vendor.
     *
     * @throws RuntimeException
     */
    public static function get(string $key): string
    {
        $value = self::lookup($key);

        if ($value === null || $value === '') {
            throw new RuntimeException(
                "Platform credential [{$key}] is not configured. Set it in Ops → Platform → "
                .'Credentials, which is where it belongs (doc 38 D-149); the .env seed of the '
                .'same name still works and is the bootstrap path, not the managed one.'
            );
        }

        return $value;
    }

    /**
     * Whether a credential is present, without throwing.
     *
     * ⛔ **"WITHOUT THROWING" WAS FALSE FROM THE DAY IT WAS WRITTEN UNTIL
     * 2026-08-25, IN THE SECOND CORRECTION THIS DOCBLOCK HAS NEEDED FOR THE SAME
     * REASON** (9321). The paragraph below records that the *first* sentence of
     * this docblock described a guard that did not exist; the **title line**
     * described one too. A stored credential whose ciphertext this install can
     * no longer decrypt — what a changed `APP_KEY` does to every one of them at
     * once — made `CredentialStore::resolve()` raise `DecryptException` out of
     * Laravel's `encrypted` cast, so this method threw at every caller that was
     * using it precisely in order not to explode: the marketing home, the Places
     * client, both AI clients, and the plan screen's card panel.
     * `DecryptException extends RuntimeException`, so a caller catching that
     * degraded silently and a caller expecting a `bool` did not.
     * ✅ **`CredentialStore::readable()` is the guard, and it is measured rather
     * than asserted** — `CredentialFaultBellTest` reddens with *"The payload is
     * invalid."* on a tree with the `try` removed.
     *
     * ⚠️ **THE SHAPE IS THE ONE THE PARAGRAPH BELOW ALREADY NAMES, ONE LEVEL
     * UP.** There, a true sibling claim made a false one read as considered.
     * Here the correction *itself* was the true sibling: a reader arriving at
     * this docblock met a bold, dated, self-critical paragraph about the
     * sentence underneath and had no reason to doubt the line above it.
     *
     * ⛔ **THIS DOCBLOCK DESCRIBED A GUARD THAT DID NOT EXIST, FROM THE DAY IT
     * WAS WRITTEN UNTIL 2026-08-24 (9144).** It read *"the Places client uses
     * this to degrade rather than explode: a public visitor on the marketing
     * home must never see a stack trace because our key is missing"*, and
     * `has('google_places_key')` had **zero call sites in the tree** —
     * `GooglePlacesClient` read the key with `get()` and nothing caught the
     * `RuntimeException`. `CredentialManifest`'s entry for the same key said the
     * same thing, in a sentence the Ops board renders to an operator *precisely
     * when the key is absent*. The key was unset in the production registry, so
     * every visitor who typed a business name on the marketing home met a 500.
     *
     * ✅ **THE SENTENCE IS TRUE NOW AND IS KEPT RATHER THAN DELETED**
     * (`CLAUDE.md`'s 4368 rule): `GooglePlacesClient::isConfigured()` asks this,
     * and so does `ZernioGbpClient::assertUsable()`. What is added is the reason
     * the old sentence survived so long — **there was a true sibling claim**.
     * `Account\Plan::cardPanel()` explains at length why the Accept.js keys are
     * read with `has()` rather than `get()`, and it is correct, and it is what
     * made the false one read as considered.
     *
     * ⚠️ **WHICH READERS GUARD IS NOT A FACT ABOUT THIS FILE AND MUST NOT BE
     * RESTATED HERE.** It is derived on every run by
     * `tests/Feature/Architecture/CredentialsTest.php`'s census — *"every
     * platform credential read is enumerated, with whether it is checked before
     * it is used"* — which fails the build when a reader appears, moves or stops
     * guarding. A list in this paragraph would be an inventory, and an inventory
     * of somebody else's files is the thing that went stale here.
     */
    public static function has(string $key): bool
    {
        $value = self::lookup($key);

        return $value !== null && $value !== '';
    }

    /**
     * The encrypted store, then `.env`.
     *
     * Resolved from the container rather than constructed, because
     * {@see CredentialStore} is bound as a singleton for its per-process memo —
     * a fresh instance here would query on every call and would never see a
     * rotation performed elsewhere in the same request.
     */
    private static function lookup(string $key): ?string
    {
        return app(CredentialStore::class)->resolve($key);
    }
}
