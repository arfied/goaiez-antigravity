<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Models\Business;
use App\Models\Location;
use App\Services\Config\DefaultsRegistry;
use App\Services\Messaging\SendingGuard;
use App\Support\Tenancy;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The two carrier-mandated auto-replies — the only outbound texts this platform
 * sends without asking anybody's permission, and the ones it is *required* to
 * send.
 *
 * ⛔ **A REGISTERED 10DLC CAMPAIGN REQUIRES A WORKING HELP RESPONSE, AND
 * CARRIERS TEST IT.** 2111 puts the registration in hand, so this is owed
 * **before the campaign submission is filed**, not after it. 2125 records the
 * gap: `InboundMessages` has parsed HELP since row 4 slice 2 and deliberately
 * did not answer, because *"the reply would have to name the tenant — which is
 * the thing this path cannot resolve."* {@see TenantNumbers} resolves it now.
 *
 * ⛔ **AND THE STOP CONFIRMATION IS THE SECOND ONE, MISSING UNTIL 3260.** The
 * campaign drafted at Infobip declares five messages in its message-flow
 * section; three existed in code and two did not, and both of the missing pair
 * are carrier-mandatory. This class held `answerHelp()` and no opt-out reply of
 * any kind: {@see InboundMessages} routed `InboundKeyword::Stop` to a handler
 * that suppressed correctly and **sent nothing back**. A STOP honoured in
 * silence is indistinguishable, from the handset, from a STOP that was never
 * received — which is the one thing a person who has just asked to be left
 * alone needs to know did not happen.
 *
 * ⚠️ **THE CONFIRMATION IS SENT TO A NUMBER WE HAVE JUST SUPPRESSED, AND THAT
 * IS THE REQUIREMENT RATHER THAN A HOLE IN IT** (3261). The suppression written
 * one line earlier in `InboundMessages::stop()` is precisely what makes a
 * *permitted* send to this person impossible for ever — `ConsentService::
 * permit()` asks `hasOptedOut()` before it asks anything else. So the
 * confirmation can only ride the permit-free path, and CTIA's rule is that it
 * is the **single final message**: one, immediately, carrying no offer. Nothing
 * here can send a second, because this class takes no body from a caller and
 * `InboundMessages` calls it once per inbound row, behind a unique index on the
 * carrier's own message id.
 *
 * ## Why this does not go through the permit gate, and why that is not a hole
 *
 * ⚠️ **{@see PlatformTexter}'s DOCBLOCK ARGUES AT LENGTH THAT SMS HAS NO
 * UNPERMITTED SEND, AND THIS IS THE EXCEPTION THAT PROVES IT RATHER THAN THE ONE
 * THAT BREAKS IT.** That argument is about *outreach* — messages this platform
 * originates to somebody it chose to contact. Consent is the condition for that,
 * always, and nothing here changes it.
 *
 * A HELP reply is not outreach. It is a **carrier-mandated auto-response to an
 * inbound message from the very number it answers**, sent within seconds,
 * carrying no offer and no invitation. 2099 makes the direction explicit:
 * *"STOP, HELP and suppression are unconditional under both"* sending bases —
 * platform-captured consent and `ImportAttestation` alike. **Unconditional means
 * it must not consult a consent record**, because consulting one means a person
 * with no record — the exact person most likely to text HELP asking who we
 * are — gets no answer.
 *
 * ⛔ **SO THE GUARD HERE IS THE SHAPE OF THE MESSAGE, NOT A PERMIT**, and it is
 * enforced by construction: this class takes no body from a caller. It composes
 * the only sentence it can compose, from the tenant's own name and contact
 * details, and there is no parameter through which a campaign could inject one.
 * A lint in `MessagingTest` confines it to the inbound path.
 *
 * ## What it deliberately does not respect
 *
 * ⛔ **NOT {@see SendingGuard}.** A tenant paused for a
 * high complaint rate still has to answer HELP — refusing to tell somebody who
 * is texting them *because too many people have complained* is precisely
 * backwards, and it is the moment the answer matters most.
 *
 * ⚠️ **BUT IT DOES RESPECT THE KILL SWITCHES**, `sms.enabled` and
 * {@see SendingGuard::OPERATOR_HALT_KEY}, and that is a genuine trade rather
 * than an oversight. An operator who halts the platform has to actually halt
 * it — *"you may not send what you cannot stop"* is the rule that let SMS ship
 * at all — and a send path immune to the stop button is a worse failure than a
 * missed auto-reply. **The consequence, stated plainly so nobody discovers it
 * during a carrier audit: while an operator has the platform halted, HELP is
 * unanswered and the campaign is out of compliance.** That is an operator's
 * decision to make knowingly, which is why it is written here rather than left
 * implicit.
 *
 * ⛔ **AND "KNOWINGLY" IS WHY {@see SendingGuard::AUTOMATIC_HALT_KEY} IS NOT ON
 * THAT LIST — 3780'S ARGUMENT, WHICH WAS FALSE FOR ONE OF THE TWO SWITCHES
 * UNTIL 3980.** This class refuses to consult the automatic number quarantine
 * because *"an automatic quarantine is neither"* an operator's decision — and
 * `WatchPlatformComplaintRate` ran every fifteen minutes writing the very key
 * enumerated above. So the first automatic platform trip returned false here
 * for **every STOP and HELP on the platform**, silently: `InboundMessages::
 * stop()` writes the suppression first and swallows the failure, so the opt-out
 * was honoured and the person was told nothing, and a carrier testing HELP got
 * silence at the moment it was already looking at us. The sweep now writes its
 * own key, `SendingGuard` refuses sends on either, and **this class reads the
 * operator's alone**. Adding the automatic key to the two reads below would
 * reinstate the defect exactly.
 *
 * ⚠️ **THE OWNER MAY YET RULE THE OTHER WAY**, that an automatic halt should
 * stop everything including these replies. This is the safe direction and 2099
 * is written as absolute; a ruling reversing it is a one-line change here and
 * belongs in `DECISIONS.md` before it is made.
 *
 * ## Two replies, because a number that names no tenant still has to answer
 *
 * ⛔ **A HELP ON THE SHARED LANE A NUMBER USED TO GET NOTHING, AND A CARRIER
 * TESTS HELP.** `TenantNumbers::tenantFor()` answers null for a pool number by
 * design, and this class read that as *"send nothing"* when what it means is
 * *"send nothing **about a tenant**"*. {@see self::platformHelpBody()} is the
 * other reply: it names GO AI EZ, which is the truthful answer for a message sent
 * over the GOAIEZ 10DLC brand from the GOAIEZ pool (2101), and it is offered
 * **only for a number this platform actually holds** —
 * {@see TenantNumbers::isPlatformNumber()} draws the line `tenantFor()` cannot.
 *
 * ## Why the wording lives here
 *
 * The public `/sms-optin` page promises what HELP does, and this class does it —
 * two places holding one fact, which is the shape that stops matching. L0 pinned
 * their opt-out keywords by reading them out of `InboundKeyword::parse()` rather
 * than listing them again. **The same rule applies here and this class is the
 * source**: `helpBody()` is public so the page can render the promise from the
 * sentence that is actually sent, and a test reads it back out of the rendered
 * HTML.
 *
 * ⚠️ **{@see self::platformStopBody()} IS PUBLIC FOR EXACTLY THAT REASON AND NO
 * OTHER** (3263). That page already promised the *scope* of a STOP — *"it stops
 * every message to your number from every business using GO AI EZ"* — and from
 * 3260 it also shows the confirmation a reader will actually receive. Retyping
 * that sentence into Blade would be the second list, so the page renders the
 * platform form of the real body and `SmsOptInPageTest` reads it back.
 *
 * ## The scope sentence is not a wording preference
 *
 * ⛔ **"FROM ANY BUSINESS USING GO AI EZ" IS LOAD-BEARING.** {@see OptOutScope}
 * has two cases because the lanes are literally different phone numbers, and
 * `InboundMessages` writes the **platform** one for every carrier STOP —
 * deliberately, with no scope parameter anywhere on that path (1582). A
 * confirmation naming one business would understate what was just written and
 * would contradict `/sms-optin`, which is the page a carrier reviewer clicks
 * through from.
 *
 * ⚠️ **"REPLY START TO RESUBSCRIBE" IS TRUE TODAY AND WAS CHECKED RATHER THAN
 * ASSUMED** (3262). `InboundKeyword::Start` parses, `InboundMessages::start()`
 * calls `liftFromCarrier()`, and `LiftSource::CarrierStart` is the reversal it
 * writes. This is the claim-before-the-mechanism shape (314–316) turned the
 * right way round: the sentence ships because the lift already exists.
 */
