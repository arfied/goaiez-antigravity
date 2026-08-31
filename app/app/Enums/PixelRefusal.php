<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why the collector did not archive a batch.
 *
 * `GOAIEZ_PIXEL_MASTER_BUILD` §11 orders the collector's gates and says of the
 * whole pipeline: *"Reject early and cheaply. Always 204, always <50 ms."* So
 * every refusal below returns the same empty success the pixel gets on the happy
 * path — the client is a script on somebody else's website and there is nothing
 * useful it could do with an error.
 *
 * ⛔ **THAT IS EXACTLY WHY THIS ENUM EXISTS.** A pipeline whose every outcome is
 * `204 No Content` is one where "working" and "silently dropping every request"
 * are the same observation from outside. §11 row 2 asks for `ingest_rejects` and
 * §11's own wording is *"Rejects captured, never silent"*. What is built for
 * **every** refusal is a named reason on this enum plus a structured log line
 * carrying the reason, the business id where one is known, and **nothing
 * else** — a count, not a record. ⚠️ **`ingest_rejects` ITSELF IS NOW BUILT
 * (decision 5000s), AND IT IS NARROWER THAN THIS ENUM ON PURPOSE**: it holds a
 * queryable row only for {@see OriginNotAllowed}, the one refusal §11's own
 * schema names a reason for (`origin`) and the one shape most likely to mean a
 * real, mis-installed tenant rather than an anonymous stranger probing the
 * endpoint. See `App\Services\Pixel\IngestRejects`'s docblock for the rest of
 * the argument — recording a row per dropped-at-cap event would turn a
 * 500,000-event free-tier cap into 500,000 database rows, and every other
 * refusal here is already observable in the log line this file's docblock
 * already promises.
 *
 * ⚠️ **NO CASE HERE MAY EVER CARRY A PAYLOAD, A KEY, AN ADDRESS OR AN ORIGIN.**
 * This is the diagnostic surface for an endpoint that anonymous browsers on
 * third-party pages post to, so anything it records is a log of visitor
 * behaviour by another name. The reason is a fixed vocabulary and the vocabulary
 * is this file.
 */
enum PixelRefusal: string
{
    /**
     * The body was not a JSON object of the shape the collector accepts, or was
     * larger than the endpoint will read.
     *
     * ⚠️ **NOT THE SAME AS A MALFORMED *PAYLOAD*, WHICH IS ARCHIVED.** L0 is
     * faithful to a broken client (decision 4874), so a batch whose `events`
     * array holds nonsense still lands and becomes a reject downstream. What is
     * refused here is a body the collector cannot even find a public key in.
     */
    case Unreadable = 'unreadable';

    /**
     * §11 row 1: *"Validate `data-k` → tenant. Unknown → 204 drop."*
     *
     * ⚠️ **THE 204 IS THE POINT AND A 404 WOULD BE A DEFECT.** Answering
     * differently for a real key than for an invented one turns this endpoint
     * into an oracle that confirms a guessed key, on an unauthenticated route
     * with no cost to the guesser.
     */
    case UnknownKey = 'unknown_key';

    /**
     * §11 row 2: *"Origin must match `tenants.domains`. Mismatch → 204 drop."*
     */
    case OriginNotAllowed = 'origin_not_allowed';

    /**
     * ⛔ **THE HIPAA GATE, HALF ONE: THE BUSINESS.**
     *
     * `29` §2 rule 24 routes a `Phi` tenant's data to a separate schema, a
     * separate role and **a separate KMS key**, and `CLAUDE.md` puts that key
     * behind Stage 3 — *"rule 24's KMS key and collector enforcement wait on
     * Stage 3"*. So there is nowhere for a covered entity's traffic to land, and
     * the fail-closed reading is to refuse it rather than write it under the
     * ordinary key to a differently-named prefix.
     *
     * ⚠️ **A BAA DOES NOT LIFT THIS, AND THAT IS NOT AN OVERSIGHT.** §15's
     * ladder ends *"ONLY NOW `form_value_capture` = 'full'"*, so an executed
     * agreement governs **values**; what is missing here is the *key*, which no
     * signature creates. Reading `BaaRecords::isExecutedFor()` at this gate would
     * make an agreement look like it unlocked storage that does not exist — the
     * protection-asserted-before-it-is-true failure (314–316).
     */
    case HealthTenant = 'health_tenant';

