<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Ops\OperatorAlerts;
use App\Services\Pixel\PixelAcceptances;

/**
 * What may honestly be said to a tenant about whether their pixel is collecting
 * anything (7800).
 *
 * ⛔ **THE SCREEN THIS SERVES SAID THE OPPOSITE OF THE TRUTH FOR EVERY
 * FRESHLY PROVISIONED TENANT** — *"Once it is on your site, there is nothing
 * else to do"* — while `WidgetPlugins::provision()` writes an empty allowlist,
 * `originIsAllowed()` reads empty as **serve nowhere** (4968), and
 * `PixelIngestController` answers `204` to every refusal by §11's transport
 * rule, so the browser's network tab shows success. Three artefacts agreeing
 * with each other and none of them agreeing with the archive.
 *
 * ⛔ **THIS PARAGRAPH READ "ONLY ONE OF THESE FOUR IS A POSITIVE AND IT IS THE
 * WEAKEST OF THEM" AND WENT ON TO ARGUE THAT A FIFTH COULD NOT EXIST. IT WAS
 * WRONG WHEN IT WAS WRITTEN — BOTH READINGS KEPT AND DATED, 2026-08-22
 * (7980–7983).** The sentence continued: *"Nothing in this application writes a
 * `last_seen_at` for accepted pixel traffic — the collector archives to object
 * storage on a queued job, and `pixel_keys` carries no such column — so **there
 * is no state here meaning 'we are receiving your data'**, and {@see
 * self::Listening} is careful to claim only what a refusal-shaped reader can
 * see. A 'connected ✓' wired to nothing is `CLAUDE.md`'s most repeated defect
 * wearing a green tick, and this enum is deliberately incapable of rendering
 * one."*
 *
 * ⛔ **EVERY CLAUSE ABOUT `pixel_keys` IS TRUE AND THE CONCLUSION DOES NOT
 * FOLLOW FROM IT.** [[\App\Jobs\ArchivePixelBatchJob]] archives to object
 * storage **and then loads the derived event layer inline in the same job**, per
 * tenant, row-level secured, on an index whose leading columns are the tenant
 * and the receipt time. That write landed twelve hours *before* the screen this
 * enum serves. So the acceptance signal was in the schema the whole time, under
 * a different table than the one this docblock went looking in — 314–316's
 * shape, in the file whose entire subject is what may honestly be said.
 * `App\Services\Warehouse\PixelArrivals` is the reader, and it carries
 * the five separate reasons its **negative** is still weak.
 *
 * ✅ **SO THERE IS A FIFTH CASE NOW AND IT RENDERS AS `Ok`** —
 * {@see self::Collecting}, and it is the only one here that is a claim rather
 * than an inference from silence. ⚠️ **{@see self::Listening} IS UNCHANGED IN
 * KIND AND SHARPER IN WORDING**: it now means *listed, nothing turned away and
 * nothing arrived*, which is a narrower statement than it could make before —
 * and it is still `Unknown` rather than `Alert`, because a queue, a horizon, a
 * replay and a website nobody has visited all produce it.
 *
 * ⛔ **AND THE SENTENCE THAT NARROWING PRODUCED WAS ITSELF A CLAIM ABOUT
 * SOMEBODY ELSE'S BUSINESS THAT THIS PLATFORM COULD NOT SUPPORT — CORRECTED
 * 2026-08-26 (9900–9902).** {@see self::Listening} went on to say *"A website
 * has to be visited before there is anything to send"*, which is a statement
 * about the tenant's visitors made out of a fact about **our own object
 * store**: [[\App\Jobs\ArchivePixelBatchJob]] archives first and derives
 * second, so a disk that throws leaves no derived row (9844) and the reader
 * above answers `null` — and `Storage::disk('s3')` could not be built on any
 * deployment between 2026-08-18 and 2026-08-25 (9408, 9421). **For that week
 * an owner whose line was installed correctly, on a site with real visitors,
 * was told nobody had come.**
 *
 * ✅ **SO THERE IS A SIXTH CASE AND IT IS THE FIRST ONE HERE WHOSE SUBJECT IS
 * US** — {@see self::AcceptedNotShown}, decided by
 * `App\Services\Pixel\PixelAcceptances` reading the counter the collector
 * already writes on the hot path. {@see self::Listening} keeps its sentence and
 * loses the population it was lying to.
 *
 * `22`: colour is never the sole indicator, so every case carries a label and a
 * sentence, and the Blade pairs them with the pill's own icon.
 */
