<?php

declare(strict_types=1);

namespace App\Enums;

use App\Console\Commands\WarehouseReplay;
use App\Exceptions\MailNotDeliverable;
use App\Jobs\ArchivePixelBatchJob;
use App\Services\Messaging\SendingRates;
use App\Services\Ops\OperatorAlerts;
use App\Services\Ops\PlatformHealthChecks;
use App\Services\Voice\VoiceSpend;

/**
 * The bells this platform may ring at its operator (T176 §3, P23).
 *
 * *"`AutomationRuns` is a screen someone must open; a 24/7 agent and live money
 * webhooks need a bell that rings."*
 *
 * ⛔ **A BELL, NEVER A BRAKE** (R25). Nothing in this feature refuses, delays or
 * degrades any operation. An alert path that fails leaves the thing it was
 * watching working exactly as it was — which is why every raise is wrapped and
 * why none of these kinds has a "and then stop sending" arm. The controls that
 * *do* stop things — the complaint trip, the global halt — are elsewhere and
 * stay elsewhere.
 *
 * ⚠️ **EVERY KIND IS DE-DUPLICATED ON `(kind, subject)`**, so a broken thing
 * pages once per quiet window rather than once per sweep. The subject is part of
 * the key because two providers going down twenty minutes apart is a real
 * incident shape, and a kind-only key would silence the second one.
 */
enum OperatorAlertKind: string
{
    /**
     * More jobs landed in `failed_jobs` inside the window than the threshold
     * allows.
     *
     * ⚠️ **THE SUBJECT IS THE QUEUE'S HEALTH, NOT ANY ONE JOB.** A single job
     * failing is ordinary — a vendor timed out, a retry will get it. A *spike*
     * is a deploy that broke a payload shape, a credential that expired, or a
     * dependency that went down, and it is invisible on every screen because the
     * queue drains normally while every message inside it dies.
     */
    case FailedJobSpike = 'failed_job_spike';

    /**
     * Forged, unsigned or unverifiable webhooks are arriving.
     *
     * See {@see PlatformHealthSignal::WebhookSignature} for why the commonest
     * cause is our own missing secret rather than an attacker.
     *
     * ## ⛔ WHAT IT CANNOT RING ABOUT, AND THE QUANTITY IS NOT OURS
     *
     * ⛔ **THE THRESHOLD IS A COUNT OF THE VENDOR'S OWN TRAFFIC** (12350). The
     * counter cannot increment unless somebody posts to the endpoint, so a
     * rotated secret is caught quickly where a vendor posts often and **never**
     * where it posts rarely — and an endpoint for a switched-off feature
     * receives nothing at all, so no figure above zero arms it. ⚠️ **This is
     * `PlatformCredentialUnusable`'s argument below, arriving at the opposite
     * answer**: that case has no threshold *because* a credential is absent at
     * one call an hour exactly as much as at ten thousand, and this one keeps
     * its threshold because **a signature failure is not always our fault**. A
     * bell on the first one would page an operator for a stray unsigned POST to
     * a public URL, which is 511.
     *
     * ⚠️ **SO THE GAP IS REAL AND IS COVERED ELSEWHERE OR NOT AT ALL.** A
     * signing secret that is **missing** is reported at any volume by
     * `ops:webhook-material`, which runs on every deploy. A secret that is
     * **present and wrong** on a low-traffic endpoint is watched by nothing
     * here, and closing that needs a fact this repository does not hold — how
     * much unsigned traffic these URLs actually receive.
     */
    case WebhookSignatureFailures = 'webhook_signature_failures';

    /**
     * A process that beats every minute has not beaten for longer than the
     * staleness threshold — `scheduler` or `queue`.
     *
     * ⛔ **THIS IS THE ONE ALERT ABSENCE RAISES**, and it is why the check that
     * produces it cannot live in the scheduler. A dead scheduler cannot notice
     * that it is dead; a dead worker cannot report that no job ran. The watch
     * therefore runs in the web process, which fails independently of both.
     */
    case HeartbeatSilent = 'heartbeat_silent';

    /**
     * A vendor is failing a large enough fraction of a large enough number of
     * calls — today, the model providers.
     *
     * ⚠️ **A RATE OVER A FLOOR, NEVER A COUNT.** Ten failures out of ten
     * thousand is a healthy Tuesday; ten out of twelve is an outage. And below
     * the floor no rate means anything at all — one failed call out of one is
     * 100%, and an alert that fires on it is one somebody turns off in a week.
     */
    case VendorErrorRate = 'vendor_error_rate';

    /**
     * A day's inbound call minutes have crossed their ceiling — decision 4686.
     *
     * ⛔ **THE ONE BELL RUNG ABOUT MONEY, AND IT IS RUNG BECAUSE THE BRAKE
     * CANNOT REACH THE EXPENSIVE HALF.** Inbound voice is billed by duration and
     * is *the one uncapped path a stranger can trigger*: nobody has to be a
     * tenant, logged in, or consented to make it cost money. The minutes
     * themselves are incurred at the carrier by a forwarding rule, so no code
     * path in this application can decline one — {@see VoiceSpend}
     * refuses the downloads that follow and this rings about the rest. **The
     * action it is asking for is in the vendor's portal**, which is why it has
     * to be a person and cannot be an arm.
     *
     * ⚠️ **THE SUBJECT IS A BUSINESS ID, OR `unattributed` FOR THE POOL.** Calls
     * to a number no account owns are the sharpest case and would otherwise be
     * invisible: `VoiceCalls::record()` writes no `calls` row for one at all.
     */
    case InboundVoiceMinutes = 'inbound_voice_minutes';

    /**
     * A tenant has reached §11 row 4's monthly pixel event cap — decision
     * 5000s.
     *
     * ⚠️ **NOT A BRAKE ON THE TENANT — THEY KEEP MEASURING.** Pageviews keep
     * landing at the cap; only other event types are dropped. This bell is for
     * *us*: a single tenant driving 500,000 events in a month is either a
     * viral traffic spike worth knowing about or a runaway script on their
     * site, and either way it is the free-tier infrastructure budget of §2.1
     * that this is protecting, not anything billed to the tenant.
     *
     * ⚠️ **THE SUBJECT IS A BUSINESS ID**, on {@see InboundVoiceMinutes}'s
     * precedent one case above.
     */
    case PixelMonthlyCapReached = 'pixel_monthly_cap_reached';

    /**
     * A tenant's pixel batches are being refused at the origin allowlist —
     * §5.7's *"a spike in rejects means a pixel bug and you want to know that
     * day"*, decision 5000s.
     *
     * ⚠️ **THIS IS THE BELL ON THE MOST LIKELY SUPPORT TICKET THIS PRODUCT
     * WILL EVER RECEIVE.** Decision 4968 made the origin allowlist fail closed
     * — a freshly provisioned tenant lists no website, so the collector
     * archives nothing for them until somebody names one — and named that as
     * *"the most likely reason a real tenant will report the pixel is not
     * working"*. Without this bell the tenant discovers it, not us, and only
     * once they think to look at a dashboard that has been empty for a week.
     *
     * ⚠️ **THE SUBJECT IS A BUSINESS ID** and the context carries the rejected
     * origin and the tenant's **rolling 24-hour** reject total
     * (`rejects_last_24h`), which together are the whole diagnosis: the tenant to
     * call and the website they forgot to add.
     *
     * ⛔ **THIS SAID "THE RUNNING HOURLY COUNT" UNTIL 2026-08-22, AND THE HOURLY
     * BUCKET IS THE READING `IngestRejects` EXPLICITLY REJECTED** (7735). The
     * table is an hourly rollup, so the sentence was plausible; the figure on the
     * bell is `recentRejects()`'s sum over `RECENT_WINDOW_HOURS`, and that
     * class's pruner refuses to cut inside that window **precisely because the
     * bell carries it**. The distinction is not cosmetic to anybody sizing a
     * threshold — an hourly count and a daily one differ by up to twenty-four
     * times — and this docblock is what a reader reaches for first.
     */
    case PixelIngestRejects = 'pixel_ingest_rejects';

    /**
     * A pixel canary tripped its `js_error` regression threshold and was halted
     * automatically — `GOAIEZ_PIXEL_MASTER_BUILD.md` §10, decision 4980.
     *
     * ⚠️ **THE ONE KIND WHOSE ACTION ALREADY HAPPENED, WHICH IS NOT THE
     * EXCEPTION R25 SEEMS TO FORBID.** `WatchPixelCanary` repoints `/p.js` away
     * from the canary itself — `SendingGuard`'s reasoning, that a kill switch
     * needing somebody awake is not a kill switch, applied to a bad deploy
     * rather than a complaint rate. This alert rings *about* that halt; it is
     * not what performs it, and every other kind here still has no arm that
     * stops anything — this one is a report of a stop, not a second one.
     */
    case PixelCanaryHalted = 'pixel_canary_halted';

    /**
     * Visitor events this platform answered `204` to were never archived, and
     * the queue has stopped trying.
     *
     * ⛔ **THE `204` IS THE REASON THIS KIND HAS TO EXIST AT ALL.**
     * `App\Services\Pixel\PixelCollector` answers *received* the moment the
     * bytes are read and dispatches the archive to a queue — §11's *"always
     * 204, always <50 ms"*, kept deliberately when the collector moved off the
     * edge and into Laravel. So by the time
     * {@see ArchivePixelBatchJob} runs out of attempts, **the browser
     * that sent the beacon has been gone for minutes and there is nobody left
     * to tell.** Nothing retries, nothing re-sends, and the events are gone for
     * good: the visitor closed the tab, and a pixel on somebody else's website
     * may not be asked to remember for us.
     *
     * ⚠️ **THE SUBJECT IS THE DISK, NOT THE TENANT** — `warehouse.l0_disk`.
     * Every plausible cause of this is one fault about one archive: an object
     * store that is unreachable, a bucket that does not exist, an endpoint
     * pointing at the wrong company, a driver that is not installed. Keying the
     * dedupe on a business id would page once per tenant for a single cause,
     * which is `AutomationAbandoned`'s argument at the other end of the
     * platform. The account is in `context` instead.
     *
     * ⛔ **AND IT IS NOT THE FAILED-JOB SPIKE WEARING A DIFFERENT NAME**
     * (9370–9375). `ops.failed_job_spike` counts failures, which is a count of
     * **traffic**: a tenant whose site sends a handful of beacons an hour never
     * reaches twenty-five in a window however completely their archive has
     * stopped, and one busy tenant reaches it while saying nothing about which
     * subsystem died. This has no threshold, because a store that is refusing
     * writes is refusing them at one beacon an hour exactly as much as at ten
     * thousand.
     *
     * ⛔ **WHAT IT DOES NOT SAY.** It cannot name how many events were lost —
     * this platform counts admitted *events* per tenant per month and counts
     * beacons nowhere, so no figure here can be differenced against a batch —
     * and it cannot tell an operator whether a given day's archive is
     * complete. ⚠️ **"COUNTS ACCEPTED BEACONS NOWHERE" IS WHAT THIS SAID UNTIL
     * 2026-08-26 AND IT WAS TRUE ABOUT BEACONS AND FALSE AS WRITTEN**:
     * `MonthlyEventCap::increment()` has upserted `pixel_monthly_usage.events_total`
     * on the collector's own request since 2026-08-18. **The conclusion is
     * unchanged and the reason for it is narrower** — that counter is monthly,
     * and it is taken upstream of two further refusal gates, so it cannot be
     * differenced against one batch. ⛔ **What is spent is 9848's COST
     * argument**, which refused a finer counter as *"a per-tenant per-day write
     * on the hottest path in the product"*: **that write already exists**, so a
     * finer counter is a narrower column on it rather than a new write, and
     * 9848 stays open on its own terms. What is knowable is
     * that the fault existed, and {@see WarehouseReplay}
     * reads exactly that back before an operator trusts a quiet replay.
     */
    case PixelArchiveFailed = 'pixel_archive_failed';

