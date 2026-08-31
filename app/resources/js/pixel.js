/**
 * The site intelligence pixel — the sensor half of the loop.
 *
 * `GOAIEZ_PIXEL_MASTER_BUILD.md` §10 governs this file's internals and
 * overrides the general precedence order for its own scope (`CLAUDE.md`
 * §Documentation). §19 row 8 is the build order line; `29` §12.1's "build fails
 * >14 KB gzipped" is the gate, and `tests/Feature/Architecture/PixelTest.php`
 * is where it fails.
 *
 * ⛔ **"NOTHING SERVES THIS FILE AND THERE IS NO COLLECTOR" WAS TRUE UNTIL
 * 2026-08-18 AND IS NOW WRONG ON BOTH COUNTS.** The collector landed first
 * (`PixelCollector`, decision 4960) and reddened `PixelTest`'s original
 * tripwire — *"no route ingests pixel or collector traffic"* — exactly as
 * designed: the endpoint constant below is deliberately spelled `/api/pixel/e`
 * so that building the collector could not slip past it under a name chosen to
 * avoid it, and that tripwire was replaced with an exact route inventory rather
 * than deleted. **Delivery is what this comment used to say did not exist and
 * is what this slice built** (§10's Delivery paragraph, decision 4980):
 * `PixelDelivery::publish()` stamps a real value over `buildId` below and
 * serves the result from `/v/<sha>/p.js` (immutable) and `/p.js` (short-lived
 * pointer, 1% canary). `PixelTest`'s delivery tripwire was replaced the same
 * way the ingest one was — see its own docblock for what replaced it.
 *
 * ⛔ NO IMPORTS AND NO EXPORTS, EVER. Same rule and same reason as `widget.js`:
 * §10's install snippet is a *classic* `<script async>`, Vite emits ES modules,
 * and a file with neither reads identically under both. Add either and the built
 * artefact opens with a token a classic script cannot parse, on a page we do not
 * control, with no error anybody will ever see.
 *
 * ⛔ **NO FORM VALUE IS EVER READ. NOT ONE, NOT EVER** (4574). §14 specifies
 * envelope-encrypted values under the identity DEK; `.value` is therefore
 * absent from this file entirely and a lint fails the build on it.
 *
 * ⛔ **"§11.9 PUTS THE PHI STRIP AT THE COLLECTOR — AND THE COLLECTOR DOES NOT
 * EXIST" WAS TRUE AND IS NOT — CORRECTED 2026-08-27 (wave 39 lane D, decision
 * 10750s), THE SAME CORRECTION :10-11 ABOVE ALREADY MADE FOR THIS FILE'S
 * DELIVERY CLAIM AND NEVER MADE FOR THIS ONE.** The collector exists
 * (`PixelCollector`, decision 4960) and its HIPAA gate runs at ingest — see
 * that class's docblock for the two refusals it actually performs. A `.value`
 * that reached the collector would have nowhere to be stripped only in the
 * sense that nothing here ever sends one; it is not a sentence about the
 * collector's existence any more.
 *
 * ⛔ **AND "THIS BUNDLE STRUCTURALLY CANNOT DELIVER PHI" IS AN OVERCLAIM THE
 * SAME LANE FOUND FALSE, NOT MERELY STALE.** What is true is narrower: no
 * *value* is ever read, and `.value`'s absence from this file is a property a
 * lint proves. What is not true is that a field's **name** is therefore safe
 * to send unexamined. `record('form_submitted', …)` below sends `input.name`
 * verbatim — unvalidated, untruncated, chosen entirely by the tenant's own
 * HTML — and a real business's markup can spell it `patient_dob`, `ssn`,
 * `diagnosis_code`: the label a developer gave a field is exactly the shape
 * PHI/PII-classifying metadata takes, and nothing downstream inspects it
 * either — `PixelCollector::FORM_FIELD_KEYS` allowlists the field entry's
 * KEYS (`name`, `type`, `required`, `missing`), never the string its `name`
 * key holds. See that constant's docblock and `docs/DECISIONS.md`'s
 * 10750s block for the full census; no code changed here or there, because
 * every mitigation this lane considered — a blocklist of alarming names, a
 * hash, a refusal — either repeats the failure `PixelCollector`'s own field
 * capture docblock argues against, or destroys the very feature this
 * paragraph is truthfully describing. "Field names, types and counts are
 * enough for abandonment, classification and identity intent" remains the
 * honest description of what this capture is *for*; it stops here being read
 * as a claim that a name cannot itself be sensitive, which it never was.
 *
 * ⛔ **NO FINGERPRINTING SURFACE IS TOUCHED AND NO SIGNAL IS EVER CONCATENATED**
 * (`29` §2, §12.1's "no fingerprint assembly"). No canvas, no WebGL, no
 * AudioContext, no font enumeration, no `navigator.plugins`. The device signals
 * §10 does ask for travel as **separate typed fields on the payload** and are
 * joined into no string here, hashed by nothing here, and used for device-class
 * bucketing and bot scoring on the far side. This file contains no hashing
 * primitive at all — `btoa`, `crypto.subtle` and every hand-rolled digest are
 * absent, and a lint says so — because the shortest path from "device signals"
 * to "a stable identifier" is one `join('|')` somebody adds to make a cache key.
 *
 * ⛔ NO KEYSTROKE CAPTURE, NO SESSION REPLAY, NO CROSS-ORIGIN READ. §21's frozen
 * decisions, verbatim. `keydown` is observed as **one boolean** — did any key
 * press happen before a conversion, which is §12's 20-point bot signal — and no
 * property carrying what was typed (`key`, `code`, `keyCode`, `which`, `data`)
 * is read anywhere. There is no `MutationObserver` and nothing that could
 * reconstruct a document. Form observation is gated on a same-origin check
 * before anything else, which auto-excludes Stripe, Calendly, Intercom and every
 * other embed with no allowlist and no configuration (§14).
 *
 * ⚠️ **RAW IP CANNOT BE STORED HERE BECAUSE IT CANNOT BE READ HERE**, and that
 * is worth writing down rather than assuming: a browser does not expose the
 * client address, so the only way this file could learn one is by asking a
 * third-party echo service. It makes **no third-party request of any kind** —
 * one origin, derived from its own `src`, and nothing else.
 *
 * ⚠️ **THE `_q_optout` COOKIE OF §18 IS NOT IMPLEMENTABLE FROM HERE AND ITS
 * ABSENCE IS NOT AN OVERSIGHT** (4573). §18 sets it on `.goaiez.com`; this file
 * runs on the tenant's domain, where a cookie scoped to ours is unreadable, and
 * the collector will not receive it either — a cross-site request carries no
 * cookie under any current browser's default policy. What is implementable
 * client-side is GPC, TCF and Consent Mode, all of which are in-page, and they
 * are what this file reads. The universal opt-out survives as the **hash
 * suppression list** §18 also describes, checked server-side at ingest, which
 * needs no cookie and works for a visitor who has never seen our domain.
 */
