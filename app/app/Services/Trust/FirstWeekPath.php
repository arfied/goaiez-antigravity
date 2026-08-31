<?php

declare(strict_types=1);

namespace App\Services\Trust;

use App\Enums\FirstWeekWinType;
use App\Enums\ReviewSource;
use App\Models\Business;
use App\Models\FirstWeekRun;
use App\Models\Location;
use App\Models\Review;
use App\Models\User;
use App\Models\WizardProgress;
use App\Notifications\FirstWeekAuditSummary;
use App\Notifications\FirstWeekFallbackProof;
use App\Notifications\FirstWeekImportPrompt;
use App\Notifications\FirstWeekReviewCelebration;
use App\Notifications\FirstWeekSummary;
use App\Services\Config\DefaultsRegistry;
use App\Services\Consent\CustomerImports;
use App\Services\Feedback\FeedbackPages;
use App\Services\Mail\PlatformMailer;
use App\Services\Proof\ProofNumbers;
use App\Support\Tenancy;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

/**
 * The First 7-Day Results Path (`28` §3.2, row 5 — the one item the row still
 * owed after decision 900 refused it as vacuous).
 *
 * `first_week_runs` (`DATA-MODEL` §Trust & early results) is one row per
 * tenant, started at wizard completion, and this class is the only thing that
 * writes it — an `ArchitectureTest` lint in `OutboundTest.php` holds the
 * chokepoint the way `ReviewInviteSender` holds the review-invite one.
 *
 * ## Why decision 900 is answered rather than still open
 *
 * 900 refused the path because every one of its wins is delivered by SMS and
 * the 10DLC campaign was unfiled — a path whose only output channel is absent,
 * green because nothing it produces can be observed. Decision 1562 records what
 * lifts that: *"a working sender, not an approved campaign"* — and row 4's
 * `PlatformTexter` now exists, is driveable in tests through `LogTexter`, and
 * is proven observable by `ReviewInviteSmsTest`'s use of real `OutreachMessage`
 * rows. That sender is genuinely used here, indirectly: the Day-1 and Day-2/3
 * signals this class reads (`OutreachMessage`, `reviews.source = 'google'`) are
 * rows `ReviewInviteSender` and `SyncGoogleReviewsJob` already write, and a test
 * that drives this class end to end is exercising real output through a real
 * channel — never a mock standing in for one.
 *
 * ## `28` §3.2 asks for SMS to the owner, and none of it ships that way
 *
 * ⚠️ **THIS IS THE ONE DELIBERATE NARROWING THAT TOUCHES EVERY STEP, SO IT IS
 * STATED ONCE HERE RATHER THAN ON EACH.** §3.2's table says "SMS" for the
 * import prompt, the celebration, the Day-5 fallback and the Day-7 summary.
 *
 * ⛔ **THE TWO FACTS THAT RULED IT OUT ARE BOTH FALSE SINCE WAVE 38, AND THIS
 * PARAGRAPH IS THE SOURCE TWO OTHER FILES QUOTED — CORRECTED 2026-08-28
 * (10951).** They are kept and dated rather than deleted (4368):
 *
 *   1. ⛔ **SUPERSEDED.** *"`PlatformTexter::sendToCustomer()` is the only way
 *      to send a text in this application, and its own docblock is explicit
 *      that there is no account-holder category on SMS — 'an owner who gets a
 *      text from us is being texted at a number that reached us through a
 *      consent record like anyone else's.' Unlike `PlatformMailer`, which
 *      carries a second, unpermitted `send()` for exactly this case, the SMS
 *      transport has one gate and it is consent."* **`PlatformTexter` has
 *      itself dated that sentence**: `sendToOwner()` is the account-holder
 *      category, built on the owner ruling of 2026-08-27 (10540). ⚠️ **The last
 *      clause survives and is the important half** — the SMS transport still
 *      has one gate and it is still consent: `sendToOwner()` takes an
 *      `OwnerSendPermit` minted only from a recorded consent event, and is
 *      **not** `PlatformMailer::send()`'s bare-account-relationship shortcut
 *      with a different name.
 *   2. ⛔ **SUPERSEDED.** *"There is no column anywhere in this schema that
 *      records an owner's phone number. `users` and `businesses` were both
 *      checked."* The check was right about those two tables and the
 *      conclusion is now wrong: `owner_notify_numbers.e164` records it, and
 *      since 10660 `owner_notification_consents.e164` records which number a
 *      disclosure was shown to.
 *
 * ⛔ **AND THE NARROWING STANDS ANYWAY, ON A REASON NEITHER OF THEM HAD**
 * (10941, 10950). *"Building an owner-SMS channel means inventing a consent
 * mechanism for an account holder's own number — a real compliance question
 * with its own argument"* was correct, the argument was had, and **the
 * mechanism that came out of it is scoped**:
 * `OwnerNotifyDisclosure::TEXT` discloses *"an urgent message from a customer,
 * or something that needs your reply"*. **Every §3.2 beat is an onboarding
 * nudge or a celebration**, which is neither — so the SMS half is refused on
 * the **scope of the consent** rather than on the absence of a mechanism.
 * ⛔ **Do not read the mechanism arriving as these four becoming buildable**;
 * widening the disclosure is the owner's, and 10948 is the question.
 *
 * So every send in this class goes through `PlatformMailer::send()`, addressed
 * to `$business->owner->email` — the exact authorisation model decision 706
 * already uses for `impersonation.notify_owner`: the account relationship, not
 * a consent record. `first_week_path.enabled` is the one switch over all of it
 * (see `DefaultsManifest`).
 *
 * ## What the day-by-day table collapses to
 *
 * `28` §3.2 lists seven rows; this processes four days plus one continuous
 * check, and the collapse is stated per case rather than left to be
 * discovered:
 *
 *   - **Day 0** (`runDay0()`) — sends {@see FirstWeekAuditSummary} if the
 *     wizard's frozen audit pre-fill carries any findings, and says nothing if
 *     it does not. "Found", never "found and fixed" — see that notification's
 *     own docblock for why the doc's wording is narrowed.
 *   - **Day 0-1** (`runDay1()`) — sends {@see FirstWeekImportPrompt} once,
 *     unless {@see CustomerImports::hasAnyImport()} already says yes, which is
 *     §3.2's own "skippable if its win already happened organically" rule
 *     applied to a seed step rather than a win.
 *   - **Day 1's "invites out" row has no action of its own.** It observes
 *     something `ReviewInviteSender` already does automatically on every
 *     feedback submission — there is no proactive "wave" to send, because
 *     `28` §11.2 row 5's own Import v1 ships refusing (843) and there is no
 *     customer list to invite from that this path could seed itself.
 *   - **Day 2-3's win and Day 3's alternate win** both collapse into
 *     {@see detectWin()}, run on every tick rather than gated to one day —
 *     see that method's own docblock for why, and `App\Enums\FirstWeekWinType`
 *     for why the alternate win has no case of its own.
 *   - **Day 5** (`runDay5()`) — {@see FirstWeekFallbackProof}, only if no win
 *     has landed by then.
 *   - **Day 7** (`runDay7()`) — {@see FirstWeekSummary}, always, and it closes
 *     the run.
 *
 * ## The narrowing that was missing from the list above (1882)
 *
 * ⚠️ **§3.2 SAYS THE PATH "GOES SILENT THE MOMENT THE OWNER REPLIES STOP", AND
 * NOTHING HERE IMPLEMENTS THAT.** It is stated here because a list that
 * enumerates every *other* narrowing at length is exactly what stops the next
 * reader looking for one it left out (314–316).
 *
 * STOP is an SMS keyword. It exists because a text message is the one channel
 * where the carrier, the registers and the statute all define it — and 1760
 * removed that channel from this path entirely. What survived the substitution
 * is *not* an equivalent: `PlatformMailer::send()` deliberately bypasses
 * suppression because it is account mail, these notifications carry no
 * unsubscribe link, and `first_week_path.enabled` resolves through
 * `DefaultsRegistry::value()` to a `PlatformSetting` — **platform-wide, not
 * per tenant**. So an owner who wants these five emails to stop has exactly two
 * levers, and both are bigger than the request: pause the whole account
 * (`TenantPause`, honoured at `AutopilotJob`'s execution check), or ask an
 * operator to switch the path off for every tenant at once.
 *
 * ⚠️ **AN OWNER-LEVEL OPT-OUT IS OWED AND IS NOT TAKEN HERE ON THIS SLICE'S OWN
 * AUTHORITY.** `CLAUDE.md`'s standing rule is *never add a tenant-facing
 * toggle*, overruled exactly once and only by an explicit owner ruling. A
 * "stop emailing me about my first week" switch is a tenant-facing toggle by
 * any reading, so it is recorded as owed with the decision it needs rather than
 * invented inside a fix wave.
 */
