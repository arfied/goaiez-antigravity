<?php

declare(strict_types=1);

namespace App\Services\Actuation\WordPress;

use App\Enums\WordPressConnectionRefusal;
use App\Exceptions\TenantMismatch;
use App\Exceptions\WordPressRequestFailed;
use App\Models\Location;
use App\Models\WordPressCredential;
use App\Services\Actuation\ActuationActor;
use App\Services\AuditService;
use App\Support\Tenancy;

/**
 * The one way this platform takes, checks, uses or gives up a login to somebody
 * else's WordPress.
 *
 * ⛔ **THE ONLY READER AND WRITER OF `wordpress_credentials`**, held by a
 * chokepoint lint in `tests/Feature/Architecture/ActuationTest.php` on
 * `PlatformCredential`'s reasoning: every read of the password decrypts a live
 * write credential for a stranger's website into memory, so the number of
 * classes that touch the model *is* the number of places a stray dump would pay
 * off. `SiteChanges` is the same shape one table over, for the same slice.
 *
 * ## Connecting runs §19.7's gate, and connecting is the only way in
 *
 * ⛔ **THERE IS NO METHOD HERE THAT STORES A CREDENTIAL WITHOUT PROBING IT.**
 * Decision 5582: an Application Password carries the capabilities of the user it
 * belongs to and is not a scope, so the gate is a fact about the live site that
 * has to be *asked*. A `store()` beside {@see self::connect()} would be a second
 * route to a credential nobody checked — `SiteChanges`' rule-32 argument,
 * transplanted: the guard is only a guard if there is one door.
 *
 * ⚠️ **AND IT IS RE-ASKED, NOT ASKED ONCE.** {@see self::verify()} runs the same
 * probe against the stored credential, because a WordPress role can be changed
 * inside WordPress at any time and a connection recorded as least-privilege in
 * March says nothing about today. `WordPressAdapter::health()` is what calls it.
 *
 * ## What a refusal does not do
 *
 * ⚠️ **A FAILED RE-CHECK NEVER DELETES THE STORED CREDENTIAL.** An owner who
 * promoted their own user to Administrator, or whose site was briefly down, has
 * a connection this platform refuses to *use* — which is not the same as one it
 * should throw away, because throwing it away turns a five-second fix inside
 * WordPress into a full reconnection. `health()` says no; the row waits.
 *
 * ## The seam slice B is waiting on
 *
 * ⚠️ **{@see self::isConnected()} IS THE PREDICATE `ActuationTiers` NEEDS AND
 * THIS SLICE DELIBERATELY DOES NOT WIRE IT.** Conflict 7 changes what makes T1
 * reachable — it is no longer a plugin connection but a verified WordPress
 * credential — and slice B's `ActuationTiers::for()` ships with no T1 arm at all
 * (its decision 5541) precisely because no such store existed when it was
 * written. The store exists now. **Adding the arm belongs to whoever merges
 * second**, and the predicate it should read is this method.
 */
final class WordPressCredentials
{
    public function __construct(
        private readonly WordPressRestClient $client,
        private readonly AuditService $audit,
    ) {}

