<?php

declare(strict_types=1);

namespace App\Services\Pixel;

use App\Enums\DataClassification;
use App\Enums\PixelRefusal;
use App\Jobs\ArchivePixelBatchJob;
use App\Models\Business;
use App\Services\Widgets\WidgetPlugins;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * `GOAIEZ_PIXEL_MASTER_BUILD` §11's collector pipeline — the writer L0 never had.
 *
 * ⚠️ **THE BUNDLE IS NEVER NAMED BY PATH ANYWHERE IN THIS DIRECTORY**, and that
 * is not squeamishness — `PixelTest`'s delivery tripwire (4571) fails the build
 * when any file under `app/`, `routes/` or `resources/views/` spells it, because
 * that is how a *delivery* would look. `L0Receipt` records the same, having been
 * caught by it, and so was this docblock. The lint cannot tell a reference from a
 * mention, and the rule stands.
 *
 * ⛔ **THE JUSTIFICATION FOR IT DID NOT, AND SAYING SO IS THIS SLICE'S OWN
 * SUBJECT — CORRECTED 2026-08-22 (7984).** That sentence ended *"which is the
 * right trade while §10's canary and one-command rollback are unbuilt"*, and
 * both were built on 2026-08-18: `pixel:publish`, `pixel:rollback` and
 * `pixel:watch-canary` are commands in this repository (4980–4998). ⚠️ **The
 * lint is unchanged and the trade is still right** — a path spelled in this
 * directory is still how a second delivery would look — but the reason is that
 * there is exactly one delivery seam, not that there is none.
 *
 * ⛔ **THIS IS THE HEAD OF THE CHAIN DECISION 4861 SAYS WAS MISSING.** W15 built
 * byte-identical replay and then stated the honest consequence: *"nothing writes
 * L0 because the collector is unbuilt, so in production every range is empty and
 * every replay is a no-op"* — 272's shape at the top of a five-part chain
 * (4580). This is part 1. ⚠️ **Read [[\App\Contracts\L0Archive]] before changing
 * anything here**: L0 is append-only, so a byte written wrongly is wrong for as
 * long as the archive lives. ⛔ **AND THAT IS LONGER THAN THIS DOCBLOCK USED TO
 * SAY** — it read *"append-only for seven years"*, which is §5.2's sentence and
 * not this application's behaviour: **nothing expires L0 at seven years or at
 * any other number** (7706). [[\App\Services\Warehouse\ObjectStoreL0Archive]]
 * carries the whole finding.
 *
 * ---------------------------------------------------------------------------
 * §11's ORDER, KEPT AS §11 WRITES IT
 * ---------------------------------------------------------------------------
 * *"Ordered. Reject early and cheaply. Always 204, always <50 ms."* The gates
 * below run in the specification's own sequence and are numbered against it,
 * because `docs/FAILURE-SHAPES.md`'s *"Verify a vendor string, price or
 * parameter against the raw artefact"* rule applies to our own documents as
 * much as to a vendor's — decision 4573 is what happens when it
 * is not.
 *
 *   1. public key → business ............ [[PixelRefusal::UnknownKey]]
 *   2. origin allowlist ................. [[PixelRefusal::OriginNotAllowed]],
 *                                         plus [[IngestRejects]] — decision 5000s
 *   3. rate limit ....................... route middleware, not here
 *   4. monthly cap ...................... [[MonthlyEventCap]],
 *                                         [[PixelRefusal::MonthlyCapReached]] —
 *                                         decision 5000s
 *   5. `event_id` idempotency ........... the derived layer's primary key —
 *                                         **`(business_id, event_id)` since
 *                                         2026-08-20 (6240), so the idempotency
 *                                         is within a tenant and a colliding id
 *                                         from another one no longer discards
 *                                         this batch's event** — through
 *                                         [[\App\Services\Warehouse\L1Loader]]
 *                                         and not here. ⚠️ The table is named by
 *                                         that class and deliberately not by this
 *                                         one — `WarehouseTest`'s *"nothing
 *                                         outside the warehouse writes to a
 *                                         derived layer"* lint matches the literal
 *                                         in raw file contents, comments included,
 *                                         and it caught this docblock. Adding an
 *                                         exception for a mention would be 511's
 *                                         failure; rewording costs nothing
 *   6. bot score ........................ `L1Derivation`, flag never drop
 *   7. opt-out / GPC suppression ........ [[PixelRefusal::GpcNotHonored]] —
 *                                         decision 5000s, see gpcViolation()
 *   8. enrich, `ip_hash` ................ [[PixelEnrichment]] — decision 5000s,
 *                                         computed by the caller and carried
 *                                         through, never recomputed here
 *   9. **the HIPAA gate** ............... [[PixelRefusal::HealthTenant]] and
 *                                         [[PixelRefusal::FormValuePresent]].
 *                                         ⛔ **HALF (a) DOES NOT RUN NINTH — IT
 *                                         RUNS BEFORE ROW 2** (decision 5000s),
 *                                         because rows 2 and 4 now write, and a
 *                                         tenant refused outright must be
 *                                         refused before anything is recorded
 *                                         about it. Half (b) stays here
 *  10. write L0, enqueue ................ [[ArchivePixelBatchJob]]
 *
 * ⚠️ **FOUR ROWS WERE NOT BUILT AND ARE NOW BUILT, IN THE SHAPE ARGUED BELOW
 * RATHER THAN §11's OWN WORDING TAKEN LITERALLY** (decision 5000s):
 *
 *  - **Row 2's `ingest_rejects`.** §11 row 2's own text: *"Mismatch → 204 drop +
 *    `ingest_rejects`."* [[IngestRejects]] is the one writer, and the creating
 *    migration argues why the table it writes is narrower than §5.7's own
 *    schema — a mismatched origin only, not every refusal this enum names.
 *  - **Row 4, the monthly cap.** This schema still has no `tenant_usage` table
 *    and rule 43's dollar cap is still deleted (3293) — neither changed. What
 *    changed is the ruling: §11 row 4 is a **volume** cap on the free-tier
 *    infrastructure budget of §2.1, never billed and never touching
 *    `credit_ledger`, so 3297's *"a path with no debit does not look uncapped,
 *    it looks free"* does not apply to it — it was never a money path.
 *    [[MonthlyEventCap]] carries the full argument, including why it is a
 *    **batch**-level refusal rather than a per-event drop.
 *  - **Row 7, opt-out and GPC suppression at ingest.** The universal hash
 *    suppression list decision 4573 named is still unbuilt and still needs an
 *    identity this collector does not resolve — that half is genuinely still
 *    owed. What is built is narrower and does not need one: the **`Sec-GPC`
 *    request header**, which a page cannot set and a browser adds on the
 *    person's own preference, cross-checked against what the batch claims.
 *    See [[gpcViolation()]].
 *  - **Row 8, enrichment and `ip_hash`.** Built as a genuine schema version
 *    bump — `config('warehouse.schema_version')` is now 2,
 *    `App\Services\Warehouse\L0Line::keysFor()` versions the line shape, and
 *    the derived event table carries `ip_hash`/`browser`/`browser_version`/
 *    `os`.
 *    [[PixelEnrichment]] is the one function in `App\Services\Pixel` permitted
 *    to read a raw address, and it discards it in the same function scope —
 *    see that class's docblock. Geo/ASN is deliberately still unbuilt: no
 *    GeoLite2 database exists in this environment, verified rather than
 *    assumed, and `App\Contracts\GeoIpLookup` is the seam a real
 *    implementation binds to rather than a column this application would have
 *    to fill with "unknown" for ever.
 *
 * ---------------------------------------------------------------------------
 * ⛔ THE HIPAA GATE — WHAT IT ENFORCES AND WHY IT REFUSES RATHER THAN STRIPS
 * ---------------------------------------------------------------------------
 * §11 row 9 reads: *"If `data_classification='phi'` and
 * `form_value_capture='schema_only'`: strip all form field values here,
 * server-side, before the queue."* §15 puts the same rule the other way round —
 * *"ENFORCED AT COLLECTOR (§11.9)"* — and adds the sentence that decides the
 * shape of this class: *"The gate is server-side because a cached script or
 * manual install would otherwise land PHI before the BAA exists."*
 *
 * **Two refusals, and neither is a strip.**
 *
 * **(a) A `Phi` business is refused outright.** Not stripped, not routed to a
 * `class=phi/` prefix. §5.2 requires PHI to land under a separate prefix **with a
 * separate KMS key**, `CLAUDE.md` puts that key behind Stage 3, and
 * `ObjectStoreL0Archive::store()` already throws on a `Phi` batch for exactly
 * that reason (decision 4863). ⚠️ **So this gate is not the archive's guard
 * repeated** — it is what stops every covered entity's request becoming a 500
 * from a service that promised *"always 204"*, and it is where the refusal can be
 * named and observed. 4580 recommended precisely this reading and called it a
 * recommendation rather than a ruling; 4863 then precedented it one layer down.
 *
 * ⚠️ **AN EXECUTED BAA DOES NOT LIFT IT.** What §15's ladder unlocks is
 * `form_value_capture = 'full'`; what is missing here is the key, and no
 * signature creates one. Wiring `BaaRecords::isExecutedFor()` into this gate
 * would make an agreement appear to unlock storage that does not exist — 314–316,
 * whose fourth instance (4606) is a security defect precisely because the
 * paragraph explaining the hazard is what stopped the reviewer looking.
 *
 * **(b) A form value is refused for EVERY business, not only a `Phi` one**, and
 * the batch is dropped rather than sanitised. Three reasons, in order of weight:
 *
 *  1. ⛔ **THE PAYLOAD IS ARCHIVED AS AN OPAQUE STRING AND MAY NEVER BE
 *     RE-ENCODED** (decision 4874). A strip means decode, mutate, re-encode —
 *     which changes key order, float rendering and unicode escaping, and destroys
 *     the one property L0 exists to have. **There is no way to strip and stay
 *     faithful**, so the choice is refuse or store. ⚠️ **The same reasoning is
 *     why [[MonthlyEventCap]] refuses a whole batch rather than dropping some of
 *     its events** — see that class's docblock.
 *  2. `form_value_capture` is `schema_only` **platform-wide** today, not per
 *     tenant: §14's storage split puts values under the identity DEK, §13's
 *     identity resolution is unbuilt, and the KMS key is Stage 3. Nobody can be
 *     at `full`, so a per-tenant branch would be a branch with one reachable arm
 *     — 256's vacuity wearing a condition.
 *  3. **The endpoint is anonymous.** `PixelTest`'s form-value lint proves the
 *     *bundle* reads no value; anybody holding a public key can post anything.
 *     A `Pii` tenant is not a licence to archive a card number or a date of birth
 *     for ever — nothing expires L0 (7706) — and §14 is explicit that card
 *     data must never reach storage at all.
 *
 * ⚠️ **WHAT (b) CANNOT DO, SAID PLAINLY** (352, 397). It is an allowlist over the
 * `form_submitted` field schema and a denylist for a `value` key at any depth. A
 * client that posts a patient's name under `properties.q1` defeats both, and no
 * server-side check over an arbitrary JSON document can catch that. The
 * containment that actually holds is that **our** bundle reads nothing and this
 * is its server-side backstop; the honest claim is *"a value-shaped field cannot
 * arrive by accident, or by a client copying our own schema"*, never *"PHI cannot
 * reach L0"*.
 */