    /**
     * ⛔ **THE HIPAA GATE, HALF TWO: THE PAYLOAD.**
     *
     * §11 row 9: *"If `data_classification='phi'` and
     * `form_value_capture='schema_only'`: strip all form field values here,
     * server-side, before the queue."* This refuses the batch instead of
     * stripping it, and refuses it for **every** business rather than only a
     * `Phi` one. Both departures are argued in
     * [[\App\Services\Pixel\PixelCollector]].
     *
     * ⚠️ **THE ENDPOINT IS ANONYMOUS, SO THE BUNDLE'S GUARANTEE IS NOT THE
     * COLLECTOR'S.** `PixelTest`'s form-value lint proves that
     * the pixel bundle reads no value; it proves nothing about what arrives
     * here, because anyone holding a public key can post whatever they
     * like. This is the server-side half rule 24 asks for.
     */
    case FormValuePresent = 'form_value_present';

    /**
     * §11 row 3's limiter refused it.
     *
     * ⚠️ Recorded for completeness; the limiter answers before the collector
     * runs, so this case is produced by the route middleware's own response and
     * never by [[\App\Services\Pixel\PixelCollector::receive()]].
     */
    case RateLimited = 'rate_limited';

    /**
     * §11 row 4: *"Monthly cap (`free_event_cap_monthly`). At cap: pageviews
     * continue, others dropped."* Decision 5000s.
     *
     * ⚠️ **THIS IS A BATCH-LEVEL REFUSAL, NOT A PER-EVENT DROP** — see
     * `App\Services\Pixel\MonthlyEventCap`'s docblock for why the payload's
     * archive-as-opaque-string rule makes a per-event strip impossible here for
     * the same reason it is impossible at the HIPAA gate. A batch made only of
     * `pageview` events is admitted at the cap and never produces this case.
     */
    case MonthlyCapReached = 'monthly_cap_reached';

    /**
     * §11 row 7: the browser asserted a real, request-level Global Privacy
     * Control signal and the batch did not honour it. Decision 5000s.
     *
     * ⚠️ **THE SERVER-SIDE CHECK THAT DOES NOT TRUST THE CLIENT'S OWN STAMP.**
     * §10 already has `pixel.js` resolve consent in-page and stamp
     * `consent_state` on every event — `gpc_optout` when `navigator.
     * globalPrivacyControl` is true, restricted to a bare pageview with no
     * identifier. §11 row 7 is the server independently verifying that against
     * the one GPC signal it can see for itself: the `Sec-GPC` request header,
     * which a page cannot set and a browser adds on the person's own
     * preference. A batch that arrives under `Sec-GPC: 1` but claims a
     * different consent state, or carries more than the bare pageview §10
     * promises, is refused whole — see `App\Services\Pixel\PixelCollector::
     * gpcViolation()`.
     *
     * ⚠️ **NOT THE UNIVERSAL HASH-SUPPRESSION LIST §18 ALSO DESCRIBES.** That
     * list answers "has this identity opted out everywhere", and its input is
     * an identity this collector does not resolve — §13's identity resolution
     * is unbuilt, and `PixelTest`'s own lint refuses this directory from
     * touching one. This case answers the narrower, request-level question a
     * `Sec-GPC` header can actually settle without one.
     */
    case GpcNotHonored = 'gpc_not_honored';

    /**
     * Whether this refusal is a compliance boundary rather than ordinary traffic
     * management.
     *
     * ⚠️ **USED FOR THE LOG LEVEL AND NOTHING ELSE.** A rate limit or a stray
     * origin is a fact about the internet; a covered entity, a form value or an
     * unhonoured GPC signal arriving is a fact somebody needs to see — the
     * first means a healthcare tenant has installed a pixel that will collect
     * nothing until Stage 3, the second means a client this application did not
     * write is posting personal data at it, and the third means a client is
     * not honouring a legally recognised opt-out signal. `MonthlyCapReached`
     * is deliberately not one of these three: it is an infrastructure-budget
     * event about traffic volume, not a compliance one.
     */
    public function isComplianceBoundary(): bool
    {
        // ⛔ **EXHAUSTIVE RATHER THAN A `===` DISJUNCTION, ON
        // `TenantDeletionOutcome::destroyed()`'s CONVENTION** (5148) — *"so that
        // a further case cannot be added without deciding this question"*. A
        // disjunction answers `false` for a case nobody thought about, so a new
        // compliance refusal would drop to `Log::info` and be indistinguishable
        // from a rate limit, in the one line anybody ever reads to find out that
        // it fired. ⚠️ **Two lanes added a case to this enum in a single wave**,
        // which is what makes the silent default the likely outcome rather than
        // the theoretical one.
        return match ($this) {
            self::HealthTenant, self::FormValuePresent, self::GpcNotHonored => true,
            self::Unreadable, self::UnknownKey, self::OriginNotAllowed,
            self::RateLimited, self::MonthlyCapReached => false,
        };
    }
}