    /**
     * Take a login the owner pasted, after proving it is the right kind.
     *
     * ⚠️ **THE PASSWORD IS A PARAMETER AND NEVER A PROPERTY.** It reaches a
     * {@see WordPressSite} for the length of the probe and a database column
     * encrypted at rest, and nothing in between holds it.
     *
     * ⚠️ **AND IT ARRIVES AS AN {@see ApplicationPassword} RATHER THAN A
     * `string`**, so that an exception thrown anywhere below this frame renders
     * it as a class name instead of its first fifteen characters. That class
     * carries the measurement.
     */
    public function connect(
        Location $location,
        string $siteUrl,
        string $username,
        ApplicationPassword $applicationPassword,
        ActuationActor $actor,
    ): WordPressConnection {
        $this->assertSameTenant($location);

        $siteUrl = $this->normaliseSiteUrl($siteUrl);

        if (! str_starts_with($siteUrl, 'https://')) {
            // ⛔ **NEVER UPGRADED FOR THE OWNER.** See
            // `WordPressConnectionRefusal::NotHttps`: Basic Auth over `http://`
            // hands a working login to anybody on the path, and guessing that
            // the site answers on TLS would be a guess made *after* the
            // credential was sent.
            return $this->refuse($location, WordPressConnectionRefusal::NotHttps, $actor);
        }

        try {
            $restRoot = $this->client->discover($siteUrl);
        } catch (WordPressRequestFailed $e) {
            // ⚠️ **THREE OUTCOMES RATHER THAN TWO SINCE 6262**, and the third is
            // ours rather than the site's: discovery goes through the same
            // per-host budget as every other outbound call, so *"we are inside
            // this host's cooldown"* arrives here and must not be reported as
            // *"we could not find WordPress at that address"* — 231's confident
            // mismatch, said to the person who can least check it.
            return $this->refuse($location, $this->brakeRefusal($e) ?? match ($e->reason) {
                'unreadable:no_core_endpoints' => WordPressConnectionRefusal::NoCoreEndpoints,
                default => WordPressConnectionRefusal::NoRestApi,
            }, $actor);
        }

        if (! str_starts_with($restRoot, 'https://')) {
            // The site's own discovery answer is a vendor-supplied string.
            return $this->refuse($location, WordPressConnectionRefusal::NotHttps, $actor);
        }

        $site = new WordPressSite($restRoot, $username, $applicationPassword);

        $probe = $this->probe($site);

        if ($probe instanceof WordPressConnectionRefusal) {
            return $this->refuse($location, $probe, $actor);
        }

        [$identity, $refusal] = $probe;

        if ($refusal !== null) {
            return $this->refuse($location, $refusal, $actor);
        }

        // ⛔ **NOT `updateOrCreate()`, AND THE REASON IS THE GUARD ABOVE IT.**
        // `location_id` is in the model's `$guarded` — it decides *which
        // website* a credential unlocks — so the attribute from an
        // `updateOrCreate()` match array is silently dropped and the insert
        // fails on a NOT NULL column. It failed loudly here, which is the good
        // direction; the same shape on a nullable column is a row pointing at
        // nothing.
        $credential = $this->credentialFor($location) ?? new WordPressCredential;

        $credential->forceFill([
            'location_id' => $location->id,
            'site_url' => $siteUrl,
            'rest_root' => $restRoot,
            'username' => $username,
            'application_password' => $applicationPassword->reveal(),
            'wp_user_id' => $identity->userId,
            'wp_roles' => $identity->roles,
            'verified_at' => now(),
        ])->save();

        // ⚠️ **THE AUDIT ROW NAMES THE ROLES AND NOTHING ELSE.** `29` §2 rule 42
        // makes this a sensitive action, and what an auditor needs is the
        // evidence §19.7's gate was run — never the username, which is half a
        // credential, and never the site's own error text.
        $this->audit->record('wordpress.connected', $actor->auditActor(), $location, [
            'location_id' => (int) $location->id,
            'wp_user_id' => $identity->userId,
            'wp_roles' => $identity->roles,
        ]);

        return WordPressConnection::established($identity->roles);
    }

    /**
     * Ask the site again whether the stored credential is still the right kind.
     *
     * ⚠️ **IT REFRESHES `wp_roles` AND `verified_at` ON THE WAY PAST**, so the
     * stored evidence is the evidence from the last successful check rather than
     * from the day of connection. A role changed inside WordPress therefore
     * shows up as a difference somebody can see, which is the whole reason those
     * two columns exist.
     */
    public function verify(Location $location): WordPressConnection
    {
        return $this->recheck($location)[1]
            ?? WordPressConnection::refused(WordPressConnectionRefusal::NotAuthenticated);
    }