final readonly class PixelCollector
{
    /**
     * The exact keys a `form_submitted` field entry may carry.
     *
     * ⛔ **AN ALLOWLIST, NOT A BLOCKLIST, AND THE DIRECTION IS THE CONTROL.**
     * These four are what the pixel bundle emits and are what §14's
     * *"abandonment from events, not values"* asks for. A blocklist of forbidden
     * names would pass `{"name":"ssn","v":"…"}`; this refuses anything it was not
     * told to expect, which is the only version that survives a client we did not
     * write.
     *
     * ⚠️ **`missing` IS READ WITH `required`, NEVER ALONE** (decision 4578) —
     * `missing: false` on an optional field means *unknown*, not *filled*.
     * Nothing here reads either; the note is for whoever derives them.
     *
     * ⛔ **THIS IS AN ALLOWLIST OVER THE KEYS OF A FIELD ENTRY AND NEVER OVER
     * THE STRING THE `name` KEY HOLDS — CENSUSED AND LEFT AS A NAMED, ARGUED
     * GAP, NOT A REFUSAL, 2026-08-27 (wave 39 lane D, `docs/DECISIONS.md`
     * 10750s).** `record('form_submitted', …)`'s `name` is `input.name`,
     * verbatim, chosen entirely by the tenant's own HTML — nothing here, in
     * `L1Derivation::rows()`, or anywhere `properties` is later read validates,
     * truncates or inspects that string. A real business's own booking form can
     * spell it `patient_dob`, `ssn` or `diagnosis_code`, and it passes this
     * allowlist exactly as `email` does, is archived to L0 **for ever** (no
     * expiry exists at any horizon — this class's own docblock, 7706), and
     * survives in `l1_events.properties` for `WarehouseRetention::L1_RETENTION_DAYS`
     * (400) days, read by nothing but `L1Derivation` and this class's own
     * derivation logic. `tests/Feature/Pixel/PixelIngestToReplayTest.php`'s
     * *"a form field name travels the whole pipeline unfiltered …"* test pins
     * exactly this, end to end.
     *
     * ⚠️ **EVERY MITIGATION CONSIDERED WAS REJECTED, NOT OVERLOOKED.** A
     * blocklist of alarming names repeats the failure this docblock's own
     * paragraph above already argues against — an incomplete list masquerading
     * as a control (decision 511). Hashing or truncating `name` defeats the
     * feature this constant exists for (§14's per-field abandonment signal
     * needs the actual label, not a digest) while doing nothing about a short,
     * ordinary name like `dob` or `ssn`, which is the realistic case. Refusing
     * the event type outright discards a capability a wave-38 scout confirmed
     * is shipped end to end. **None of these shipped.**
     *
     * ⚠️ **THIS IS THE CLASSIFICATION-LAG GAP, SEEN THROUGH A MORE LEGIBLE
     * SYMPTOM.** `App\Services\Compliance\TenantClassification` is deliberately
     * under-inclusive, and `PhiExclusion` said so before it was deleted with
     * `29` §2 rule 24 on 2026-08-30 (12539) —
     * *"it cannot see a business nobody has reclassified … a covered entity
     * whom nothing has flagged is `pii` to every layer of this system."* A
     * field named `patient_dob` on a `Pii`-classified tenant is not a new hole;
     * it is that same classification lag, made visible in the one place a
     * human reading the raw archive would recognise it. The gate that already
     * exists for a **correctly** classified `Phi` business — refusal outright,
     * above, before row 2 — is the fix; nothing new closes the lag itself, and
     * that is the owner's call, not a night lane's.
     *
     * @var list<string>
     */
    public const array FORM_FIELD_KEYS = ['name', 'type', 'required', 'missing'];

    /**
     * The event type whose properties carry a field schema.
     */
    public const string FORM_EVENT = 'form_submitted';

    /**
     * The one event type permitted to carry a `value` key, and its exact shape.
     *
     * ⚠️ Read off the bundle: `record('vital', { metric: …, value: … })`. See
     * [[isWellFormedVital()]] for why this is an allowlist rather than an
     * exemption keyed on a type the client chooses.
     */
    public const string VITAL_EVENT = 'vital';

    /** @var list<string> */
    public const array VITAL_PROPERTY_KEYS = ['metric', 'value'];

    /** @var list<string> */
    public const array VITAL_METRICS = ['LCP', 'INP', 'TTFB', 'CLS'];

    /**
     * §10's own default consent state, and the one every event must carry
     * under a genuinely honoured `Sec-GPC` signal.
     */
    public const string GPC_CONSENT_STATE = 'gpc_optout';

    /**
     * The exact `Sec-GPC` header value the Global Privacy Control spec defines
     * as asserted. Anything else — absent, `0`, malformed — is not a signal.
     */
    public const string GPC_HEADER_ASSERTED = '1';

    public function __construct(
        private PixelKeys $keys,
        private WidgetPlugins $plugins,
        private IngestRejects $rejects,
        private MonthlyEventCap $cap,
        private PixelDeliveryHealth $health,
    ) {}

    /**
     * Run §11's gates over one received batch and archive it, or name the refusal.
     *
     * ⚠️ **`$rawBody` IS WHAT IS ARCHIVED AND `$payload` IS ONLY EVER INSPECTED.**
     * They are the same bytes twice — one opaque, one decoded — and keeping them
     * apart is what makes the archive faithful (4874). A future edit that
     * re-encodes `$payload` into the receipt is the single most damaging thing
     * that can be done to this file and it would look like a tidy-up.
     *
     * @param  array<string, mixed>  $payload
     * @param  string|null  $gpc  The raw `Sec-GPC` request header, or null when
     *                            absent. Decision 5000s — see [[gpcViolation()]].
     */
    public function receive(
        array $payload,
        string $rawBody,
        ?string $origin,
        PixelEnrichment $enrichment,
        ?string $gpc = null,
    ): ?PixelRefusal {
        // ⚠️ UNCONDITIONALLY FIRST, AND `ResolveWidget` STATES THE SAME INVARIANT
        // FOR THE SAME REASON. The tenant lives in a PostgreSQL *session*
        // variable that outlives a request whenever a connection is reused, so an
        // early return without clearing would let this request inherit a tenant
        // it has no claim to — and here that would mean archiving one business's
        // visitors under another's prefix, in an append-only store.
        Tenancy::forgetAll();

        // §11 row 1.
        $key = $payload['k'] ?? null;

        if (! is_string($key)) {
            return $this->refuse(PixelRefusal::Unreadable, null);
        }

        $business = $this->keys->resolve($key);

        if (! $business instanceof Business) {
            return $this->refuse(PixelRefusal::UnknownKey, null);
        }

        $businessId = (int) $business->getKey();

        Tenancy::set($businessId);

        // §11 row 9, half (a). Read the class docblock before touching this.
        //
        // ⛔ **THIS RUNS BEFORE EVERY GATE THAT WRITES SOMETHING, AND THE ORDER
        // IS THE POINT — decision 5000s.** §11 numbers the HIPAA gate row 9 and
        // the first draft of this lane obeyed that literally, leaving it below
        // the origin allowlist and the monthly cap. Both of those now **record**
        // — an `ingest_rejects` bucket and a `pixel_monthly_usage` counter — so
        // a covered entity whose traffic this application refuses **outright**
        // would still have accumulated rows describing when, and from which
        // website, its patients' browsers reached it, and would have had its
        // event cap consumed by traffic that was never archived. Neither row is
        // PHI, and `CLAUDE.md`'s ambiguity rule still points one way: *less
        // stored PII*. **A tenant refused wholesale must be refused before
        // anything is written about it.** Moving this back down is a regression
        // a green suite would not catch on its own, which is why a test drives
        // the absence of both rows rather than only the returned refusal.
        if ($business->data_classification === DataClassification::Phi) {
            return $this->refuse(PixelRefusal::HealthTenant, $businessId);
        }

        // §11 row 2. See originIsAllowed() for what this buys and what it does
        // not — it refuses a browser on an unlisted site and refuses nothing
        // else, because `Origin` is trivially set by anything that is not one.
        if (! $this->originIsAllowed($origin)) {
            // §11 row 2's own text: "Mismatch → 204 drop + ingest_rejects."
            // Decision 5000s — see [[IngestRejects]] for why this is the one
            // refusal that gets a queryable row rather than only a log line.
            $this->rejects->record(PixelRefusal::OriginNotAllowed, $origin);

            return $this->refuse(PixelRefusal::OriginNotAllowed, $businessId);
        }

        // §11 row 4 — decision 5000s. See [[MonthlyEventCap]] for the whole
        // argument, including why this is a batch-level admission rather than
        // a per-event drop.
        if (! $this->cap->admit($this->eventCount($payload), $this->isPageviewOnly($payload))) {
            return $this->refuse(PixelRefusal::MonthlyCapReached, $businessId);
        }

        // §11 row 7 — decision 5000s. See [[gpcViolation()]].
        if ($this->gpcViolation($payload, $gpc)) {
            return $this->refuse(PixelRefusal::GpcNotHonored, $businessId);
        }

        // §11 row 9, half (b).
        if ($this->carriesFormValue($payload)) {
            return $this->refuse(PixelRefusal::FormValuePresent, $businessId);
        }

        // §10's canary auto-halt — decision 4980. ⚠️ AFTER EVERY REFUSAL ABOVE,
        // NEVER BEFORE: SendingHealth's "the denominator must have the same
        // membership as the numerator" (3032) applies here too, so a batch the
        // HIPAA gate drops must never be counted toward a canary's error rate.
        // Reads $payload, which is already decoded here — it never touches the
        // raw bytes ArchivePixelBatchJob carries, on decision 4874's rule.
        $this->health->observe($payload);

        // §11 row 10.
        //
        // ⚠️ **THE RECEIPT TIME IS DECIDED HERE AND NEVER AGAIN** — decision 4862.
        // It is receipt metadata, archived in L0, and every later derivation reads
        // it back rather than asking the clock; a job that stamped `now()` on
        // delivery would make the same batch derive differently depending on how
        // long the queue was.
        //
        // ⚠️ **AND THE BATCH ID IS MINTED HERE, NOT IN THE JOB**, which is what
        // makes a retry idempotent: `ObjectStoreL0Archive::store()` refuses an
        // overwrite and its own docblock names the collision it can actually
        // prevent — *"a re-run of the same batch, which is what a retried queue
        // job does"*. A job minting its own id would write a second object
        // carrying the same events on every retry, forever.
        //
        // ⚠️ **§11 ROW 8's ENRICHMENT TRAVELS HERE, ALREADY COMPUTED.** `$enrichment`
        // was built by the caller from the request that is about to go out of
        // scope; the job never sees the request and never reads an address.
        ArchivePixelBatchJob::dispatch(
            $businessId,
            (string) Str::uuid(),
            $rawBody,
            (string) Str::uuid(),
            CarbonImmutable::now(),
            $enrichment->ipHash,
            $enrichment->browser,
            $enrichment->browserVersion,
            $enrichment->os,
        );

        return null;
    }

    /**
     * §11 row 7 — the server-side check that does not trust the client's own
     * `consent_state` stamp.
     *
     * ⚠️ **THIS IS NOT THE UNIVERSAL HASH-SUPPRESSION LIST §18 ALSO
     * DESCRIBES.** See [[PixelRefusal::GpcNotHonored]]'s docblock for the full
     * argument: that list needs an identity this collector does not resolve
     * and is still genuinely unbuilt. What this checks is narrower and needs
     * no identity — `pixel.js`'s own docblock names it as the thing that
     * *is* implementable client-side and says the collector should not simply
     * take its word for it: *"read TCF and Google Consent Mode if present …
     * stamp resolved state on every event so the collector never has to infer
     * it."* `Sec-GPC` is the one part of that a page cannot set on its own
     * behalf — a browser adds it because the *person* configured Global
     * Privacy Control, not because the site asked to be trusted.
     *
     * ⚠️ **A REAL `Sec-GPC: 1` HEADER MEANS EVERY EVENT MUST CLAIM
     * `gpc_optout`, NO EXCEPTIONS.** §10: *"GPC true → `gpc_optout`, pageview
     * only, no cookie, no identifiers … stamp the resolved state on every
     * event."* A batch that arrives under a genuine GPC signal but contains an
     * event claiming `granted`, `denied` or `unknown` is either running a
     * stale or broken build of the bundle, or is not `pixel.js` at all — and
     * either way §10's promise to the visitor is not being kept, so the whole
     * batch is refused rather than trusted selectively.
     *
     * ⚠️ **AN EVENT THAT CANNOT BE CHECKED IS TREATED AS A VIOLATION**, on
     * `carriesFormValue()`'s own precedent: "cannot tell" and "is fine" must
     * not be the same answer on a compliance gate.
     *
     * @param  array<string, mixed>  $payload
     */
    private function gpcViolation(array $payload, ?string $gpc): bool
    {
        if ($gpc !== self::GPC_HEADER_ASSERTED) {
            return false;
        }

        $events = $payload['events'] ?? null;

        if (! is_array($events) || $events === []) {
            return true;
        }

        foreach ($events as $event) {
            if (! is_array($event)) {
                return true;
            }

            if (($event['consent_state'] ?? null) !== self::GPC_CONSENT_STATE) {
                return true;
            }
        }

        return false;
    }

    /**
     * §11 row 4's admission needs a plain event count — computed once here
     * rather than in [[MonthlyEventCap]], which stays ignorant of the
     * payload's shape on the same layering [[originIsAllowed()]] keeps.
     *
     * @param  array<string, mixed>  $payload
     */
    private function eventCount(array $payload): int
    {
        $events = $payload['events'] ?? null;

        return is_array($events) ? count($events) : 0;
    }

    /**
     * Whether every event in this batch is a bare `pageview` — §11 row 4's
     * *"pageviews continue"* clause, answered at the batch rather than at the
     * event, exactly as [[MonthlyEventCap]]'s docblock argues.
     *
     * @param  array<string, mixed>  $payload
     */
    private function isPageviewOnly(array $payload): bool
    {
        $events = $payload['events'] ?? null;

        if (! is_array($events) || $events === []) {
            return false;
        }

        foreach ($events as $event) {
            if (! is_array($event) || ($event['type'] ?? null) !== 'pageview') {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether any part of this payload looks like a captured form value.
     *
     * @param  array<string, mixed>  $payload
     */
    private function carriesFormValue(array $payload): bool
    {
        $events = $payload['events'] ?? null;

        // The envelope — `k`, `device`, `referrer_*`, `utm`, `click_id`. No part
        // of it carries a value key in any shape the bundle sends, so the broad
        // net runs over it unqualified.
        $envelope = $payload;
        unset($envelope['events']);

        if ($this->hasValueKey($envelope)) {
            return true;
        }

        if (! is_array($events)) {
            return false;
        }

        // ⛔ **THE BROAD NET RUNS PER EVENT, AND A WELL-FORMED WEB VITAL IS THE
        // ONE EXEMPTION — READ THIS BEFORE SIMPLIFYING IT.** The first draft ran
        // `hasValueKey()` over the whole payload, which was a **silent, total
        // defect**: the bundle emits `record('vital', { metric: 'LCP', value: … })`
        // and LCP fires on essentially every pageview, so *every real batch* would
        // have been refused with a `204` and nothing collected, for every tenant,
        // forever. None of this file's own tests caught it because their fixtures
        // never carried a genuine vital — exactly the shape `CLAUDE.md` records as
        // "a set threshold on a dead counter", arriving as a gate on a live one.
        //
        // ⚠️ **THE EXEMPTION IS AN ALLOWLIST, NOT A CARVE-OUT KEYED ON A
        // CLIENT-SUPPLIED TYPE**, which would be trivially bypassed by posting
        // form values inside an event typed `vital`. `4574`'s precedent is the
        // shape used: *enumerate the legitimate receivers rather than banning the
        // token*. So `value` is permitted only where the **whole event** is the
        // vital shape — the type, exactly the two property keys, a metric from
        // the set the bundle actually sends, and a numeric value. Anything else
        // holding a `value` key is refused, and what a smuggler gets through the
        // remaining hole is one number.
        foreach ($events as $event) {
            if (! is_array($event)) {
                return true;
            }

            if (! $this->isWellFormedVital($event) && $this->hasValueKey($event)) {
                return true;
            }

            if (($event['type'] ?? null) !== self::FORM_EVENT) {
                continue;
            }

            $properties = $event['properties'] ?? null;
            $fields = is_array($properties) ? ($properties['fields'] ?? null) : null;

            if (! is_array($fields)) {
                continue;
            }

            foreach ($fields as $field) {
                if (! is_array($field)) {
                    // A field entry that is not an object cannot be checked
                    // against the schema, so it is refused rather than skipped —
                    // "cannot tell" and "is fine" must not be the same answer on
                    // a compliance gate.
                    return true;
                }

                foreach (array_keys($field) as $name) {
                    if (! in_array((string) $name, self::FORM_FIELD_KEYS, true)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Exactly the web vital the bundle sends, and nothing that merely resembles
     * one.
     *
     * ⚠️ **EVERY CLAUSE IS LOad-BEARING AND THE METRIC SET WAS READ OFF THE
     * BUNDLE RATHER THAN REMEMBERED** — `docs/FAILURE-SHAPES.md`'s *"Verify a
     * vendor string, price or parameter against the raw artefact"* rule applied
     * to our own client: `record('vital', { metric: 'LCP'
     * | 'INP' | 'TTFB' | 'CLS', value: … })`, and nothing else. A missing clause
     * here is a hole in the only place a `value` key is permitted to travel.
     *
     * ⚠️ **`CLS` IS THE FLOAT, AND IT IS WHY `is_numeric` RATHER THAN `is_int`.**
     * `Math.round(cls * 1000) / 1000` sends `0.001`; the other three are already
     * integers. It travels in the opaque payload and never reaches
     * `CanonicalJson`, which refuses floats — decision 4867 renders it as an exact
     * decimal string at the derivation instead. **Nothing here may start decoding
     * it into the receipt.**
     *
     * @param  array<array-key, mixed>  $event
     */
    private function isWellFormedVital(array $event): bool
    {
        if (($event['type'] ?? null) !== self::VITAL_EVENT) {
            return false;
        }

        $properties = $event['properties'] ?? null;

        if (! is_array($properties) || array_keys($properties) !== self::VITAL_PROPERTY_KEYS) {
            return false;
        }

        return in_array($properties['metric'], self::VITAL_METRICS, true)
            && is_numeric($properties['value'])
            && ! is_string($properties['value']);
    }

    /**
     * A `value` or `values` key at any depth.
     *
     * ⚠️ **THE BROAD NET, AND IT IS THE WEAKER OF THE TWO CHECKS.** The allowlist
     * above is what actually holds the form schema; this catches a value arriving
     * through an event type nobody has invented yet, under the name the obvious
     * implementation would use. Both are named in the class docblock together
     * with what neither can do.
     *
     * @param  array<array-key, mixed>  $value
     */
    private function hasValueKey(array $value): bool
    {
        foreach ($value as $key => $item) {
            $name = mb_strtolower((string) $key);

            if ($name === 'value' || $name === 'values') {
                return true;
            }

            if (is_array($item) && $this->hasValueKey($item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * §11 row 2, answered from the allowlist this application already has.
     *
     * ⛔ **§11's `tenants.domains` DOES NOT EXIST IN THIS SCHEMA AND A SECOND
     * DOMAIN LIST WAS REFUSED.** `plugins.allowed_domains` is already the
     * tenant-written, screen-backed, normalised answer to *"which of my websites
     * may run a GO AI EZ script?"* — `WidgetPlugins::setAllowedDomains()` writes
     * it, `originIsAllowed()` compares it, and `normaliseHost()` is public
     * specifically so one string is not parsed by two parsers (3093, 2967).
     * Adding `businesses.domains` beside it would be two sources of truth for one
     * question and a second screen to keep them agreeing — and `CLAUDE.md`'s
     * ambiguity rule says least support surface.
     *
     * ⚠️ **THE CONSEQUENCE IS FAIL-CLOSED AND IS STATED RATHER THAN DISCOVERED**:
     * a freshly provisioned tenant has `allowed_domains = []`, which
     * `originIsAllowed()` treats as *serve nowhere*, so **the collector archives
     * nothing for them until somebody names their website on the widget screen.**
     * That is the direction a cross-origin allowlist has to fail, and it is the
     * behaviour the widget feed already has; it is also the most likely reason a
     * real tenant reports "the pixel is not working", so it is named here and in
     * the refusal vocabulary rather than left to be inferred from silence.
     *
     * ⛔ **NAMED IN THREE DOCBLOCKS, A DECISION ROW AND AN OPERATOR ALERT, AND
     * IN NOTHING THE TENANT COULD SEE — FROM THE DAY THIS COLLECTOR SHIPPED
     * (2026-08-18) TO 2026-08-22 (7800).** The install screen told them *"there
     * is nothing else to do"*, this endpoint answers `204` on every path by
     * §11's transport rule so their browser agreed, and the one artefact that
     * dissented — `IngestRejects` — rang a bell at an operator.
     * ✅ {@see PixelCollections} answers it for the tenant now, from this same
     * allowlist and this same table.
     * ⚠️ **NOTHING ABOUT THIS GATE CHANGED**, deliberately: the fail-closed
     * direction is right, and what was wrong was the silence around it.
     *
     * ⚠️ **NOT A SECURITY CONTROL**, and `WidgetPlugins::originIsAllowed()`'s own
     * docblock refuses to be described as one: `Origin` is set by the browser and
     * cannot be forged *by a page*, which is the whole of what this buys.
     * `curl -H 'Origin: …'` passes. What makes that tolerable for a feed is that
     * its contents are public; what makes it tolerable **here** is different and
     * weaker — a forged origin writes to an append-only archive — so the honest
     * reading is that the public key is the credential and this narrows who can
     * spend it *from a browser*.
     */
    private function originIsAllowed(?string $origin): bool
    {
        return $this->plugins->businessAllowsOrigin($origin);
    }

    /**
     * Name the refusal, and say so where somebody can see it.
     *
     * ⛔ **NOTHING FROM THE PAYLOAD, THE KEY, THE ORIGIN OR THE ADDRESS TRAVELS
     * INTO THIS LOG.** The reason is a fixed vocabulary and the business id is an
     * integer we already own; anything else would make the application log a
     * record of visitor behaviour on third-party websites, which is the thing
     * `29` §2's privacy rules are about.
     *
     * ⚠️ **THIS IS STILL NOT `ingest_rejects`, EVEN THOUGH THAT TABLE NOW
     * EXISTS.** [[IngestRejects]] is written explicitly, once, from the origin
     * gate alone — see the call in [[receive()]] and that class's docblock for
     * why the other refusals stay log-only. This method's own contract is
     * unchanged: a count, never a record.
     */
    private function refuse(PixelRefusal $reason, ?int $businessId): PixelRefusal
    {
        $context = ['reason' => $reason->value, 'business_id' => $businessId];

        if ($reason->isComplianceBoundary()) {
            Log::warning('pixel.ingest.refused', $context);
        } else {
            Log::info('pixel.ingest.refused', $context);
        }

        return $reason;
    }
}