final class FirstWeekPath
{
    /**
     * The last step index — one past `LAST_STEP - 1`'s Day-7 handler, set
     * alongside `completed_at`. Four processed days: 0, 1, 2 (Day 5), 3
     * (Day 7). The migration's CHECK constraint restates this range.
     */
    private const int LAST_STEP = 4;

    /**
     * Day-elapsed threshold each step index becomes eligible to run at.
     * Index => days since `started_at`.
     *
     * @var array<int, int>
     */
    private const array STEP_DAY_THRESHOLD = [
        0 => 0,
        1 => 1,
        2 => 5,
        3 => 7,
    ];

    public function __construct(
        private readonly DefaultsRegistry $defaults,
        private readonly PlatformMailer $mailer,
        private readonly ProofNumbers $proofNumbers,
        private readonly CustomerImports $imports,
        private readonly FeedbackPages $feedbackPages,
    ) {}

    /**
     * Start the clock. Idempotent — `business_id UNIQUE` at the database means
     * a second call for a tenant that already has a row returns the existing
     * one rather than restarting it.
     *
     * Called once, from `Livewire\Setup\Done::mount()` the moment
     * `SetupFlow::complete()` succeeds — "started at activation" (`28` §3.2),
     * and completing the wizard is the only genuine activation signal this
     * application has. See that component's docblock for why.
     *
     * ⚠️ **`firstOrCreate()` WOULD NOT WORK HERE, AND IT LOOKS LIKE IT
     * SHOULD.** Its create path fills through the model's ordinary mass
     * assignment, which `FirstWeekRun::$guarded` refuses for every column —
     * there is no request-shaped writer this row is meant to accept.
     * `ProofNumbers::recompute()`'s own pattern — read, then `forceFill()`
     * past the guard on a miss — is what a derived row's one legitimate
     * writer needs instead.
     */
    public function begin(Business $business): FirstWeekRun
    {
        $this->assertIsAmbientTenant($business);

        $run = FirstWeekRun::query()->where('business_id', $business->id)->first();

        if ($run instanceof FirstWeekRun) {
            return $run;
        }

        $run = new FirstWeekRun;
        $run->forceFill([
            'business_id' => $business->id,
            'started_at' => now(),
        ])->save();

        return $run;
    }