    /**
     * The site object to write with — **after** re-running §19.7's gate against
     * the live credential, in the same call.
     *
     * ⛔ **THE GATE IS A PROPERTY OF THE CREDENTIAL AT THE INSTANT OF THE WRITE,
     * AND {@see self::connect()} CAN ONLY SPEAK FOR THE INSTANT OF THE CONNECT**
     * (5985, closing 5819(c)). A WordPress administrator can change a user's
     * role at any moment — and *"give that user admin, it'll be easier"* is the
     * single most likely thing an owner or their web person does when something
     * on their site is not working. Until today the write path called
     * {@see self::siteFor()}, which reads the row and asks the site nothing at
     * all, so a credential that had become an Administrator was written with
     * happily and refused only by the *next* `health()` — which on the revert
     * path nothing ever calls.
     *
     * ⛔ **BOTH ARMS, NOT ONE.** Running arm 1 alone here to save a request
     * would make the write-time gate weaker than the connect-time gate while
     * reading like the same gate, and 5585's hole — a multisite Super Admin
     * whose `allcaps` is an Editor's — is a grant a network administrator can
     * make after the connection exactly as they can make any other.
     *
     * ⛔ **IT NARROWS THE WINDOW AND DOES NOT CLOSE IT, AND THAT MUST NOT BE
     * WRITTEN UP AS THOUGH IT DID** (314–316). WordPress offers no
     * check-and-write, so a role changed between this probe and the `POST` one
     * request later is unobservable from here. What changes is the size of the
     * window: from *"since the connection, possibly months"* to *"since the
     * previous HTTP request"*.
     *
     * ⚠️ **COSTED RATHER THAN ASSUMED — TWO EXTRA REQUESTS PER WRITE** (5986).
     * A publish already makes seven calls to the tenant's server (health,
     * two to snapshot, two to locate, the write, the read-back); this makes it
     * nine. **Writes are rare**: `PublishingVolume` caps a location at four
     * pages and four posts a month, so the ceiling is of the order of sixteen
     * extra requests per location per month. `CLAUDE.md`'s third tie-breaker —
     * lower marginal cost — is for the ambiguous case, and this is not one: the
     * first tie-breaker and the correctness of a `29` §19.7 promise both point
     * the same way.
     *
     * ⚠️ **THE READ PATH IS DELIBERATELY NOT GATED HERE.** `snapshot()` keeps
     * {@see self::siteFor()}: it runs on every publish attempt, it reads rather
     * than writes, and `Publishing::canWriteToSite()` has already asked
     * `health()` before it. Gating it would double the cost of deciding not to
     * publish.
     *
     * ⚠️ **AND {@see self::forget()} IS NOT GATED EITHER, WHICH IS THE ONE THAT
     * WOULD BE ACTIVELY WRONG.** Giving a credential back is the correct
     * response to discovering it has become too powerful; refusing to revoke it
     * because it is too powerful would leave us holding the exact credential
     * §19.7 exists to stop us holding.
     *
     * @return WordPressSite|WordPressConnection|null `null` when nothing is
     *                                                connected — kept distinct from a refusal so that *"this location
     *                                                has no website login"* and *"the login it has is one we will not
     *                                                write with"* stay two different sentences in the audit row.
     */
    public function siteForWriting(Location $location): WordPressSite|WordPressConnection|null
    {
        [$site, $connection] = $this->recheck($location);

        return $site ?? $connection;
    }

    /**
     * Ask the site about the stored credential once, and answer both questions
     * it settles.
     *
     * ⛔ **ONE PROBE, TWO CALLERS, BECAUSE TWO PROBES WOULD BE TWO GATES.**
     * {@see self::verify()} wants the verdict and {@see self::siteForWriting()}
     * wants the verdict *and* the site; a second copy of this sequence is where
     * one of them quietly loses an arm.
     *
     * @return array{?WordPressSite, ?WordPressConnection} **Both null when
     *                                                     nothing is connected**, which is the state that has to stay
     *                                                     distinguishable from a refusal: no probe was made, no site was
     *                                                     asked, and there is no credential to have a verdict about.
     *                                                     Otherwise the site is non-null exactly when the connection is
     *                                                     established.
     */
    private function recheck(Location $location): array
    {
        $this->assertSameTenant($location);

        $credential = $this->credentialFor($location);

        if ($credential === null) {
            return [null, null];
        }

        $site = $this->siteOf($credential);

        $probe = $this->probe($site);

        if ($probe instanceof WordPressConnectionRefusal) {
            return [null, WordPressConnection::refused($probe)];
        }

        [$identity, $refusal] = $probe;

        if ($refusal !== null) {
            // See the class docblock: refused to use, never thrown away.
            return [null, WordPressConnection::refused($refusal)];
        }

        $credential->forceFill([
            'wp_user_id' => $identity->userId,
            'wp_roles' => $identity->roles,
            'verified_at' => now(),
        ])->save();

        return [$site, WordPressConnection::established($identity->roles)];
    }

