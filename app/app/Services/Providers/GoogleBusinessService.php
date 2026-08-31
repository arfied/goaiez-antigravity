<?php

declare(strict_types=1);

namespace App\Services\Providers;

use App\Enums\ConnectionStatus;
use App\Enums\OauthProvider;
use App\Exceptions\ProviderNotConnected;
use App\Exceptions\ProviderRequestFailed;
use App\Models\Business;
use App\Models\ProviderHealth;

/**
 * Google Business Profile, through the vault.
 *
 * READ THIS BEFORE BUILDING ANYTHING ON TOP OF IT. Google Business Profile API
 * access is an application with a lead time, not a switch. A new Cloud project
 * sits at **0 QPM** until that application is approved, the business profile
 * itself must have been verified for 60+ days, and there is no sandbox
 * (https://developers.google.com/my-business/content/basic-setup, last updated
 * 2025-08-28: "The Google My Business API is only visible in the Google API
 * Console to users who submit and receive approval").
 *
 * So the normal state of this class, for a long time, is *unavailable*. That is
 * not a failure mode to handle later — it is the design centre: an automation
 * that reaches Google Business Profile has to implement handoff() as its real
 * path (`29` §2 rule 44).
 *
 * ⛔ **THE CLAUSE THAT FOLLOWED — "`available()` BELOW IS WHAT THOSE AUTOMATIONS
 * GATE ON" — WAS FALSE, AND HAD ALREADY BEEN CORRECTED SIXTY LINES BELOW IN
 * THIS SAME FILE WITHOUT BEING CORRECTED HERE. BOTH READINGS KEPT AND DATED,
 * 2026-08-24** (4368). `available()`'s own docblock has said since 2026-08-21
 * that no automation asks it (7262); this one went on saying the opposite, and
 * **the class docblock is the one a reader meets first**. That is 314–316 with
 * the claim and its correction inside a single file, which is the form that
 * costs the most: a reader who reaches the method has already been told what it
 * is for.
 *
 * ⛔ **AND IT IS WIDER THAN `available()`, WHICH NOTHING HAD RECORDED: NOTHING
 * IN `app/` CALLS THIS CLASS AT ALL.** Enumerated untruncated 2026-08-24 —
 * `grep -rn "GoogleBusinessService" app/` answers five lines, and every one is
 * this declaration, an import in `Exceptions\ProviderRequestFailed` reached only
 * from an `@see`, or prose in `Contracts\GbpClient`. So `accounts()` has no
 * caller either, and *"every automation that would call this"* has no referent
 * in this application. **Reviews are read through `Contracts\GbpClient` and
 * Zernio, permanently and by ruling** (decisions 530, 546–548), so this class is
 * the destination rather than the current path — which is the true reason the
 * handoff sentence above is load-bearing, and a better one than the false
 * clause it replaces.
 *
 * ⚠️ **NOTHING FAILS THE BUILD ON THAT ABSENCE, AND THAT IS A DECISION RATHER
 * THAN A GAP — BUT DO NOT READ IT AS WIDER THAN IT IS.**
 * `tests/Feature/Architecture/VisibilityTest.php` holds a chokepoint list of
 * every file in `app/` that names `provider_health` or its model, against what
 * each does to it, and it refuses to assert the absence for a stated reason: a
 * tripwire that fails when the defect is **fixed** is the shape `PixelTest`'s
 * delivery tripwire was replaced for. ⛔ **What that list cannot see is a caller
 * of this class**, because a caller need not name `provider_health` at all —
 * so a job in `app/Jobs/Gbp/` calling `available()` tomorrow reddens nothing
 * anywhere and leaves this paragraph false again. **The claim above is dated
 * and carries its command for that reason**: it is a reading, not a guarantee,
 * and the way to check it is to run the grep rather than to trust this line.
 *
 * `place_id` deliberately does not come from here. It comes from the Places API,
 * which needs none of the above, and that is what makes the entire no-GBP-access
 * path possible.
 *
 * NOT BUILT, DELIBERATELY: Google Q&A management and Business Messages are
 * retired. The basic-setup page still lists "My Business Q&A API" among the
 * services to enable, which is stale — nothing here calls it.
 */
final class GoogleBusinessService extends ProviderClient
{
    protected function provider(): OauthProvider
    {
        return OauthProvider::Google;
    }

    /**
     * The Google accounts this business's connection can act for.
     *
     * GET https://mybusinessaccountmanagement.googleapis.com/v1/accounts
     * (last updated 2024-10-16). pageSize default and maximum is 20 — the
     * maximum is 20, not merely the default, so paging is mandatory rather than
     * an optimisation.
     *
     * Failure modes, each handled rather than assumed away:
     *   - no connection or a dead one   ProviderNotConnected -> handoff()
     *   - 0 QPM / 429                   ProviderRequestFailed with quota=true
     *   - 5xx or unreachable            ProviderRequestFailed, retryable
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws ProviderNotConnected
     * @throws ProviderRequestFailed
     */
    public function accounts(Business $business, ?string $pageToken = null): array
    {
        $url = rtrim((string) config('oauth.providers.google.account_management_endpoint'), '/')
            .'/accounts';

        $response = $this->send(
            $business,
            'GET',
            $url,
            fn () => $this->authorized($business)->get($url, array_filter([
                'pageSize' => 20,
                'pageToken' => $pageToken,
            ], static fn (mixed $v): bool => $v !== null)),
        );

        $accounts = $response->json('accounts');

        return is_array($accounts) ? $accounts : [];
    }

    /**
     * Whether the full-capability path is open right now.
     *
     * ⛔ **"THE QUESTION EVERY GBP AUTOMATION ASKS BEFORE CHOOSING `execute()`
     * OVER `handoff()`" IS WHAT THIS SAID UNTIL 2026-08-21 AND NO AUTOMATION
     * ASKS IT** (7262). Enumerated untruncated: the only `->available(` in
     * `app/` is `SearchConsoleProperties::available()`, a different class's
     * different method, and this one's four call sites are all in
     * `tests/Feature/Oauth/ProviderClientTest.php`. **The sentence is kept and
     * marked rather than deleted** (4368) — it is the only written record of
     * what this method is for, and it is load-bearing evidence for a larger
     * finding: this is the sole reader of `provider_health` anywhere in the
     * application, so a reader with no caller means **no row of that table has
     * ever been read by the running application.** 272's shape at table scale.
     * `tests/Feature/Architecture/VisibilityTest.php` carries the untruncated
     * enumeration and reddens when it changes.
     *
     * ⚠️ **A docblock naming three handled states is what stops the next reader
     * asking whether anything calls the method** — 314–316, in the same
     * neighbourhood as 7261.
     *
     * Answers false for "not connected", "connection expired" and "no quota"
     * alike, because all three mean the same thing to the caller and the
     * distinction belongs on the health board rather than in a branch.
     *
     * Does not make a request. Calling the API to find out whether we may call
     * the API spends the quota it is asking about.
     */
    public function available(Business $business): bool
    {
        $connection = $this->tokens->connection($business, $this->provider());

        if ($connection === null
            || $connection->status !== ConnectionStatus::Active
            || $connection->access_token_enc === null) {
            return false;
        }

        // Read, never write. A quota wall recorded by the last real call is the
        // signal here; overwriting it from a mere availability check would erase
        // the thing being asked about.
        $health = ProviderHealth::query()
            ->where('provider', $this->provider())
            ->first();

        return $health === null || $health->token_status !== 'quota_exhausted';
    }
}