    /**
     * The tenant's run, or null if the wizard was never completed.
     */
    public function for(Business $business): ?FirstWeekRun
    {
        return FirstWeekRun::query()->where('business_id', $business->id)->first();
    }

    /**
     * Do whatever today's tick calls for.
     *
     * ⚠️ **IDEMPOTENCE HERE IS PERSISTED STATE AND NOTHING ELSE, AND IT IS
     * THREE COLUMNS: `step`, `win_type` AND `completed_at`** (1950, corrected at
     * 2011). This docblock used to say "every step checks what it would do
     * before doing it", and that is false for three of the four: `runDay0()`
     * sends unconditionally whenever the frozen audit pre-fill has findings,
     * `runDay1()`'s check is about whether the *tenant* has imported rather than
     * about whether the prompt already went, and `runDay7()` has no check at
     * all. Only `runDay5()` is self-guarding, and it is guarded by `win_type` —
     * which is again persisted state.
     *
     * ⚠️ **`completed_at` IS THE THIRD MARKER AND 1950 SAID IT WAS NOT ONE.** It
     * is what gates {@see detectWin()}, and **`step` cannot stand in for it,
     * because `detectWin()` does not look at `step` at all** — its only gate is
     * `win_type`. So what stops a closed week celebrating a review that arrives
     * afterwards is not "the loop has finished"; it is the early return below,
     * and behind it the merely contingent fact that `win_type` is *usually* set
     * by the end of the week. Usually, not always: `runDay5()` leaves it null on
     * `no_feedback_page`, and since 2010 on `no_owner_address` as well.
     * Executed, with that return mutated away and a Google review created after
     * the week closed — a real `FirstWeekReviewCelebration` goes out. The
     * paragraph 45 lines down already listed all three markers correctly while
     * this one, the load-bearing sentence, named two: the third consecutive wave
     * in which the re-argument is where the claim gets written from intent
     * rather than from the code.
     *
     * So the whole of this class's re-run safety rests on those three columns
     * being on the row before the message leaves, which is why the ordering
     * below is load-bearing rather than tidy. 314–316's shape, found *inside*
     * the decision that re-argued it — twice now.
     *
     * ⚠️ **`notified` IS THE ONE KEY `AdvanceFirstWeekPathJob` READS**, and it is
     * true whenever this call sent at least one email — which is what decides
     * whether the run writes to the activity feed at all. `29` §2 rule 42 wants
     * every automated action there within 60 seconds, and most days this class
     * runs are genuinely quiet: writing "Sent you an update" to the feed on a
     * day nothing was sent would be the fabricated-activity mistake `29` §12.1
     * exists to catch, one layer up from a fabricated number.
     *
     * ⚠️ **AT MOST ONE EMAIL PER CALL — §3.2's "never more than one message per
     * day", which the first version of this method broke in ordinary operation**
     * (1881). Two shapes broke it and neither was an edge case: a catch-up pass
     * ran every due step in one `while` loop and sent four emails at once, and a
     * Google review arriving on a day a step was also due sent two, because win
     * detection is decoupled from the loop on purpose. The loop now stops at the
     * first send and leaves `step` where it got to, so tomorrow's tick continues
     * from there. **Silent steps do not stop it** — a step that decides it has
     * nothing to say costs the owner no message, so consuming a day for it would
     * be the rule enforced against the owner instead of for them.
     *
     * ⚠️ **THE WIN WINS THE TIE.** When a win is detected on a tick where a step
     * is also due, the celebration goes and the step waits. §3.2 makes
     * time-to-first-win the activation metric this whole path exists to move,
     * and the win is the only one of the two that is *about today* — a review
     * arrived; saying so tomorrow is a worse message. The step is a scheduled
     * nudge that keeps perfectly, and the log records which one was deferred.
     *
     * ⚠️ **NOTHING IS SILENTLY CONSUMED.** A tenant switched off for a fortnight
     * still receives every step, one per day, over the days that follow — the
     * loop advances `step` only for a step that actually ran, and
     * `AdvanceFirstWeekPathJob`'s idempotency key is what makes "per tick" mean
     * "per calendar day" in production.
     *
     * ⚠️ **THE WRITE COMES BEFORE THE SEND, EVERYWHERE, AND THAT IS THE ONE
     * ORDERING RULE THIS CLASS HAS** (1950). `notifyOwner()` persists `$run`
     * immediately before it hands anything to `PlatformMailer`, so every caller
     * sets its marker — `step`, `win_type`/`win_at`, `completed_at` — on the
     * model *first* and lets that method commit it. Written the other way round
     * (send, then save) the two failure modes are not symmetric: a save that
     * fails after an enqueued email leaves the owner with a message and this row
     * with no record of it, and tomorrow's tick — a *different* calendar day
     * under a *different* idempotency key, so `claimIsSpent()` has nothing to
     * say about it — sends the same message to the same person again. Inverted,
     * the same failure loses the message instead.
     *
     * ⚠️ **AND "LOST" MEANS GONE — NOT "RECOVERED LATER BY SOMETHING"** (2013).
     * `claimIsSpent()`'s docblock calls that direction *"recoverable"*, and this
     * one quoted it as though the word named a mechanism. It does not: it is a
     * comparison between two bad outcomes, and the better of the two is still
     * nobody's to repair. `AutomationRunStatus::Failed` has exactly one reader
     * outside `AutopilotJob` — a label in `Livewire\Admin\AutomationRuns`
     * reading "Needs a look", with no action behind it — no console command
     * sweeps failed runs,
     *
     * ⚠️ **AND THE CONCLUSION THAT USED TO FOLLOW FROM THAT SENTENCE NO LONGER
     * DOES — CORRECTED BY THE INTEGRATOR, 2026-08-26 (9701).** Every clause
     * above is still literally true: no reader was added to the column, and
     * that was deliberate. **What changed is that an automation which gives up
     * now reaches an operator through `AutopilotJob::failed()` rather than
     * through the column** — because `automation_runs` records an *attempt* and
     * not a *surrender*. The row is written and rethrown so the queue retries,
     * and the table carries no attempt number, no correlation between attempts
     * of one dispatch and no terminal flag, so a dispatch that failed once and
     * succeeded ninety seconds later leaves a row identical in shape to one
     * that died. ⛔ **A sweep over this column would therefore report recovered
     * work as lost work and be muted (511) before it had ever been right** —
     * which is why the answer was a hook and not the reader this paragraph
     * reads as asking for.
     *
     * `claimIsSpent()` is `true` so the backoff ladder burns
     * every remaining attempt against the claim the first one made, and
     * `FirstWeekRun::$guarded` refuses every column, so even a hand repair needs
     * `forceFill()`. **A first-week message that is lost is simply not sent, to
     * anybody, ever.**
     *
     * What that costs a tenant is exactly one step, and the week continues past
     * it — `FirstWeekPathTest`'s *"a step whose send fails has already consumed
     * its day, and is never sent twice"* proves it: the next tick moves on to
     * Day 5. ⚠️ **That case is quoted by its real name here because it used to
     * be quoted by a made-up one** — *"a step whose send throws"*, which reads
     * as an exact reference and greps to nothing (9780). **A throw is what this
     * paragraph is about and `fails` is what the test is called**, so the words
     * around the quotation carry the distinction instead of the quotation
     * silently losing it. `runDay7()` is the sharpest instance, because
     * `completed_at` commits first, so a throw at its send loses the closing
     * summary **and** closes the run permanently. All of that is the deliberate
     * trade — a duplicate is the worse outcome and cannot be undone at all — but
     * it is written here as a loss rather than as a word implying somebody
     * catches it.
     *
     * ⚠️ **THE WINDOW IS REAL ON THIS STACK RATHER THAN THEORETICAL.**
     * `PlatformMailer::send()` pushes onto the queue (Redis under Horizon, the
     * `jobs` table on the cPanel box) while the save writes PostgreSQL. A worker
     * OOM, a SIGKILL, a statement timeout, a deadlock or a `saving` observer all
     * land squarely between the two.
     *
     * @return array<string, mixed> what happened, for the automation run row
     */
    public function advance(FirstWeekRun $run, Business $business): array
    {
        $this->assertIsAmbientTenant($business);

        if ($run->completed_at !== null) {
            return ['status' => 'completed', 'notified' => false];
        }

        // ⚠️ THE ONE GATE, CHECKED BEFORE ANYTHING ELSE IS LOOKED AT — the same
        // ordering `ReviewInviteSender` uses for its own feature switch, and
        // for the same reason: a switch checked after work has already
        // happened is not a switch, and days already elapsed while this was
        // off are simply processed in one catch-up pass once it is back on,
        // because `step` and `started_at` do not move while it is off.
        if ($this->defaults->value('first_week_path.enabled') !== true) {
            return ['status' => 'switched_off', 'notified' => false];
        }

        // ⚠️ THE SECOND GATE, AND IT IS THE SAME GATE (1880). Every step below
        // records that something was said to the owner — `win_type`, `win_at`,
        // `completed_at`, and a `FirstWeekUpdateSent` feed line reading "Sent
        // you an update about your first week". `PlatformMailer::send()` cannot
        // fail on us by design (702), and `DeliverPlatformMail` correctly
        // `fail()`s rather than rethrowing, so without this check a queue push
        // into a mailer that delivers nothing was being promoted to a win. With
        // no relay picked (`CLAUDE.md` §Vendors) that is the state this
        // application is in *right now*, not a hypothetical.
        //
        // Deliberately shaped as the switch is, and for the same reason: `step`,
        // `win_type` and `started_at` do not move, so the existing catch-up pass
        // replays the week the day mail works. Row 5's own Import v1 (842–844)
        // is the precedent — **it ships refusing**, and a path that ships
        // *claiming* is the one thing a feature whose product promise is a
        // visible win cannot survive.
        //
        // ⚠️ `canDeliver()` IS NOT A DELIVERY RECEIPT, and its own docblock says
        // so.
        //
        // ⛔ AND THE SENTENCE THAT USED TO FOLLOW IS HALF PAID — 10996. It read:
        // *"A refused SMTP connection past this point still leaves the run
        // asserting a send; closing that needs the relay's bounce webhook (open
        // question H), and 1889 records it as owed rather than implying this
        // gate is more than it is."* ✅ The run no longer asserts a send it did
        // not make: `notifyOwner()` uses `PlatformMailer::deliverNow()`, which
        // throws, so `notified` is never returned true for a message the
        // transport refused and the "Sent you an update about your first week"
        // feed line is not written. ⛔ What is NOT paid is the wider claim, and
        // it is the one 1889 was about: a message the transport **accepted** and
        // then bounced is still recorded as sent, and closing that needs the
        // bounce feed (open question H). **Accepted is not arriving** — the
        // operator alert board says those exact words about its own strongest
        // state.
        //
        // ⛔ AND `win_type`/`win_at` STILL MOVE ON A REFUSED SEND, WHICH IS THE
        // SECOND SURVIVING HALF AND MUST NOT BE "FIXED". `notifyOwner()` commits
        // the caller's markers before it sends, deliberately (1950, 2013), so
        // the throw always arrives after the save. Reversing that to protect the
        // column would put the same email in an owner's inbox on two calendar
        // days under two different idempotency keys, which is 1884 exactly.
        // **What changed is the RETURN, not the ordering**: `advance()` no
        // longer answers `notified: true` for a message the transport refused,
        // which is the value `AdvanceFirstWeekPathJob::activityAction()` reads
        // to tell the owner we sent them something.
        if (! $this->mailer->canDeliver()) {
            return ['status' => 'undeliverable', 'notified' => false];
        }

        $log = [];
        $sent = false;

        // Win detection runs on every tick, independent of the step below —
        // see detectWin()'s own docblock for why that decoupling is the whole
        // point. detectWin() returns the case it set rather than leaving the
        // caller to re-read $run->win_type after a mutating call, which static
        // analysis cannot follow across a method boundary.
        //
        // ⚠️ THE WIN IS PERSISTED BEFORE ITS EMAIL, INSIDE detectWin(), BY
        // notifyOwner() — the ordering rule above (1950). 1885 added a second
        // `$run->save()` here, after the call; it is gone, and **the reason it
        // is safely gone is a property of the code rather than of a mutation
        // run** (2010). detectWin() has exactly two exits that matter: it
        // notified, in which case notifyOwner() committed the win before the
        // send; or it did not, in which case it put `win_type`/`win_at` back and
        // returned null, so there is nothing here left to persist. 1950 justified
        // the deletion on "deleting it reddens nothing", which was true and was
        // *evidence of the wrong thing*: it was inert only because the refusal
        // path was assumed unreachable, and that assumption was made in the same
        // commit that refused to fix the refusal path. Each argument rested on
        // the other, and with the address blank the pair deadlocked the run
        // forever.
        //
        // ⚠️ WHAT 1885 FOUND IS STILL TRUE AND HAS MOVED, NOT GONE. Persisting
        // the win is only load-bearing on a tick where **no step is due**, and
        // every win test used a `started_at` that made one due — 398's shape, an
        // outer write making the inner one unfalsifiable. That test still exists
        // and now drives notifyOwner()'s save instead.
        if ($run->win_type === null) {
            $detected = $this->detectWin($run, $business);

            if ($detected instanceof FirstWeekWinType) {
                $log['win_type'] = $detected->value;
                $sent = true;
            }
        }

        $daysElapsed = min(
            (int) floor($run->started_at->diffInSeconds(now(), true) / 86400),
            self::STEP_DAY_THRESHOLD[self::LAST_STEP - 1],
        );

        while ($run->step < self::LAST_STEP && self::STEP_DAY_THRESHOLD[$run->step] <= $daysElapsed) {
            // §3.2's one-message-per-day rule, enforced in the one place both
            // senders meet: the win above and every earlier step below reach
            // here having set $sent, and a due step that has to wait is logged
            // by name rather than dropped silently. `step` is deliberately not
            // advanced, so this exact step runs on the next tick.
            if ($sent) {
                $log['deferred_step'] = 'day_'.self::STEP_DAY_THRESHOLD[$run->step];

                break;
            }

            $current = $run->step;

            // ⚠️ ADVANCED IN MEMORY *BEFORE* THE STEP RUNS, SO THAT THE STEP'S
            // OWN notifyOwner() COMMITS IT AHEAD OF THE SEND (1950). Written
            // the other way round — run the step, then `step++`, then save —
            // the email is already on the queue while `step` is still the old
            // value, and any failure in between hands the owner the same
            // message again tomorrow under a different idempotency key.
            //
            // The consequence is deliberate and is the trade the ordering rule
            // buys: a step that throws *at or after* its send has consumed its
            // day and that message is lost. A step that throws *before* its
            // send has committed nothing, so the next tick runs it again.
            $run->step = $current + 1;

            $stepResult = $this->runStep($run, $business, $current);
            // Keyed on the calendar day, not the step index — 'day_5' reads
            // as something an operator looking at automation_runs.output can
            // place against the doc's own table; 'day_2' does not.
            $log['day_'.self::STEP_DAY_THRESHOLD[$current]] = $stepResult;

            if (($stepResult['sent'] ?? false) === true) {
                $sent = true;
            }

            // ⚠️ SAVED AFTER EVERY STEP, AND WHAT IT IS FOR IS NARROWER THAN IT
            // LOOKS — SO IT IS STATED AS THE MUTATION ACTUALLY PROVES IT (1950).
            // It persists a tick that **ends on a silent step**: one that had
            // nothing to say, so it never reached notifyOwner() and nothing
            // committed its advance. Day 0 with no audit findings and Day 1 not
            // yet due is the everyday case; without this the tenant re-runs
            // Day 0 tomorrow, and the day after, forever.
            //
            // It is *not* what protects a silent step followed by a sending one
            // — the sending step's own commit inside notifyOwner() carries the
            // earlier advance with it, because `step` is already at its new
            // value in memory. Claiming otherwise here would be 314–316's shape
            // in the very comment that fixes 314–316's shape; deleting this line
            // reddens exactly one test, and it is the one that asserts `step`
            // after a tick that stopped on a silent Day 0.
            //
            // For a step that did send this is a no-op: the model is clean.
            // Untested until 1885 — "found by satisfying a lint, not by a test"
            // (1767) meant exactly what it said.
            $run->save();
        }

        // No further check or write of completed_at here — runDay7() (step
        // index LAST_STEP - 1) sets it *before* its own send, so notifyOwner()
        // has already committed it by the time the loop can exit.

        $log['notified'] = $sent;

        return $log;
    }