    /**
     * A revocation this platform owes on a deleted customer's Google Business
     * account was refused by the vendor and is still outstanding — decisions
     * 4883, 4888(a).
     *
     * ⛔ **THE ONLY KIND HERE THAT IS BOTH A PRIVACY FACT AND A BILL, AND THE
     * PRIVACY HALF IS THE LARGER ONE.** Until the grant ends, a subprocessor
     * holds `business.manage` — read **and write** — on a former customer's
     * Google Business Profile, and the erasure destroyed the tenant record
     * anybody would have revoked it from. The money rides along: the account is
     * genuinely still connected, so `zernio:meter` goes on writing an
     * account-day for it every night and we go on paying for a customer we no
     * longer have.
     *
     * ⚠️ **IT RINGS ABOUT A RECORD AND NEVER ABOUT AN INFERENCE, WHICH IS THE
     * WHOLE OF WHY IT IS ONE CASE AND NOT TWO** (4884, 4888(b)). A binding
     * carrying `revocation_owed_at` was stamped inside the transaction that
     * destroyed the business (4880), so *"this belonged to somebody we erased"*
     * is something this platform wrote down. The other orphan population —
     * bindings whose business vanished before that stamp existed, found by
     * `gbp:revoke-owed-grants --reconcile` — is an **inference**, and
     * deliberately has no bell: `RevokeOwedGbpGrants` carries the argument,
     * which is that nothing in this application can clear one.
     *
     * ⚠️ **THE SUBJECT IS THE PROVIDER, NOT A BUSINESS.** Every business named
     * by this alert has already been destroyed, so a business id is a reference
     * to nothing an operator can open — and one bell carrying a count is what an
     * outage-shaped failure calls for, where a bell per former tenant would ring
     * fifty times on the night Zernio is down. `GbpProvider::Direct` has no
     * revocation path at all yet (4888(d)); when it gains one it is a second
     * subject and rings separately.
     *
     * ⚠️ **THE ACTION IS ON `Admin\GbpGrantRevocations`**, which offers a
     * per-row retry against exactly this population — the one orphan list on
     * that screen that does carry a button, because it is the one built from a
     * record.
     */
    case GbpGrantOutstanding = 'gbp_grant_outstanding';

    /**
     * A statutory erasure that was due tonight did not happen, and the reason
     * is not one this application planned for — decisions 8842, 8848.
     *
     * ⛔ **THE BELL EXISTS BECAUSE THE ALTERNATIVE WAS TO MAKE A LOUD FAILURE
     * QUIET.** Until 8842, one un-deletable tenant threw out of
     * `tenants:execute-deletions` and **aborted every other tenant's erasure in
     * the same run**, for ever, because the same set failed again the next
     * night. Containing that in a `catch` is right and it removes the only
     * signal there was: the scheduler discards output, so a `warn()` line
     * reaches nobody, and Laravel's own handler no longer logs an exception
     * that no longer escapes. **A containment with no report is how a
     * permanent failure becomes invisible**, which is `sending_health_windows`
     * arriving through the door marked "we fixed the blast radius".
     *
     * ⚠️ **THE SUBJECT IS THE SWEEP AND NOT A BUSINESS, WHICH IS
     * {@see self::GbpGrantOutstanding}'s ARGUMENT AND NOT ITS REASON.** There
     * the businesses are destroyed, so a business id opens nothing; here they
     * very much exist and an operator wants their references — but the failure
     * shape that matters most is the database being unreachable, where a bell
     * per due account rings for every one of them at once. So it is one bell
     * with a count, and the references ride in the context where an incident
     * review can read them. They are references and never names: `28` §9.5's
     * record is `tenant_deletion_requests`, and a summary that reaches a text
     * message carries no customer.
     *
     * ⛔ **`Alert` RATHER THAN `Attention`, AND THE CLOCK IS WHY.** Erasure is
     * the one obligation in this platform with a statutory deadline attached,
     * and this kind means it is being missed *and nothing will clear it on its
     * own* — the same request is due again tomorrow and will fail the same way.
     * It does not decay gently overnight the way a reached cap or a halted
     * canary does; every night it rings is another night of holding data
     * somebody asked us to destroy.
     */
    case TenantErasureFailed = 'tenant_erasure_failed';

    /**
     * The provider says a connected Google account lives on a **different
     * profile** than the one we recorded for the business holding it —
     * decisions 6767, 6768.
     *
     * ⛔ **THE BELL IS THE WHOLE ACTION, AND THAT IS A RULING RATHER THAN A
     * SCOPE LINE** (6767). `GbpConnections::BINDING_MISMATCHED` is a detector
     * and deliberately not a correction: the tempting next line is to re-point
     * or disconnect the connection, and 4884 is the standing answer — acting
     * unattended on a contradiction disconnects a live customer's Google
     * Business Profile the first time a filter is wrong, with no way back but
     * asking the owner to consent again at the vendor. **A human reads the
     * record and acts.** So this kind rings, and nothing anywhere acts on what
     * it reports.
     *
     * ⚠️ **WHAT IS WRONG IS AN ATTRIBUTION, NOT A CREDENTIAL.** The account
     * reference is unchanged and still ours — the webhook reaches this
     * comparison only after our own binding index resolved the tenant and the
     * connection inside it was found holding that exact account. What disagrees
     * is which *profile* the account sits on, and a profile is the vendor's
     * tenant boundary, so the disagreement means our record of **which customer
     * owns that listing** is wrong. Nothing is read or written across a tenant
     * boundary while nobody is looking; the harm lands the moment somebody
     * *acts* on the wrong attribution — which is why {@see self::severity()}
     * answers `Attention` rather than `Alert`, and the ordering that produces is
     * argued rather than assumed.
     *
     * ⚠️ **THE SUBJECT IS A BUSINESS ID**, on {@see InboundVoiceMinutes}'s
     * precedent. Not the account reference — that value decides whose Google
     * listing a call reaches and stays inside `GbpConnections` by that class's
     * own rule — and not the profile reference either, which is the same kind of
     * value one level up: `GET /v1/accounts?profileId=…` is the query that reads
     * a tenant's accounts, and this table is platform-scoped, un-RLS'd and
     * rendered on a staff screen. ⛔ **Both references are already written down,
     * in the `audit_log` row inside the tenant's own record**, which is where
     * that evidence belongs and where the boundary still applies. The bell
     * carries the business and the location so somebody can find that row, and
     * nothing else.
     *
     * ⚠️ **NOT PER LOCATION, AND THAT IS A FORK RATHER THAN AN OVERSIGHT.**
     * `GbpConnections::profileRefFor()` reuses the first stored profile
     * reference for **every** location a business has, so the value being
     * compared is business-wide by construction: two of a tenant's locations
     * disagreeing with the vendor is one wrong attribution seen twice, and a
     * per-location subject would ring twice for it. Nor is it
     * {@see GbpGrantOutstanding}'s per-provider subject — that case collapses
     * every tenant into one bell **because every business it names has already
     * been destroyed**, and here the business exists and is openable.
     *
     * ⛔ **WHAT IT COSTS WHEN NOBODY HEARS IT IS A CONFIDENT SENTENCE NAMING THE
     * WRONG CUSTOMER.** `Architecture\GbpTest`'s profile-index lint says that
     * about a wrong row and it is as true of a stale one: the profile index is
     * what attributes an unaccounted-for Zernio account to a business on the Ops
     * screen, `complete()`'s ownership check (6603) asks the vendor about the
     * profile we hold, and a reconnect reuses it. Each of those answers
     * confidently and wrongly, and none of them errors.
     */
    case GbpBindingMismatched = 'gbp_binding_mismatched';

    /**
     * Zernio is billing us for connected Google accounts that nothing in this
     * application is using — decisions 6779(d), 6917, 7393, 7394, 9200, built
     * here at 9232–9235.
     *
     * ⛔ **THE DECIDING ARGUMENT IS NOT THE BILL, AND 9200 SAYS SO.** An orphan
     * is a subprocessor holding `business.manage` — read **and write** — on a
     * real business's Google listing, with **no record here of whose**. The
     * money is the only part of it this platform can measure, so the money is
     * what the threshold is denominated in; what makes the threshold worth
     * having at all is the access behind it.
     *
     * ⚠️ **THE SUBJECT IS THE PROVIDER, ON {@see self::GbpGrantOutstanding}'s
     * PRECEDENT (7024) AND NOT ON {@see self::GbpBindingMismatched}'s.** That
     * one keys on a business id because a mismatch is *about* a business we can
     * name and open. ⛔ **An orphan has no binding at all — that absence is its
     * definition — and half of them are unattributable** (7395), so there is no
     * business id to be the subject and a per-account subject would be the one
     * value 4884 keeps inside `GbpConnections`: the argument to
     * `DELETE /v1/accounts/{id}`, which decides whose listing a call reaches.
     * One bell carrying a count is what a vendor-shaped disagreement calls for.
     *
     * ⚠️ **IT SHARES A SUBJECT STRING WITH {@see self::GbpGrantOutstanding} AND
     * DOES NOT SHARE ITS DEDUPE.** `OperatorAlerts::alreadyRang()` keys on
     * `(kind, subject)`, so the two ring independently — which is right, because
     * they name different populations: that one is a grant we recorded and
     * failed to end, this one is an account we never recorded at all.
     *
     * ⛔ **`Attention` RATHER THAN `Alert`, AND IT IS 7394's RULING.** An orphan
     * is money already spent that is not getting worse tonight — the ladder is
     * monthly and one more night of it is cents — and the second half of
     * {@see self::severity()}'s question fails outright: **the action is in
     * Zernio's own console, which this application cannot reach** (4884, 6767).
     * An operator woken at 3am can read the screen and can do nothing else until
     * they can open a browser. Ranking it `Alert` would put it beside a statutory
     * erasure that did not happen, and the cost of that is paid by the bells
     * beside it (511).
     *
     * ⛔ **AND IT IS CLEARABLE, WHICH IS WHY IT MAY RING AT ALL WHERE
     * `gbp:revoke-owed-grants --reconcile`'s POPULATION MAY NOT** (7022). That
     * one gets no bell because *nothing in this application clears one*: an
     * operator who does the correct thing leaves the binding row untouched and
     * the probe finds it again tomorrow, so the bell would ring for ever and be
     * muted. **Here the opposite is true.** Disconnecting the account in
     * Zernio's console removes it from the vendor's own list, which is the side
     * of the diff this reads — so the correct action makes it stop.
     */
    case GbpOrphanedAccounts = 'gbp_orphaned_accounts';