final class ComplianceReplies
{
    /** Who the record names for a reply nobody in this company composed. */
    public const string ACTOR = 'system:compliance-reply';

    /**
     * The longest a message declared on the campaign filing may be.
     *
     * ⚠️ **THIS IS THE CARRIER'S FIELD LIMIT, NOT A SEGMENT BUDGET** (3264). A
     * 10DLC message-flow entry is capped at 320 characters, so a body this
     * application can compose above that length is one the filing cannot
     * truthfully declare — the sample text and the shipped text would differ,
     * which is the misrepresentation `/sms-optin`'s own header refuses.
     *
     * ⚠️ **AND IT LIVES HERE FOR THE OPT-IN CONFIRMATION TOO, WHICH IS NOT IN
     * THIS CLASS.** `OptInConfirmations` composes the third declared message
     * and reads this constant rather than restating `320`: one number in two
     * files is the shape that stops matching, and this is the class that had to
     * state it first.
     *
     * ⛔ **IT IS NOT A TRUNCATION BUDGET.** 1570 refuses truncation in a send
     * path — *"the first thing a length limit would cut is the tail, and the
     * tail is the opt-out instruction"*. Every body here answers an overlong
     * business name by dropping the **attribution**, which is the only variable
     * part, and never by cutting the sentence.
     */
    public const int CARRIER_FIELD_LIMIT = 320;