    /**
     * @return array<string, mixed>
     */
    private function runStep(FirstWeekRun $run, Business $business, int $step): array
    {
        if ($step === 0) {
            return $this->runDay0($run, $business);
        }
        if ($step === 1) {
            return $this->runDay1($run, $business);
        }
        if ($step === 2) {
            return $this->runDay5($run, $business);
        }
        if ($step === 3) {
            return $this->runDay7($run, $business);
        }

        return ['sent' => false, 'reason' => 'unknown_step'];
    }

    /**
     * Day 0 — "we found N things worth fixing", from the wizard's own frozen
     * audit pre-fill.
     *
     * ⚠️ **NOT SELF-GUARDING, AND THE CLASS DOCBLOCK USED TO CLAIM IT WAS**
     * (1950). Findings present means this sends, every time it is called. What
     * makes it run once is the caller having already advanced `step` in memory
     * and `notifyOwner()` committing that before the message leaves.
     *
     * ⚠️ **READS `wizard_progress.data`, NEVER `public_audits` DIRECTLY.**
     * `TenantProvisioner::prefill()`'s own docblock is explicit about why: the
     * audit prunes on a 90-day TTL and the wizard's copy does not, so a live
     * re-read is a foreign key to a table that deletes on a schedule. The copy
     * is what this reads, frozen at signup.
     *
     * @return array<string, mixed>
     */
    private function runDay0(FirstWeekRun $run, Business $business): array
    {
        $progress = WizardProgress::query()->where('user_id', $business->owner_user_id)->first();

        $findings = $progress?->data['audit']['findings'] ?? null;

        if (! is_array($findings) || $findings === []) {
            return ['sent' => false, 'reason' => 'no_audit_data'];
        }

        if (! $this->notifyOwner($run, $business, new FirstWeekAuditSummary(count($findings)))) {
            return ['sent' => false, 'reason' => 'no_owner_address'];
        }

        return ['sent' => true, 'findings' => count($findings)];
    }

