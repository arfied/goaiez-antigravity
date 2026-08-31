<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What `platform_health_windows` counts — the machinery's own vital signs
 * (T176 §3, P23).
 *
 * ⚠️ **PLATFORM HEALTH, NEVER TENANT HEALTH.** `sending_health_windows` already
 * answers *"is this tenant's list healthy"* and has a dashboard. These three
 * answer *"is the machinery running"*, which is a question about us, so no case
 * here may ever gain a tenant dimension — see the creating migration for why a
 * business id on this table would be a leak rather than a feature.
 *
 * ⛔ **"THERE ARE NOW SEVEN" WAS TRUE FOR ONE DAY AND THERE ARE NOW EIGHT —
 * FOURTH CORRECTION, AND THE ONLY THING WORTH TAKING FROM THAT IS THAT THE
 * COUNT SHOULD NEVER HAVE BEEN WRITTEN HERE.** {@see self::ScheduledRunFailed}
 * joined at 9680–9686 and **counts failures only**. ⚠️ **This paragraph is
 * deliberately the last one to add a number**: `cases()` is the authority, the
 * rule below is what a reader needs, and the next case may correct the rule if
 * it moves and must not correct an arithmetic sentence that never had to exist.
 *
 * ⛔ **"THERE ARE NOW SIX" WAS TRUE UNTIL 2026-08-25 AND THERE ARE NOW SEVEN —
 * BOTH READINGS KEPT AND DATED, AND THIS IS THE THIRD TIME THIS PARAGRAPH HAS
 * BEEN WRONG ABOUT A NUMBER IT DID NOT NEED TO CARRY.**
 * {@see self::WebhookKeyUnavailable} joined at 9380–9394 and **counts failures
 * only**, so the arithmetic sentence below is now right about five of the seven
 * and the rule it states is unchanged for the fourth time. ⚠️ **The count is
 * still one `cases()` away**; what is worth reading here is the rule.
 *
 * ⛔ **"THERE ARE NOW FOUR" WAS TRUE UNTIL 2026-08-25 AND THERE ARE NOW SIX —
 * BOTH READINGS KEPT AND DATED, AND THE COUNT IS DELIBERATELY THE LAST THING
 * THIS DOCBLOCK RELIES ON.** {@see self::CredentialAbsent} and
 * {@see self::CredentialUnreadable} joined at 9320–9327 and **both count
 * failures only**, so the arithmetic sentence below is now right about four of
 * the six and the rule it states is unchanged for the third time. ⚠️ **The
 * number is one `cases()` away and a paragraph that carries it has now been
 * wrong twice**; what is worth reading here is the rule, which is that a rate
 * is meaningful only where the happy path also increments `total`.
 *
 * ⛔ **"TWO OF THE THREE" WAS TRUE UNTIL 2026-08-21 AND THERE ARE NOW FOUR —
 * BOTH READINGS KEPT AND DATED.** {@see self::ScheduledRun} joined them
 * (7080–7099) and it counts successes, so the sentence below is right about
 * `Heartbeat` and `WebhookSignature` and wrong about the arithmetic. **Two of
 * the four count failures only**, and the rule the paragraph states is
 * unchanged.
 *
 * ⚠️ **TWO OF THE THREE COUNT FAILURES ONLY, AND READING A RATE OFF ONE OF THEM
 * IS THE MISTAKE THIS DOCBLOCK EXISTS TO PREVENT.** `failures / total` is a
 * meaningful number only where the happy path also increments `total`. It does
 * for {@see self::VendorCall}; it does not for the other two, where every row is
 * an observation of the thing going wrong and the rate is therefore always 100%.
 * The checks read each one the way it is written — an absolute count for the
 * failure-only signals, a rate over a floor for the vendor one — and
 * {@see self::countsSuccesses()} is what a caller asks rather than remembering.
 */
enum PlatformHealthSignal: string
{
    /**
     * A process saying "I am still here" — `scheduler`, `queue`.
     *
     * ⛔ **PRESENCE IS RECORDED AND ABSENCE IS WHAT ALERTS.** A heartbeat that
     * only fired on success would be a check that cannot report the one failure
     * it exists for: a dead scheduler produces silence, not an error. So nothing
     * here raises anything — the watch reads `last_at` and alerts on its age,
     * and it deliberately runs somewhere neither the scheduler nor a queue
     * worker can take down with them.
     */
    case Heartbeat = 'heartbeat';