    /**
     * Everything a STOP confirmation says after the attribution.
     *
     * ⚠️ **ONE STRING SHARED BY BOTH FORMS, BECAUSE THE ONLY THING THAT DIFFERS
     * IS WHO IS SPEAKING.** {@see self::helpBody()} and
     * {@see self::platformHelpBody()} restate their clauses independently and
     * have already drifted once in review; the two STOP bodies cannot, because
     * there is one copy of the sentence that carries the legal content.
     */
    private const string STOP_SENTENCES = 'you are unsubscribed and will receive no further messages '
        .'from any business using GO AI EZ. Reply START to resubscribe.';

    public function __construct(
        private readonly TenantNumbers $numbers,
        private readonly PlatformTexter $texter,
        private readonly DefaultsRegistry $defaults,
    ) {}

    /**
     * Answer a HELP, if we can work out who it was addressed to.
     *
     * @param  string  $from  The person who texted HELP. Already normalised by
     *                        the inbound handler — never re-derived here.
     * @param  string|null  $toNumber  The number they texted, which is the only
     *                                 thing in the payload that names a tenant.
     */
    public function answerHelp(string $from, ?string $toNumber): bool
    {
        return $this->reply($from, $toNumber, 'HELP', $this->helpBody(...), $this->platformHelpBody(...));
    }

    /**
     * Confirm a STOP to the person who sent it.
     *
     * ⛔ **THE FIFTH MESSAGE THE CAMPAIGN FILING DECLARES, AND THE ONE THE
     * PRODUCT DID NOT SEND** (3260). `InboundMessages::stop()` suppressed and
     * answered with silence, and from a handset a STOP honoured in silence and a
     * STOP that never arrived are the same event. Carriers require the
     * confirmation and test it the way they test HELP.
     *
     * ⚠️ **CALLED AFTER THE SUPPRESSION IS WRITTEN, NEVER BEFORE, AND
     * `InboundMessages` IS WHERE THAT ORDER IS ENFORCED.** A confirmation sent
     * first would be a promise made before the thing it promises exists — and if
     * the write then failed, the person would hold a text saying they are
     * unsubscribed while every gate still permitted a send to them.
     *
     * @param  string  $from  The person who texted STOP. Already normalised by
     *                        the inbound handler — never re-derived here.
     * @param  string|null  $toNumber  The number they texted.
     */
    public function answerStop(string $from, ?string $toNumber): bool
    {
        return $this->reply($from, $toNumber, 'STOP', $this->stopBody(...), $this->platformStopBody(...));
    }