    /**
     * Messages have been handed to a carrier and **not one outcome has come
     * back**, so the automatic complaint trip and 2102's platform halt are both
     * reading a rate measured over nothing — decisions 7480–7495, 7543, closed
     * here at 7600–7619.
     *
     * ⛔ **THIS IS THE ONE KIND THAT RINGS ABOUT A CONTAINMENT BEING OFF RATHER
     * THAN ABOUT A THING GOING WRONG.** Every other case here reports damage —
     * jobs dying, minutes billed, a grant outstanding, a listing attributed to
     * the wrong customer. This one reports that **nothing is being reported**,
     * which is the hardest state in this codebase to notice because it produces
     * no error, no failure, no red screen and no rising count: a delivery
     * receipt has exactly one join key, and a carrier reporting under a
     * different one leaves `delivered` at zero for ever. `delivered` is both the
     * denominator of the trip and the proof that anything is reporting back
     * ({@see SendingRates::trafficWithoutOutcomes()}), so an empty one reads
     * identically to a platform that has sent nothing — and the sweep printed
     * `0 bp over 0 delivered`, which is what a spotless platform looks like.
     *
     * ⛔ **WHAT IT COSTS WHEN NOBODY HEARS IT IS THE WHOLE OF 2102.** Tenant
     * number allocation puts every tenant's traffic on the shared GOAIEZ 10DLC
     * brand and our own number pool, so what a carrier scores is the total — and
     * 2101/2102/2113 make the automatic halt a **precondition of sending at all**,
     * which is the ground the attestation override was accepted on. In this state
     * that halt cannot fire for any tenant, messages keep going to members of the
     * public, and the complaints keep accruing at the carrier where we cannot see
     * them. On a campaign in carrier review, that is the account.
     *
     * ⛔ **A BELL AND NEVER A BRAKE, AND HERE THAT IS COUNTER-INTUITIVE ENOUGH
     * TO STATE** (R25, 7489). The instinct is to stop sending until reporting
     * comes back. It is wrong twice: it punishes tenants for **our** vendor's
     * reporting, and it still does not stop the tenant who is actually
     * generating complaints, because the same silence hides them both. One layer
     * up it is worse — halting the platform would silence every tenant's
     * carrier-mandated STOP confirmation and HELP answer, which 2099 makes
     * unconditional, and that is 3980–3983's collision arriving through a
     * different door. **So this rings, and nothing acts.**
     *
     * ⚠️ **THE SUBJECT IS THE CHANNEL, AND THAT IS A FORK RATHER THAN A
     * DEFAULT.** A per-business subject was considered and refused on three
     * grounds. **(a)** One vendor misconfiguration causes this, so a per-tenant
     * subject rings once per affected tenant for a single incident — which is
     * {@see self::GbpGrantOutstanding}'s own argument for collapsing to a
     * provider. **(b)** The raiser is the platform sweep, and
     * `PlatformRateSample` is six aggregate integers carrying **no tenant
     * identity at all** — deliberately, because `PlatformComplaintRate::report()`
     * and `platform_halt_incidents` both refuse to name a tenant, since naming
     * the worst-performing account in a record support reads routinely leaks one
     * tenant's standing to everybody who opens the screen. A per-business
     * subject would have to invent that identity first. **(c)** Nothing is wrong
     * *with* any business — what is wrong is our own integration with a vendor.
     * ⚠️ **{@see self::GbpBindingMismatched}'s counter-precedent does not reach
     * this case**: that one went per business because the mis-stated fact is a
     * fact *about* that business and the business is openable. Here opening a
     * business tells an operator nothing.
     *
     * ⚠️ **CHANNEL RATHER THAN A BARE PLATFORM CONSTANT**, on
     * {@see self::VendorErrorRate}'s and {@see self::ScheduledRunOverranLock}'s
     * precedent, where the subject names *the thing to go and look at*. The
     * sweep measures one channel at a time, and email receipts going silent
     * would be a different vendor, a different notification profile and a
     * genuinely separate incident that must not be de-duplicated against this
     * one.
     *
     * ⚠️ **THE CONDITION IS CONTINUOUS AND NOTHING EVER CLEARS IT**, so this
     * re-raises on every sweep and the dedupe suppresses all but the first in
     * each quiet window — the board's group count then reads as *"how many quiet
     * windows this outlasted"*, which is the existing convention. ⛔ **That is
     * why every figure it carries is a ROLLING one**: only the first bell in a
     * window lands, so anything counted per bucket would be pinned at its
     * first-bell value for ever. `sent` is the rolling 24-hour count and grows
     * between bells, which is what makes *"how much traffic went out blind"*
     * answerable from the alert row alone.
     *
     * ⛔ **AND THE PREDICATE BEHIND ALL OF THIS IS AN AGGREGATE, WHICH IS A
     * NARROWER CLAIM THAN THE PARAGRAPHS ABOVE READ — 2026-08-22 (7720).**
     * `PlatformRateSample::reportingHasGoneSilent()` sums `delivered` and
     * `failed` across every tenant, **so one tenant reporting normally makes it
     * false for everybody**: a tenant with four thousand blind sends is invisible
     * the moment any other account's receipts are working, which on a platform
     * with more than one customer is the ordinary case. Nothing above is wrong —
     * this kind's subject really is the channel and it really does report the
     * channel going dark — but *"the automatic halt cannot fire"* is true of one
     * tenant long before it is true of the platform.
     * {@see self::TenantDeliveryReceiptsSilent} is that half, and the two are
     * mutually exclusive by construction at the raiser.
     */
    case DeliveryReceiptsSilent = 'delivery_receipts_silent';

    /**
     * Some tenants have handed messages to a carrier and had **nothing** come
     * back, while the rest of the platform is being reported on normally — so
     * *their* automatic complaint trip is reading a rate measured over nothing
     * (7720–7739, closing 7615(a)).
     *
     * ⛔ **THIS IS THE CASE ITS SIBLING CANNOT SEE, AND THE REASON IS
     * ARITHMETIC RATHER THAN OVERSIGHT.** {@see self::DeliveryReceiptsSilent}
     * fires on `sum(delivered) + sum(failed) === 0`, and one healthy tenant puts
     * a non-zero number into that sum. Wave 11 built that bell and wrote down
     * (7615(a)) that the per-tenant half had nowhere to ring from; what was
     * missing was not a home but the **measurement** — every tenant's
     * `SendingRates` already passed through `PlatformComplaintRate::measure()`
     * and five integers survived the loop.
     *
     * ⛔ **THE HARM IS THE SAME HARM AND IT IS NOT SMALLER FOR BEING NARROWER.**
     * R8 puts every tenant's traffic on the shared GOAIEZ 10DLC brand and our own
     * number pool, so a blind tenant's complaints accrue against **the
     * registration everybody else is sending on** — with 2102's per-tenant
     * containment unable to fire for exactly the account generating them, and
     * with every screen green because the aggregate is healthy. On a campaign in
     * carrier review that is the account.
     *
     * ⚠️ **THE ACTION IS DIFFERENT FROM ITS SIBLING'S, WHICH IS THE WHOLE
     * JUSTIFICATION FOR A SECOND KIND.** When the channel is dark the cause is
     * ours and global: the delivery notification profile in the vendor console.
     * When *some* accounts are dark and others are not, that profile is
     * demonstrably working — other tenants' receipts are arriving through it — so
     * the thing that differs is the **route**: the number those accounts send
     * on, the campaign they are attached to, or a send path that never reached
     * the carrier at all. Two headlines, two first moves. ⛔ **A shared kind with
     * two subjects was refused for that reason**: the headline is what an
     * operator reads on a phone at 3am, and *"delivery reports have stopped"* is
     * the wrong sentence when most of them have not.
     *
     * ⚠️ **THE SUBJECT IS THE CHANNEL, NOT A BUSINESS, AND THAT AGREES WITH ITS
     * SIBLING'S ARGUMENT RATHER THAN OVERTURNING IT.**
     * {@see self::DeliveryReceiptsSilent} refused a per-business subject on three
     * grounds and **(a)** survives here unchanged and is decisive: a bell per
     * blind tenant rings once per affected account for what may be one incident,
     * which is {@see self::GbpGrantOutstanding}'s own argument for collapsing to
     * a provider and carrying a **count** — and 511's *"the first outage sends
     * two hundred texts"* is not hypothetical when `OperatorAlerts::raise()` is a
     * mail **and** an inline carrier call per raise. ⚠️ **This read *"a
     * synchronous mail and a text"* until 2026-08-22 and the mail half was never
     * true** (7873a) — `PlatformMailer::send()` is a job dispatch — which leaves
     * the argument standing on the half that is genuinely inline. One bell,
     * whatever the number.
     * ⛔ **Ground (b) — that the sample carries no tenant identity to name — is
     * the one this case had to overturn**, and it is overturned deliberately
     * rather than stepped around: `PlatformComplaintRate::measure()` now keeps
     * the identity it was already computing. What (b) actually protects is
     * `report()`'s cache entry and `platform_halt_incidents`, both of which are
     * about **standing** — *"this account has the worst complaint rate"* — and
     * neither is touched. ⚠️ **Blindness is not standing**: a blind tenant's
     * complaint rate is *unknown*, and saying which accounts a vendor is not
     * reporting on is a fact about our integration, not a league table. **Ground
     * (c) — "nothing is wrong with any business" — is the one that is false
     * here** and was true there: the accounts named are the ones whose own
     * containment is off.
     *
     * ⚠️ **THE IDENTITIES RIDE IN `context` AND NEVER IN THE SUMMARY**, because
     * the summary is delivered by SMS. The text carries counts; the ids are
     * evidence for whoever opens the row, capped at a stated number and ordered
     * by traffic — see `WatchPlatformComplaintRate::NAMED_BLIND_TENANTS`.
     *
     * ⚠️ **ITS FLOOR IS THE PER-TENANT TRIP'S OWN, NOT THE PLATFORM'S** (2409).
     * `messaging.complaint_trip_min_delivered` is what `SendingGuard::shouldTrip()`
     * requires before it will act, so the claim stays exact: *as many messages
     * have been handed to a carrier for this account as its own trip needs to
     * have been delivered, and not one of them has been reported on.* ⛔ **Which
     * means this bell is armed by a different pair of registry keys than its
     * sibling** — zeroing the platform trip silences the platform bell and leaves
     * this one ringing, and zeroing the per-tenant trip does the reverse. That is
     * a closure rather than a coupling: before this case existed, blanking one
     * platform key switched off the only bell on this whole failure.
     *
     * ⛔ **A BELL AND NEVER A BRAKE (R25), AND THE TEMPTATION IS SHARPER HERE
     * THAN FOR THE SIBLING.** A named account is something a machine could pause,
     * and pausing it would be wrong in the same two ways: it punishes a tenant for
     * a carrier's reporting, and it does not stop whoever is actually generating
     * complaints, because the same silence hides them both. Nothing acts on this.
     */
    case TenantDeliveryReceiptsSilent = 'tenant_delivery_receipts_silent';

    /**
     * The platform stopped **its own** sending, for every business at once, and
     * nothing starts it again but a person — 7732, closed at 7860–7879.
     *
     * ⛔ **THIS REPORTS A CONTAINMENT WORKING RATHER THAN A THING GOING WRONG,
     * AND IT IS THE LOUDER OF THE TWO KINDS HERE THAT DO.** Most of this enum
     * announces damage — a queue dying, a vendor failing, a grant outliving its
     * customer. {@see self::PixelCanaryHalted} is the other one that does not,
     * and the difference between them is argued in {@see self::severity()}
     * rather than here, because that is the method the difference changes.
     * `WatchPlatformComplaintRate::halt()` did exactly what 2102 asks of it — the
     * aggregate complaint rate crossed the line and every tenant's outbound
     * messaging stopped. **What earns the bell is not that the machine was
     * wrong. It is that the machine's decision is permanent until somebody undoes
     * it**, and the command that made it deliberately never writes `false`.
     *
     * ⛔ **UNTIL 2026-08-22 THE LOUDEST THING THIS PLATFORM CAN DO WAS ALSO ITS
     * QUIETEST.** `halt()` had three effects and no fourth: a registry key, a
     * `platform_halt_incidents` row, and `$this->error()` — which is `stdout`
     * from a scheduled command whose documented cron line is
     * `schedule:run >> /dev/null 2>&1`. `Admin\SendingControls` renders both the
     * switch and the incidents, and a screen is something somebody opens. So the
     * machine could stop every tenant's sending at three in the morning and the
     * first signal was a customer asking why nothing sent (7732).
     *
     * ⚠️ **THE SUBJECT IS EMPTY, BECAUSE THE KIND HAS ONE SUBJECT** — the
     * platform, on {@see self::FailedJobSpike}'s precedent. ⛔ **The channel was
     * refused and the distinction is not pedantry**: the rate is *measured* over
     * SMS, and the switch `SendingGuard::refusalFor()` reads refuses **every**
     * channel, so a subject of `sms` on the board would read as though a tenant's
     * email still went out. It does not.
     *
     * ⛔ **AND A SUBJECT PER INCIDENT WAS REFUSED FOR A HARDER REASON.** The
     * dedupe on `(kind, subject)` is the only rate limit
     * `Sms\PlatformTexter::alertOperator()` has — that method's own docblock says
     * so — and a subject that changed every time would remove it from the one
     * bell a person can make ring over and over, by releasing a platform whose
     * rate is still over the line and letting the next sweep stop it again. ⚠️ **The cost is
     * real and is stated rather than glossed**: a second, genuinely separate halt
     * inside the quiet window is suppressed as a *bell*. **It is never suppressed
     * as a record** — the incident row is filed every time, `registry_changes`
     * keeps every write, and `OperatorAlerts::fire()` logs the suppression with
     * the window that did it (7580–7599).
     *
     * ⚠️ **EVERY FIGURE ON IT IS A SNAPSHOT AND NOT A ROLLING ONE, WHICH IS THE
     * OPPOSITE OF ITS TWO SIBLINGS ABOVE** (7605). Those report a *continuous*
     * condition that re-raises on every sweep, so a per-bucket figure would be
     * pinned at its first-bell value for ever. This one is **discrete**: it can
     * only fire on the sweep that throws the switch, because the next sweep
     * returns at *"already halted"*. The figures are the arithmetic that caused
     * the halt, and freezing them is the point — an incident review needs the
     * reading the machine acted on, not the reading an hour later.
     *
     * ⚠️ **IT NAMES THE KEY IT THREW, AND THAT ANSWERS THE QUESTION AN OPERATOR
     * ASKS FIRST AT 3AM** (3980–3983). `messaging.automatic_halt` and
     * `messaging.global_halt` stop the same sends, and only the operator's one
     * stops a carrier-mandated STOP confirmation or HELP answer. A machine may
     * never throw that one, so the summary can say plainly that compliance
     * replies still go out — which is the difference between an operator
     * investigating a complaint rate and an operator scrambling to undo a
     * carrier violation that did not happen.
     *
     * ⚠️ **NO TENANT IS NAMED AND NONE COULD BE.** `PlatformRateSample` is six
     * aggregate integers carrying no tenant identity at all, deliberately —
     * `PlatformComplaintRate::report()` and `platform_halt_incidents` both refuse
     * to name one, because the worst-performing account's standing must not leak
     * into a record support reads routinely (7602(b), untouched by 7725).
     * ⛔ **That is a genuine limit on the first move rather than a courtesy**: the
     * bell says the platform stopped and cannot say who caused it, and the route
     * to that answer is the per-tenant trip's own `sending_pauses` rows on the
     * same screen.
     *
     * ⛔ **ONLY THE AUTOMATIC DOOR RAISES THIS TODAY, AND THAT IS A DECISION
     * RATHER THAN A GAP** (7862, 7863). `Admin\SendingControls::haltPlatform()`
     * and `releasePlatform()` are a person at a keyboard, the pager's recipient
     * is a single number that person typed in themselves, and a bell that always
     * rings for something you just did is 511 aimed at the channel this bell
     * shares. The name of this case deliberately does not say *automatic*, so a
     * later ruling that a second operator or the owner must be told can reuse it
     * with a different subject rather than minting a near-duplicate.
     *
     * ⛔ **A BELL, NEVER A BRAKE (R25), AND THE READING IS INVERTED HERE.** For
     * every other kind the temptation is to make the alert *stop* something; the
     * brake has already been applied by the time this rings, so the temptation is
     * the other one — to let the alert path decide, retry or release. It does
     * none of those. `raise()` is the last thing `halt()` does, after the switch
     * and after the incident, so no failure of the pager can leave the platform
     * unhalted or the evidence unwritten.
     */
    case PlatformSendingHalted = 'platform_sending_halted';