    /**
     * A webhook that arrived without a signature we could verify — `stripe`,
     * `authorize_net`, `infobip`, `zernio`, `ses`, `gmail`.
     *
     * ⚠️ **FAILURE-ONLY.** Nothing counts a webhook that verified: the happy
     * paths are in six different controllers and adding a write to each is six
     * chances to slow down a payment notification for a denominator nobody reads.
     * The check on it is therefore an absolute count in a window.
     *
     * ⚠️ **AND A NON-ZERO COUNT IS NOT NECESSARILY AN ATTACK.** The commonest
     * cause by far is a missing or rotated signing secret, which fails closed and
     * looks exactly like nothing happening — `StripeWebhookController`'s docblock
     * describes the symptom: Checkout works perfectly and no subscription ever
     * leaves `pending_checkout`. That is precisely why it is worth a bell.
     */
    case WebhookSignature = 'webhook_signature';

    /**
     * A call to an outside service — `anthropic`, `openai`, and whatever calls
     * the same recorder next.
     *
     * ⚠️ **BOTH OUTCOMES ARE COUNTED HERE**, so this is the one signal carrying
     * a real denominator and the only one a rate may be computed from. A model
     * provider that answers ninety-nine calls and fails one is healthy; the same
     * one failure out of two is not, and only a rate can tell them apart.
     *
     * ⛔ **A REFUSAL IS NOT A FAILURE AND MUST NEVER BE COUNTED AS ONE.** A model
     * declining to answer is the safety classifier working; a 500 from the
     * provider is the vendor being down. Counting the first would put the alert
     * threshold at the mercy of what customers happen to ask.
     */
    case VendorCall = 'vendor_call';

    /**
     * One run of one scheduled command, and how long it took — decision 6975's
     * residual, closed at 7080–7099.
     *
     * ⛔ **THE SOURCE IS THE ARTISAN COMMAND NAME, WHICH IS THE ONE THING THAT
     * LETS THIS BE COMPARED AGAINST `routes/console.php`.** `ops:heartbeat`,
     * `billing:send-renewal-reminders`. The window a stranded lock is measured
     * against lives on the schedule entry rather than in the registry, so the
     * reader — `ops:schedule-runtimes` — joins the two by this name.
     *
     * ⚠️ **BOTH OUTCOMES ARE COUNTED, SO THIS IS THE SECOND SIGNAL WITH A REAL
     * DENOMINATOR.** `total` is every run whose duration was measured;
     * `failures` is the subset that ran for at least as long as their own
     * `withoutOverlapping()` window, **which is still the only definition of
     * "failure" this signal has** and is deliberately not the exit code. That
     * half is unchanged and is 9371's rule: the honest counter is not levelled
     * down to match a dishonest one.
     *
     * ⛔ **THE REASON GIVEN FOR IT WAS FALSE AND HELD FOR THREE DAYS — BOTH
     * READINGS KEPT AND DATED (9680–9683).** The sentence continued: *"It is
     * deliberately **not** the command's exit code: a background entry's
     * non-zero exit is never dispatched to the parent process at all
     * (`ScheduleRunCommand` gates that throw on `! runInBackground`), so
     * counting exit codes here would report a failure rate for five entries and
     * silence for thirty."* **Every clause of that is true about the parent and
     * the conclusion does not follow**, because the exit code is not read in the
     * parent. `CommandBuilder::buildBackgroundCommand()` emits
     * `( … ; schedule:finish "<mutex>" "$?" ) &`, `ScheduleFinishCommand` calls
     * `Event::finish($app, $code)` — which assigns `$this->exitCode` — **and
     * only then** dispatches `ScheduledBackgroundTaskFinished`. So this
     * application's own listener is handed the real exit code of all
     * thirty-nine background entries, in the `schedule:finish` process, however
     * the child died. Read in `vendor/` on 2026-08-25 rather than recalled
     * (255). ⚠️ **The five foreground entries are the same one step earlier**:
     * `Event::run()` calls `finish()` itself, so `exitCode` is already assigned
     * when `ScheduledTaskFinished` is dispatched. **There was never a partial
     * denominator to refuse.**
     *
     * ✅ **What carries an exit code is {@see self::ScheduledRunFailed}**, so
     * the two questions have two counters and neither answers in the other's
     * place.
     *
     * ⚠️ **`max_duration_ms` IS THE COLUMN THAT MATTERS AND ONLY THIS SIGNAL
     * WRITES IT.** See the adding migration.
     */
    case ScheduledRun = 'scheduled_run';