    /**
     * Send one compliance reply, whichever of the two it is.
     *
     * ⚠️ **ONE DISPATCH FOR BOTH REPLIES, AND THAT IS THE POINT RATHER THAN
     * TIDINESS** (3265). Every guard below was argued once, for HELP, and each
     * one is exactly as true of STOP: the unresolvable receiving number, the two
     * kill switches, the platform fallback, the tenant that has since gone, and
     * the swallowed transport failure. Copying them into a second method would
     * make it possible to add a guard to one reply and not the other — and the
     * half that would then be wrong is whichever one nobody was looking at.
     *
     * ⚠️ **RETURNS A BOOL AND SWALLOWS EVERY TRANSPORT FAILURE.** This runs
     * inside a carrier webhook. An exception escaping here turns a 200 into a
     * 500, and Infobip's response to a 500 is to redeliver the same inbound
     * message — which, with the reply already sent, texts the person twice and
     * keeps doing so. **A failed reply is logged and the webhook still
     * succeeds**, because the alternative is a retry storm aimed at a member of
     * the public.
     *
     * ⛔ **AND *LOGGED* IS THE WHOLE OF IT, WHICH IS AN OPEN QUESTION RATHER
     * THAN A SETTLED DESIGN** (11470, 11471). Both callers discard this
     * boolean — `InboundMessages::stop()` throws it away and the HELP arm's
     * `match` is a statement — and nothing on this path writes a record of any
     * kind: no `outreach_messages` row, no `SendingHealth` count, no audit row,
     * no feed item, and `inbound_messages` has no `replied_at` column. **So a
     * carrier-mandated confirmation that did not arrive is invisible to every
     * screen and every command this platform has**, and the only trace is the
     * `Log::warning` below, in a file nobody is watching for it.
     *
     * ⚠️ **IT IS INVISIBLE AND NOT FALSIFIED, AND THAT DISTINCTION IS WHY
     * `PlatformTexter::replyToInbound()` WAS LEFT UNGUARDED IN THE WAVE THAT
     * GUARDED `PlatformTexter::alertOperator()`.** There the same fabricated
     * acceptance was **stamped into a column an instrument reads**; here it
     * reaches nothing at all, and the ordering above means a dead transport
     * costs the confirmation and never the suppression. **A bell, a counter and
     * a column were each considered and none was built** — the argument is at
     * 11471 and the choice is the owner's.
     *
     * ⚠️ **AND THERE IS NO LIVE OCCUPANT TODAY, WHICH NARROWS THE URGENCY AND
     * NOT THE FINDING** (11483). `credentials.infobip_webhook_secret` is unset
     * on the running install, so `InfobipInboundController` answers 401 to
     * every genuine delivery and nothing in this class is reached at all.
     * ⛔ **What that costs is upstream and is worse**: the suppression write
     * this class is deliberately sequenced behind is on the far side of the
     * same 401. **A gap with no occupant is still a gap** — and the day the
     * secret is pasted in, this one acquires its occupant in the same minute.
     *
     * @param  string  $kind  For the log line only. ⚠️ **A keyword of ours, never
     *                        anything off the payload** — this is the one path
     *                        holding a member of the public's mobile number.
     * @param  Closure(Business): string  $tenantBody
     * @param  Closure(): string  $platformBody
     */
    private function reply(string $from, ?string $toNumber, string $kind, Closure $tenantBody, Closure $platformBody): bool
    {
        if ($toNumber === null) {
            // ⚠️ **NOT AN ERROR, AND NOT GUESSABLE.** Some inbound payloads omit
            // the receiving number. Without it there is no tenant, and the only
            // alternatives are inferring one from the sender's history — which
            // is wrong the first time somebody is a customer of two tenants —
            // or answering with the platform's own name, which tells the person
            // nothing about the business that actually texted them.
            //
            // ⛔ **AND THE PLATFORM REPLY BELOW IS NOT AVAILABLE HERE EITHER**,
            // which is the distinction worth keeping. That reply answers *for a
            // number this platform holds*; with no receiving number in the
            // payload we cannot say the message even arrived on one of ours, and
            // the reply would go out on whichever number the selector picked. A
            // stranger receiving an unsolicited text naming a company they never
            // messaged is worse than a missed auto-reply.
            return false;
        }

        // ⛔ **THE `try` STARTS HERE, NOT FOUR LINES LOWER, AND THAT IS 3285.**
        // The two registry reads and `TenantNumbers::tenantFor()` are database
        // calls, and they sat *outside* it while `InboundMessages` claimed this
        // method "swallows its own failure by construction" — 314–316's shape in
        // the slice that quotes it. A connection blip in any of the three threw
        // out through `handle()` into the carrier webhook, which turns a 200 into
        // a 500 and, worse, **drops every later message in the same delivery
        // batch** — against that controller's own rule that one unreadable entry
        // must not cost the STOP beside it.
        try {
            if ($this->defaults->value('sms.enabled') !== true) {
                return false;
            }

            // ⛔ **THE OPERATOR'S KEY, NEVER `SendingGuard::AUTOMATIC_HALT_KEY`**
            // (3980). See this class's docblock: the automatic sweep throws a
            // different switch precisely so that a machine cannot stop a
            // carrier-mandated reply. `SendingGuard::haltedPlatformWide()` is
            // the wrong call here for the same reason — it answers "may a send
            // go out", and this is not a send.
            if ($this->defaults->value(SendingGuard::OPERATOR_HALT_KEY) === true) {
                return false;
            }

            $businessId = $this->numbers->tenantFor($toNumber);

            if ($businessId === null) {
                return $this->answerAsPlatform($from, $toNumber, $kind, $platformBody);
            }

            return Tenancy::actingAs($businessId, function () use ($from, $tenantBody): bool {
                // ⚠️ `idOrFail()` rather than the captured id: `actingAs()` is
                // what established the tenant, so this asks the resolver what it
                // actually set rather than trusting a variable from outside the
                // closure. ⚠️ **The result used to be assigned back over
                // `$businessId` and never read** — a dead store that shadowed the
                // outer variable and read as though the two could differ.
                $business = Business::query()->find(Tenancy::idOrFail());

                if ($business === null) {
                    return false;
                }

                $sent = $this->texter->replyToInbound($from, $tenantBody($business));

                return $sent !== null;
            });
        } catch (Throwable $e) {
            // ⚠️ **THE MESSAGE, NEVER THE NUMBER.** `CLAUDE.md` requires vendor
            // payloads be redacted before logging, and the one thing this path
            // holds is a member of the public's mobile number.
            Log::warning("A {$kind} reply could not be delivered.", [
                'reason' => $e::class,
                'actor' => self::ACTOR,
            ]);

            return false;
        }
    }