    /**
     * Day 0-1 — the past-customer import prompt, sent once, ever, and never if
     * the win already happened organically (§3.2's own rule).
     *
     * ⚠️ **`hasAnyImport()` IS NOT AN "ALREADY SENT" CHECK, AND READS LIKE
     * ONE** (1950). It asks whether the *tenant* has imported a list — which is
     * §3.2's skip rule, and is a fact about them rather than about us. Call this
     * twice for a tenant who has imported nothing and it prompts twice. `step`
     * is what stops that, committed by `notifyOwner()` before the send.
     *
     * @return array<string, mixed>
     */
    private function runDay1(FirstWeekRun $run, Business $business): array
    {
        if ($this->imports->hasAnyImport()) {
            return ['sent' => false, 'reason' => 'already_imported'];
        }

        if (! $this->notifyOwner($run, $business, new FirstWeekImportPrompt)) {
            return ['sent' => false, 'reason' => 'no_owner_address'];
        }

        return ['sent' => true];
    }

    /**
     * Day 5 — the guaranteed-visible artifact, sent only when nothing has won
     * by then.
     *
     * ⚠️ **THE ONE STEP THAT IS GENUINELY SELF-GUARDING** — and what it is
     * guarded by is `win_type`, which is again persisted state, set here and
     * committed by `notifyOwner()` before the email goes.
     *
     * @return array<string, mixed>
     */
    private function runDay5(FirstWeekRun $run, Business $business): array
    {
        if ($run->win_type !== null) {
            return ['sent' => false, 'reason' => 'win_already_happened'];
        }

        $url = $this->reviewLinkUrl($business);

        if ($url === null) {
            // No feedback page to link to — should not happen for a tenant
            // that completed provisioning, and is refused rather than guessed.
            return ['sent' => false, 'reason' => 'no_feedback_page'];
        }

        // Set before the send, committed by the send — see advance()'s ordering
        // rule (1950). Reversed, a failure between the two leaves an owner
        // holding a "your review link is ready" email against a row that still
        // says nothing has won, so the next tick sends it again.
        $run->win_type = FirstWeekWinType::FallbackProof;
        $run->win_at = now();

        // ⚠️ PUT BACK ON A REFUSAL, BECAUSE THE LOOP'S TRAILING save() WOULD
        // OTHERWISE COMMIT A WIN NOBODY WAS TOLD ABOUT (2010). `notifyOwner()`
        // refuses *before* its own save, so nothing of this reached the
        // database — but the model is still dirty, and this step returning
        // silently is exactly the case that reaches the loop's save. Setting
        // both back to their loaded values leaves the model clean.
        if (! $this->notifyOwner($run, $business, new FirstWeekFallbackProof($url))) {
            $run->win_type = null;
            $run->win_at = null;

            return ['sent' => false, 'reason' => 'no_owner_address'];
        }

        return ['sent' => true];
    }