enum PixelCollectionState: string
{
    /**
     * The tenant has no review feed at all, so there is nowhere for them to
     * name a website.
     *
     * ⚠️ **NOT THE SAME AS {@see self::NoWebsiteListed}, AND THE DIFFERENCE IS
     * WHAT A PERSON CAN DO ABOUT IT.** The collector cannot tell them apart —
     * both refuse every origin — but sending this tenant to the reviews screen
     * would send them to a page that shows *"Not ready yet"* and no box.
     */
    case NotSetUpYet = 'not_set_up_yet';

    /**
     * Feeds exist and no website is named on any of them: the state every
     * tenant is provisioned into.
     */
    case NoWebsiteListed = 'no_website_listed';

    /**
     * Websites are listed and traffic is still being refused — so something is
     * calling with this tenant's line from an address that is not on the list.
     *
     * ⚠️ **USUALLY A NEAR MISS RATHER THAN A STRANGER**: `example.com` and
     * `www.example.com` are two different websites to the gate, by
     * `normaliseHost()`'s deliberate refusal of wildcards.
     */
    case TrafficRefused = 'traffic_refused';

    /**
     * Websites are listed, nothing has been refused, and events from this
     * tenant's own pixel have been accepted and kept.
     *
     * ⛔ **THE ONE CASE HERE THAT IS EVIDENCE RATHER THAN AN INFERENCE FROM
     * SILENCE**, and the only one that may render a green tick. A derived event
     * row exists only if a batch presented this tenant's public key from an
     * origin their own account lists, passed the rule 24 gate, the monthly cap,
     * the Global Privacy Control check and the form-value check, was archived,
     * and derived at least one event. Nothing else in this application can put
     * one there.
     *
     * ⚠️ **IT RANKS BELOW {@see self::TrafficRefused} AND THAT IS DELIBERATE.**
     * A tenant can be collecting from one website and having another turned
     * away — the `www` near miss is the common case — and the pill carries the
     * sentence somebody can act on. **The arrival itself is rendered anyway**,
     * beside whichever state won, so the reassurance is not lost to the
     * ordering.
     */
    case Collecting = 'collecting';

    /**
     * Websites are listed, this tenant's own events got past every gate the
     * collector can refuse them at, and not one of them has become visible.
     *
     * ⛔ **THE ONLY CASE IN THIS ENUM WHOSE SUBJECT IS THIS PLATFORM RATHER THAN
     * THE TENANT, AND IT EXISTS BECAUSE {@see self::Listening} WAS ANSWERING
     * FOR BOTH** (9900). Everything else here is a fact about their account:
     * no feed, no website listed, an address that does not match, events kept.
     * This one says *we took your visitors' events and they are not here*, and
     * every remaining explanation for it is on our side of the wire — a queue
     * that has not run, an archive that threw, a batch that derived nothing, a
     * replay that emptied the range, or one of the two refusals the collector
     * applies after the counter.
     *
     * ⚠️ **IT IS NOT "THE ARCHIVE IS BROKEN" AND MUST NEVER BE RENDERED AS
     * IT.** Queue latency reaches this state a second after a healthy install's
     * first visit, and so does a permanently dead object store; nothing on this
     * screen can tell those apart, and the wording below is deliberately true of
     * both. {@see OperatorAlerts} is where the difference is
     * knowable, and it is knowable by us rather than by the owner —
     * `OperatorAlertKind::PixelArchiveFailed` (9840) is that bell.
     *
     * ⚠️ **IT OUTRANKS NOTHING.** A refused address and an empty website list
     * are still shown first, on {@see self::Collecting}'s own reasoning: the
     * sentence somebody can act on goes in front of the sentence they cannot.
     */
    case AcceptedNotShown = 'accepted_not_shown';