    /**
     * A scheduled command's `withoutOverlapping()` window did not do what its
     * number says — decision 6975's residual, closed at 7080–7099.
     *
     * ⛔ **THIS IS THE ONE BELL RUNG ABOUT THIS APPLICATION'S OWN CONFIGURATION
     * RATHER THAN ABOUT THE WORLD.** Every other kind here reports something
     * outside going wrong — a vendor, a queue, a website, a deleted customer's
     * grant. This one reports that a number somebody argued in
     * `routes/console.php` is smaller than the thing it is guarding, which is a
     * fact nothing else in this codebase could ever have observed: wave 6
     * reasoned all thirty-five windows `routes/console.php` then held from what
     * each command *does*, and nothing has ever measured what one *has done*
     * (6975).
     *
     * ⚠️ **TWO CONDITIONS, ONE KIND, AND THEY ARE THE SAME FINDING SEEN FROM
     * TWO SIDES.** Either a run finished having taken at least as long as its
     * own window — so the lock was already free while the process was still
     * working — or a run *started* while a previous run of the same entry had
     * not reported finishing, which is the same thing observed one step later,
     * with the second copy already alive. Splitting them would ring two bells
     * for one incident and give an operator a distinction they cannot act on
     * differently: the action is the same either way, which is to widen the
     * window in `routes/console.php` or make the command faster.
     *
     * ⚠️ **THE SUBJECT IS THE ARTISAN COMMAND NAME** — `ops:heartbeat`,
     * `billing:send-renewal-reminders` — on {@see VendorErrorRate}'s precedent.
     * Two entries overrunning are two incidents; the same entry overrunning
     * every night is one, and the quiet window is what keeps the second
     * command's bell audible over the first's.
     *
     * ⛔ **WHAT IT COSTS WHEN NOBODY HEARS IT IS NOT "A REPEATED RUN".** 6971
     * enumerates the seven entries where a second copy is not survivable, and
     * the sharpest is `billing:send-renewal-reminders`: its own block says the
     * mutex is its only guard, so an expiry under a still-running sweep is a
     * **second statutory pre-renewal notice mailed to a customer**, and unlike
     * every skipped run in that file a posted notice cannot be taken back.
     */
    case ScheduledRunOverranLock = 'scheduled_run_overran_lock';

    /**
     * A scheduled command exited non-zero, or could not be started at all, so
     * the work that entry does did not happen (9680–9690, 9945–9959).
     *
     * ## ⛔ What this reports that {@see self::ScheduledRunOverranLock} cannot
     *
     * ⛔ **THE SIBLING MEANS *IT RAN AND TOOK TOO LONG*. THIS MEANS *IT DID NOT
     * WORK*, AND FOLDING THEM WOULD BE 9371's ERROR ONE LAYER UP.** Both are
     * raised by `ScheduledRunMeter` about the same set — every entry on the
     * schedule, which is a property of that class's four unfiltered listeners
     * rather than a count this file may state (11261) — and the
     * two remedies have nothing in common: an overrun is answered by widening a
     * `withoutOverlapping()` window in `routes/console.php` or making the
     * command faster, and this is answered by finding out why the command
     * broke. **A run can be both at once** — slow *and* non-zero — and an
     * operator woken by one word for both would go and edit a window over a
     * command that never got as far as doing anything.
     *
     * ## ⛔ It is the only thing that says so for every BACKGROUND entry
     *
     * ⛔ **A BACKGROUND ENTRY'S OUTPUT IS DISCARDED TWICE OVER.** The child's
     * streams go to `/dev/null` (`Event::getDefaultOutput()`), the cron line in
     * `.claude/skills/deploying/` redirects the parent's, and
     * `ScheduleRunCommand` gates its throw on `! $event->runInBackground` so no
     * `ScheduledTaskFailed` reaches the exception handler. **Four scheduled
     * commands return `self::FAILURE` in sentences composed for an operator,
     * into that** — `jobs:prune-failed`'s is *"failed_jobs has no retention
     * horizon on this deployment"*, over the least protected copy of
     * end-customer contact details in this schema. Before this bell the whole
     * of what any of them reached was a counter nobody is subscribed to and a
     * `critical` log line in a file on a box.
     *
     * ## ⛔ No threshold, no arming row, no registry key
     *
     * ⛔ **ON {@see self::PlatformMailUndeliverable}'s AND
     * {@see self::AutomationAbandoned}'s RECORDED ARGUMENT, AND THE ARITHMETIC
     * IS SHARPER HERE THAN AT EITHER OF THEM.** A count of failures is a count
     * of **traffic**: `ops.failed_job_spike` wants twenty-five rows in an hour,
     * a stopped mail transport produced three, and a stopped **nightly** sweep
     * produces exactly **one a night, for ever**. There is no figure below one
     * per night, so any threshold at all would be a bell that the entries most
     * likely to be silently broken can never reach — `CLAUDE.md`'s decoration,
     * seeded on.
     *
     * ## ⚠️ The subject is the artisan command name
     *
     * ⚠️ **ON {@see self::ScheduledRunOverranLock}'s PRECEDENT AND FOR ITS
     * REASON.** Two entries failing are two things to go and fix; the same entry
     * failing every night is one. It is also the string that joins a bell to a
     * line in `routes/console.php` and to a row on `ops:schedule-runtimes`, which
     * is the report somebody opens next.
     *
     * ## ⚠️ What bounds the repeat, since nothing bounds the ring
     *
     * ⚠️ **`ScheduledRunMeter::FAILED_RUN_REPEAT_HOURS`, ASKED THROUGH
     * {@see OperatorAlerts::rangSince()}** — named in backticks rather than
     * `{@see}` because Pint turns one into a `use` and an enum of alert names has
     * no business importing the meter. ⛔ **AND IT IS DELIBERATELY UNDER A DAY,
     * WHICH IS THE CLAUSE 9690 WROTE DOWN AND A LATER SUMMARY LOST.** Copying
     * `PlatformHealthChecks::CREDENTIAL_REPEAT_DAYS`' thirty would give a broken
     * nightly sweep one ring in a month and silence on the other twenty-nine
     * nights — the bell speaking on the first night of an outage and on none of
     * the others, which is worse than a bell that never rang because it looks
     * like the problem stopped.
     *
     * ## ⛔ What it cannot see, which is the expensive kind of claim
     *
     * ⛔ **A SCHEDULER THAT IS NOT RUNNING AT ALL RINGS NOTHING HERE.** Every
     * one of these is raised from a framework scheduler event, so cron not
     * firing `schedule:run`, a wedged tick and a box that is off all produce
     * **silence** rather than a bell. Quiet here is not evidence that the
     * schedule ran.
     *
     * ⛔ **AND IT CANNOT TELL A COMMAND THAT DID ITS WORK FROM ONE THAT RAN AND
     * DECIDED NOTHING** (9688). `actuation:measure-site-changes` and
     * `actuation:judge-speed-fixes` are permanent no-ops on every deployment
     * that exists and they exit **zero**, which is indistinguishable from here
     * and from every other instrument on this platform.
     */
    case ScheduledRunFailed = 'scheduled_run_failed';

    /**
     * A bell of some other kind has spent its whole daily push allowance, so
     * this platform is still recording it and has stopped sending it — 7839(a),
     * closed here.
     *
     * ⛔ **THIS IS THE ONLY KIND IN THIS ENUM THAT REPORTS ON THE PAGER ITSELF,
     * AND IT EXISTS BECAUSE A BOUNDED PAGER AND A FIXED PROBLEM ARE THE SAME
     * SILENCE.** {@see OperatorAlerts::PUSH_BUDGET_PER_KIND} withholds the
     * eleventh push of a kind in a rolling day, on purpose, and 7484's lesson
     * with its sign flipped is what happens next: the operator's handset goes
     * quiet, which is exactly what it does when somebody fixes the thing. **The
     * message that says why the other ten stopped is the only one that can tell
     * those two apart**, and no screen can deliver it, because the reader of a
     * screen is somebody who already decided to look.
     *
     * ⛔ **8042's REFUSAL DOES NOT REACH IT, AND THE DIFFERENCE IS WHO IS AT THE
     * KEYBOARD.** 7862 and 7863 were refused because the pager has one recipient
     * and both bells would have texted the person who had just pressed the
     * button. **A flood has nobody at the keyboard**; it is provoked by a
     * stranger, a vendor's retry loop, or a change of ours that broke origin
     * checking for every tenant at once (7828's own honest case).
     *
     * ⚠️ **THE SUBJECT IS THE FLOODED KIND'S OWN `value`**, never a tenant, a
     * number or a process. So the de-duplication is one bell per flooded kind
     * per quiet window, the board groups it that way, and the line an operator
     * reads names which bell went quiet. ⛔ **It is never this kind's own value**
     * — {@see OperatorAlerts} refuses to ring about itself, because a pager
     * reporting that it has stopped reporting is a loop with no reader.
     *
     * ⛔ **IT CARRIES ITS OWN PER-KIND ALLOWANCE AND THAT IS THE WHOLE
     * MECHANISM, NOT AN EXEMPTION.** 7827 made the budget per kind precisely so
     * that a flood on one path cannot starve a different bell, so this one
     * reaches the same handset through the one channel the flood cannot
     * saturate. ⚠️ **And it bounds itself with the same arithmetic**: ten pushes
     * a day, whatever is happening, after which the pager is wholly deaf on this
     * kind too. **That is a real limit and it is stated rather than glossed** —
     * the last thing a pager can do is stop, one bell cannot solve that, and
     * what covers the rest is `Admin\OperatorAlertBoard`'s *what stopped
     * reaching you* section, which is true at every volume and needs no push at
     * all.
     *
     * ⚠️ **IT CANNOT RING ON AN INSTALL WITH NO ADDRESS AND NO NUMBER, WHICH IS
     * CORRECT RATHER THAN A GAP.** The budget counts pushes that left the
     * building (7826), so an unreachable platform spends nothing, withholds
     * nothing and has nothing to announce. The board says so on this kind's own
     * row rather than leaving it to be inferred from a quiet screen.
     */
    case PagerBudgetSpent = 'pager_budget_spent';