    /**
     * Day 7 — the closing summary. Always sends, whatever the week held, and
     * closes the run.
     *
     * ⚠️ **NO GUARD OF ITS OWN AT ALL, AND `completed_at` IS NOW SET BEFORE THE
     * SEND RATHER THAN AFTER IT** (1950). The old order — notify, then set
     * `completed_at`, then let the loop save — was wrong even in memory: it put
     * the one column that closes the run permanently on the far side of the
     * irreversible act it is meant to record.
     *
     * ⚠️ **RECOMPUTES `proof_numbers` ITSELF RATHER THAN READING A STALE
     * ROLLUP**, and the reason has changed while the behaviour has not.
     *
     * ⛔ **THIS PARAGRAPH SAID "NOTHING IN THIS APPLICATION SCHEDULES
     * `ProofNumbers::recompute()`" AND ENDED "ABSENT A SCHEDULER THIS
     * APPLICATION DOES NOT YET HAVE, COULD BE NOTHING AT ALL" — BOTH CLAUSES
     * ARE FALSE SINCE 2026-08-21 (6840–6859, 6851(a)).** `proof:recompute` runs
     * hourly at :07 and fans a job per tenant over `ALL` and the current month.
     * ⚠️ **The old text is kept because it was the evidence, not merely the
     * claim**: it is the sentence a 2026-08-21 lane brief quoted to establish
     * the gap, and the file admitting its own missing scheduler is what made the
     * defect findable at all.
     *
     * ✅ **`Livewire\Account\Home` STILL DELIBERATELY DOES NOT RECOMPUTE ON
     * RENDER, AND 907 IS WHY** — *"recomputing on every render would hide a
     * broken scheduler behind a screen that always looks right"*. ⛔ **That
     * ruling is not softened by the scheduler existing; it is vindicated by
     * it.** The safeguard did exactly its job for months: the screen showed
     * zeros rather than lying, and what was missing was somebody reading them.
     * **Do not "simplify" this by recomputing on render now that a sweep
     * exists** — that reverses 907 and re-hides every future failure.
     *
     * ⚠️ **AND THIS METHOD SHOULD GO ON CALLING `recompute()` DIRECTLY.** A
     * Day-7 summary is a message sent once, on one day, and must not depend on
     * when a sweep last ran; the call is a one-time need, not a cache read.
     *
     * @return array<string, mixed>
     */
    private function runDay7(FirstWeekRun $run, Business $business): array
    {
        $numbers = $this->proofNumbers->recompute(ProofNumbers::ALL);

        $run->completed_at = now();

        // ⚠️ `completed_at` IS THE ONE MARKER THAT IS *NOT* PUT BACK WHEN THERE
        // IS NOBODY TO WRITE TO, AND THE ASYMMETRY IS DELIBERATE (2010). It does
        // not claim a message went — it claims the week is over, which is true
        // whether or not the summary reached anyone, and the loop has already
        // advanced `step` to `LAST_STEP` in memory for the same reason every
        // other silent step advances it. Put back, this run would sit at step 4
        // with nothing left that could ever close it: the loop cannot run again,
        // so `completed_at` would stay null and every future tick would re-read
        // the review table and write another `automation_runs` row, forever.
        // `win_type` is put back because it is a claim about the tenant's
        // outcome that other code reads; this is a terminator.
        if (! $this->notifyOwner($run, $business, new FirstWeekSummary(
            $numbers->google_reviews,
            $numbers->leads,
            $numbers->recovered,
            $run->win_type,
        ))) {
            return ['sent' => false, 'reason' => 'no_owner_address'];
        }

        return ['sent' => true, 'win_type' => $run->win_type?->value];
    }