    /**
     * Websites are listed, nothing has been refused inside the window, nothing
     * of this tenant's has been admitted inside the window, and nothing has
     * arrived.
     *
     * ⛔ **THE SECOND OF THOSE FOUR CLAUSES WAS ADDED ON 2026-08-26 AND IT IS
     * WHAT MAKES THE SENTENCE BELOW TRUE** (9901). Without it this case also
     * covered the tenant whose visits we accepted and then lost, and told them
     * their website had not been visited. See {@see self::AcceptedNotShown}.
     *
     * ⚠️ **ONE POPULATION IS STILL HERE THAT DOES NOT BELONG, AND IT IS NAMED
     * RATHER THAN FIXED** (9908): a `Phi`-classified account is refused at §11
     * row 9(a), **ahead of every gate that records anything**, so a busy
     * health-classified website reads here exactly like a website nobody has
     * visited. No such tenant can be onboarded while rule 24's KMS key waits on
     * Stage 3, so the arm is unreachable today and copy written for it would be
     * copy nobody can see — 256's vacuity with a sentence attached.
     *
     * ⛔ **THIS IS NOT "BROKEN" AND MUST NEVER BE RENDERED AS IT**, which is the
     * mirror image of what this case's docblock used to warn about. It read:
     * *"THIS IS NOT 'WORKING' AND MUST NEVER BE RENDERED AS IT. Silence here is
     * the same observation as a snippet nobody pasted: this reader sees
     * refusals, and an accepted batch writes nothing it can read."* ⚠️ **The
     * last clause stopped being true on 2026-08-22 and the caution survives the
     * correction** — an accepted batch now writes something a reader can see, so
     * this case no longer covers the working tenant, and what it covers instead
     * is five things at once: a line nobody has pasted, a website nobody has
     * visited, a batch still on the queue, a range a replay has emptied, and
     * traffic older than the derived layer's four-hundred-day horizon.
     * `App\Services\Warehouse\PixelArrivals` argues each of them.
     *
     * ⚠️ **TWO OF THOSE FIVE LEFT ON 2026-08-26 AND THE PARAGRAPH IS KEPT
     * BECAUSE ITS ARGUMENT IS UNCHANGED** (9901). *A batch still on the queue*
     * and *a range a replay has emptied* both imply something of this tenant's
     * was admitted, so inside
     * {@see PixelAcceptances::MONTHS_CONSIDERED} they are
     * now {@see self::AcceptedNotShown} — which is the whole point, because
     * neither of them is a fact about anybody's website. What is left here is a
     * line nobody has pasted, a website nobody has visited, and traffic old
     * enough that the horizon or a replay explains it, all three of which the
     * sentence below is true of.
     */
    case Listening = 'listening';

    /**
     * The short label beside the icon. Outcome language (`22`) — what is true
     * for the owner, never how it is determined.
     */
    public function label(): string
    {
        return match ($this) {
            self::NotSetUpYet => 'Not ready yet',
            self::NoWebsiteListed => 'Nothing is being collected',
            self::TrafficRefused => 'Some visits are being turned away',
            self::Collecting => 'Visits are reaching us',
            // ⚠️ **THE ONLY LABEL HERE THAT NAMES US AS THE SUBJECT**, because
            // it is the only state that is about us (9900). It says the good
            // half first — the visits did reach us, so the line is working —
            // and then says whose problem the rest is, which is the fact the
            // owner actually needs: nobody should be re-pasting a snippet that
            // is already working.
            self::AcceptedNotShown => 'Visits reached us — ours to sort out',
            // ⚠️ **THIS READ "Nothing turned away" AND THE STRONGER SENTENCE IS
            // NOW SAYABLE** (7981). That label described what this reader could
            // not see; with arrivals readable, the honest short form is the
            // thing the person came to find out.
            self::Listening => 'Nothing has reached us yet',
        };
    }