    /**
     * This install cannot read the suppression hashes it has stored, so
     * `ConsentService::decide()` is refusing **every send on the platform** —
     * 8093(a) and 8197(a), closed here.
     *
     * ⛔ **THE ONLY STATE IN THIS ENUM WHERE THE PRODUCT HAS ALREADY STOPPED
     * AND NOBODY HAS BEEN TOLD ANYTHING AT ALL.**
     * {@see self::PlatformSendingHalted} is the near neighbour and it is the
     * *contrast* rather than the precedent: that one is a machine deciding to
     * stop, recorded in `platform_halt_incidents`, rendered on
     * `Admin\SendingControls`, thrown by a command an operator can find.
     *
     * ⛔ **THE CLAUSE THAT FOLLOWED SAID THIS STATE HAD *"no incident row, no
     * screen and no log line of its own"*, AND THE SCREEN HALF STOPPED BEING
     * TRUE ON 2026-08-25 — BOTH READINGS KEPT AND DATED** (9646, 9449(c)). It
     * continued: *"grep `IdentifierHashEpochs` across `app/Livewire/` and it
     * returns nothing, and the refusal writes nothing anywhere. The whole of
     * what says so today is `php artisan consent:hash-epoch`, **a command
     * nobody has run**, and its exit code is wired to nothing (8088,
     * 8211(b))."* **That grep now returns two files.**
     * `Admin\SendingControls` and `Admin\Credentials` both render
     * `IdentifierHashEpochs::readability()` — named in backticks and never in a
     * `{@see}`, on `OperatorAlerts::probeChannels()`' rule — the headline, and
     * `operatorSentence()` whole and in order — so the near neighbour above is
     * no longer a contrast on the *screen* axis and the two states are on the
     * same page as each other.
     *
     * ⚠️ **THE REST OF THE OLD CLAUSE SURVIVES AND IS WHY THIS BELL IS STILL
     * THE LOAD-BEARING INSTRUMENT.** There is still **no incident row and no
     * log line of its own**, the refusal still writes nothing anywhere, and
     * the command's exit code is still wired to nothing. ⛔ **And the whole
     * argument for a pager is untouched: a screen only reports to somebody
     * already looking at it**, and every way into this state is a thing nobody
     * remembers doing. **The bell is what reaches the person who does not know
     * to go and look.**
     *
     * ⛔ **AND THE TENANTS FIND OUT BEFORE THE OPERATOR DOES.**
     * `SendRefusalReason::ownerSentence()` renders *"our record of who has asked
     * us to stop could not be read"* on tenant-facing surfaces, on every refused
     * send, while the person who could fix it is holding a silent handset. That
     * is the ordering this case exists to reverse.
     *
     * ## ⛔ Three ways in, and not one of them is somebody deciding to
     *
     * An in-place upgrade with suppression rows already stored (8183); a lost,
     * unread or regenerated `APP_KEY` — `composer setup` runs `key:generate`
     * unconditionally (8093(d), still open); and, until the 8200 hotfix, an
     * ordinary crash between the durable write and the epoch write. **None of
     * the three is an act anybody would remember performing**, which is why the
     * bell has to carry the diagnosis rather than merely the alarm.
     *
     * ## ⚠️ The subject is the status, and it is a fork rather than a default
     *
     * Two states reach here and **they have different first moves**:
     * `rotated` is answered by putting the previous `APP_KEY` back, and
     * `unattributed` by `consent:hash-epoch --adopt`. Both are lossless and
     * neither is the other. So the subject is
     * {@see IdentifierHashEpochStatus}'s own value: a transition between them —
     * which only a person or a key change can cause — rings a fresh bell
     * immediately instead of being swallowed as *"the same thing"* by a quiet
     * window whose answer has just changed underneath it.
     *
     * ⛔ **IT CANNOT BE USED TO RING FASTER, WHICH IS WHAT
     * {@see self::PlatformSendingHalted} REFUSED A PER-INCIDENT SUBJECT OVER.**
     * There are exactly two values, both reachable only from outside this
     * application, and the two states cannot alternate: leaving `unattributed`
     * goes through `adopt()` or `retire()`, and both end at `Current`. **The
     * ceiling this adds is one extra bell across one genuine transition**, not a
     * dedupe a caller can defeat.
     *
     * ⚠️ **TWO KINDS WERE CONSIDERED AND REFUSED**, on
     * {@see self::TenantDeliveryReceiptsSilent}'s own test rather than by
     * analogy: that pair split because *"delivery reports have stopped"* is the
     * **wrong headline** when most of them have not. Here one headline is exactly
     * true of both states — every send is refused because the register cannot be
     * read — and what differs is the remedy, which is a sentence and not a
     * headline. A summary has 300 characters and can carry a command; splitting
     * the kind would buy nothing and cost a second severity, a second board row
     * and a second thing to keep in step.
     *
     * ## ⛔ It self-clears, and that is a weaker property than it sounds
     *
     * ✅ **THE STATE CANNOT SURVIVE ITS OWN CORRECT FIX** (8210). Restoring the
     * key moves the fingerprint; `--adopt` and `--accept-loss` both write rows
     * the reader reads. So this can never become the bell that rings for ever
     * and gets muted, taking a real one with it — which is the objection
     * `RevokeOwedGbpGrants` sustained against a bell on an **inference**.
     *
     * ⛔ **IT ALSO DOES NOT SURVIVE A NON-FIX, AND THAT IS WHY THE ROW MATTERS
     * MORE THAN THE PUSH.** On an `unattributed` install the next inbound
     * carrier STOP writes a hash **and observes an epoch under the current
     * key**, so `status()` answers `Current`, `isReadable()` turns true, sending
     * resumes and this bell stops — over the very rows nobody ever attributed.
     * The handset goes quiet exactly as it does when somebody fixes it. **The
     * `operator_alerts` row, kept a year, is then the only surviving evidence
     * that this install was ever in that state**, which is an argument for the
     * record rather than for the text.
     */
    case SuppressionsUnreadable = 'suppressions_unreadable';

    /**
     * A platform credential something needed and could not use — 9297's and
     * 9301(a)'s owed bell, built at 9320–9327.
     *
     * ## ⛔ What this closes, and who asked for it
     *
     * ⛔ **TWO LANES A WAVE APART FOUND THE SAME HOLE INDEPENDENTLY AND NEITHER
     * COULD FILL IT.** 9235(b) recorded that a Zernio failure rings nothing;
     * 9297 recorded that *"no billing failure of any kind rings a bell — not an
     * unset key, not an outage, not a decline"*, and both raised the same
     * remedy: **not** {@see self::VendorErrorRate}, which is a rate over a
     * floor and therefore quietest in exactly the outage it exists for, but *"a
     * check on the credential itself"*. 9301(a) addressed it to whoever holds
     * this file. This is that case.
     *
     * ## ⚠️ It reports a USE, not a setting, and that is the whole of why it is
     * not noise
     *
     * ⚠️ **`Admin\Credentials` ALREADY ANSWERS *"WHICH KEY IS ABSENT"* AND THIS
     * DOES NOT REPEAT IT.** A bell on the standing configuration would ring
     * today about every credential this platform declares and has not been given
     * — five payment keys among them — which is 511 arriving on the day of
     * install. The counters behind this are written **at the point of use**:
     * nothing is recorded until a real request, job or sweep asked for a
     * credential and was refused, so a key nobody reads is silent however long
     * it stays blank, and the sentence an operator gets is *somebody just could
     * not do something*, which is the half no screen here can show.
     *
     * ## ⛔ Two faults, one headline, and the remedy is the sentence
     *
     * ⛔ **{@see PlatformHealthSignal::CredentialAbsent} AND
     * {@see PlatformHealthSignal::CredentialUnreadable} ARE DIFFERENT
     * INCIDENTS.** One is a key nobody has pasted. The other is a stored
     * ciphertext this install can no longer decrypt — an `APP_KEY` that changed
     * — which is the same root cause as {@see self::SuppressionsUnreadable} seen
     * at a second table, and which **`Admin\Credentials` renders as a green
     * "Store" row**, because that screen deliberately has no decryption path at
     * all (9322, owed).
     *
     * ⚠️ **ONE KIND RATHER THAN TWO, ON {@see self::SuppressionsUnreadable}'s
     * OWN TEST RATHER THAN BY ANALOGY**: that case refused a split because one
     * headline was exactly true of both its states and what differed was the
     * remedy, which is a sentence and not a headline. The same holds here — a
     * credential this platform needs cannot be used — and the two remedies
     * (paste it in Ops; put the previous `APP_KEY` back) fit inside 300
     * characters with room to spare.
     *
     * ## ⚠️ The subject is the key, and the repeat is bounded by history rather
     * than by a threshold
     *
     * ⚠️ **PER KEY, LIKE {@see self::WebhookSignatureFailures} IS PER
     * ENDPOINT** — `38` Part 1 requires the board to show *"exactly which key is
     * absent"*, and three absent Authorize.Net keys are three things somebody
     * has to paste. ⛔ **THERE IS NO THRESHOLD AND NO ARMING ROW, DELIBERATELY**:
     * a count of refused reads is a count of *traffic*, so any figure an
     * operator picked would be unreachable on the install this platform actually
     * has — the arithmetic 9282 did for Zernio, and the arithmetic nobody had
     * done for `ops.webhook_signature_failure_spike` or `ops.failed_job_spike`.
     * **A credential is either there or it is not, at any volume.** What bounds
     * the repeat instead is `PlatformHealthChecks::CREDENTIAL_REPEAT_DAYS` —
     * named in backticks because Pint turns a `{@see}` into a `use` and an enum
     * of alert names has no business importing the sweep — so this rings on the
     * first refused read and then not again about that key for thirty days.
     */
    case PlatformCredentialUnusable = 'platform_credential_unusable';