    /**
     * A scheduled command that exited non-zero — the source is the artisan
     * command name, exactly as {@see self::ScheduledRun}'s is (9680–9686).
     *
     * ⛔ **UNTIL THIS CASE EXISTED, A SCHEDULED COMMAND'S FAILURE WAS RECORDED
     * AS A HEALTHY RUN, AND THE FASTER IT DIED THE HEALTHIER IT LOOKED.**
     * `ScheduleRunCommand` dispatches `ScheduledTaskFinished` **before** it
     * looks at the exit code, so `ScheduledRunMeter::finished()` had already
     * written `total + 1, failures + 0` with a duration of a few hundred
     * milliseconds by the time anything could have failed — and on the
     * thirty-nine background entries nothing could, because the parent's throw
     * is gated on `! runInBackground` and both streams of the child go to
     * `/dev/null`. **Four scheduled commands return `self::FAILURE` in words
     * written for an operator, into that.**
     *
     * ⚠️ **A SEPARATE CASE RATHER THAN A SECOND MEANING FOR
     * {@see self::ScheduledRun}'s `failures`, WHICH IS 9371's RULE.** That
     * column means *overran its own `withoutOverlapping()` window* and means it
     * accurately; folding exit codes into it would make one number answer two
     * questions with different remedies — widen the window, versus the command
     * is broken — and the tidy-minded edit that merges them reads on a diff as
     * removing an inconsistency.
     *
     * ⚠️ **FAILURE-ONLY, AND THE DENOMINATOR IS NOT MERELY UNCOUNTED BUT
     * WRONG.** {@see self::ScheduledRun}'s `total` is every run whose *duration*
     * was measured, and a background run whose start marker was lost or expired
     * is deliberately not measured at all — so the two populations differ, and a
     * rate of one over the other would be a ratio between two different
     * questions. {@see self::countsSuccesses()} answers false and
     * {@see \App\Services\Ops\PlatformHealth} therefore refuses a rate over it.
     *
     * ⛔ **"AND NOTHING RINGS ABOUT IT YET" WAS TRUE FOR ONE WAVE AND IS NOT —
     * CORRECTED 2026-08-26 (9945–9959). BOTH READINGS KEPT AND DATED** (4368).
     * The superseded text ran: *"This counter and a `critical` log line are the
     * whole of what a failed scheduled command reaches today; the bell is owed
     * and is named in 9690."* ✅ **`OperatorAlertKind::ScheduledRunFailed` is
     * rung from `ScheduledRunMeter::writeFailedRun()`**, after this counter is
     * written and never before it, bounded by
     * `ScheduledRunMeter::FAILED_RUN_REPEAT_HOURS` rather than by a threshold.
     *
     * ⚠️ **THE TWO ARE NOT THE SAME READING AND THIS COUNTER DID NOT BECOME
     * REDUNDANT.** The bell fires once per command per repeat window; this
     * counts **every** failure, which is what `ops:schedule-runtimes` prints and
     * what tells one bad night from three bad weeks.
     */
    case ScheduledRunFailed = 'scheduled_run_failed';

    /**
     * A platform credential this application needed and did not have — the
     * source is the credential key (9320–9327).
     *
     * ⚠️ **THE OBSERVATION IS "SOMETHING NEEDED IT", NOT "IT IS UNSET".** A key
     * nobody reads produces no row however long it stays blank, which is what
     * keeps this off the fresh install and off every credential this platform
     * declares and does not use. `Admin\Credentials` is where the standing
     * configuration lives and it answers a different question — *which keys are
     * absent* — pulled by somebody who already suspects. This counts the moment
     * a real request, job or sweep asked for one and was refused.
     *
     * ⚠️ **FAILURE-ONLY, AND THE ALTERNATIVE WAS PRICED RATHER THAN ASSUMED.**
     * Counting the successful reads too would put a database write on every
     * credential resolution on the platform — every model call, every text,
     * every webhook verification — to build a denominator no check divides by.
     * The reading here is a state, so {@see self::countsSuccesses()} answers
     * false and {@see PlatformHealth} therefore refuses to compute a rate over
     * it at all.
     */
    case CredentialAbsent = 'credential_absent';