    /**
     * Answer a reply that arrived on a number belonging to no tenant.
     *
     * ⛔ **A CARRIER TESTS HELP AND STOP, AND SILENCE IS THE FAILURE THAT
     * MATTERS.** The
     * shared Lane A pool number is what every tenant without one of their own
     * sends from, so it is the number a recipient — or a carrier auditor — is
     * most likely to text. Until this existed the answer was nothing at all,
     * because `tenantFor()` correctly refuses to name a business it cannot
     * resolve, and the refusal was read as "send nothing" rather than "send
     * nothing *about a tenant*".
     *
     * ⚠️ **IT NAMES THE PLATFORM AND NEVER A BUSINESS**, which is 2190 held
     * rather than bent. Inferring the tenant from the sender's message history is
     * exactly the inference this whole file refuses: it is wrong the first time
     * somebody is a customer of two tenants, and it hands an attacker the choice
     * of which business a reply names. "GO AI EZ" is the truthful answer to *"who
     * is texting me"* for a message that went out over the GOAIEZ 10DLC brand
     * from the GOAIEZ pool — which, per 2101, is precisely what a Lane A send is.
     *
     * ⛔ **AND ONLY FOR A NUMBER WE ACTUALLY HOLD.** `tenantFor()` collapses two
     * different facts into null — one of ours that nobody owns, and one that was
     * never ours — and only the first may be answered. Replying for a number this
     * platform does not hold would send an unsolicited text, from some *other*
     * number, to somebody who never messaged us.
     *
     * ⛔ **AND THE STOP CONFIRMATION NEEDS THIS BRANCH MORE THAN HELP DOES.**
     * Every tenant without their own R8 number sends over the shared pool
     * number, so that is the number most STOPs arrive on — and it is the number
     * whose STOP `InboundMessages` can attribute to nobody (the counter gap that
     * class records). Leaving the pool number unconfirmed would have meant the
     * majority of real opt-outs going unacknowledged.
     *
     * @param  Closure(): string  $platformBody
     */
    private function answerAsPlatform(string $from, string $toNumber, string $kind, Closure $platformBody): bool
    {
        if (! $this->numbers->isPlatformNumber($toNumber)) {
            return false;
        }

        try {
            return $this->texter->replyToInbound($from, $platformBody()) !== null;
        } catch (Throwable $e) {
            // The message, never the number — the same rule as the tenant branch.
            Log::warning("A platform {$kind} reply could not be delivered.", [
                'reason' => $e::class,
                'actor' => self::ACTOR,
            ]);

            return false;
        }
    }

    /**
     * The sentence a HELP gets back when no tenant owns the number it arrived on.
     *
     * ⚠️ **THE SAME FOUR CLAUSES AS {@see self::helpBody()}, WITH THE FIRST ONE
     * ANSWERED HONESTLY RATHER THAN OMITTED.** Who is texting me — GO AI EZ, on
     * its own behalf, because nobody else can be named. How do I reach a human —
     * `support.contact_email`, and **only when it is set**: decision 482's rule,
     * that printing an unmonitored address on the one line whose whole job is to
     * give somebody a way to reach us is worse than printing none. `STOP to opt
     * out` is the CTIA requirement that rides on every HELP response, and
     * message-and-data-rates is the standing disclosure.
     *
     * ⚠️ **NO LINK, FOR {@see self::helpBody()}'s REASON** (2105): a URL in an
     * auto-reply to an unknown number is what carrier link-filtering exists to
     * catch.
     */
    public function platformHelpBody(): string
    {
        $contact = $this->platformContact();

        $parts = array_filter([
            'GO AI EZ.',
            $contact === null ? null : "Contact: {$contact}.",
            'Msg&data rates may apply.',
            'Reply STOP to opt out.',
        ]);

        return implode(' ', $parts);
    }