    /**
     * Whether a Google review has arrived since this tenant's week started,
     * and celebrate it if so.
     *
     * ⚠️ **RUN ON EVERY `advance()` TICK, NOT GATED TO A DAY.** §3.2's own
     * rule — "every step is idempotent and skippable if its win already
     * happened organically" — only means something if the win is noticed as
     * soon as it happens rather than on whichever scheduled day the state
     * machine next looks. A review that arrives on day one is still the win.
     *
     * ⚠️ **`reviews.source = 'google'`, NEVER UNFILTERED**, on `ProofNumbers`'
     * own precedent (902): counting every review regardless of source would
     * report a tenant's own feedback-form submission as a review on their
     * Google listing, to the one person least able to check it.
     *
     * Returns the win it set, so the caller never has to re-read `$run->
     * win_type` after this mutates it.
     */
    private function detectWin(FirstWeekRun $run, Business $business): ?FirstWeekWinType
    {
        $review = Review::query()
            ->where('source', ReviewSource::Google)
            ->where('created_at', '>=', $run->started_at)
            ->orderBy('created_at')
            ->first();

        if (! $review instanceof Review) {
            return null;
        }

        // Set before the send, committed by the send (1950) — the celebration
        // is the one message an owner would most obviously notice arriving
        // twice, and `win_type` is the only thing that stops it.
        $run->win_type = FirstWeekWinType::GoogleReview;
        $run->win_at = now();

        // Put back on a refusal, and returning null is what stops the caller
        // spending the day on a celebration that was never sent — before 2010
        // this branch consumed the tick *and* committed nothing, so `step`
        // never moved and the week deadlocked permanently.
        if (! $this->notifyOwner($run, $business, new FirstWeekReviewCelebration)) {
            $run->win_type = null;
            $run->win_at = null;

            return null;
        }

        return $run->win_type;
    }

    /**
     * The tenant's one feedback page, as an absolute URL — real since signup,
     * on `TenantProvisioner::provision()`'s own ordering (the page is
     * published before any destination is enabled).
     *
     * ⚠️ **THROUGH `FeedbackPages::forLocation()`, NEVER `FeedbackPage::
     * query()` DIRECTLY** — an `ArchitectureTest` lint in `ReviewsTest.php`
     * confines that model to `Services/Feedback/`, and this class is not in
     * that directory.
     */
    private function reviewLinkUrl(Business $business): ?string
    {
        // ⚠️ ORDERED, BECAUSE `first()` WITHOUT ONE IS WHATEVER PostgreSQL FELT
        // LIKE (1888). A multi-location tenant would otherwise get an arbitrary
        // — and not even stable between ticks — feedback page in the one email
        // whose whole job is to hand them a link they can use.
        $location = Location::query()->orderBy('id')->first();

        if (! $location instanceof Location) {
            return null;
        }

        $page = $this->feedbackPages->forLocation($location);

        if ($page === null) {
            return null;
        }

        return Route::has('feedback.show') ? route('feedback.show', ['slug' => $page->slug]) : null;
    }