(function () {
    'use strict';

    /**
     * The script tag that loaded this file.
     *
     * `document.currentScript` is set while a classic script executes, async
     * included. The fallback covers a page that loaded this some other way.
     */
    var self = document.currentScript || document.querySelector('script[data-k]');

    if (!self) {
        return;
    }

    /** Public key only. §10: never a secret, never tenant data, never end-user PII. */
    var key = self.getAttribute('data-k');

    if (!key) {
        return;
    }

    /**
     * Where events go — derived from the address the browser actually fetched
     * this from, never compiled in, for `widget.js`'s reason: this application
     * answers on more than one hostname over its life and a baked-in origin
     * would send a tenant's page to the wrong one for ever.
     */
    var origin;

    try {
        origin = new URL(self.src, window.location.href).origin;
    } catch (e) {
        return;
    }

    /** See the docblock: this path exists so the collector's arrival is loud. */
    var endpoint = origin + '/api/pixel/e';

    /**
     * §10's delivery version — a 32-character placeholder, replaced with a real
     * random token by `pixel:publish` in the served bytes only. Never a hash of
     * this file's own content: `PixelDelivery::publish()`'s docblock has the
     * reason computing one from the other cannot work. `resources/js/pixel.js`
     * itself always carries the placeholder unstamped, which is what
     * `pixel:publish`'s exactly-once check is for.
     */
    var buildId = '__GOAIEZ_PIXEL_BUILD_TOKEN__';

    var nav = window.navigator || {};
    var doc = window.document;

    /*
    |--------------------------------------------------------------------------
    | Consent, resolved before one byte is collected
    |--------------------------------------------------------------------------
    |
    | §10: default `unknown`; GPC true → `gpc_optout`, pageview only, no
    | identifier, no storage; read TCF and Google Consent Mode if present. The
    | resolved state is stamped on every event so the collector never has to
    | infer it, and `29` §12.1 tests all three refusals.
    |
    | Ordered most-restrictive-first on purpose. A page carrying both GPC and a
    | granting CMP must resolve to the refusal: GPC is a legally recognised
    | signal from the person, and a consent platform's default is a setting on
    | the website.
    */
    var CONSENT_GPC = 'gpc_optout';
    var CONSENT_DENIED = 'denied';
    var CONSENT_GRANTED = 'granted';
    var CONSENT_UNKNOWN = 'unknown';

    /**
     * What the IAB TCF frame says, or nothing at all.
     *
     * The TCF API is asynchronous by design and this runs synchronously at load,
     * so what is readable here is a CMP's *already published* state. A CMP that
     * is present but has not answered yet leaves this at `''` — no opinion —
     * which resolves to §10's `unknown` default and is the honest reading.
     */
    function tcfState() {
        var tcf = window.__tcfapi;

        if (typeof tcf !== 'function') {
            return '';
        }

        var state = '';

        try {
            tcf('getTCData', 2, function (data, success) {
                if (success && data && data.purpose && data.purpose.consents) {
                    // Purpose 1 — "Store and/or access information on a device".
                    // Everything this pixel does beyond a bare pageview needs it.
                    state = data.purpose.consents[1] === true ? CONSENT_GRANTED : CONSENT_DENIED;
                }
            });
        } catch (e) {
            return '';
        }

        return state;
    }

    /**
     * What Google Consent Mode says, or nothing at all.
     *
     * Consent Mode publishes into `dataLayer` as arguments objects, so the state
     * is the *last* `consent` entry rather than the first — an update overrides
     * a default, which is the whole mechanism. Read from `dataLayer` rather than
     * through `gtag` because a page may have one and not the other, and because
     * calling somebody else's `gtag` from our script would put an entry in their
     * analytics that they did not write.
     */
    function consentModeState() {
        var layer = window.dataLayer;

        if (!layer || typeof layer.length !== 'number') {
            return '';
        }

        var state = '';

        for (var i = 0; i < layer.length; i++) {
            var entry = layer[i];

            if (!entry || entry[0] !== 'consent' || !entry[2]) {
                continue;
            }

            if (entry[2].analytics_storage === 'denied') {
                state = CONSENT_DENIED;
            } else if (entry[2].analytics_storage === 'granted') {
                state = CONSENT_GRANTED;
            }
        }

        return state;
    }

    function resolveConsent() {
        if (nav.globalPrivacyControl === true) {
            return CONSENT_GPC;
        }

        var tcf = tcfState();
        var mode = consentModeState();

        // ⛔ ANY DENIAL WINS OVER ANY GRANT. Two consent platforms on one page is
        // an ordinary state of affairs on a site that changed vendors, and the
        // only safe resolution of a disagreement is the refusal.
        if (tcf === CONSENT_DENIED || mode === CONSENT_DENIED) {
            return CONSENT_DENIED;
        }

        if (tcf === CONSENT_GRANTED || mode === CONSENT_GRANTED) {
            return CONSENT_GRANTED;
        }

        // Nothing on the page has an opinion. §10's default, and the collector
        // is told that rather than told a grant it never received.
        return CONSENT_UNKNOWN;
    }

    var consent = resolveConsent();

    /**
     * Two refusals, and only the first is total.
     *
     * `denied` means a consent platform said no: transmit nothing at all.
     * `gpc_optout` means the person said no to *sale and sharing*: §10 keeps a
     * bare pageview with no identifier and no storage, which is the reading
     * every state signal statute takes of an analytics measurement the site
     * operator performs for itself.
     */
    if (consent === CONSENT_DENIED) {
        return;
    }

    var mayIdentify = consent !== CONSENT_GPC;

    /*
    |--------------------------------------------------------------------------
    | Identity — random, first-party, and absent entirely under GPC
    |--------------------------------------------------------------------------
    */

    var STORE_ANON = '_q_a';
    var STORE_SESSION = '_q_s';

    function readStore(name) {
        if (!mayIdentify) {
            return null;
        }

        try {
            return window.localStorage.getItem(name);
        } catch (e) {
            // Storage can throw outright — Safari in private mode, a page with
            // storage blocked by policy. Treated as "no id", never as an error.
            return null;
        }
    }

    function writeStore(name, payload) {
        if (!mayIdentify) {
            return;
        }

        try {
            window.localStorage.setItem(name, payload);
        } catch (e) {
            // As above. An unstorable visitor is a new visitor every page, which
            // is a worse measurement and not a broken one.
        }
    }

    /**
     * A random id, and nothing derived from the device — **a UUIDv4, always.**
     *
     * `crypto.randomUUID` where it exists, `getRandomValues` where it does not,
     * and `Math.random` last. **No branch of this reads a device signal** — the
     * whole point of `29` §2's fingerprinting rule is that an id must carry no
     * information about the machine, so a "fallback" that mixed in screen size
     * or timezone would be the prohibited thing wearing the word fallback.
     *
     * ⛔ **NO BRANCH OF THIS MAY READ THE CLOCK, AND A LINT NOW FAILS THE BUILD
     * IF ONE DOES** (`PixelTest`, *"the pixel mints no identifier from the
     * clock"*). `GOAIEZ_PIXEL_MASTER_BUILD.md` §8 says `session_id` is a
     * **UUIDv7** and the owner overruled it on 2026-08-20 — *"uuidv7 stays v4,
     * don't change it"* (6004, 6128), a ruling that had to be an owner's because
     * `CLAUDE.md`'s conflict rule gives the pixel spec its own internals. **The
     * reason is privacy and not style**: a v7 carries its creation time inside
     * the value, and this one function mints `session_id`, `event_id` **and
     * `anonymous_id`** — so *"use the modern UUID"* reads on a diff as a tidy-up
     * and stamps a timestamp into three identifiers this bundle deliberately
     * made random, in the artefact governed by *no fingerprinting, no stable
     * identifier assembly*. `anonymous_id` is what §13's disabled identity
     * resolution would key on.
     *
     * ⛔ **ALL THREE ARMS RETURN THE SAME SHAPE, AND TWO OF THEM DID NOT UNTIL
     * 7685.** `getRandomValues` returned 32 undashed hex characters and
     * `Math.random` returned a run of digits, and **neither is a UUID** —
     * `L1Derivation::uuid()` matches the dashed form and returns `null` for
     * anything else, so on a page where `crypto.randomUUID` is missing every
     * `session_id` and `anonymous_id` arrived at L1 as `NULL`, the session mart
     * skipped the rows outright (`Replayer`: `AND session_id IS NOT NULL`) and
     * the tenant's session count read zero while their pageviews counted
     * normally. **`crypto.randomUUID` is secure-context only**, so the trigger
     * is not an exotic browser — it is a tenant whose website is served over
     * plain HTTP.
     */
    function randomId() {
        var c = window.crypto || window.msCrypto;

        if (c && typeof c.randomUUID === 'function') {
            return c.randomUUID();
        }

        var bytes = new Uint8Array(16);

        if (c && typeof c.getRandomValues === 'function') {
            c.getRandomValues(bytes);
        } else {
            // ⚠️ NOT CRYPTOGRAPHIC AND SAID SO OUT LOUD. It is reached only when
            // the browser offers no randomness at all, and a weakly random UUID
            // is measurably better than an id L1 discards.
            for (var i = 0; i < 16; i++) {
                bytes[i] = Math.floor(Math.random() * 256);
            }
        }

        // RFC 4122 §4.4: version 4 in the high nibble of byte 6, variant 10 in
        // the top bits of byte 8. Neither byte carries a clock.
        bytes[6] = (bytes[6] & 15) | 64;
        bytes[8] = (bytes[8] & 63) | 128;

        var hex = '';

        for (var j = 0; j < 16; j++) {
            hex += (bytes[j] + 256).toString(16).slice(1);
        }

        return hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16)
            + '-' + hex.slice(16, 20) + '-' + hex.slice(20);
    }

    var anonymousId = null;

    if (mayIdentify) {
        anonymousId = readStore(STORE_ANON);

        if (!anonymousId) {
            anonymousId = randomId();
            writeStore(STORE_ANON, anonymousId);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Session — §8's canonical definition, implemented exactly
    |--------------------------------------------------------------------------
    |
    | Ends on 30 minutes of inactivity for the same anonymous id, on UTC
    | midnight, or on a new non-empty campaign differing from the current one.
    | §8 says "ambiguity here produces shifting numbers, which produces the
    | support tickets N3 forbids", so it is implemented rather than approximated.
    */

    var SESSION_IDLE_MS = 30 * 60 * 1000;

    var params = new URLSearchParams(window.location.search);

    function param(name) {
        return params.get(name) || '';
    }

    var campaign = param('utm_source') || param('gclid') || param('fbclid') || param('msclkid');

    function utcDay(at) {
        return new Date(at).toISOString().slice(0, 10);
    }

    /**
     * A new session, every field of it stamped from one instant.
     *
     * ⚠️ **`started` HAS NO RUNTIME READER AND IS DELIBERATELY KEPT** (7683).
     * Nothing in this bundle reads it, it is never put on the wire, and no
     * column in `l1_events` or any `l2_fact_*` mart is derived from it — a
     * session's start is reconstructed server-side from `occurred_at`, which is
     * the right source, because a visitor's clock is not ours. **It stays
     * because it is the only observable that can witness the invariant above**:
     * `started === seen` on a fresh mint is false the instant somebody reads the
     * clock twice, and with the field gone there is nothing left to assert it
     * against. Do not sweep it as a dead field without replacing the witness.
     */
    function startSession(at) {
        return { id: randomId(), started: at, seen: at, day: utcDay(at), campaign: campaign, views: 0 };
    }

    /**
     * The session this pageview belongs to, given one reading of the clock.
     *
     * ⛔ **`now` IS A PARAMETER AND NOT A `Date.now()`, AND THAT IS THE WHOLE
     * POINT OF THIS SIGNATURE.** Until 7680 this function read the clock itself
     * and its caller read it again one line later, so a freshly minted session
     * got `started` from the first reading and `seen` from the second — equal on
     * almost every load and one millisecond apart whenever the clock ticked in
     * between. It reddened `main` once in twenty-five runs and was green the
     * other twenty-four, which is the worst shape a defect can have: **the
     * passing run and the failing run differed only in which side of a
     * millisecond the machine was on.**
     *
     * ⚠️ **EVERY CLAUSE OF §8's DISJUNCTION IS NOW JUDGED AGAINST ONE INSTANT.**
     * The idle arithmetic, the UTC day, the day stamped on a new session and the
     * `seen` written after it are four readings of the same number rather than
     * four readings of the clock — so no session boundary can ever be decided
     * against a moment that has already passed by the time it is recorded.
     *
     * @param {number} now Milliseconds since the epoch, read once by the caller.
     */
    function currentSession(now) {
        if (!mayIdentify) {
            // No storage means no continuity: each pageview is its own session,
            // which is what "no identifiers" costs and is the correct cost.
            return startSession(now);
        }

        var stored = readStore(STORE_SESSION);
        var session = null;

        if (stored) {
            try {
                session = JSON.parse(stored);
            } catch (e) {
                session = null;
            }
        }

        var expired = !session
            || typeof session.seen !== 'number'
            || now - session.seen > SESSION_IDLE_MS
            || session.day !== utcDay(now)
            || (campaign !== '' && campaign !== session.campaign);

        return expired ? startSession(now) : session;
    }

    // ⛔ ONE READING OF THE CLOCK FOR THE WHOLE OF §8. See `currentSession()`.
    var sessionAt = Date.now();
    var session = currentSession(sessionAt);

    session.seen = sessionAt;
    session.views = (session.views || 0) + 1;

    writeStore(STORE_SESSION, JSON.stringify(session));

    /*
    |--------------------------------------------------------------------------
    | The payload
    |--------------------------------------------------------------------------
    */

    /** §10 truncates nothing by accident; every free-text field is bounded here. */
    function text(value, limit) {
        if (value === undefined || value === null) {
            return '';
        }

        return String(value).slice(0, limit);
    }

    var referrerHost = '';
    var referrerPath = '';

    try {
        if (doc.referrer) {
            var ref = new URL(doc.referrer);

            referrerHost = ref.host;
            referrerPath = ref.pathname;
        }
    } catch (e) {
        // A referrer we cannot parse is a referrer we do not report.
    }

    var screen = window.screen || {};
    var connection = nav.connection || {};

    /**
     * Device signals, as separate fields.
     *
     * ⛔ Read the docblock before adding one. Each of these is here for device
     * classing or for a named row of §12's bot table, and **none of them is ever
     * joined to another**. `webdriver` is 30 points, `hardwareConcurrency`
     * implausibility is 15, and the rest bucket a device as phone/tablet/desktop
     * without asking the machine to identify itself.
     */
    function device() {
        return {
            screen_w: screen.width || 0,
            screen_h: screen.height || 0,
            viewport_w: window.innerWidth || 0,
            viewport_h: window.innerHeight || 0,
            language: text(nav.language, 16),
            timezone: timezone(),
            cores: typeof nav.hardwareConcurrency === 'number' ? nav.hardwareConcurrency : 0,
            memory: typeof nav.deviceMemory === 'number' ? nav.deviceMemory : 0,
            touch: typeof nav.maxTouchPoints === 'number' ? nav.maxTouchPoints : 0,
            network: text(connection.effectiveType, 12),
            webdriver: nav.webdriver === true,
        };
    }

    function timezone() {
        try {
            return text(Intl.DateTimeFormat().resolvedOptions().timeZone, 64);
        } catch (e) {
            return '';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | The queue, and what happens to a batch that does not arrive
    |--------------------------------------------------------------------------
    |
    | ⛔ **THIS QUEUE USED TO BE EMPTIED BEFORE EITHER TRANSPORT WAS ATTEMPTED,
    | AND NOTHING ANYWHERE READ THE RESULT** (10060). `queue = []` ran above the
    | `sendBeacon` call, the `fetch` had an empty `.catch()` with a comment
    | saying the silence was deliberate, and the words `status` and `ok` did not
    | occur in this file — so a collector that answered `500`, a DNS failure, a
    | TLS failure and a body the far end refuses all produced the same outcome:
    | **the events were gone, on the product's only sensor, permanently, with no
    | symptom on either side of the wire.**
    |
    | ⚠️ **THE SILENCE TOWARD THE VISITOR IS KEPT AND IS STILL RIGHT.** Nothing
    | below writes to `console`, throws, or renders anything: this is a script on
    | somebody else's website and every failure it can see is our problem rather
    | than theirs. What changed is that a failure it can see is now **acted on**
    | rather than discarded — silence toward the page was never the same
    | decision as silence toward the data.
    |
    | ⛔ **AND THE PART THIS CANNOT DO IS WRITTEN DOWN RATHER THAN IMPLIED**
    | (10068): a batch this bundle finally gives up on is reported to nobody,
    | because the only channel it has for saying so is the transport that just
    | failed — `CLAUDE.md`'s *a bell whose only clapper is the mail system cannot
    | ring about the mail system* (9377), in a browser. Carrying a loss counter
    | forward to the next page needs storage this bundle deliberately does not
    | take, and that refusal is argued at 10069.
    */

    /**
     * The most events one flush may carry.
     *
     * ⚠️ **READ BY `StorePixelBatchRequest::MAX_EVENTS`'s OWN DOCBLOCK, WHICH
     * NAMES THIS FILE** — the collector permits fifty and states the margin is
     * for a future event type rather than for a client inventing its own limit.
     * Twenty is what it says this bundle sends and twenty is what it sends.
     */
    var BATCH_EVENTS = 20;

    /**
     * The largest body this bundle will build, in **bytes**.
     *
     * ⛔ **THERE ARE THREE CEILINGS AT ROUGHLY THIS SIZE AND THIS BUNDLE
     * RESPECTED NONE OF THEM UNTIL 10062.** `StorePixelBatchRequest`'s
     * `MAX_BODY_BYTES` is 65,536 and a body over it is dropped in
     * `prepareForValidation()` — no merge, so `rules()` fails on `k` and the
     * request answers the same `204` an accepted batch gets, **writing no reject
     * row and dispatching no archive job.** The Fetch standard bounds a
     * `keepalive` request's body at 64 KiB per origin and rejects over it, and
     * `sendBeacon` returns `false` at its own quota.
     *
     * ⚠️ **MEASURED RATHER THAN REASONED ABOUT**: twenty `form_submitted`
     * events, each with the sixty fields the loop below permits and the 2,048 /
     * 1,024 / 256 characters `text()` already truncates to, is **221,385
     * bytes** — 3.4 times the collector's limit, and every byte of it lost in
     * silence. An ordinary twenty-pageview batch is 7,225. The cliff is not
     * exotic: it is a form-heavy page, or a shop whose URLs carry query
     * strings.
     *
     * ⚠️ **DELIBERATELY BELOW THE COLLECTOR'S FIGURE RATHER THAN EQUAL TO IT**,
     * and the two are not one fact in two files. The far end states what it will
     * accept; this states what we will build, and the gap is the room a
     * `keepalive` request needs while another is still in flight against the
     * same per-origin quota. **`PixelTest` reads both numbers and fails the
     * build if this one ever stops being the smaller** — the relationship is
     * linted, neither number is re-typed.
     */
    var BATCH_BYTES = 48 * 1024;

    /**
     * The most events this bundle will hold for a collector that is not
     * answering.
     *
     * A requeue that is never drained is a memory leak on a stranger's page, so
     * the queue has a ceiling and **the newest records are what it refuses**.
     * That direction is a choice: the `pageview` is event one and is the only
     * event `PixelArrivals` reads to decide whether to tell an owner anybody
     * visited their website, so dropping the oldest would destroy the most
     * valuable event in the batch to keep a scroll depth.
     */
    var QUEUE_EVENTS = 100;

    /**
     * How many times one batch is offered to the network.
     *
     * ⚠️ **BOUNDED, BACKED OFF AND JITTERED, BECAUSE A RETRY IS LOAD ON A
     * COLLECTOR THAT IS ALREADY FAILING.** Three attempts over roughly three to
     * six seconds is a 3× worst case against an incident, which is the price of
     * not losing 100% of a tenant's data during one; anything unbounded would
     * make our own outage worse from every page of every tenant at once.
     *
     * ⚠️ **AND IT IS ONLY EVER TRIED WHILE THE PAGE IS ALIVE** — `setTimeout`
     * does not run on a page that has gone, so a retry after an unload flush
     * would be a line that never executes.
     */
    var SEND_ATTEMPTS = 3;

    /**
     * How long a flush may be considered in flight before another may start.
     *
     * ⚠️ **THE LATCH BELOW COULD STRAND THE QUEUE AND THIS IS WHAT UNSTRANDS
     * IT** (10063). `flushing` was cleared only in the `fetch` chain's `.then()`,
     * so a `keepalive` request on a backgrounded or frozen page that never
     * settles left it `true` for the life of the page — `flush()` returned at
     * its first line for ever afterwards while `record()` went on appending.
     * **This clears the latch and deliberately does not requeue**: the original
     * request may still be in flight, and if it later settles as a failure its
     * own handler puts its batch back. A batch that arrives twice is harmless by
     * construction — §11.5 dedupes on the client-minted `event_id`, which is the
     * property that makes any of this safe.
     */
    var STRAND_MS = 10000;

    var queue = [];
    var flushing = false;

    function record(type, properties) {
        if (queue.length >= QUEUE_EVENTS) {
            return;
        }

        queue.push({
            // The client mints the event id and §11.5 dedupes on it, which is
            // what makes a retried beacon harmless.
            event_id: randomId(),
            type: type,
            occurred_at: new Date().toISOString(),
            session_id: session.id,
            anonymous_id: anonymousId,
            consent_state: consent,
            page_url: text(window.location.href, 2048),
            page_path: text(window.location.pathname, 1024),
            page_title: text(doc.title, 256),
            properties: properties || {},
        });

        if (queue.length >= BATCH_EVENTS) {
            flush(false);
        }
    }

    /** The batch envelope — §11's line format, one request's worth. */
    function envelope(events) {
        return JSON.stringify({
            k: key,
            bv: buildId,
            schema_version: 1,
            sent_at: new Date().toISOString(),
            device: device(),
            referrer_host: text(referrerHost, 256),
            referrer_path: text(referrerPath, 1024),
            utm: {
                source: text(param('utm_source'), 128),
                medium: text(param('utm_medium'), 128),
                campaign: text(param('utm_campaign'), 256),
                term: text(param('utm_term'), 256),
                content: text(param('utm_content'), 256),
            },
            click_id: {
                gclid: text(param('gclid'), 256),
                fbclid: text(param('fbclid'), 256),
                msclkid: text(param('msclkid'), 256),
            },
            events: events,
        });
    }

    /**
     * Take as much of the queue as will fit down the wire, and send it.
     *
     * ⚠️ **THE LATCH IS BYPASSED WHEN THE PAGE IS LEAVING**, which it was not
     * before. A flush already in flight made `flush(true)` return at its first
     * line, so everything recorded after it — every conversion click and every
     * CLS reading on the way out — went with the page. On the way out there is
     * no later attempt to protect, and `sendBeacon` is a transport the browser
     * finishes after we are gone.
     */
    function flush(unloading, attempt) {
        if ((flushing && !unloading) || !queue.length) {
            return;
        }

        // ⚠️ **HALVING RATHER THAN MEASURING EACH EVENT.** The ordinary path
        // builds one envelope and stops; only an over-size batch loops, and it
        // reaches a single event in at most five passes. `Blob.size` is the
        // byte count rather than `String.length`, which counts UTF-16 units —
        // a page whose titles are not ASCII would otherwise be measured at up
        // to a third of what the collector actually weighs.
        var take = queue.length < BATCH_EVENTS ? queue.length : BATCH_EVENTS;
        var body;

        for (;;) {
            body = new Blob([envelope(queue.slice(0, take))], { type: 'text/plain;charset=UTF-8' });

            // ⚠️ **A SINGLE EVENT IS SENT WHATEVER IT WEIGHS.** No event this
            // bundle records can reach the budget — every free-text field is
            // truncated and the widest of them, a sixty-field `form_submitted`,
            // is roughly eleven kilobytes — so this arm exists for a future
            // event type rather than for anything shipping today, and it fails
            // toward trying rather than toward discarding.
            if (body.size <= BATCH_BYTES || take === 1) {
                break;
            }

            take = take >> 1;
        }

        var batch = queue.slice(0, take);

        queue = queue.slice(take);

        send(batch, body, unloading, attempt || 1);
    }

    function send(batch, body, unloading, attempt) {
        // `sendBeacon` first: it is the only transport a browser will finish
        // after the page is gone, which is where most of these land.
        //
        // ⚠️ **IT REPORTS QUEUE ACCEPTANCE AND NOTHING ELSE, BY CONSTRUCTION**,
        // so nothing below can be true of this arm — there is no response to
        // read and no promise to settle. That asymmetry is why the fetch path
        // stays the fall-through for a refused beacon rather than the two paths
        // being made to look alike: what a failure means here is *the browser
        // would not take it*, and the answer to that is to try the other
        // transport, not to retry this one.
        if (unloading && typeof nav.sendBeacon === 'function') {
            try {
                if (nav.sendBeacon(endpoint, body)) {
                    return;
                }
            } catch (e) {
                // Fall through to fetch.
            }
        }

        flushing = true;

        // See STRAND_MS. Clears the latch and requeues nothing.
        window.setTimeout(function () {
            flushing = false;
        }, STRAND_MS);

        try {
            window
                .fetch(endpoint, {
                    method: 'POST',
                    // ⛔ No cookie may travel from a third-party page, and there
                    // is nothing on our origin worth sending anyway.
                    credentials: 'omit',
                    keepalive: true,
                    headers: { 'Content-Type': 'text/plain;charset=UTF-8' },
                    body: body,
                })
                .then(
                    function (response) {
                        // ⛔ **`< 500` AND NOT `response.ok`, AND THE DIFFERENCE
                        // IS THE WHOLE OF §11.** This collector answers `204` to
                        // everything it decides — an accepted batch, an unknown
                        // key, a rule-24 refusal, a validation failure and a
                        // rate-limit refusal are one response, deliberately, so
                        // that the endpoint is not an oracle
                        // (`PixelRateLimits::refusal()` is *"204, NOT 429"*).
                        // **So a resolved response under 500 means the far end
                        // decided, and re-sending would get the same decision
                        // for ever.** `response.ok` would read identically today
                        // and would start retrying into a deliberate refusal the
                        // day anything answered `4xx`.
                        //
                        // ⚠️ **AND IT MEANS THIS BUNDLE CANNOT RETRY INTO A
                        // THROTTLE EVEN IF IT WANTED TO** — a refusal on volume
                        // is invisible from here, which is the contract working
                        // rather than a gap.
                        settle(batch, unloading, attempt, !response || response.status < 500);
                    },
                    function () {
                        // ⚠️ **A REJECTED PROMISE IS "WE DO NOT KNOW", NEVER "IT
                        // DID NOT ARRIVE"** — a network failure, a TLS failure,
                        // a `keepalive` quota, and a CORS response the request
                        // *reached the server* to earn all land here. Retrying
                        // the last of those re-sends data that was already
                        // stored; §11.5's dedupe on `event_id` is what makes
                        // that the cheap mistake rather than the expensive one.
                        settle(batch, unloading, attempt, false);
                    },
                );
        } catch (e) {
            settle(batch, unloading, attempt, false);
        }
    }

    function settle(batch, unloading, attempt, delivered) {
        flushing = false;

        if (delivered) {
            return;
        }

        // Back at the front, so the batch keeps its place in the queue, and
        // capped from the far end for QUEUE_EVENTS' reason.
        queue = batch.concat(queue).slice(0, QUEUE_EVENTS);

        if (unloading || attempt >= SEND_ATTEMPTS) {
            return;
        }

        // ⚠️ **JITTERED, AND THAT IS NOT POLISH.** Without it every visitor on
        // every page of every tenant retries at the same offset from the same
        // failure, which turns one collector incident into a synchronised second
        // wave. `Math.random()` is already in this file and mints nothing here.
        window.setTimeout(function () {
            flush(false, attempt + 1);
        }, (1 << attempt) * 500 * (1 + Math.random()));
    }

    /*
    |--------------------------------------------------------------------------
    | What is observed
    |--------------------------------------------------------------------------
    */

    var loadedAt = Date.now();

    record('pageview', { is_new_session: session.views === 1, session_views: session.views });

    // Under GPC the bargain is a bare pageview and nothing else. Everything
    // below observes behaviour, which is the half the signal refuses.
    if (mayIdentify) {
        observe();
    }

    flushOnExit();

    function observe() {
        var activeMs = 0;
        var lastTick = Date.now();
        var maxScroll = 0;
        var sawPointer = false;
        var sawKey = false;
        var clickTimes = [];

        // §12's "no mousemove AND no keydown before conversion" is 20 points, so
        // both are observed as booleans. ⛔ NOTHING READS WHAT WAS TYPED — no
        // `key`, no `code`, no `keyCode`. The listener takes no argument at all,
        // which makes the absence structural rather than remembered.
        doc.addEventListener('pointermove', function () {
            sawPointer = true;
        }, { passive: true, once: true });

        doc.addEventListener('keydown', function () {
            sawKey = true;
        }, { passive: true, once: true });

        doc.addEventListener('scroll', function () {
            var height = doc.documentElement.scrollHeight - window.innerHeight;
            var depth = height > 0 ? Math.round(((window.scrollY || 0) / height) * 100) : 100;

            maxScroll = Math.max(maxScroll, Math.min(100, Math.max(0, depth)));
        }, { passive: true });

        doc.addEventListener('visibilitychange', function () {
            if (doc.visibilityState === 'hidden') {
                activeMs += Date.now() - lastTick;
            } else {
                lastTick = Date.now();
            }
        });

        /*
        | Auto-conversions — §10. A `tel:` is the conversion most small
        | businesses have never measured once, and it needs no tagging.
        */
        doc.addEventListener('click', function (event) {
            var now = Date.now();

            clickTimes.push(now);

            // Rage clicks: three inside a second. Counted, never replayed —
            // there is no coordinate, no target text and no path recorded.
            if (clickTimes.length >= 3 && now - clickTimes[clickTimes.length - 3] < 1000) {
                record('rage_click', {});
                clickTimes = [];
            }

            var link = event.target && event.target.closest ? event.target.closest('a[href]') : null;

            if (!link) {
                return;
            }

            var href = link.getAttribute('href') || '';
            var type = conversionFor(href);

            if (!type) {
                return;
            }

            record(type, {
                // ⛔ The scheme, never the destination. A `tel:` href is a phone
                // number and a `mailto:` is an email address — both are the
                // visitor's own contact details in the general case, and §10
                // asks for the *conversion*, not for who was contacted.
                scheme: href.split(':')[0].slice(0, 16),
                had_pointer: sawPointer,
                had_key: sawKey,
                ms_since_load: now - loadedAt,
            });
        }, { capture: true, passive: true });

        /*
        | Form observation — same-origin first, submit only, names only.
        |
        | §14's one-line same-origin gate auto-excludes every third-party embed
        | with no allowlist and no configuration. ⛔ AND NO VALUE IS READ: see the
        | docblock. What travels is the field's name, its type and whether it was
        | filled, which is everything abandonment and classification need.
        */
        doc.addEventListener('submit', function (event) {
            var form = event.target;

            if (!form || !form.elements) {
                return;
            }

            var action;

            try {
                action = new URL(form.getAttribute('action') || window.location.href, window.location.href);
            } catch (e) {
                return;
            }

            if (action.origin !== window.location.origin) {
                return;
            }

            var fields = [];

            for (var i = 0; i < form.elements.length && fields.length < 60; i++) {
                var field = form.elements[i];
                var kind = (field.type || '').toLowerCase();

                if (!field.name || kind === 'password' || kind === 'file' || kind === 'hidden' || kind === 'submit') {
                    continue;
                }

                fields.push({
                    name: text(field.name, 64),
                    type: text(kind, 24),
                    required: field.required === true,
                    // ⚠️ §14's `field_completed` IS ONLY OBSERVABLE FOR A
                    // REQUIRED FIELD, AND SAYING SO IS THE POINT. `valueMissing`
                    // is the browser's own answer to "required and empty"; for an
                    // optional field there is no way to learn whether anything
                    // was typed except by reading what was typed, which this file
                    // does not do. An optional field therefore reports
                    // `missing: false` meaning *unknown*, and the collector must
                    // read it alongside `required` rather than alone.
                    missing: field.validity ? field.validity.valueMissing === true : false,
                });
            }

            record('form_submitted', {
                form_id: text(form.getAttribute('id') || form.getAttribute('name'), 64),
                fields: fields,
                had_pointer: sawPointer,
                had_key: sawKey,
                ms_since_load: Date.now() - loadedAt,
            });

            flush(false);
        }, { capture: true, passive: true });

        /*
        | Core Web Vitals and errors — §10 and B2/B3. The highest-value feature
        | in the set is "your contact form has been throwing in Safari since the
        | 12th", and it is these two observers that make it possible.
        */
        vitals();

        window.addEventListener('error', function (event) {
            record('js_error', {
                // ⚠️ TRUNCATED HARD, because an error message is free text a
                // stranger's website generated and can carry anything at all —
                // an email address in a validation message is the ordinary case.
                message: text(event.message, 300),
                source: text(event.filename, 300),
                line: event.lineno || 0,
                column: event.colno || 0,
            });
        });

        window.addEventListener('unhandledrejection', function (event) {
            record('js_error', { message: text(event.reason, 300), source: 'unhandledrejection', line: 0, column: 0 });
        });

        doc.addEventListener('scrollend', function () {
            record('scroll_depth', { max_scroll_pct: maxScroll, active_ms: activeMs + (Date.now() - lastTick) });
        }, { passive: true });
    }

    function conversionFor(href) {
        var lower = href.toLowerCase();

        if (lower.indexOf('tel:') === 0) {
            return 'phone_click';
        }

        if (lower.indexOf('mailto:') === 0) {
            return 'email_click';
        }

        if (lower.indexOf('maps.google.') > -1 || lower.indexOf('goo.gl/maps') > -1 || lower.indexOf('maps.app.goo.gl') > -1) {
            return 'directions_click';
        }

        return '';
    }

    function vitals() {
        if (typeof window.PerformanceObserver !== 'function') {
            return;
        }

        var cls = 0;

        watch('largest-contentful-paint', function (entry) {
            record('vital', { metric: 'LCP', value: Math.round(entry.startTime) });
        });

        watch('event', function (entry) {
            if (entry.duration >= 40) {
                record('vital', { metric: 'INP', value: Math.round(entry.duration) });
            }
        });

        watch('layout-shift', function (entry) {
            if (!entry.hadRecentInput) {
                cls += entry.value;
            }
        });

        // ⚠️ **LATCHED, BECAUSE A BUFFERED OBSERVER DELIVERS THE NAVIGATION
        // ENTRY TWICE AND THIS SHIPPED EMITTING EVERY TTFB TWICE** (6003). There
        // is exactly one navigation per page, so a second `navigation` entry is
        // always the same one arriving again: `watch()` observes with
        // `buffered: true`, and an entry that is still being finalised when this
        // bundle runs — which is every async load, because the navigation entry
        // is not complete until `loadEventEnd` — is dispatched live **and**
        // replayed out of the buffer. It was found by capturing what a real
        // browser actually posts, not by reading this file: a third of every
        // batch's events were one metric counted twice, spending the visitor's
        // bytes, §11 row 4's monthly event cap and L0's seven years on it.
        var reportedTtfb = false;

        watch('navigation', function (entry) {
            if (reportedTtfb) {
                return;
            }

            reportedTtfb = true;

            record('vital', { metric: 'TTFB', value: Math.round(entry.responseStart) });
        });

        window.addEventListener('pagehide', function () {
            record('vital', { metric: 'CLS', value: Math.round(cls * 1000) / 1000 });
        });
    }

    function watch(type, each) {
        try {
            var observer = new window.PerformanceObserver(function (list) {
                var entries = list.getEntries();

                for (var i = 0; i < entries.length; i++) {
                    each(entries[i]);
                }
            });

            observer.observe({ type: type, buffered: true });
        } catch (e) {
            // An entry type this browser does not know is a metric we do not
            // get, never a thrown error on somebody's homepage.
        }
    }

    function flushOnExit() {
        window.addEventListener('pagehide', function () {
            flush(true);
        });

        doc.addEventListener('visibilitychange', function () {
            if (doc.visibilityState === 'hidden') {
                flush(true);
            }
        });
    }
})();