    /**
     * The sentence a HELP gets back.
     *
     * ⚠️ **PUBLIC BECAUSE `/sms-optin` RENDERS THE SAME FACT AND MUST READ IT
     * FROM HERE.** Two lists of the same thing is the shape that stops matching;
     * L0 drove their opt-out keywords out of `InboundKeyword::parse()` for
     * exactly this reason, and their page's promise about HELP is now pinned to
     * this method.
     *
     * ⛔ **NO PAGE RENDERS THIS METHOD, AND NONE EVER HAS — NOTED 2026-08-20
     * (6102).** `answerHelp()` is its only caller in `app/`; `/sms-optin`
     * renders {@see self::platformStopBody()}, which is public for the reason
     * this paragraph gives and is the one it is actually true of. **The
     * paragraph is kept because it now states a rule rather than a mechanism**,
     * and the rule matters more from this slice than it did before it: this body
     * carries a **tenant's own phone number**, so a public page rendering it
     * would put one business's contact details on a page every other business's
     * carrier reviewer opens. If that page ever does read this method, it must
     * read the platform form.
     *
     * ⚠️ **EVERY CLAUSE IS REQUIRED AND NONE IS DECORATION.** The business name
     * answers *"who is texting me"*, which is the whole question. The contact
     * route answers *"how do I reach a human"*. `STOP to opt out` is the CTIA
     * requirement that rides on every HELP response. Message-and-data-rates is
     * the standing disclosure.
     *
     * ⚠️ **NO LINK, DELIBERATELY.** A URL in an auto-reply to an unknown number
     * is what carrier link-filtering exists to catch, and 2105 flags carrier
     * link checking as an input to this roster rather than an audit of it. The
     * phone number is the contact route when there is one.
     *
     * ⛔ **THIS IS THE FOURTH DECLARED MESSAGE AND 3271 DID NOT COVER IT**
     * (3283). That decision said one `support.contact_email` in Ops would
     * satisfy *"both declared messages"*. **It is wrong about this one**: this
     * body has never read that key — {@see self::contactFor()} reads
     * `locations.primary_phone` — so for every tenant with their own R8 number
     * the HELP reply carries the business's phone and can never carry
     * `support@goaiez.com`, however the registry is set. The test that pinned
     * 3271 pinned {@see self::platformHelpBody()} only.
     *
     * ✅ **THE BUSINESS'S OWN PHONE *IS* A CUSTOMER CARE CONTACT, AND A BETTER
     * ONE**, so a tenant with a number already satisfies Infobip's requirement.
     * What was genuinely missing is the tenant with **no** location phone, whose
     * HELP reply carried no contact at all — and the platform address is then
     * the only one there is. 482's rule survives intact and is *narrowed to what
     * it actually says*: never substitute our support desk **for** a business's
     * own contact, because that answers a question the person did not ask. It
     * never said a reply with no contact at all is preferable to one with ours,
     * and Infobip makes that reply non-conforming.
     *
     * ⛔ **"A TENANT WITH A NUMBER" DESCRIBED NOBODY, AND THE PARAGRAPH ABOVE
     * READ AS THOUGH IT DESCRIBED ALMOST EVERYBODY — CORRECTED 2026-08-20
     * (6101).** `locations.primary_phone` had **no writer in `app/`** from Stage
     * 0 until that date: `LocationProvisioner` creates a location with a name and
     * stops, and `LocationFactory` was the only thing in the whole repository
     * that ever filled the column. So {@see self::contactFor()} returned null for
     * **every tenant that has ever registered**, the fallback written for the
     * exception fired in **100% of cases**, and — since `support.contact_email`
     * was seeded at 3413 — every member of the public who texted HELP to any
     * business on this platform was answered with `support@goaiez.com`. **That is
     * the collision 482 exists to prevent, landing on everybody rather than on
     * nobody**, and 3283's *"can never carry `support@goaiez.com`, however the
     * registry is set"* was exactly inverted.
     *
     * ⚠️ **THE READING IS KEPT RATHER THAN REWRITTEN BECAUSE THE RULE IT STATES
     * IS UNCHANGED AND IS NOW TRUE.** `App\Services\Tenant\LocationDetails` is
     * the writer, `Account\Locations` is the screen, and the ordering below does
     * what 3283 said it already did. **What changed is the population**: the
     * first arm is reachable.
     *
     * ⚠️ **AND THE TELL WAS AVAILABLE THE WHOLE TIME** — every test of this body
     * built its location from a factory that filled the column, so the suite was
     * green on an arm production could not reach. That is `CLAUDE.md`'s opening
     * sentence, and the factory no longer fills it.
     *
     * ⚠️ **THE ORDER IS THE WHOLE OF IT**: the location's number first, always;
     * the platform address only when there is none, and only when an operator
     * has set it — so an unmonitored mailbox is still never printed.
     *
     * ⛔ **AND THE CONTACT CLAUSE IS NOW TENANT-TYPED TEXT INSIDE A CARRIER-
     * MANDATED MESSAGE, WHICH IT WAS NOT BEFORE** (6102). Every other variable
     * part of this body is the business *name*; the phone number is a second one,
     * and this class's own rule two paragraphs up is that the reply carries **no
     * link, deliberately** (2105). Nothing here can enforce that, because by the
     * time the string arrives it is just a string —
     * `LocationDetails::normalisePhone()` is where it is enforced, by refusing
     * every character that is not a digit, a space or one of `+ - ( ) .`, and
     * `LocationDetails::PHONE_MAX` is what keeps the fallback body below
     * {@see self::CARRIER_FIELD_LIMIT} when the attribution has already been
     * dropped and there is nothing left to drop.
     *
     * ⚠️ **AND THE 320-CHARACTER CEILING APPLIES HERE TOO** (3284). This body
     * had no guard, so a business name over roughly 230 characters composed a
     * HELP reply above the limit this branch declared load-bearing for the other
     * three. Same resolution as the others: the attribution goes, never the
     * clauses.
     */
    public function helpBody(Business $business): string
    {
        $name = trim($business->name);
        $contact = $this->contactFor($business) ?? $this->platformContact();

        $clauses = implode(' ', array_filter([
            $contact === null ? null : "Contact: {$contact}.",
            'Msg&data rates may apply.',
            'Reply STOP to opt out.',
        ]));

        $body = $name === '' ? "GO AI EZ. {$clauses}" : "{$name} via GO AI EZ. {$clauses}";

        return mb_strlen($body) > self::CARRIER_FIELD_LIMIT ? "GO AI EZ. {$clauses}" : $body;
    }