    /**
     * A stored platform credential whose ciphertext could not be decrypted —
     * the source is the credential key (9320–9327).
     *
     * ⛔ **THIS IS A DIFFERENT FAULT FROM {@see self::CredentialAbsent} AND THE
     * TWO ARE SEPARATE CASES SO THAT THE COUNTER CARRIES THE CLASSIFICATION AT
     * THE MOMENT OF THE FAULT.** They could have shared one case and been told
     * apart at alert time by asking whether a `platform_credentials` row exists
     * — and that derivation is exact only until somebody pastes the key between
     * the fault and the sweep, at which point the alert names the wrong remedy.
     * A counter that is right when it is written cannot be raced.
     *
     * ⚠️ **AND THE TWO ARE NOT NESTED.** A key whose ciphertext is unreadable
     * while `.env` still carries its bootstrap seed resolves perfectly and
     * produces no {@see self::CredentialAbsent} row at all — the platform runs,
     * on whatever value the environment file holds, while Ops shows the key as
     * stored and every rotation performed there is silently not in effect. That
     * state has no other instrument in this application.
     */
    case CredentialUnreadable = 'credential_unreadable';

    /**
     * A webhook this platform could not judge, because the signing material it
     * judges with could not be fetched — the source is the endpoint, exactly as
     * {@see self::WebhookSignature}'s is (9380–9394).
     *
     * ⛔ **THE OTHER SIGNAL SAYS *"WE CHECKED AND IT FAILED"*; THIS ONE SAYS *"WE
     * NEVER CHECKED"*, AND UNTIL THIS CASE EXISTED THE SECOND WAS RECORDED AS
     * THE FIRST.** Two endpoints fetch signing material over the network before
     * they can verify anything — SNS's certificate and Google's push keys — and a failure of that fetch produced a bare `false`, which the
     * controller counted as an unverifiable signature. An operator paged about
     * it was then told, in the words `PlatformHealthChecks` puts on the page,
     * that *"the usual cause is a missing or rotated signing secret"* — the
     * remedy for our own misconfiguration, printed about somebody else's outage.
     *
     * ⚠️ **FAILURE-ONLY, LIKE ITS SIBLING, AND FOR A SHARPER REASON THAN ITS
     * SIBLING'S.** There is no happy path to count here at all: a successful
     * fetch is cached for an hour and most verifications make no request, so a
     * `total` would be a count of cache misses and a rate over it would mean
     * nothing. {@see self::countsSuccesses()} answers false and
     * {@see PlatformHealth} therefore refuses to compute a rate over it.
     *
     * ⛔ **AND ITS READER HAS NO ARMING ROW, WHICH IS
     * {@see self::CredentialAbsent}'s ARGUMENT ARRIVING AT A SECOND FAULT.** A
     * key host is unreachable at one webhook an hour exactly as much as at ten
     * thousand, and the cost of the fault is the traffic it discards — so any
     * volume bar would be a threshold only a busy platform could reach, over a
     * loss a quiet one suffers just as completely.
     */
    case WebhookKeyUnavailable = 'webhook_key_unavailable';

    /**
     * Whether the happy path also increments `total` for this signal.
     *
     * ⚠️ **ASKED RATHER THAN REMEMBERED**, because the failure mode is silent:
     * a rate computed over a failure-only signal is 100% by construction, so the
     * first forged webhook anybody posts would trip a rate threshold set at any
     * value at all.
     */
    public function countsSuccesses(): bool
    {
        return match ($this) {
            self::VendorCall, self::ScheduledRun => true,
            self::Heartbeat,
            self::WebhookSignature,
            self::ScheduledRunFailed,
            self::CredentialAbsent,
            self::CredentialUnreadable,
            self::WebhookKeyUnavailable => false,
        };
    }
}