    /**
     * A **queued** platform email was handed to the transport and did not go,
     * permanently (9370–9379).
     *
     * ## ⛔ WHAT THIS DOES NOT WATCH, AND IT IS NOT EVERY EMAIL WE SEND
     *
     * ⛔ **RAISED IN EXACTLY ONE PLACE — `App\Jobs\DeliverPlatformMail::failed()`
     * — SO IT WATCHES `PlatformMailer::send()`'s POPULATION AND NO OTHER.**
     * Every `deliverNow()` caller is outside it, and wave 41 moved six senders
     * across that line in one night (11010, 10980–10995), each reporting
     * instead through its own `AutopilotJob::failed()` or its command's exit
     * code. ⚠️ **The board row was corrected for this at 11132 and this
     * docblock was not — carried here 2026-08-28 (11264).** The correction had
     * to travel to two artefacts and reached one: the operator surface said
     * *"a QUEUED platform email"* while the enum a lane opens to find out what
     * the bell means went on saying *"a platform email"*, which is the wider
     * of the two readings living on in the file a builder consults.
     * ⚠️ **`self::headline()` is deliberately NOT narrowed with it**, and the
     * reason is that it is rendered in two different jobs: on the fired-alert
     * lists it names one real alert, where *"Email this platform sent was not
     * delivered"* is exactly true, and on the board's *what is being watched*
     * list it is the title over the detail that already states the narrowing.
     * **Qualifying it would make four render sites wrong to fix a fifth the
     * sentence beneath it already fixes.**
     *
     * ## ⛔ The instance, which is not an argument
     *
     * ⛔ **PRODUCTION CARRIED THREE CONSECUTIVE PERMANENT FAILURES OF THIS
     * PLATFORM'S OWN MAIL PATH, EIGHTEEN MINUTES APART, AND NOTHING RANG.** An
     * SES transport was handed a credential in another vendor's format; three
     * separate messages each burned their three attempts and landed in
     * `failed_jobs`, where they sat for five days, visible to `SELECT` and to
     * nobody else. ⚠️ **The sharper edge is what the count means**: three is
     * not a small incident, it is **every message anybody tried to send** on a
     * platform whose mail volume is currently three messages in twenty minutes.
     *
     * ## ⛔ Why {@see self::FailedJobSpike} could not ring, and why no figure
     * would have made it
     *
     * ⛔ **`ops.failed_job_spike` SEEDS AT 25 IN A 60-MINUTE WINDOW AND THREE IS
     * NOT TWENTY-FIVE.** That is not a badly-chosen figure — it is 9282's
     * arithmetic arriving at a second bell. A count of failures is a count of
     * **traffic**, and the traffic on this path is the platform's own account
     * mail: sign-in links, renewal notices, support replies. **A mail transport
     * that has stopped is stopped at three messages an hour exactly as much as
     * at ten thousand**, so any arming row here would be reachable only by an
     * install that does not need the bell. This case therefore has no threshold
     * and no registry key, on {@see self::PlatformCredentialUnusable}'s own
     * recorded argument rather than by analogy to it.
     *
     * ## ⚠️ One kind, two faults, and the headline is only what is true of both
     *
     * ⚠️ **THE TWO ARMS KNOW DIFFERENT AMOUNTS AND THE HEADLINE SAYS ONLY THE
     * SMALLER.** Where `DeliverPlatformMail` was refused by
     * {@see MailNotDeliverable}, the fault is **ours** and every
     * one of its causes is platform-wide — a `log` transport, no from address,
     * a ceiling nobody stated. Where the transport itself threw, this
     * application cannot tell an authentication failure that stops everything
     * from a single rejected recipient that stops one message. ⛔ **So the
     * headline claims only what both arms carry** — *a message was not
     * delivered and nothing retries it* — and the width of the fault is in the
     * summary, which is where the two arms differ. Writing *"no email is
     * leaving this platform"* would have been true of the arm that motivated
     * this case and false of the other, on a bell read at 3am.
     *
     * ## ⚠️ What bounds the repeat, since nothing bounds the ring
     *
     * ⚠️ **A BROKEN TRANSPORT IS BROKEN FOR EVERY MESSAGE, SO THE QUIET WINDOW
     * IS THE WRONG MEMORY** (`CLAUDE.md`, 2026-08-25: *a fault that is a
     * standing state needs a longer memory than the pager's quiet window*).
     * With the seeded sixty minutes a five-day outage is a hundred and twenty
     * texts, which is 511 with a handset attached.
     * `OperatorAlerts::MAIL_PATH_REPEAT_HOURS` is the bound, asked through
     * {@see OperatorAlerts::rangSince()} — named in backticks rather than
     * `{@see}` because Pint turns one into a `use`. ⚠️ **It sits on
     * `OperatorAlerts` rather than on the job that reads it**, which is not
     * where its sibling lives: two build-failing chokepoints forbid any file in
     * `app/` outside `PlatformMailer` from naming `DeliverPlatformMail` at all,
     * and `Admin\OperatorAlertBoard` has to print the figure.
     *
     * ⚠️ **A DAY RATHER THAN {@see PlatformHealthChecks}'
     * THIRTY, AND THE DIFFERENCE IS THE SUBJECT.** A credential is pruned out
     * of the counters at thirty days and the standing answer is on
     * `Admin\Credentials` the whole time. **There is no screen anywhere that
     * says platform mail has stopped**, and the outage this case was minted for
     * ran five days — so a bell that says it once and then goes quiet for a
     * month would have said it on day one of five and nothing on days two to
     * five. A repeat interval strands nothing either way; a day is the widest
     * one that still speaks during the incident it is about.
     *
     * ## ⛔ Its own email channel rides the path it is reporting on
     *
     * ⛔ **AND THE BOARD SAYS SO, WHICH IS THE HALF A DOCBLOCK CANNOT DO.**
     * {@see OperatorAlerts::email()} sends through the same mailer, so on the
     * configuration arm the email for this bell is refused by the same guard
     * that raised it and `emailed_at` stays null. **On this one kind the SMS
     * channel is the only one that can be relied on**, and
     * `Admin\OperatorAlertBoard`'s row for it states that to the operator
     * rather than leaving it here.
     */
    case PlatformMailUndeliverable = 'platform_mail_undeliverable';

    /**
     * A webhook endpoint could not fetch the material it verifies with, so every
     * delivery arriving meanwhile is being turned away unjudged (9380–9394).
     *
     * ## ⛔ Why this is not {@see self::WebhookSignatureFailures}
     *
     * ⛔ **THAT BELL SAYS *"WE CHECKED AND IT FAILED"* AND ITS SUMMARY NAMES THE
     * REMEDY FOR IT — *"a missing or rotated signing secret"*.** Two of the eight
     * endpoints fetch signing material over the network before they can check
     * anything, and until this case existed a failure of that fetch was counted
     * as an unverifiable signature. **So an operator woken by an outside outage
     * was sent to look at our own secrets**, which is 314–316 arriving in the
     * one sentence a person acts on at 3am.
     *
     * ⚠️ **AND THE TWO CAN BE TRUE AT ONCE, WHICH IS WHY THEY ARE TWO KINDS
     * RATHER THAN TWO SENTENCES ON ONE.** A rotated secret and an unreachable
     * key host produce two different remedies for two different endpoints, and
     * `(kind, subject)` de-duplication would have collapsed them.
     *
     * ## ⛔ No threshold and no arming row, on {@see self::PlatformCredentialUnusable}'s
     * argument rather than by analogy
     *
     * ⛔ **A KEY HOST IS UNREACHABLE AT ONE WEBHOOK AN HOUR EXACTLY AS MUCH AS
     * AT TEN THOUSAND**, and what the fault costs is the traffic it discards —
     * so a volume bar would be a figure only a busy platform could reach over a
     * loss a quiet one suffers just as completely. 9282's arithmetic is the
     * worked example: a bell armed at twenty calls an hour cannot ring for an
     * install that makes twelve.
     *
     * ⚠️ **AND UNLIKE THAT CASE THERE IS NO LONG REPEAT GATE, WHICH IS A
     * DELIBERATE DIFFERENCE.** An absent credential is a standing state and
     * would ring for ever; an unreachable key host is transient by construction,
     * it stops when the vendor recovers, and while it lasts genuine deliveries
     * are being lost every hour. **Repeating is the point**, and what bounds the
     * pager is `OperatorAlerts::PUSH_BUDGET_PER_KIND` — named in backticks
     * because Pint turns a `{@see}` into a `use` and an enum of alert names has
     * no business importing the alert service.
     *
     * ⚠️ **THE SUBJECT IS THE ENDPOINT, LIKE ITS SIBLING'S**, because the two
     * that can raise it fail independently and each has its own vendor.
     */
    case WebhookKeysUnavailable = 'webhook_keys_unavailable';

    /**
     * An automation ran out of retries, so a unit of work a customer is paying
     * for was abandoned and nothing will pick it up (9700–9719).
     *
     * ## ⛔ What this reports that `automation_runs` cannot
     *
     * ⛔ **`AutomationRunStatus::Failed` IS AN ATTEMPT, NEVER A SURRENDER, AND
     * NOTHING IN THE SCHEMA TELLS THE TWO APART.** `AutopilotJob::handle()`
     * closes the row `Failed` and rethrows so the queue retries — so a job that
     * failed once and succeeded ninety seconds later leaves a `Failed` row that
     * is byte-for-byte the shape of one that died. There is no attempt number,
     * no correlation id between the attempts of one dispatch, and no terminal
     * flag. **So a reader built over that column reports recovered work as lost
     * work**, which is 511 before it has rung once. What the queue knows and the
     * table does not is *we have stopped trying*, and `failed()` is where it
     * says so.
     *
     * ⚠️ **AND IT COVERS THE ARM THAT LEAVES NO ROW AT ALL.** `handle()` writes
     * nothing until `claimRun()` returns: a throw from `Tenancy::set()`, from
     * either kill-switch read or from the pause and suspension checks fails the
     * job with **no `automation_runs` row anywhere**. That is population three's
     * silence inside population two, and only the queue's own hook sees it.
     *
     * ## ⛔ No threshold, no arming row, no registry key
     *
     * ⛔ **ON {@see self::PlatformMailUndeliverable}'s RECORDED ARGUMENT RATHER
     * THAN BY ANALOGY TO IT.** A count of failures is a count of **traffic**:
     * `ops.failed_job_spike` wants twenty-five rows in an hour, and a single
     * tenant's whole day of automation is a handful of dispatches — so an
     * automation that has stopped working for that tenant produces three rows
     * and never approaches it, exactly as a stopped mail transport produced
     * three. **An automation abandoning work at one dispatch an hour costs that
     * customer the same thing it costs at ten thousand**, so any figure here
     * would be reachable only by an install that does not need the bell.
     *
     * ## ⚠️ The subject is the automation, and the account is in the summary
     *
     * ⚠️ **NOT THE TENANT, WHICH IS THE CHOICE THAT BOUNDS THIS BELL.** Four
     * kinds take a business id as their subject and `OperatorAlerts::RETENTION_DAYS`
     * names the cost: one row per tenant per quiet window, growing with the
     * customer base. `SendReviewInviteJob` is dispatched **per customer**, so a
     * tenant with two hundred invites due and a bad credential would ring two
     * hundred subjects. Keyed on the automation, the same fault is one bell that
     * names the thing an operator would go and fix.
     *
     * ⚠️ **THE PRICE IS STATED RATHER THAN GLOSSED**: a second account hitting
     * the same automation inside the repeat window is not paged about. It is on
     * `Admin\AutomationRuns` under its own account and in `automation_runs`,
     * which is what makes the trade affordable here and would not make it
     * affordable for a job that writes no run row.
     *
     * ## ⚠️ What bounds the repeat, since nothing bounds the ring
     *
     * ⚠️ **`AutopilotJob::ABANDONED_REPEAT_HOURS`, ASKED THROUGH
     * {@see OperatorAlerts::rangSince()}** — named in backticks rather than
     * `{@see}` because Pint turns one into a `use` and an enum of alert names has
     * no business importing a job. Without it the quiet window is the memory, and
     * a credential that is wrong for five days is a hundred and twenty texts about
     * one thing somebody already knows — 511 with a handset attached.
     *
     * ⛔ **IT SITS ON THE JOB THAT READS IT, WHICH IS WHERE
     * {@see PlatformHealthChecks}' SIBLING SITS AND WHERE THE MAIL BELL'S COULD
     * NOT.** That one moved to `OperatorAlerts` because two chokepoints forbid
     * any file in `app/` outside `PlatformMailer` from naming `DeliverPlatformMail`
     * at all; no such lint covers `AutopilotJob`, so the constant stays beside
     * `$tries` and `backoff()` — the two figures that decide when it is reached.
     *
     * ## ⛔ It cannot tell you how much was abandoned
     *
     * ⛔ **ONE BELL PER AUTOMATION PER WINDOW IS ONE BELL, NOT ONE COUNT.** Two
     * hundred abandoned invites and one abandoned invite raise the same row with
     * the same summary. The extent is on the run rows, and the row on the board
     * says so rather than leaving an operator to infer a scale from a bell that
     * has none.
     */
    case AutomationAbandoned = 'automation_abandoned';