    /**
     * The platform's own support address, when an operator has stood one up.
     *
     * ⚠️ **ONE READER OF `support.contact_email`'S EMPTY STATE, SHARED BY BOTH
     * HELP FORMS**, so the two cannot come to disagree about what an unset row
     * means — which is exactly how 3271 came to be wrong about one of them.
     */
    private function platformContact(): ?string
    {
        $contact = $this->defaults->value('support.contact_email');
        $contact = is_string($contact) ? trim($contact) : '';

        return $contact === '' ? null : $contact;
    }

    /**
     * The sentence a STOP gets back, naming the business it arrived for.
     *
     * ⛔ **THREE CLAUSES, ALL THREE REQUIRED.** Who is confirming — the business,
     * with the platform disclosure that every Lane A message carries. **What was
     * done, and how far it reaches** — the scope sentence, which is the one this
     * class's docblock argues is not a wording preference. How to undo it —
     * `START`, which {@see InboundMessages::start()} really honours through
     * `LiftSource::CarrierStart`.
     *
     * ⛔ **NO CONTACT CLAUSE, UNLIKE {@see self::helpBody()}, AND THE ASYMMETRY
     * IS THE POINT** (3266). A HELP asks *"how do I reach a human"* and the
     * clause answers it. A STOP asks nothing — it is an instruction, already
     * obeyed — and offering a support address on the way out invites a reply
     * from somebody who has just said they want no more contact. It is also what
     * keeps this body clear of the 482 collision that the HELP reply and the
     * opt-in confirmation both have to live with.
     *
     * ⚠️ **NO LINK, FOR {@see self::helpBody()}'s REASON** (2105).
     *
     * ⚠️ **THE ATTRIBUTION IS DROPPED RATHER THAN THE SENTENCE TRUNCATED WHEN A
     * BUSINESS NAME WOULD PUSH THIS PAST {@see self::CARRIER_FIELD_LIMIT}.** The
     * fixed text is the compliance content; the name is the only variable part,
     * so it is the only part that may go.
     */
    public function stopBody(Business $business): string
    {
        $name = trim($business->name);

        if ($name === '') {
            return $this->platformStopBody();
        }

        $body = "{$name} via GO AI EZ: ".self::STOP_SENTENCES;

        return mb_strlen($body) > self::CARRIER_FIELD_LIMIT ? $this->platformStopBody() : $body;
    }