    /**
     * The sentence under the label.
     *
     * ⚠️ **THE SECOND ONE IS THE WHOLE POINT OF THIS SLICE.** It is the sentence
     * a tenant should have seen on day one, and it has to be a plain statement
     * of consequence rather than a warning about configuration: their line is
     * on their website, it is sending, and we are throwing all of it away.
     *
     * ⚠️ **NO SCREEN NAMES ARE WRITTEN HERE.** The action lives in the view,
     * beside the link that performs it, so this file cannot come to name a
     * screen that has been renamed.
     */
    public function explanation(): string
    {
        return match ($this) {
            self::NotSetUpYet => 'We have not finished setting your account up for this yet, '
                .'so nothing you paste can reach us. Ask us and we will finish it off.',
            self::NoWebsiteListed => 'Nothing is reaching us and nothing will, however well the '
                .'line is installed. We only take what arrives from a website your account lists, '
                .'and your account lists none yet.',
            self::TrafficRefused => 'Something is calling us with your line from a website your '
                .'account does not list, and we are turning it away. Addresses have to match '
                .'exactly, so a site answering on both plain and www needs both written down.',
            self::Collecting => 'Your line is working. Visits from the websites below are '
                .'reaching us and we are keeping them.',
            // ⛔ **EVERY CLAUSE OF THIS IS SOMETHING THE SCHEMA CAN ACTUALLY
            // SUPPORT, WHICH IS WHY IT IS SHORTER THAN THE DIAGNOSIS SOMEBODY
            // WILL WANT TO ADD TO IT** (9902). *Reached us* is the counter the
            // collector writes before it hands the batch on; *have not come
            // through* is the derived layer being empty; *ours rather than
            // yours* is what is left once every gate the owner controls has
            // been passed. ⛔ **It may not say the archive failed** — queue
            // latency produces this state on a healthy install a second after
            // the first visit — and it may not promise the events are coming,
            // because two later refusals and a permanently dead store all
            // arrive here identically.
            //
            // ⚠️ **"TELL US" IS SUPPORT SURFACE BOUGHT DELIBERATELY**, against
            // this file's usual rule. The bell that covers this rings to an
            // operator address that seeds empty (9849), so on an install where
            // nobody has set one the owner is the only person in the world who
            // can see that anything is wrong.
            self::AcceptedNotShown => 'Your line is working: visits from your website reached '
                .'us. None of them have come through to this page, and that is ours rather than '
                .'yours — nothing about your website or the line on it needs changing. Tell us '
                .'if it stays this way.',
            // ⛔ **THE OLD SENTENCE ENDED "We cannot yet show you here whether
            // anything has arrived at all" AND THAT WAS THE CLAIM THIS SLICE
            // DISPROVED** (7980). What replaces it is the ordinary reason a
            // freshly installed line shows nothing — nobody has been to the
            // website yet — with the delay named beside it, because the second
            // most likely reason somebody is reading this sentence is that they
            // pasted the line ninety seconds ago.
            self::Listening => 'Nothing has been turned away and nothing has reached us. A '
                .'website has to be visited before there is anything to send, and a visit takes '
                .'a moment to get to us — so if you have just added the line, come back shortly.',
        };
    }

    /**
     * The pill this renders as (`29` §5.4).
     *
     * ⛔ **THIS SAID "A GREEN TICK WOULD BE A CLAIM NOTHING IN THIS APPLICATION
     * COULD EVER HAVE MADE" AND THAT WAS THE SENTENCE THAT STOPPED ANYBODY
     * LOOKING — BOTH READINGS KEPT AND DATED, 2026-08-22 (7980).** It read:
     * *"{@see self::Listening} IS `Unknown` RATHER THAN `Ok`, AND THAT IS THE
     * HONEST MAPPING — `WidgetInstallState::signal()`'s reasoning for
     * `NotSeenYet`, arriving one screen over for a stronger reason. There it is
     * a check that cannot see a page nobody visited; here there is no acceptance
     * signal in the schema at all."* **There was one, and it was written by the
     * same job that writes the archive.** ✅ **{@see self::Collecting} is `Ok`**,
     * and it is earned rather than assumed: it is only reachable when a derived
     * event row exists for this tenant.
     *
     * ⚠️ **{@see self::Listening} STAYS `Unknown`, AND THAT MAPPING IS THE HALF
     * OF THE OLD PARAGRAPH THAT SURVIVES INTACT.** It is now a genuine
     * absence-of-measurement rather than a refusal to look: nothing arrived, and
     * a queue, a horizon, a replay and an unvisited website are all reasons that
     * is not a fault. `SignalState::Unknown`'s own docblock is exactly this —
     * *"the absence of a measurement … a fact about us"*.
     *
     * ⚠️ **{@see self::NoWebsiteListed} IS `Attention` AND IS THE ONE STATE HERE
     * THIS PLATFORM WAS EVER CERTAIN OF.** It is a fact about a gate whose
     * answer is already written down. `Collecting` is now the second, from the
     * other direction.
     *
     * ⚠️ **{@see self::AcceptedNotShown} IS `Unknown` AND NOT `Attention`, AND
     * THE READING IS `SignalState`'s OWN** (9902): *"Every other state is a fact
     * about the business; this one is a fact about us."* `Attention` means
     * *worth a look* by the person reading it, and this is the one state on the
     * screen where there is nothing for them to look at — the measurement that
     * is absent is ours to take. ⛔ **It shares a hue with
     * {@see self::Listening} and that is safe here for the reason `22` gives
     * rather than by luck**: the pill carries a label and an icon as well as a
     * colour, and these two carry opposite labels.
     */
    public function signal(): SignalState
    {
        return match ($this) {
            self::NotSetUpYet, self::Listening, self::AcceptedNotShown => SignalState::Unknown,
            self::NoWebsiteListed, self::TrafficRefused => SignalState::Attention,
            self::Collecting => SignalState::Ok,
        };
    }
}