    /**
     * The headline an operator reads first — on a phone, at 3am, out of context.
     *
     * `22`'s outcome language: it names what is wrong with the product, not
     * which class noticed. "Background jobs are failing" is actionable at a
     * glance; "FailedJobSpike" is a class name somebody has to translate.
     */
    public function headline(): string
    {
        return match ($this) {
            self::FailedJobSpike => 'Background jobs are failing',
            self::WebhookSignatureFailures => 'Webhooks are being rejected',
            self::HeartbeatSilent => 'Part of the platform has stopped running',
            self::VendorErrorRate => 'A provider is failing',
            self::InboundVoiceMinutes => 'A number is taking an unusual amount of call time',
            self::PixelMonthlyCapReached => 'A tenant has reached its monthly pixel event cap',
            self::PixelIngestRejects => 'A tenant\'s website is not listed, so their pixel is collecting nothing',
            self::PixelCanaryHalted => 'A pixel bundle update was halted automatically',
            // ⚠️ IT NAMES THE LOSS AND THE FACT THAT IT IS FINAL, AND THE
            // SECOND HALF IS THE ONE THAT CHANGES WHAT SOMEBODY DOES. Read on a
            // phone at 3am beside the two lines above, both of which are also
            // about the pixel, *"the archive is failing"* reads as a backup job
            // somebody can look at on Monday. What an operator has to know is
            // that the queue has already finished with these beacons, the
            // browsers that sent them answered `204` and moved on, and every
            // hour this stays true is more visitor data that no replay can ever
            // rebuild.
            self::PixelArchiveFailed => 'Website visitor events are being accepted and then lost, and nothing will retry them',
            self::GbpGrantOutstanding => 'A deleted customer\'s Google listing is still connected to us',
            self::TenantErasureFailed => 'An account we were told to erase was not erased',
            self::ScheduledRunOverranLock => 'A scheduled task outlived its own overlap lock',
            // ⚠️ IT NAMES THE CONSEQUENCE AND NOT THE EXIT CODE, AND THE
            // SECOND HALF IS WHAT SEPARATES IT FROM THE LINE ABOVE. Read on a
            // phone at 3am the two are one word apart — both begin *"a
            // scheduled task"* — and they send somebody to two different
            // places: one to widen a window over a command that ran, this one
            // to find out why a command did not. **What an operator has to know
            // is that something the platform does on a timer did not get done**,
            // which is true whether it exited non-zero or never started.
            self::ScheduledRunFailed => 'A scheduled task failed and the work it does did not happen',
            self::GbpBindingMismatched => 'A Google listing may belong to a different customer than our records say',
            // ⚠️ NAMES WHAT IS DIFFERENT ABOUT IT FROM THE TWO LINES ABOVE.
            // Read on a phone at 3am, all three are about a Google listing
            // and a customer — and they send somebody to three different
            // places. That one is a former customer we can name; the one
            // above it is a live customer attributed wrongly. **This one
            // names nobody, because not knowing whose is the finding.**
            self::GbpOrphanedAccounts => 'Google listings are connected to us that nothing here can account for',
            self::DeliveryReceiptsSilent => 'Delivery reports have stopped, so the automatic sending halt cannot fire',
            // ⚠️ NAMES WHAT IS DIFFERENT ABOUT IT, NOT WHAT IT SHARES. Read on a
            // phone at 3am beside the line above, the two have to send somebody
            // to two different places — the vendor's notification profile, or
            // the numbers those particular accounts are sending on.
            self::TenantDeliveryReceiptsSilent => 'Some accounts are getting no delivery reports, so their sending halt cannot fire',
            // ⚠️ IT NAMES THE STATE AND THE THING THAT DOES NOT HAPPEN NEXT.
            // Read on a phone at 3am, *"sending has stopped"* alone invites
            // waiting to see whether it clears; nothing here ever clears it, and
            // the half of this sentence that changes what somebody does is the
            // second half.
            self::PlatformSendingHalted => 'Sending has stopped for every business, and only a person can start it again',
            // ⚠️ IT NAMES BOTH HALVES, AND THE SECOND HALF IS THE ONE THAT
            // CHANGES WHAT SOMEBODY DOES. *"A bell has stopped paging you"*
            // alone reads as housekeeping; the thing an operator has to know at
            // 3am is that the silence after it is not the problem going away.
            self::PagerBudgetSpent => 'A bell has stopped paging you and is still ringing',
            // ⛔ IT NAMES THE CAUSE, BECAUSE THE EFFECT IS ALREADY TAKEN. Read on
            // a phone at 3am, *"sending has stopped for every business"* is the
            // line above and sends somebody to `Admin\SendingControls` to
            // release a halt that was never thrown. The half that changes what
            // they do is **why** — and it is deliberately the same words a
            // tenant is already seeing on their own screen
            // (`SendRefusalReason::ownerSentence()`), so an owner forwarding
            // that sentence and this bell are recognisably one incident.
            self::SuppressionsUnreadable => 'Every send is refused: our record of who asked us to stop cannot be read',
            // ⛔ IT NAMES THE PART OF THE PRODUCT, NOT THE KEY. Read on a phone
            // at 3am, `authorize_net_transaction_key` is a string somebody has
            // to translate; the summary carries it one line later, along with
            // which of the two faults this is. ⚠️ AND IT SAYS *"WORK HAS ALREADY
            // BEEN REFUSED"* RATHER THAN *"IS NOT CONFIGURED"*, because the
            // second reads as housekeeping an operator can leave until Monday
            // and this bell only exists at all because somebody was turned away.
            self::PlatformCredentialUnusable => 'Work has already been refused because a vendor credential cannot be used',
            // ⛔ IT CLAIMS ONLY WHAT BOTH ARMS CARRY. The configuration arm
            // knows the whole platform is stopped; the transport arm cannot
            // tell an authentication failure that stops everything from one
            // rejected recipient. *"No email is leaving this platform"* would
            // have been true of the fault that minted this case and false of
            // its sibling, on a line read at 3am — see this case's docblock.
            // ⚠️ AND THE SECOND HALF IS THE ONE THAT CHANGES WHAT SOMEBODY
            // DOES. *"An email was not delivered"* reads as a bounce somebody
            // can look at on Monday; the thing an operator has to know is that
            // the queue has already finished with it and nobody will try again.
            self::PlatformMailUndeliverable => 'Email this platform sent was not delivered, and nothing will retry it',
            self::WebhookKeysUnavailable => 'A webhook endpoint cannot check who is calling it',
            self::AutomationAbandoned => 'An automation gave up',
        };
    }