    /**
     * The sentence a STOP gets back when no tenant owns the number it arrived on.
     *
     * ⚠️ **"GO AI EZ" TWICE, AND THE SECOND ONE IS NOT A DUPLICATE OF THE
     * FIRST.** The first is the sender identifying itself, exactly as
     * {@see self::platformHelpBody()} does. The second is the *scope* — the
     * statement that this refusal reaches every business on the platform — and
     * dropping it to avoid the repetition would turn a platform-wide opt-out
     * into a sentence a reader would take as covering one sender.
     *
     * ⚠️ **PUBLIC SO `/sms-optin` CAN SHOW IT** (3263). That page tells a carrier
     * reviewer, and any member of the public, what replying STOP does; from 3260
     * it also shows the exact text they get back, read from here rather than
     * retyped.
     */
    public function platformStopBody(): string
    {
        return 'GO AI EZ: '.self::STOP_SENTENCES;
    }

    /**
     * How to reach this business, or null when we do not know.
     *
     * ⛔ **THIS BLOCK SAID "NULL IS ANSWERED BY OMITTING THE CLAUSE, NEVER BY
     * SUBSTITUTING OUR OWN SUPPORT LINE" AND THE CALLER HAS SUBSTITUTED ONE
     * SINCE 3283 — BOTH READINGS KEPT AND DATED (6101).** That paragraph was
     * written before 3283 and 3283 changed the behaviour without touching it, so
     * for the whole of its life this method has carried a categorical rule its
     * one caller broke on the next line. **The surviving half is 3283's
     * narrowing**: never substitute our support desk *for* a business's own
     * contact — the ordering in {@see self::helpBody()} is what enforces it — and
     * a reply with no contact at all is what Infobip makes non-conforming, so
     * ours goes in when there is nothing else.
     *
     * ⚠️ **THE ARGUMENT THE OLD PARAGRAPH MADE IS STILL THE RIGHT ONE ABOUT THE
     * THING IT WAS ABOUT.** A person asking who texted them is asking about the
     * business, and routing them to the platform's support desk answers a
     * question they did not ask while hiding the one they did. **That is exactly
     * what happened to every tenant on this platform until 2026-08-20**, because
     * this method could not return anything — see {@see self::helpBody()}.
     */
    private function contactFor(Business $business): ?string
    {
        // ⚠️ **`locations.primary_phone`, BECAUSE `businesses` HAS NO PHONE
        // COLUMN AND THE FIRST DRAFT OF THIS METHOD ASSUMED IT DID.** That is
        // the writerless-column failure approached from the other side: a
        // property read that never existed would have returned null forever and
        // the clause would have silently vanished from every reply, with a green
        // suite and a HELP response that answers half the question.
        //
        // ⛔ **AND IT LANDED ON A WRITERLESS *COLUMN* INSTEAD, WHICH DID EXACTLY
        // THAT FOR TWO MONTHS — CORRECTED 2026-08-20 (6101).** The paragraph
        // above describes, precisely, the failure the line below then had:
        // `locations.primary_phone` was written by `LocationFactory` and by
        // nothing in `app/`, so this returned null forever, with a green suite,
        // and the clause did not vanish — it was silently replaced by our own
        // support address. **Avoiding a writerless property by reaching for a
        // column is not avoiding anything unless somebody checks the column has a
        // writer**, and this is 314–316 in the comment that warns about it.
        // `App\Services\Tenant\LocationDetails` is the writer now.
        //
        // ⚠️ **NO `primary_phone_confirmed_at` PREDICATE, DELIBERATELY.** A phone
        // number with no confirmation is unrepresentable — the CHECK
        // `locations_phone_and_confirmation_travel_together` says so at the
        // database — so a second predicate here would be an inner guard the outer
        // one makes unfalsifiable (398), and an unfalsifiable guard is the one
        // somebody deletes as redundant while believing it was doing something.
        //
        // The location's number is the better source anyway — it is the number
        // the business publishes and the one a person is expecting to recognise.
        $phone = Location::query()
            ->where('business_id', $business->id)
            ->whereNotNull('primary_phone')
            ->orderBy('id')
            ->value('primary_phone');

        return is_string($phone) && trim($phone) !== '' ? trim($phone) : null;
    }
}