    /**
     * Whether this location has a stored, gate-passed WordPress connection.
     *
     * ⚠️ **THIS IS A FACT ABOUT THE STORE AND NOT A LIVE ANSWER**, and the
     * distinction matters to the caller that is coming. A row only exists
     * because {@see self::connect()} probed the credential and §19.7's gate
     * passed, so this is *"an owner connected a limited WordPress user"* —
     * which is the right predicate for deciding a location's actuation tier.
     * *"Can we write to it right now"* is `WordPressAdapter::health()`, which
     * costs two HTTP requests and must not be asked by a screen rendering a
     * tier.
     */
    public function isConnected(Location $location): bool
    {
        $this->assertSameTenant($location);

        return $this->credentialFor($location) !== null;
    }

    /**
     * The site object every request is built from, or null if nothing is
     * connected.
     *
     * ⛔ **ONLY {@see WordPressAdapter} CALLS THIS**, and it is the single point
     * at which a stored password becomes plaintext in this process.
     */
    public function siteFor(Location $location): ?WordPressSite
    {
        $this->assertSameTenant($location);

        $credential = $this->credentialFor($location);

        return $credential === null ? null : $this->siteOf($credential);
    }

    /**
     * Give the site back: revoke the credential at the site, then destroy our
     * copy.
     *
     * ⛔ **THIS IS 4880's OBLIGATION, AND IT IS A REAL REVOCATION RATHER THAN A
     * DELETE OF OUR OWN ROW.** `WordPressRestClient::revokeOwnCredential()` asks
     * the site which application password the request is authenticated with and
     * deletes that one by UUID — never the `DELETE …/application-passwords`
     * route with no UUID, which would revoke the owner's backup plugin along
     * with us.
     *
     * ⛔ **OUR COPY GOES EVEN WHEN THE REVOCATION DOES NOT, AND THAT ORDERING IS
     * DELIBERATE.** Two failures are possible and they are not equally bad:
     * keeping a working password for a site we have been told to leave is a
     * credential we could lose, while a password we have thrown away and the
     * owner has not yet deleted is one we cannot use. The second is strictly
     * safer, so the row goes either way and the outcome says which happened.
     *
     * ⚠️ **WHAT IS OWED IS A SCREEN, AND IT IS SLICE G's.** When the revocation
     * fails the owner has to remove the application password themselves inside
     * WordPress, and nothing in this slice tells them so — the audit row records
     * it and no human reads audit rows. Recorded rather than silently skipped,
     * because a credential nobody revoked is exactly what 4880 found after the
     * fact.
     *
     * @return bool|null `null` when nothing was connected, `true` when the site
     *                   confirmed the revocation, `false` when our copy is gone
     *                   and the owner still has to remove theirs. ⚠️ **Three
     *                   states rather than two, because the third is the one
     *                   somebody owes an action on** — a plain `bool` here would
     *                   make "we gave the site back" and "we let go of a
     *                   password that still works" the same answer.
     */
    public function forget(Location $location, ActuationActor $actor): ?bool
    {
        $this->assertSameTenant($location);

        $credential = $this->credentialFor($location);

        if ($credential === null) {
            return null;
        }

        $revoked = false;

        try {
            $revoked = $this->client->revokeOwnCredential($this->siteOf($credential));
        } catch (WordPressRequestFailed) {
            // The site is unreachable or refused. See the docblock: our copy
            // still goes.
        }

        $credential->delete();

        $this->audit->record('wordpress.disconnected', $actor->auditActor(), $location, [
            'location_id' => (int) $location->id,
            'revoked_at_site' => $revoked,
        ]);

        return $revoked;
    }

    /**
     * Read the credential's identity and run §19.7's gate against it.
     *
     * @return WordPressConnectionRefusal|array{WordPressIdentity, ?WordPressConnectionRefusal}
     */
    private function probe(WordPressSite $site): WordPressConnectionRefusal|array
    {
        try {
            $identity = $this->client->identify($site);
        } catch (WordPressRequestFailed $e) {
            // ⚠️ **OUR OWN REFUSAL, SAID AS OUR OWN** (6262). The owner is in
            // front of a form and the honest sentence is about this platform
            // waiting, not about their WordPress being missing.
            // ⛔ **AND THAT SENTENCE SAT THREE LINES ABOVE A `'throttled' =>
            // Busy` THAT DID NOT DO IT** (9754, closed 9800–9819): the arm was
            // right that a throttle is not `NoRestApi`, and wrong that one
            // refusal covers it — two thirds of that population is our own cap
            // and `Busy` says *"your website asked us to slow down"*. **The
            // corrected neighbour is what made the uncorrected mapping read as
            // considered**, which is why the sentence is kept above the fix.
            return $this->brakeRefusal($e) ?? match ($e->reason) {
                'unauthenticated', 'forbidden' => WordPressConnectionRefusal::NotAuthenticated,
                'unreachable', 'unavailable' => WordPressConnectionRefusal::NoRestApi,
                default => WordPressConnectionRefusal::Unreadable,
            };
        }

        return [
            $identity,
            LeastPrivilege::refusalFor($identity->capabilities, $this->client->canManagePlugins($site)),
        ];
    }