    /**
     * Which bell to answer first when two of them rang in the night.
     *
     * ⛔ **THE QUESTION IS NOT "WHICH IS WORSE"** — every kind here was argued
     * worth waking somebody for, so a ranking of badness would put nearly all of
     * them at the top and be decoration. ⚠️ **No count of the cases is written
     * here or anywhere in this slice** — two people counted this enum on one day
     * and disagreed, and it is one `cases()` away. The question this answers is
     * narrower and an operator can act on it: **is the thing it reports still
     * getting worse while nobody is looking, and can this operator do something
     * about it now?** `Alert` is yes to both. `Attention` is anything else — the
     * damage already happened and cannot be undone, or the meter has stopped, or
     * the next move belongs to a tenant, a vendor's support desk or a code
     * change in daylight.
     *
     * ⚠️ **`SignalState` RATHER THAN AN URGENCY ENUM OF ITS OWN**, because the
     * board already renders that vocabulary and a second three-valued scale
     * beside it would be two words for one idea on one screen. ⛔ **Two of its
     * four cases can never be returned here and that is deliberate rather than
     * sloppy**: `Ok` is meaningless about a bell that has already rung, and
     * `Unknown` is *"we could not measure"*, where a kind's urgency is a
     * property of the kind and is always known. A caller may switch on the two
     * that occur and does not need a third arm.
     *
     * ⚠️ **THE `match` IS EXHAUSTIVE AND FAILS LOUDLY, WHICH IS THE SIDE TO BE
     * ON HERE.** A missing arm raises `UnhandledMatchError` — Larastan sees it
     * before a merge, and at runtime it would surface on the alert board rather
     * than being swallowed. That is not a new exposure: {@see self::headline()}
     * has always been reached from that same `render()`. ⛔ **The dangerous
     * spelling is the other one** — `OperatorAlerts::text()` calls `headline()`
     * inside a `catch (Throwable)`, so a missing arm there leaves *the bell mute
     * with a green suite and a row on the screen saying it rang*. Nothing calls
     * this method from inside that catch, and nothing should.
     *
     * ⚠️ **IT HAS A CONSUMER, AND NAMING IT IS THE POINT** — `CLAUDE.md`'s first
     * recurring failure is a control nothing reads.
     * `OperatorAlertBoard::ringing()` orders the *still ringing* section by this
     * answer before recency, and renders it as the row's own state word. A
     * `severity()` that only described would be
     * `PlatformHealthSignal::countsSuccesses()` a second time.
     *
     * ⚠️ **REPETITION STILL ESCALATES AND THIS DOES NOT REPLACE IT** (7152). The
     * board treats this as the **floor**: a kind that answers `Attention` and has
     * outlasted more than one quiet window is still shown as needing action,
     * because *"this has been true all night"* is a fact about the incident that
     * no property of the kind can know. {@see self::ScheduledRunOverranLock} is
     * the case that makes the pairing worth keeping — one overrun is a config
     * edit in the morning, the same entry overrunning every night is something
     * nobody is fixing.
     */
    public function severity(): SignalState
    {
        return match ($this) {
            // Still failing, and the fix is ours and immediate — a broken
            // payload shape, an expired credential, a stopped process, a
            // provider to route around. Every minute of delay is more work
            // dying, more events refused past the vendor's dead-letter horizon,
            // or more minutes billed by a carrier this application cannot
            // decline.
            self::FailedJobSpike,
            self::WebhookSignatureFailures,
            self::HeartbeatSilent,
            self::VendorErrorRate,
            self::InboundVoiceMinutes => SignalState::Alert,

            // ⛔ THE ARM THAT HAD TO ARGUE WITH THIS METHOD'S OWN DOCBLOCK.
            // "The meter has stopped" is listed above as an `Attention` reason,
            // and this kind is exactly a stopped meter — so the arm is written
            // out rather than folded in. What that clause describes is
            // `PixelMonthlyCapReached`: a measurement stops while the thing it
            // measures is harmlessly capped. **Here the meter stops and what it
            // GOVERNS keeps running.** Messages go on reaching members of the
            // public with 2102's automatic halt unable to fire, complaints go on
            // accruing at a carrier where we cannot see them, and every hour of
            // delay is more traffic sent blind. That is "still getting worse
            // while nobody is looking", and the first move is ours and immediate
            // — the delivery notification profile in the vendor's own console,
            // at 3am, without a deploy. Only if that profile is right does this
            // become a vendor's support desk, and by then somebody is awake.
            self::DeliveryReceiptsSilent,

            // ⛔ THE SAME ARM AND THE SAME REASON, AND IT IS NOT WEAKER FOR
            // NAMING FEWER ACCOUNTS. Every clause above holds tenant by tenant:
            // the meter has stopped for those accounts and what it governs keeps
            // running, their traffic keeps reaching members of the public over
            // the shared GOAIEZ 10DLC registration with 2102's per-tenant
            // containment unable to fire, and every hour of delay is more of it.
            // ⚠️ AND THE FIRST MOVE IS OURS AND IMMEDIATE HERE TOO — the number
            // and the campaign those accounts send on, in a vendor console, at
            // 3am, without a deploy. Ranking it below its sibling would be
            // ranking by blast radius, which this method's own docblock says is
            // the question it does not answer.
            self::TenantDeliveryReceiptsSilent => SignalState::Alert,

            // ⛔ THE ARM THAT HAD TO ARGUE WITH `PixelCanaryHalted` RATHER THAN
            // WITH THIS DOCBLOCK. That kind is `Attention` and it is also a
            // containment that fired automatically, so the shapes look identical
            // — and they are not. **A halted canary restores the previous good
            // bundle**: the product goes on working, one deploy is parked, and
            // nothing about it decays overnight. **A halted platform IS the
            // product not working**, for every tenant and every channel at once,
            // and it decays by the hour: every missed call that gets no text-back
            // is a caller who rang somebody else, and that lead does not come
            // back in the morning. ⚠️ AND IT NEVER SELF-CLEARS — the sweep that
            // threw the switch deliberately never writes `false` (2408), so
            // *"wait and see"* is a decision to leave every tenant stopped.
            // ⚠️ The second half of `severity()`'s question is the easy one: the
            // first move is ours, immediate, and on a screen, at 3am, with no
            // deploy.
            self::PlatformSendingHalted => SignalState::Alert,

            // ⛔ THE ONE THAT LOOKS LIKE A MORNING ITEM AND IS NOT. A
            // subprocessor holds read *and write* access on a former customer's
            // Google listing, the tenant record anybody would have revoked it
            // from is destroyed, and the console that ends it is open to an
            // operator right now.
            self::GbpGrantOutstanding => SignalState::Alert,

            // ⛔ AND THE ONE WITH A STATUTE ON THE OTHER SIDE OF IT. The
            // account still holds everything its owner asked us to destroy,
            // the request is due again tomorrow and will fail the same way, and
            // nothing here self-clears — so "wait and see" is a decision to
            // miss the deadline by another day. The first move is ours and
            // needs no vendor: the SQLSTATE is in the log beside the reference.
            self::TenantErasureFailed => SignalState::Alert,

            // The cap is doing its job, the canary already halted itself, the
            // overrun has already finished, and a tenant's unlisted website
            // needs that tenant. None of them is getting worse tonight, and
            // waking somebody for one is how the ones above stop being answered.
            self::PixelMonthlyCapReached,
            self::PixelIngestRejects,
            self::PixelCanaryHalted,
            self::ScheduledRunOverranLock => SignalState::Attention,

            // ⛔ THE ARM THAT HAD TO ARGUE WITH ITS OWN SIBLING ONE LINE UP AND
            // WITH `FailedJobSpike`, AND IT COMES OUT BELOW BOTH OF THE `Alert`
            // KINDS IT MOST RESEMBLES. *Is it getting worse while nobody is
            // looking* — the run that failed has already finished failing, and
            // the entry's own schedule is a fresh attempt: a five-minute entry
            // gets two hundred and eighty-eight more chances tonight and a
            // nightly one gets another tomorrow. Nothing decays in between the
            // way an unread suppression register or a stopped mail transport
            // does, where every minute is more work refused.
            //
            // ⛔ AND THE SECOND HALF IS THE ONE THAT DECIDES IT, WHICH IS
            // `AutomationAbandoned`'s REASONING ARRIVING FROM A DIFFERENT
            // DIRECTION. *Can this operator do something now* — almost never,
            // and here for a reason peculiar to this kind: on every BACKGROUND
            // entry — which is all but a handful — **both of the command's own
            // streams went to `/dev/null`**, so there is no error text anywhere
            // to read at 3am. ⚠️ **STATED AS THE PROPERTY AND NOT AS TWO
            // COUNTS** (11261): this said *"thirty-nine of the forty-four"* and
            // the schedule runs 46 of 51, and **what decides the operator's
            // first move is which half an entry is in, never how many are in
            // it.**
            // The first move is running the command by hand on the server in
            // daylight, and then usually a deploy.
            //
            // ⚠️ AND `Attention` IS NOT A SHRUG, WHICH IS THE SIBLING'S OWN
            // ARGUMENT. The board treats this as the floor and escalates on
            // repetition (7152): one failed nightly sweep is a morning item, and
            // the same entry failing every night for a week is something nobody
            // is fixing — a fact about the incident that no property of the kind
            // can know.
            //
            // ⛔ RANKING THE KIND BY ITS WORST SUBJECT WAS THE ALTERNATIVE AND
            // IT WAS REFUSED. `jobs:prune-failed` failing is a retention promise
            // `TableHorizons` goes on making in writing over a table holding
            // sign-in URLs with live tokens, and it decays by the day; forty-two
            // of its siblings do not. `severity()` answers about the kind, and a
            // kind ranked by its sharpest subject would put every scheduled
            // command above `GbpBindingMismatched` on the board.
            self::ScheduledRunFailed => SignalState::Attention,

            // ⛔ THE ARM THAT HAD TO ARGUE WITH THE TWO PIXEL KINDS DIRECTLY
            // ABOVE IT, WHICH ARE BOTH `Attention` AND ARE BOTH ABOUT THE SAME
            // FEATURE. *Is it getting worse while nobody is looking* — yes, and
            // more finally than anywhere else in this enum: `PixelIngestRejects`
            // loses nothing (the tenant's own website is unlisted and the events
            // were never ours to keep), and `PixelCanaryHalted` parks a deploy
            // while the previous bundle goes on working. **Here the events were
            // accepted, acknowledged with a `204`, and destroyed** — L0 is the
            // only durable copy this architecture has, `warehouse:replay` can
            // only rebuild from it, and the browser that sent them forgot the
            // request before the queue gave up. Every hour is more of it.
            //
            // ⚠️ AND THE SECOND HALF IS THE EASY ONE, WHICH IS WHY THIS IS NOT
            // ITS NEIGHBOURS. `PixelIngestRejects` needs the tenant who forgot
            // to list their website; there is nobody to wait for here. The fault
            // is an object store this platform configures — a `.env` line, a
            // bucket, a credential — with no vendor console, no deploy and
            // nobody else's permission, which is exactly the distinction
            // `severity()` draws against `GbpOrphanedAccounts`.
            self::PixelArchiveFailed => SignalState::Alert,

            // ⛔ AND THE CASE THIS SLICE MINTED RANKS ITSELF BELOW ITS OWN
            // SIBLING, WHICH IS THE ARGUMENT RATHER THAN AN OMISSION. A
            // mismatch is a contradiction between two records; nothing is
            // spending, leaking or degrading because of it, and 6767's ruling is
            // that a person reads the evidence and decides. The harm arrives
            // when somebody acts on the wrong attribution, so the one thing that
            // would make this worse is answering it half-awake.
            self::GbpBindingMismatched => SignalState::Attention,

            // ⛔ THE ARM 7394 RULED ON BEFORE THE CASE EXISTED, AND IT
            // ARGUES WITH ITS OWN SIBLING TWO LINES UP RATHER THAN WITH
            // THIS DOCBLOCK. `GbpGrantOutstanding` is `Alert` over a
            // subprocessor holding read and write on a former customer's
            // listing, and an orphan is the same access over a listing we
            // cannot even attribute — which reads as strictly worse until
            // the second half of this method's question is asked. **There,
            // the console that ends a recorded grant is open to an operator
            // right now and the record naming it is ours; here the account
            // reference is the vendor's and 4884 keeps it out of everything
            // this bell can reach**, so the move is to open a browser onto
            // somebody else's console in the morning. ⚠️ NOR IS IT GETTING
            // WORSE WHILE NOBODY LOOKS: the ladder is billed by the month,
            // so a night of it is cents, and the grant was already live
            // before the bell rang.
            self::GbpOrphanedAccounts => SignalState::Attention,

            // ⛔ THE ARM THAT LOOKS LIKE HOUSEKEEPING AND IS THE OPPOSITE OF IT.
            // What this reports is not the flooded kind — it is that the pager
            // has gone deaf on one, which is **definitionally** getting worse
            // while nobody is looking: every alert of that kind from now until
            // the rolling day turns is recorded and sent to nobody. ⚠️ AND THE
            // SECOND HALF OF THE QUESTION IS ANSWERED BY THE BOARD RATHER THAN
            // BY A DEPLOY: the operator cannot raise the allowance — it is a
            // constant, and 7830 refuses to make it a setting — but they can
            // open the screen, read what is actually flooding and act on that,
            // now, without a deploy. ⛔ RANKING IT `Attention` WOULD PUT IT
            // BELOW ITS OWN CAUSE ON THE BOARD IT IS TELLING SOMEBODY TO READ,
            // which is backwards: `PixelIngestRejects` is `Attention` because
            // one unlisted website needs its tenant, and this bell only exists
            // because that has stopped being one website.
            self::PagerBudgetSpent => SignalState::Alert,

            // ⛔ THE ARM WHERE BOTH HALVES OF THIS METHOD'S QUESTION ARE THE
            // EASIEST YES IN THE ENUM, AND IT STILL HAD TO ARGUE WITH ONE
            // NEIGHBOUR. *Is it getting worse while nobody is looking* — every
            // review invite, every missed-call text-back and every appointment
            // reminder is being refused right now, for every tenant, and each
            // one is a customer who rang somebody else; the count of stored
            // suppressions the operator would have to give up to `--accept-loss`
            // grows the whole time, because inbound STOPs keep landing under a
            // key that is not the one the old rows were written with. *Can this
            // operator do something now* — yes, and it is the cheapest fix on
            // this list: an `.env` line and `config:clear`, or one command, with
            // no deploy and no vendor console.
            //
            // ⚠️ THE NEIGHBOUR IS `PixelCanaryHalted`, WHICH IS `Attention` AND
            // IS ALSO A CONTAINMENT THAT FIRED. That one parks a deploy and the
            // product goes on working. This one **is** the product not working,
            // which is `PlatformSendingHalted`'s reasoning arriving at the same
            // answer through a different door — and unlike that one, nothing
            // here recorded an incident, so *"wait and see"* leaves no trace
            // that it was ever true.
            self::SuppressionsUnreadable => SignalState::Alert,

            // ⛔ THE ARM WHOSE FIRST HALF IS TRUE ONLY BECAUSE OF WHEN THIS
            // BELL RINGS. A credential being absent is not, in itself, "getting
            // worse while nobody is looking" — it is a settled state, and that
            // is the argument for `Attention`. **What is getting worse is the
            // work being turned away over it**, and this bell is raised from
            // nothing but a refused read: by the time it rings, an owner has
            // been shown a card form that is not there, or a queued job has
            // spent a retry, or a public visitor has been told the check could
            // not run. Every hour it stays true is more of that, and none of it
            // is retried later.
            //
            // ⚠️ THE SECOND HALF IS THE EASIER ONE AND IS WHY THIS IS NOT ITS
            // NEIGHBOUR: the fix is a paste into Ops or an `.env` line, with no
            // deploy, no vendor console and nobody else's permission — which is
            // exactly the distinction this method's docblock draws between
            // `Alert` and `GbpOrphanedAccounts`, where the next move is in
            // somebody else's browser in the morning.
            self::PlatformCredentialUnusable => SignalState::Alert,

            // ⛔ THE ARM WHOSE FIRST HALF IS TRUE FOR A REASON THE OTHER
            // `Alert` KINDS DO NOT SHARE: the thing getting worse is **the
            // evidence**. A failed platform email is not queued, not retried
            // and not reported to its recipient, so every hour this stays true
            // is another sign-in link, renewal notice and support reply that
            // silently did not arrive — and on the configuration arm it is
            // *every* message, which is the state production was in for five
            // days with three rows in `failed_jobs` and nothing else.
            //
            // ⚠️ THE SECOND HALF IS THE EASIEST IN THE ENUM AND IS WHY THIS IS
            // NOT `Attention`: the fix is an `.env` line, a `MAIL_MAILER`, or
            // one row in Ops — no vendor console, no deploy, nobody else's
            // permission. That is precisely the distinction this method's
            // docblock draws against `GbpOrphanedAccounts`, where the next move
            // is in somebody else's browser in the morning.
            //
            // ⚠️ AND IT HAD TO ARGUE WITH `PixelIngestRejects`, WHICH IS
            // `Attention` AND IS ALSO A DELIVERY FAILURE. That one needs the
            // tenant whose website is unlisted; there is nobody else to wait
            // for here, and the message that would have asked them is the one
            // that did not go.
            self::PlatformMailUndeliverable => SignalState::Alert,
            // ⛔ THE ARM WHOSE SUBJECT IS SOMEBODY ELSE'S OUTAGE AND IS STILL
            // `Alert`, WHICH IS THE OPPOSITE OF `ReconcileZernioAccounts`'
            // REFUSAL TO PAGE ABOUT ANOTHER COMPANY BEING DOWN (511 with a
            // handset attached). The difference is what is being lost while it
            // lasts: that sweep recovers everything on tomorrow's run, and this
            // does not recover anything at all. Every delivery arriving in the
            // meantime is answered and dropped — a bounce that never suppresses
            // an address, a complaint that never reaches the trip, a customer's
            // reply that never becomes a conversation — and none of it is
            // retried by us.
            //
            // ⚠️ AND THE FIRST MOVE IS OURS RATHER THAN THE VENDOR'S, WHICH IS
            // THIS METHOD'S OWN TEST. The commonest cause of a host we can
            // reach yesterday and cannot reach today is on this side of it:
            // egress, DNS, a proxy. Only if all of that is right does this
            // become somebody's status page.
            self::WebhookKeysUnavailable => SignalState::Alert,

            // ⛔ THE ARM THAT HAD TO ARGUE WITH `FailedJobSpike`, WHICH IS
            // `Alert` AND IS THE NEAREST THING TO IT ON THIS LIST. That one is a
            // **spike** — twenty-five failures inside an hour, which is a
            // platform coming apart while somebody sleeps. This is one
            // automation that has stopped trying, and the two halves of this
            // method's question both come out the other way. *Is it getting
            // worse while nobody is looking* — no: the work is already lost and
            // nothing decays overnight, because every automation this covers is
            // re-dispatched by its own schedule and the next pass is a fresh
            // dispatch with fresh attempts. *Can this operator do something now*
            // — almost never: the cause is a vendor, a credential or a bug, and
            // the first move is reading a run row and then, usually, a deploy.
            //
            // ⚠️ AND THE PAIRING WITH REPETITION IS WHY `Attention` IS NOT A
            // SHRUG, WHICH IS `ScheduledRunOverranLock`'s OWN ARGUMENT. The board
            // treats this as the floor: one automation giving up is a morning
            // item, and the same automation giving up every night is something
            // nobody is fixing — and that is a fact about the incident that no
            // property of the kind can know.
            self::AutomationAbandoned => SignalState::Attention,
        };
    }
}