    /**
     * The *wrong tenant* case RLS cannot catch (1887).
     *
     * ⚠️ **EVERY INPUT TO THIS CLASS EXCEPT THE RECIPIENT COMES FROM THE AMBIENT
     * TENANT, AND THE RECIPIENT COMES FROM THE ARGUMENT.** `Location::query()`,
     * `Review::query()`, `CustomerImports::hasAnyImport()`, `WizardProgress` and
     * `ProofNumbers::recompute()` all read under the global scope and the
     * session's RLS predicate; `notifyOwner()` reads `$business->owner->email`.
     * Hand this a different tenant's `Business` and it advances *this* tenant's
     * run, reads *this* tenant's import state — and emails somebody else's owner
     * a summary of a week that is not theirs. No caller does that today, which
     * is what makes it worth one line now rather than a leak later.
     *
     * `ReviewGating::assertBelongsToTenant()`'s shape, for its stated reason:
     * both ids are in hand, so check them against each other.
     *
     * @throws InvalidArgumentException
     */
    private function assertIsAmbientTenant(Business $business): void
    {
        if ((int) $business->id === Tenancy::idOrFail()) {
            return;
        }

        throw new InvalidArgumentException(
            'That business is not the established tenant. Every signal this path reads — '
            .'locations, reviews, imports, wizard progress, proof numbers — comes from the '
            .'ambient tenant, so advancing another business\'s run here would email one '
            .'owner an account of a week that belongs to somebody else.'
        );
    }

    /**
     * Commit, then send — the one ordering rule this class has (1950).
     *
     * ⚠️ **THE `save()` BELOW IS NOT HOUSEKEEPING AND MOVING IT ONE LINE DOWN
     * REINTRODUCES A DUPLICATE-EMAIL BUG.** Every caller sets its own marker on
     * `$run` first — `step`, `win_type`/`win_at`, `completed_at` — and this is
     * where those become durable, immediately before an enqueue that cannot be
     * taken back. Send first and the two failure modes stop being symmetric:
     * the message is out, the row does not know, and tomorrow's tick re-runs the
     * same step on a *different* calendar day under a *different* idempotency
     * key — so `AdvanceFirstWeekPathJob::claimIsSpent()` is not even the thing
     * being asked. Committing first turns that same failure into a *lost*
     * message, and a lost message is the direction this class chooses on
     * purpose — see `advance()`'s docblock for what "lost" costs, which is more
     * than the word "recoverable" implies (2013).
     *
     * A `save()` that throws therefore means nothing was sent, which is exactly
     * the outcome to want: the caller's markers never reach the database and the
     * next tick does the whole step over, once.
     *
     * ⛔ **AND SINCE 10996 THE OTHER DIRECTION HOLDS TOO: A SEND THAT THROWS
     * MEANS THIS METHOD DOES NOT RETURN TRUE.** The ordering above is
     * deliberately unchanged — the markers still commit first and the step is
     * still lost, which is this class's choice — but `PlatformMailer::send()`
     * could not fail on its caller (9500), so `return true` was reached for a
     * message that may never have reached the queue, and
     * `AdvanceFirstWeekPathJob::activityAction()` reads exactly that to file
     * *"Sent you an update about your first week"* into the owner's feed.
     * ⚠️ **A lost step the owner is told about is worse than a lost step**, and
     * it is the only one of the two this class had not already argued for.
     *
     * ⚠️ **RETURNS FALSE WHEN THERE IS NOBODY TO WRITE TO, AND THE CALLER IS
     * REQUIRED TO ACT ON IT** (2010). Both refusals happen *before* the save, so
     * nothing of the caller's is committed here — but the marker the caller
     * already set is still sitting on the model in memory, where the loop's own
     * trailing `save()` will happily persist it. `runDay5()` and `detectWin()`
     * therefore put `win_type`/`win_at` back, and every caller returns
     * `sent: false` with `no_owner_address` rather than reporting a message that
     * never left. This replaced 1957's two silent returns, which were unreachable
     * in this schema and, being unreachable, had gone unexamined: with the
     * address blank the run deadlocked permanently — `step` never advanced, no
     * step ever ran, and the feed line "Sent you an update about your first week"
     * was written every day forever for a message that never went.
     */
    private function notifyOwner(FirstWeekRun $run, Business $business, Notification $notification): bool
    {
        $owner = $business->owner;

        if (! $owner instanceof User) {
            return false;
        }

        $address = trim((string) $owner->email);

        if ($address === '') {
            return false;
        }

        $run->save();

        // ⛔ `deliverNow()` RATHER THAN `send()`, AND THE ORDERING ABOVE IS
        // UNTOUCHED — 10996. This class chooses a lost message over a duplicate
        // and the `save()` still commits first; what changes is only whether
        // anybody finds out. `send()` swallows every throwable and returns
        // `void` (9500), so the `return true` below — which
        // `AdvanceFirstWeekPathJob::activityAction()` reads to file *"Sent you
        // an update about your first week"* into the owner's own feed — was
        // asserting a send that may never have reached the queue. **The word
        // this method returns is now earned.** A throw propagates through
        // `advance()` to the job, whose `claimIsSpent()` is `true`, so the
        // remaining attempts no-op against the spent claim and `failed()` rings
        // `App\Enums\OperatorAlertKind::AutomationAbandoned`. See `advance()`'s
        // docblock for why the step is still lost and why that is the choice.
        $this->mailer->deliverNow($address, $notification);

        return true;
    }
}