    /**
     * The owner-facing refusal for a failure where nothing was sent, or `null`
     * where something was.
     *
     * ⛔ **ASKED BEFORE EITHER `match` AND NEVER AS AN ARM INSIDE ONE**
     * (9800–9819). {@see WordPressRequestFailed::$reason} stays exactly
     * `'throttled'` for every brake, so an arm keyed on the string would be back
     * to one refusal for a population of three; and a *compound* label like
     * `throttled:day_budget_spent` would stop matching that arm altogether and
     * fall through to a `default` which, on both of the matches this sits in
     * front of, is a sentence about the owner's website. **Asking the typed
     * property first is the only ordering where a brake nobody has thought of
     * yet cannot become a claim about somebody's site** — and
     * {@see WordPressConnectionRefusal::forOutboundBrake()} is total, so it
     * raises rather than guesses.
     */
    private function brakeRefusal(WordPressRequestFailed $e): ?WordPressConnectionRefusal
    {
        return $e->brake === null
            ? null
            : WordPressConnectionRefusal::forOutboundBrake($e->brake);
    }

    private function refuse(Location $location, WordPressConnectionRefusal $refusal, ActuationActor $actor): WordPressConnection
    {
        // ⚠️ **A REFUSED CONNECTION IS STILL A SENSITIVE ACTION AND IS STILL
        // AUDITED.** An owner repeatedly pasting an administrator's password is
        // exactly the pattern support needs to be able to see, and the refusal
        // *code* — never the credential, never the site's error text — is what
        // records it.
        // ⚠️ **THE ACTOR IS THE ONE WHO ACTED, NOT A LITERAL `autopilot`.** A
        // refused connection is a person pasting a password, and an audit row
        // that cannot say which person is `ActuationActor`'s own argument (5533)
        // with the sign flipped.
        $this->audit->record('wordpress.connection_refused', $actor->auditActor(), $location, [
            'location_id' => (int) $location->id,
            'refusal' => $refusal->value,
        ]);

        return WordPressConnection::refused($refusal);
    }

    private function siteOf(WordPressCredential $credential): WordPressSite
    {
        return new WordPressSite(
            $credential->rest_root,
            $credential->username,
            new ApplicationPassword($credential->application_password),
        );
    }

    private function credentialFor(Location $location): ?WordPressCredential
    {
        return WordPressCredential::query()
            ->where('location_id', $location->id)
            ->first();
    }

    /**
     * Scheme, host, optional path — no query, no fragment, no trailing slash.
     *
     * ⚠️ **A URL WITH CREDENTIALS IN IT IS DROPPED RATHER THAN CARRIED.**
     * `https://user:pass@example.com` is a legal URL and `parse_url` reads the
     * pair happily; storing it would put a second credential in the column that
     * is not encrypted, and send it in a header on every request.
     */
    private function normaliseSiteUrl(string $url): string
    {
        $parts = parse_url(trim($url));

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return '';
        }

        return mb_strtolower($parts['scheme']).'://'
            .mb_strtolower($parts['host'])
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .rtrim($parts['path'] ?? '', '/');
    }

    /**
     * ⚠️ **`SiteChanges::assertSameTenant()`'s ARGUMENT, AT A THIRD ADDRESS.** A
     * loaded `Location` is self-scoping; an *unsaved* one is not loaded at all,
     * and if this service took its `business_id` as authorization the argument
     * would have become the boundary.
     */
    private function assertSameTenant(Location $location): void
    {
        $tenant = Tenancy::idOrFail();

        if ($location->business_id !== $tenant) {
            throw new TenantMismatch($tenant, $location->business_id);
        }
    }
}
