<?php

declare(strict_types=1);

namespace App\Services\Ops;

use App\Enums\AlertOrigin;
use App\Enums\OperatorAlertKind;
use App\Models\OperatorAlert;
use App\Notifications\OperatorAlertProbeSent;
use App\Notifications\OperatorAlertRaised;
use App\Services\Config\DefaultsRegistry;
use App\Services\Mail\PlatformMailer;
use App\Services\Sms\PlatformTexter;
use App\Support\MailFailure;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * The bell — the one place this platform pages its own operator (T176 §3, P23).
 *
 * *"`AutomationRuns` is a screen someone must open; a 24/7 agent and live money
 * webhooks need a bell that rings. Owner-approved: email + SMS alerts."*
 *
 * ## Who this is for, and why it is not `PlatformMailer::sendToCustomer()`
 *
 * ⛔ **THE RECIPIENT IS US.** Not a business owner, not a tenant's customer —
 * the person who runs this platform, at an address and a number they typed into
 * the registry themselves. That is a third authorisation model beside the two
 * `PlatformMailer` documents, and it is the narrowest of the three: there is
 * exactly one recipient, it is configured rather than looked up, and no caller
 * of this class may name one. **A consent record has nothing to say about it**;
 * `consent_records` is keyed on `customer_id` and we are nobody's customer.
 *
 * ⚠️ **THE SMS PATH IS THE ONE TO READ CAREFULLY.** `PlatformTexter`'s docblock
 * refuses an unpermitted `send()` on SMS in strong terms, and it is right:
 * texting an *account holder* has a regulator and their number reached us
 * through a consent record like anybody else's. {@see PlatformTexter::alertOperator()}
 * is not that method — it takes no recipient at all, reads the platform's own
 * number out of the registry, and is confined to this class by a lint. See its
 * docblock for the full argument, including what would make it the shortcut that
 * one warns about.
 *
 * ## De-duplication is the feature, not an optimisation
 *
 * ⚠️ **AN ALERT THAT FIRES EVERY SWEEP IS AN ALERT SOMEBODY MUTES** — 511's
 * failure with a phone number attached. The watch runs every five minutes and a
 * broken thing stays broken, so one alert per `(kind, subject)` per quiet window
 * is what keeps the second incident audible after the first one.
 *
 * ⛔ **AND FOR ONE POPULATION OF RAISERS THE DEDUPE IS NOT A RATE LIMIT AT ALL,
 * WHICH IS THE FINDING BEHIND {@see $this->pushBudgetPerKind()}** (7820–7839).
 * *"Per `(kind, subject)`"* bounds nothing when the **subject is a tenant id and
 * a stranger can pick the tenant**: two raisers run on the unauthenticated pixel
 * collector, their subjects are business ids, and a tenant's pixel key is public
 * by construction because it sits in the page source of that tenant's own
 * website. So the real bound was *one page per tenant per quiet window*, times
 * however many keys somebody had collected, and the quiet window's floor is one
 * minute. **A pager flooded from outside is a pager that has been switched off
 * by somebody who does not own it**, and the only control an operator had was to
 * blank {@see self::SMS_KEY}. There is a daily budget per kind now, and
 * {@see AlertOrigin} is the whole of who it applies to.
 *
 * ## Nothing here may fail the thing it was watching
 *
 * ⛔ **R25: a bell, never a brake.** {@see self::raise()} returns rather than
 * throws, and every step inside it is individually contained: a mailer that
 * cannot send must not stop the text, a text that cannot send must not stop the
 * record, and none of the three may propagate into a webhook, a queued job or an
 * HTTP request. **The log line is written before either channel** and is the one
 * part of this that has no dependency to fail.
 *
 * ## Two of the three destinations are optional, and the log line says which
 *
 * ⛔ **"IT REACHES THE LOG AND NOTHING ELSE" IS ONE CHANNEL SHORT** and this
 * class used to say it. The row in `operator_alerts` is written first and
 * unconditionally, and since wave 7 it has a reader on a shipped screen — so
 * the state a blank `ops.alert_email` and a blank `ops.alert_sms` produce is
 * **no push channel**, not no record. The distinction matters because the two
 * failures have different answers: a missing record is a defect, and a missing
 * push channel is a registry row nobody has filled in.
 *
 * ⚠️ **{@see self::pushChannels()} IS THE ONE PLACE THAT QUESTION IS ANSWERED**,
 * and the `critical` log line for every alert carries its answer. That is the
 * only announcement of the deaf state that reaches production at all: the
 * scheduled sweep's console warning is written by a `runInBackground()` entry,
 * so the framework redirects it to `/dev/null` before cron's own redirect gets
 * to it. `ops:alert-channels` is the same question asked deliberately, and
 * `composer deploy` asks it on every deployment.
 *
 * ## Configured, and then actually asked — {@see self::probeChannels()}
 *
 * ⛔ **EVERYTHING ABOVE ANSWERS *WHO CAN WE PAGE* AND NOTHING ANSWERED
 * *WOULD A PAGE ARRIVE*.** A typo'd address, a mailbox that rejects everything,
 * a number that changed hands, `SMS_DRIVER` on `log` and `mail.default` on
 * `log` all read as configured — here, on the settings screen, and in
 * `ops:alert-channels`. The one instrument that could catch any of them was
 * that command's undelivered count, which **needs an alert to have been
 * raised**; so on a platform where nothing has broken yet there was no way to
 * find out short of faking an incident, and {@see self::fire()} writes its row
 * first and unconditionally.
 *
 * ⛔ **THAT SENTENCE ENDED "INTO A TABLE NOTHING PRUNES" AND IT STOPPED BEING
 * TRUE ON 2026-08-22 — BOTH READINGS KEPT AND DATED** (7520–7539).
 * {@see self::prune()} exists and {@see $this->retentionDays()} is a year.
 * ⚠️ **THE PROBE'S ARGUMENT IS UNTOUCHED AND IT IS WORTH SAYING WHY**: a
 * fabricated incident that expires in twelve months is not meaningfully less
 * expensive than one that never expires, because every cost this paragraph
 * names — the ringing section, the severity ordering, the kind filter, the
 * undelivered count — is paid inside the first thirty days. **A horizon is not
 * a way to remove a row and was never the answer to this defect** (7397(c)).
 *
 * ⚠️ **THE PROBE IS THEREFORE NOT A KIND, NOT A RAISE AND NOT A
 * ROW.** It composes its own body from {@see self::PROBE_HEADLINE}, writes
 * nothing, logs at `info`, and hands back what each channel did.
 */
final class OperatorAlerts
{
    /**
     * The registry keys this class reads. Named as constants so a reader can
     * find every one of them without grepping strings, and so the watch command
     * can say which are unset.
     */
    public const string EMAIL_KEY = 'ops.alert_email';

    public const string SMS_KEY = 'ops.alert_sms';

    public const string QUIET_KEY = 'ops.alert_quiet_minutes';

    /**
     * How much of a summary the row can hold — `operator_alerts.summary` is
     * `varchar(300)` and this is that number, in the one place code may read it.
     *
     * ⛔ **IT IS A CONSTANT AND NOT A GUESS, AND A LINT PINS IT TO THE COLUMN.**
     * `OperatorAlertingTest`'s *"the summary limit this class clamps to is the
     * width of the column it writes"* reads `pg_attribute` and compares. A
     * second copy of a schema figure typed into a service is exactly the drift
     * this codebase keeps paying for; what stops it here is that widening the
     * column without moving this number reddens the build, and moving this
     * number without widening the column reddens it too.
     */
    public const int SUMMARY_LIMIT = 300;

    /**
     * What stands in the summary where something was removed.
     *
     * ⚠️ **ASCII, AND THAT IS A DECISION ABOUT A TEXT MESSAGE RATHER THAN A
     * STYLE.** A typographic ellipsis is outside GSM 03.38, so one character of
     * it moves the whole body to UCS-2 and cuts a concatenated segment from 153
     * characters to 67 — a three-segment pager text becomes five, for a
     * character nobody reads. Three dots cost three bytes and change nothing.
     */
    private const string ELISION = ' ... ';

    /**
     * The fewest characters of the opening that make an elided summary worth
     * re-ordering at all.
     *
     * ⚠️ **THE SUBJECT IS NOT IN THE TEXT MESSAGE ANYWHERE ELSE.**
     * `PlatformTexter::alertOperator()` is handed `headline().': '.$summary` and
     * nothing more, so `operator_alerts.subject` — the command name, the vendor,
     * the queue — reaches the handset only because the summary opens with it.
     * Keeping a closing sentence at the cost of that leaves a text saying what
     * to do about something it has not named.
     */
    public const int MIN_OPENING = 80;

    /**
     * The narrowest and the widest quiet window an operator may set (7580-7599).
     *
     * ## The defect these two numbers close
     *
     * ⛔ **THIS IS THE ONLY MUTE IN THE PRODUCT AND UNTIL 2026-08-22 IT HAD NO
     * CEILING.** `Admin\PlatformSettings::parse()` is a type coercion and
     * nothing else — a digits-only `preg_match()` and a cast — and
     * `DefaultsRegistry::set()`'s three guards are *withheld*, *declared* and
     * *preconditions*, none of which was a range for any key. So `1051200` was
     * a legal value, and it means **two years**: the same `(kind, subject)`
     * breaking again after recovering would have been swallowed with nothing
     * anywhere refusing it, recording it or reporting it.
     *
     * ⚠️ **SAY THE HAZARD PRECISELY RATHER THAN LARGELY.** The dedupe is
     * `(kind, subject)`, so a *different* failing provider still rings. What a
     * long window silences is a **recurrence of the same kind on the same
     * subject** — and that sounds narrower than it is, because two kinds have
     * one subject each: {@see OperatorAlertKind::FailedJobSpike} is raised with
     * `''`, and {@see OperatorAlertKind::HeartbeatSilent}'s subjects are
     * `PlatformHealthChecks::PROCESSES`, which is two strings. Once each has
     * rung once, *"jobs are failing"* and *"nothing is finishing them"* are off
     * for the length of the window — and the second is the bell that rings when
     * the thing that rings the others has stopped.
     *
     * ## Three arguments say do not bound this at all, and here is what beats
     * each one
     *
     * 1. ⚠️ **"IT IS THE ONLY MUTE, BY DESIGN."** `Admin\OperatorAlertBoard`
     *    refuses an *acknowledge* button partly because *"silence already has a
     *    home and it is one field"* — true about where silence lives, and not
     *    about what this field can do. **It is one number for every kind and
     *    every subject**, so it cannot mute *a thing*; it mutes *the pager*.
     *    The control for one noisy check is that check's own threshold at zero,
     *    which this feature's own test file calls, in as many words, *"the
     *    escape valve that keeps an operator from muting the whole channel when
     *    one check is too tight for their traffic"*. ⛔ **A ceiling here does
     *    not answer *"may an operator mark an alert as handled"* and must not be
     *    read as answering it** — that stays the owner's question, and five of
     *    the thirteen raise sites, across four classes, have no threshold of any
     *    kind behind them: `GbpConnections`, `IngestRejects`,
     *    `RevokeOwedGbpGrants` and `ScheduledRunMeter` twice, the last of which
     *    says so in its own docblock — *"needs no registry threshold and has no
     *    off switch beyond the alert"*.
     * 2. ⚠️ **"A LONG WINDOW HAS A LEGITIMATE USE."** It does, and it is named:
     *    during a multi-day vendor outage, widening so the same `(kind,
     *    subject)` rings once a day rather than every hour. That is `1440`.
     *    The ceiling is seven times it, so the legitimate use is not
     *    constrained — it is not even approached.
     * 3. ⚠️ **"THE TREE ALREADY CHOSE DESCRIPTIONS OVER BOUNDS."**
     *    `MessagingTest` says *"an operator blanking the box on the settings
     *    screen is a reachable act that no lint can refuse, and the only thing
     *    standing between them and a silently disarmed containment is the
     *    description that box is rendered with"* (4492-4495). ⛔ **THAT WAS
     *    CHOSEN BECAUSE THE DANGEROUS VALUE THERE IS A DOCUMENTED ONE.** Zero
     *    disables a significance floor on purpose, so it can be described and
     *    can never be refused. Here the dangerous value is documented nowhere
     *    and corresponds to no intent anybody has stated. ⛔ **And the subject
     *    is different in kind**: a disarmed complaint floor still leaves
     *    something able to tell an operator about it, and this is the something.
     *    ⚠️ **The description is written as well rather than instead** — it is
     *    the half that reaches a deployed install without a migration (4480), so
     *    that remedy is adopted and added to.
     *
     * ## The two figures, and which of them is a judgement
     *
     * ⚠️ **THE FLOOR IS THE CLAMP THAT WAS ALREADY THERE, MOVED FORWARD ONE
     * STEP.** A window of zero rings on every sweep, which is 511 arriving as a
     * settings edit; {@see self::quietMinutes()} has always corrected it to one.
     * What is new is that a fresh `0` is **refused at the write**, so an
     * operator is told rather than silently overruled. **The read still clamps**
     * — see that method for the row a write guard structurally cannot reach.
     *
     * ⛔ **THE CEILING IS A JUDGEMENT AND IS WRITTEN AS ONE.** A week, from
     * three inputs, none of which is comfort: the widest *stated* legitimate use
     * is a day, so nothing anybody has asked for is refused;
     * `OperatorAlertBoard::RINGING_HOURS` makes a day *"how far back somebody
     * woken at 2am has to look to see the shape of the night"*, so past a day a
     * window has stopped suppressing a repeat of this incident and started
     * suppressing the **next** one; and a mute that expires inside a working
     * week is one somebody rediscovers, where a mute measured in months is one
     * nobody remembers setting.
     *
     * ⛔ **A CONSTANT AND NOT A REGISTRY KEY**, on {@see $this->retentionDays()}'
     * argument: a ceiling on a mute, editable from the same screen as the mute,
     * is not a ceiling. Raising this is a deploy and a review, which is the
     * right amount of friction for *"may this platform's pager be off for
     * longer than a week"*.
     */
    public const int MIN_QUIET_MINUTES = 1;

    public const int MAX_QUIET_MINUTES = 7 * 24 * 60;

    /**
     * How many pushes one kind may make in a day when an inbound request is what
     * rang it (7820–7839).
     *
     * ## ⛔ The defect, stated as the thing a stranger could do
     *
     * ⛔ **A PERSON HOLDING A TENANT'S PUBLIC PIXEL KEY COULD MAKE THIS PLATFORM
     * SEND A TEXT MESSAGE AND AN EMAIL, INSIDE THEIR OWN HTTP REQUEST.** Two
     * raisers run on the unauthenticated pixel collector —
     * `Services\Pixel\IngestRejects::record()` on the refusal path and
     * `Services\Pixel\MonthlyEventCap::alertOnce()` on the admitted one — and
     * both take a **business id** as their subject, so {@see self::alreadyRang()}
     * bounded the rate at *one page per tenant per quiet window* and at nothing
     * else. **The keys are public by construction**: they sit in the page source
     * of every tenant's own website. `PixelRateLimits` bounds one source address
     * to 300 beacons a minute and cannot see a tenant at all, so it never touched
     * this; the quiet window's own floor is one minute, legitimately (7580–7599).
     *
     * ⛔ **AND THE SHARP EDGE IS NOT THE MONEY.** A flood of tenant-scoped bells
     * is a handset nobody can read, an `ops.alert_email` inbox nobody can read,
     * and `MailQuota`'s daily ceiling — which counts platform mail like any other
     * message — spent on somebody else's traffic. **The only remedy a paged
     * operator had was to blank {@see self::SMS_KEY}, which is to turn the pager
     * off.** `PlatformTexter::alertOperator()` deliberately ignores `sms.enabled`
     * and `messaging.global_halt`, so halting the platform did not slow it either.
     *
     * ## ⛔ What this may never cost, and what it therefore does not touch
     *
     * ⛔ **NOTHING HERE BOUNDS AN ALERT THIS PLATFORM RAISED ABOUT ITSELF.** A
     * remedy that makes an operator alert droppable in an incident is worse than
     * the defect it closes, and every bell that pages about *this platform being
     * broken* is {@see AlertOrigin::Platform}: the health sweep, the heartbeat,
     * the vendor error rate, the complaint rate, the canary, the scheduled-run
     * meter. **Those are delivered inline, on both channels, in the same order,
     * exactly as they were before this constant existed.** The budget is asked
     * for on one arm only — see {@see self::fire()}.
     *
     * ⛔ **AND IT IS NEVER A BRAKE** (R25). A withheld push refuses no send, no
     * pixel batch and no webhook: the row is written, `critical` is logged with
     * the withholding named in it, the alert board lists it, and the deploy-time
     * channel check is told not to read it as a broken channel.
     *
     * ## The two figures, and both are judgements
     *
     * ⚠️ **PER KIND, WHICH IS THE PROPERTY WORTH BUYING.** A single platform-wide
     * budget is one figure fewer and lets a pixel flood starve the bell about a
     * Google listing binding that no longer matches its customer. Per kind, the
     * worst a flood on one collector path can do is spend that path's own
     * allowance. ⚠️ **The cost is that the ceiling multiplies by the number of
     * request-origin kinds** — four today — **so this figure is worth re-reading
     * whenever a fifth is minted**, and that sentence is here rather than in a
     * lint because no lint can tell a stranger-reachable raiser from any other.
     *
     * ⚠️ **TEN, WHICH IS ABOVE EVERY STATED LEGITIMATE NEED AND FAR BELOW A
     * FLOOD.** The realistic honest case is not an attacker at all: a change of
     * ours breaks origin checking and `PixelIngestRejects` rings for every tenant
     * at once. **The tenth text says nothing the first did not** — by then the
     * diagnosis is *this is not one tenant* — and the eleventh onward are what
     * teach an operator to ignore the channel (511). Ten also bounds the whole
     * request-origin population to forty messages a day, which is a handset that
     * still works and a mail spend that cannot exhaust even a sandboxed SES
     * account's 200 a day.
     *
     * ⛔ **"FORTY" WAS FOUR REQUEST-ORIGIN KINDS AND THERE ARE FIVE — CORRECTED
     * 2026-08-22, THE SENTENCE ABOVE KEPT AND DATED** (4368's rule, 8120–8139).
     * {@see OperatorAlertKind::PagerBudgetSpent} is raised from
     * {@see self::ringFlood()}, on the withheld path, inheriting the origin of
     * the alert it announces — which is `Request` by construction, because
     * nothing else is ever withheld — so it carries this ceiling like the rest
     * and the ceiling on the whole population is **fifty**. ⚠️ **7839(d) predicted this
     * multiplication and asked for a look when a fifth was minted**, so the look
     * is here: fifty pushes is still a handset somebody can read and is a
     * quarter of a sandboxed SES account's day. ⛔ **The number is not written
     * here again as a fact about today** — it is `PUSH_BUDGET_PER_KIND` times
     * the request-origin kinds, that set is one `AlertOrigin` reading away, and
     * this cell has now been wrong about it once.
     *
     * ⚠️ **A ROLLING DAY, BECAUSE THAT IS ALREADY THIS TREE'S SPAN OF ONE
     * INCIDENT.** `Admin\OperatorAlertBoard::RINGING_HOURS` is twenty-four and
     * its docblock calls it *"how far back somebody woken at 2am has to look to
     * see the shape of the night"*; a budget over the same span means everything
     * the pager withheld is on the screen the operator opens. ⛔ **The constant
     * is NOT derived from that one** — 7581 is the lesson: a value read by two
     * things with opposite interests is a coupling nobody stated, and that file
     * is a screen's paging window rather than a bound on sending.
     *
     * ⛔ **"ON THE SCREEN" STOPPED MEANING "ON A LINE OF ITS OWN" ON 2026-08-22,
     * AND THE SENTENCE ABOVE IS KEPT RATHER THAN EDITED** (7960–7979, 4368's
     * rule). That section drew every `(kind, subject)` in the window and could
     * not be bounded, which is what this bullet was leaning on; it is bounded
     * now, so in a flood of a thousand a withheld alert may be **counted**
     * rather than listed. ✅ **The half this bullet needs survives and is
     * stronger for it**: the window the budget spends over is the window the
     * screen totals, and it now reports **how many pushes were withheld, per
     * kind** — which is the fact an operator needs and one that a thousand
     * unreadable lines never gave them. ⚠️ **And the row says so in the log
     * either way** — *a limit stopped this, not a channel that is failing* —
     * which is what `push_withheld_at` was minted for and what nothing
     * rendered until then.
     *
     * ⛔ **AN HOUR WAS THE FIRST DRAFT AND IT IS THE WRONG SHAPE.** Ten an hour is
     * 240 a day per kind, which bounds a burst and does not bound the adversary
     * this is about — a script does not stop at the end of the hour. ⚠️ **The
     * price of a day is stated rather than glossed**: a genuine, different
     * request-origin alert arriving twenty hours after a flood is withheld from
     * both channels. It is still a row, still `critical`, still on the board.
     *
     * ⛔ **CONSTANTS AND NOT REGISTRY KEYS**, on {@see self::MAX_QUIET_MINUTES}'
     * argument exactly: a bound on how loudly a stranger may ring the pager,
     * editable from the same screen as the pager, is not a bound. Moving either
     * is a deploy and a review.
     */
    public const int PUSH_BUDGET_PER_KIND = 10;

    public const int PUSH_BUDGET_HOURS = 24;

    /**
     * How long this platform waits before saying the same thing about the same
     * mailer twice (9370–9379).
     *
     * ⛔ **A REPEAT INTERVAL, NEVER AN ARMING THRESHOLD** (`CLAUDE.md`,
     * 2026-08-25). Nothing here decides whether this bell can ring: the first
     * permanent failure rings it, at any volume, on any install. This decides
     * only when the platform is willing to say it again — and that matters
     * because **a broken transport is broken for every message**, so with the
     * seeded sixty-minute quiet window a five-day outage is a hundred and
     * twenty texts, which is decision 511 with a handset attached.
     *
     * ⚠️ **A DAY, AND NOT {@see PlatformHealthChecks::CREDENTIAL_REPEAT_DAYS}'
     * THIRTY.** That one is thirty because the counters behind it are pruned at
     * thirty days and, the whole time, `Admin\Credentials` answers the standing
     * question on a screen. **Nothing anywhere says platform mail has stopped**,
     * and the outage that minted this ran five days — so a month of quiet would
     * have spoken on day one of five and on none of the other four.
     *
     * ⚠️ **IN CODE RATHER THAN IN THE REGISTRY**, on
     * {@see $this->pushBudgetHours()}' argument: there is nothing about this
     * platform's traffic to tune it against, and an operator handed the box is
     * handed the ability to set it to a year — which on this bell is the same
     * edit as switching it off.
     *
     * ⛔ **IT LIVES HERE RATHER THAN ON THE JOB THAT READS IT, AND A
     * BUILD-FAILING LINT IS WHY RATHER THAN TASTE.** Its natural home is
     * `DeliverPlatformMail`, beside `$tries` and `$backoff` —
     * {@see PlatformHealthChecks::CREDENTIAL_REPEAT_DAYS}
     * sits on its own raiser for exactly that reason. **But
     * `Admin\OperatorAlertBoard` has to print the figure**, and 7661's rule is
     * that a board reads a figure off the class that applies it rather than
     * repeating it — while `EmailMeteringTest`'s *"the mail transport is
     * reachable only through the mailer that meters"* and `OutboundTest`'s
     * *"`canDeliver()` is not the guard"* both fail the build on **any** file
     * in `app/` outside `PlatformMailer` naming that job at all. Those two are
     * a money chokepoint and a send-path chokepoint; widening either to let a
     * screen read a constant would trade a real guard for a placement, so the
     * constant moved instead.
     */
    public const int MAIL_PATH_REPEAT_HOURS = 24;

    /**
     * How long a rung bell is kept — a year (7520–7539).
     *
     * ⚠️ **NOT A REGISTRY KEY, ON `WatchPlatformHealth::KEEP_DAYS`' ARGUMENT
     * VERBATIM**: *"a configurable retention here would be a setting whose only
     * possible effect is to make the table bigger"*. Nothing personal is stored
     * here, no published commitment covers it, and `storage.retention_days.*`'s
     * *the owner must rule* reasoning does not reach it — that one exists
     * because those objects are **other people's personal data** (4941). This is
     * the platform's own operational record about itself.
     *
     * ## The floor is derived; the tail above it is argued, and they are not the
     * same claim
     *
     * ⛔ **THIRTY DAYS IS THE FLOOR AND IT IS NOT A CHOICE.**
     * `ShowOperatorAlertChannels` counts *the alerts that reached nobody* over
     * `WatchPlatformHealth::KEEP_DAYS`, and that count is the one instrument
     * that can catch a typo'd address or `SMS_DRIVER` still on `log`. A horizon
     * below it would delete the rows that command is counting and leave it
     * printing *"no alert has been raised in the last 30 days"* about a platform
     * that raised several — a **false** sentence rather than a shorter one. A
     * test fails the build if the two ever cross.
     *
     * ⛔ **AND IT MUST EXCEED THE COUNTERS' OWN THIRTY, WHICH IS THE CREATING
     * MIGRATION'S ARGUMENT RATHER THAN A PREFERENCE.** `context` is jsonb *"so
     * an incident review can read what the numbers actually were … by the time
     * anybody looks, the window has rolled and the sample is gone"* — a design
     * statement that this row **outlives** `platform_health_windows`. Pruning
     * both at thirty destroys exactly the property `context` exists for.
     *
     * ⚠️ **A YEAR IS A CHOICE ABOVE THAT FLOOR AND IS WRITTEN AS ONE.** The
     * question no shorter window can answer is *"is this the third time this
     * year"* — what an operator actually asks about a canary halt, a command
     * that keeps overrunning, or a tenant whose binding keeps mismatching, and
     * the only reader a horizon can take away. ⚠️ **180 was considered and its
     * argument does not transfer**: `PruneTrialOriginClaims::RETENTION_DAYS` is
     * short *"so nobody carries a network hash into a second year"*, which is a
     * sentence about personal data this table does not hold.
     *
     * ## What bounds this table today is the de-duplication, and it scales with
     * tenants
     *
     * ⛔ **THE PRECEDENT'S "A FEW DOZEN ROWS A DAY" IS FALSE HERE.**
     * `platform_health_windows` grows with signal × source × hour, which is a
     * constant. Four kinds take a **business id** as their subject
     * (`GbpBindingMismatched`, `PixelIngestRejects`, `PixelMonthlyCapReached`,
     * `InboundVoiceMinutes`), so the ceiling is one row per tenant per kind per
     * quiet window — growth proportional to the customer base rather than to the
     * platform.
     *
     * ⛔ **AND ONE FIELD ON THOSE ROWS IS WRITTEN BY A STRANGER.**
     * `IngestRejects::record()` puts the request's `Origin` header — up to 512
     * bytes of anything, from anybody who knows a tenant's public pixel key —
     * into `context.origin`. It is not personal data and it is not a leak; it is
     * an **append into a permanent store by somebody outside the building**, and
     * *permanent* is the half this constant removes. That is the argument for a
     * horizon that survives *"disk is cheap"*, which the others do not.
     */
    public const int RETENTION_DAYS = 365;

    /**
     * Rows per DELETE — `PruneTrialOriginClaims`' figure and its reasoning
     * (7760-7779). See {@see self::prune()} for why an unchunked statement here
     * would have gone a year without anybody being able to notice.
     */
    private const int CHUNK = 500;

    /**
     * The words a channel probe sends, fixed here and composed nowhere else.
     *
     * ⛔ **THE PROBE TAKES NO BODY ARGUMENT AND THIS IS WHY.** The one thing
     * that makes {@see PlatformTexter::alertOperator()} safe to exist at all is
     * that no caller can choose the recipient; a probe that took a `$body`
     * would leave the recipient fixed and hand anybody who could reach it a way
     * to send arbitrary words to the operator's real mobile number, over a path
     * that ignores `sms.enabled` and `messaging.global_halt` by design. The
     * body is a constant, so there is nothing to point anywhere.
     *
     * ⚠️ **"NOTHING IS WRONG" IS THE FIRST THING IT SAYS.** `22`'s outcome
     * language on the one message here whose reader has been trained by every
     * other message on this number to assume the platform is on fire.
     */
    public const string PROBE_HEADLINE = 'GO AI EZ pager test';

    public const string PROBE_SUMMARY = 'Nothing is wrong. Somebody ran the pager test on the platform. '
        .'If this reached you, this platform can page you.';

    /**
     * Refuse a quiet window that is not a quiet window (7580-7599).
     *
     * ⛔ **IT IS CALLED FROM INSIDE `DefaultsRegistry::set()`, WHICH IS WHY IT
     * IS STATIC** — `LegalCanon::refuseIncompleteAskFooter()`'s reason,
     * verbatim: an instance of this class takes a `DefaultsRegistry`, so
     * resolving one to answer a question about a number that has not been
     * written yet would be a container round trip back through the object doing
     * the asking. The guard needs no registry; its whole subject is the value in
     * hand.
     *
     * ⛔ **AND IT IS THERE RATHER THAN ON THE OPS SCREEN BECAUSE THE SCREEN IS
     * ONE DOOR OF SEVERAL.** `assertPreconditionsMet()`'s own docblock settles
     * this: *"an `ArchitectureTest` lint holds only the defaults registry reads
     * or writes a registry store, so a precondition attached anywhere else — an
     * Ops screen, a command, a service — is a precondition the next writer
     * routes around without noticing."* A `min`/`max` slot on the manifest was
     * the other candidate and is refused on 256's terms, in the same place: the
     * manifest has around two hundred entries and no schema field of any kind,
     * three keys have a range worth stating, and two of those three have
     * unrelated derivations — a framework sized for a population that small
     * hides the arguments it was built to carry.
     *
     * ⚠️ **IT REFUSES A NON-INTEGER RATHER THAN COERCING ONE.**
     * `platform_settings.value` is jsonb and `set()` takes `mixed`, so `null`,
     * `false` and `"soon"` are all reachable from a console — and every one of
     * them reads back through `DefaultsRegistry::int()` as `0`, which this
     * class then clamps to one minute. That is a pager ringing every five
     * minutes because somebody cleared a box, and the operator is better told
     * at the moment they clear it than at 3am. **The way back to the seed is the
     * screen's own reset chip**, which writes the reviewed value and never
     * reaches this guard.
     *
     * @throws InvalidArgumentException when the value is not a whole number of
     *                                  minutes inside
     *                                  {@see self::MIN_QUIET_MINUTES} to
     *                                  {@see self::MAX_QUIET_MINUTES}
     */
    public static function refuseUnworkableQuietWindow(mixed $value): void
    {
        if (! is_int($value)) {
            throw new InvalidArgumentException(
                'The quiet window is a whole number of minutes. Clearing this box does not turn '
                .'de-duplication off — it reads back as zero, which rings the same bell on every '
                .'sweep, five minutes apart, until somebody blocks the number.'
            );
        }

        if ($value < self::MIN_QUIET_MINUTES) {
            throw new InvalidArgumentException(
                'A quiet window of '.$value.' minutes would ring the same bell on every sweep. '
                .'The narrowest this may be set to is '.self::MIN_QUIET_MINUTES.' minute.'
            );
        }

        if ($value > self::MAX_QUIET_MINUTES) {
            throw new InvalidArgumentException(
                'A quiet window of '.$value.' minutes is '.(int) round($value / (24 * 60)).' days, and this '
                .'is the only mute the whole pager has: for that long, the same failure recovering '
                .'and breaking again would tell nobody. The widest this may be set to is '
                .self::MAX_QUIET_MINUTES.' minutes (seven days), which is already seven times the '
                .'longest use anybody has stated — one alert a day through a multi-day outage. To '
                .'quieten one noisy check rather than all of them, set that check\'s own threshold '
                .'to zero on this screen; that switches it off and leaves every other bell ringing.'
                // ⛔ ADDED BY THE WAVE-12 INTEGRATOR (7781). Lane D disproved the
                // sentence above for three keys and corrected the manifest's copy
                // of it; this file was lane E's, so the two copies differed for
                // one merge — 7595's shape, in the pair of sentences 7595 was
                // about. Verbatim from D's commit message.
                .' ⛔ That is true of the platform-health checks and of nothing else: three '
                .'other keys carry a threshold on which zero does something other than '
                .'switch a check off, and the operator alert board states, row by row, what '
                .'each one\'s zero actually does.'
            );
        }
    }

    /**
     * Fit a summary into the column **without eating the sentence that says what
     * to do** (9274).
     *
     * ## What this replaces, and what it does not
     *
     * ⛔ **THE WRITE WAS `mb_substr($summary, 0, 300)` — HEAD KEPT, TAIL
     * DROPPED — AND THE TAIL IS WHERE EVERY SUMMARY IN THIS APPLICATION PUTS
     * THE ACTION.** {@see OperatorAlertKind::ScheduledRunOverranLock}'s own
     * docblock says so in as many words (*"the action is the same either way,
     * which is to widen the window in `routes/console.php` or make the command
     * faster"*), and that clause was the part the column removed on every
     * firing of one of its two arms. So the clamp is kept — a sentence two
     * characters over the column is still no reason to swallow an outage alert
     * — and what changes is **which end it takes from**.
     *
     * ⚠️ **THE MIDDLE IS THE SAFE THING TO REMOVE, AND THAT IS AN OBSERVATION
     * ABOUT WHERE THE FIGURES LIVE RATHER THAN ABOUT PROSE.** Counts, rates,
     * windows and durations reach `context`, the `critical` log line and the
     * alert board; the words do not reach any of them. Cutting the middle
     * therefore removes the one part of the message that is stored somewhere
     * else, and keeps the two parts — what happened, and what to do — that are
     * stored nowhere.
     *
     * ⚠️ **AND THE ELISION IS VISIBLE, WHICH THE OLD CUT WAS NOT.** A tail cut
     * simply stops, mid-word, and reads on a handset like a sentence somebody
     * wrote badly rather than like a message with a hole in it.
     *
     * ⛔ **IT IS NOT A LENGTH LINT AND MUST NOT BE READ AS ONE.** Fifteen of
     * the eighteen kinds compose their summary at the raiser out of runtime
     * values — only `PixelMonthlyCapReached`, `PixelIngestRejects` and
     * `GbpBindingMismatched` pass a literal — so nothing static can measure them; this is a property of the one
     * chokepoint every summary passes through, which is why it covers all
     * eighteen. {@see self::fire()} records the fact of a cut, and the one kind
     * whose worst case *is* computable has a lint of its own over the live
     * schedule — see `ScheduledRunMeterTest`.
     *
     * @return string never longer than {@see app(self::class)->summaryLimit()}
     */
    public static function clamp(string $summary): string
    {
        if (mb_strlen($summary) <= app(self::class)->summaryLimit()) {
            return $summary;
        }

        $closing = self::closingSentence($summary);

        if ($closing !== null) {
            $room = app(self::class)->summaryLimit() - mb_strlen(self::ELISION) - mb_strlen($closing);

            // ⛔ **A FLOOR, BECAUSE KEEPING THE ACTION AT THE COST OF THE
            // SUBJECT IS NOT AN IMPROVEMENT.** Every summary here opens with
            // the thing that went wrong — the command name, the vendor, the
            // queue — and a closing sentence long enough to leave less than
            // this has consumed the only words that say what the alert is
            // about. Below the floor the message is better read as truncated
            // than as re-ordered.
            if ($room >= app(self::class)->minOpening()) {
                return rtrim(mb_substr($summary, 0, $room)).self::ELISION.$closing;
            }
        }

        return rtrim(rtrim(mb_substr($summary, 0, app(self::class)->summaryLimit() - mb_strlen(self::ELISION))).self::ELISION);
    }

    /**
     * The last full sentence of a summary, or null when it has none.
     *
     * ⚠️ **THE CAPITAL IS WHAT MAKES THIS SAFE OVER OUR OWN SENTENCES.** These
     * summaries interpolate `number_format()` figures and file names, so `. `
     * alone would find a boundary inside `$1,234.00. ` — and `routes/console.php`
     * has no space after its dot at all. Requiring an upper-case letter, or a
     * quotation mark opening one, after the space is what tells a sentence
     * boundary from a decimal point; a summary that has neither is handed back
     * as null and clamped from the tail, which is the old behaviour and the
     * right fallback.
     */
    private static function closingSentence(string $summary): ?string
    {
        $matched = [];

        // ⛔ **THE BOUNDARY IS `.` + SPACE + CAPITAL, AND EACH OF THE THREE IS
        // LOAD-BEARING OVER *THESE* SENTENCES.** A bare `.` finds
        // `routes/console.php`; a bare `. ` finds `$1,234.00. `; and without the
        // greedy `.*` in front, the *first* boundary is found rather than the
        // last. ⚠️ **A closing sentence may contain a dot** — the one this was
        // written for names a file — so it cannot be matched as "no dots to the
        // end", which is the shape that reads correct and silently declines
        // exactly the sentence worth keeping.
        if (preg_match('/^.*[.!?][ ](?=["\']?\p{Lu})/us', $summary, $matched) !== 1) {
            return null;
        }

        $closing = mb_substr($summary, mb_strlen($matched[0]));

        return $closing === '' ? null : $closing;
    }

    public function __construct(
        private readonly DefaultsRegistry $registry,
        private readonly PlatformMailer $mailer,
        private readonly PlatformTexter $texter,
    ) {}

    public function pushBudgetPerKind(): int
    {
        return $this->registry->int('ops.alerts.push_budget_per_kind');
    }

    public function pushBudgetHours(): int
    {
        return $this->registry->int('ops.alerts.push_budget_hours');
    }

    public function mailPathRepeatHours(): int
    {
        return $this->registry->int('ops.alerts.mail_path_repeat_hours');
    }

    public function summaryLimit(): int
    {
        return $this->registry->int('ops.alerts.summary_limit');
    }

    public function minOpening(): int
    {
        return $this->registry->int('ops.alerts.min_opening');
    }

    public function retentionDays(): int
    {
        return $this->registry->int('ops.alerts.retention_days');
    }

    /**
     * Ring the bell, unless it has already rung recently for this subject.
     *
     * ⚠️ **THIS METHOD IS RE-ENTERED EXACTLY ONE LEVEL DEEP AND NO FURTHER.**
     * {@see self::fire()} calls {@see self::ringFlood()} on the withheld path,
     * which calls this again for {@see OperatorAlertKind::PagerBudgetSpent} —
     * and that kind is refused a flood bell of its own, so the recursion
     * terminates by construction rather than by the dedupe happening to catch
     * it. ⛔ **It is `raise()` rather than `fire()` deliberately**: the inner
     * bell is then contained by the `catch` below and cannot cost the caller
     * the alert it was already raising.
     *
     * @param  string  $subject  What within the kind — a vendor name, a process
     *                           name. Empty where the kind has one subject.
     * @param  string  $summary  One rendered sentence, safe in a text message.
     *                           ⛔ Counts, rates, thresholds and names of ours.
     *                           **Never a customer, a phone number, an address
     *                           or a vendor's raw error string.**
     *                           ⚠️ **PUT THE ACTION IN THE LAST SENTENCE.**
     *                           Over {@see app(self::class)->summaryLimit()} characters and
     *                           {@see self::clamp()} elides the **middle**,
     *                           keeping the opening and the closing sentence —
     *                           so the words at risk are the ones in between,
     *                           and those are the ones whose figures `$context`
     *                           already carries.
     * @param  array<string, scalar|null>  $context  The figures behind it, for
     *                                               an incident review reading
     *                                               this after the window rolled.
     * @param  AlertOrigin|null  $origin  Who is ringing — omit it and the
     *                                    surroundings answer. ⛔ **State it only
     *                                    where the surroundings are wrong**, and
     *                                    there is exactly one such raiser: see
     *                                    {@see AlertOrigin} and
     *                                    {@see PlatformHealthChecks} — and
     *                                    {@see self::ringFlood()}, which
     *                                    inherits the origin of the alert it is
     *                                    announcing rather than reading the
     *                                    surroundings a second time.
     * @return OperatorAlert|null null when the same alert is inside its quiet
     *                            window — which is a decision, not a failure.
     */
    public function raise(
        OperatorAlertKind $kind,
        string $subject,
        string $summary,
        array $context = [],
        ?AlertOrigin $origin = null,
    ): ?OperatorAlert {
        try {
            return $this->fire($kind, $subject, $summary, $context, $origin ?? AlertOrigin::detected());
        } catch (Throwable $e) {
            // The outermost net. Everything below is already contained; this is
            // here because R25 has to be true even for the mistake nobody
            // predicted, and because the caller is a webhook or a sweep whose
            // real work has nothing to do with alerting.
            Log::error('operator alert could not be raised', [
                'kind' => $kind->value,
                'subject' => $subject,
                'exception' => $e::class,
            ]);

            return null;
        }
    }

    /**
     * Whether this exact alert already rang inside the quiet window.
     *
     * ⚠️ **THE READ IS ON `fired_at`, NOT ON `created_at`.** They are the same
     * value today and would stop being the same the first time a row is
     * backfilled or an alert is recorded for a moment other than now — and a
     * dedupe keyed on the wrong timestamp fails open, silently, in the direction
     * of not paging anybody.
     */
    private function alreadyRang(OperatorAlertKind $kind, string $subject, CarbonImmutable $now): bool
    {
        return OperatorAlert::query()
            ->where('kind', $kind->value)
            ->where('subject', $subject)
            ->where('fired_at', '>=', $this->quietSince($now))
            ->exists();
    }

    /**
     * Whether this exact alert has rung at all since a given moment (9320–9327).
     *
     * ⛔ **THE QUIET WINDOW IS NOT THIS QUESTION AND MUST NOT BE STRETCHED INTO
     * IT.** {@see self::quietMinutes()} is one number for the whole pager, capped
     * at seven days, and its own manifest entry says raising it silences *"the
     * platform telling you it is broken"* — including the bell for a dead queue
     * worker, which has one subject and would then ring once and never again. **A
     * check that needs a longer memory than that has to keep it itself**, which
     * is what this method is for: the caller passes its own horizon and this
     * class stays the only place that knows how the rows are shaped.
     *
     * ⚠️ **IT IS A READ AND NOT A SECOND DEDUPE.** Nothing here suppresses
     * anything — {@see self::raise()} behaves exactly as it always has, and a
     * caller that does not ask is unaffected.
     *
     * ⛔ **"THE ONE CALLER TODAY IS `PlatformHealthChecks` ON THE CREDENTIAL
     * BELL" WAS TRUE FOR ONE WAVE AND THERE HAVE BEEN TWO SINCE — AND THE COUNT
     * IS DELIBERATELY NOT RESTATED (9587).** 9370's platform-mail bell is the
     * second, bounded by {@see $this->mailPathRepeatHours()} rather than by
     * `PlatformHealthChecks::CREDENTIAL_REPEAT_DAYS`, and it was raised from a
     * queued job's `failed()` hook on the same day this paragraph was written.
     * **A tally of callers in a docblock is 2505's shape at its cheapest** —
     * `SiteChangeUndoState`'s own *"the count is deliberately not written here
     * any more"* — so what is stated here is the **property a caller has to
     * have**, which does not go stale when a third arrives.
     *
     * ⚠️ **THE PROPERTY: A CALLER ASKS THIS WHEN ITS FAULT IS A STANDING STATE
     * RATHER THAN A SPIKE.** A credential that is absent is absent every time
     * anybody looks; a mail transport that has stopped is stopped every time a
     * message is pushed at it. Re-raising on each pass would be 511 with a
     * handset attached, and the quiet window cannot be stretched to cover it —
     * see the paragraph above. ⚠️ **The horizon belongs to the caller and the
     * two in the tree differ by an order of magnitude for reasons written where
     * each is declared**, which is the whole argument for passing it in.
     *
     * ⚠️ **`fired_at`, ON {@see self::alreadyRang()}'s REASONING**, which is the
     * only reason this is not a one-line query at the call site: the two must not
     * disagree about which timestamp means *"it rang"*.
     */
    public function rangSince(OperatorAlertKind $kind, string $subject, CarbonImmutable $since): bool
    {
        return OperatorAlert::query()
            ->where('kind', $kind->value)
            ->where('subject', $subject)
            ->where('fired_at', '>=', $since)
            ->exists();
    }

    /**
     * How long one kind and subject stays quiet after ringing, as this platform
     * will actually behave (7580-7599).
     *
     * ⛔ **THE ONE PLACE THE CLAMP LIVES, AND IT IS PUBLIC FOR THAT REASON.**
     * {@see self::quietSince()} is the dedupe's floor and {@see self::prune()}
     * is a delete; both reach the registry through here.
     *
     * ⛔ **THIS PARAGRAPH TOLD THE NEXT READER TO MAKE A FIX THAT HAD ALREADY
     * LANDED, AND IT SAID SO FOR A WHOLE WAVE — BOTH READINGS KEPT AND DATED**
     * (7760-7779). It read: *"⚠️ **There is a third copy, in
     * `Admin\OperatorAlertBoard::quietMinutes()`**, which reads
     * `max(1, int(QUIET_KEY))` and whose docblock says it is 'clamped the same
     * way the service clamps it' — true until this method gained a ceiling, and
     * false since. **The fix is one line: call this.** That file belongs to no
     * lane this wave and is deliberately untouched, and the divergence errs in
     * the safe direction meanwhile: the screen prints the alarming number the
     * operator typed while the platform behaves as though they had typed the
     * ceiling. ⛔ **A lint holding 'the clamp lives in one place' is deliberately
     * NOT shipped with this** — it would be red on arrival against a file this
     * slice may not edit, and a lint shipped red is a lint somebody deletes."*
     *
     * ✅ **THE THIRD COPY IS GONE.** 7661 made that method
     * `return $alerts->quietMinutes();` — one merge after the row that reported
     * the divergence, in the same wave — so the instruction survived its own
     * subject by a single commit. ⚠️ **Two independent scouts flagged this
     * paragraph before a wave-12 brief could be written**, which is the measure
     * of how loudly a stale *"the fix is one line"* misleads: it is an
     * invitation to go and do something, in the file that owns the rule, and the
     * something is already done.
     *
     * ✅ **AND THE LINT IS SHIPPED, BECAUSE THE REASON FOR REFUSING IT WAS THE
     * RED FILE AND THAT FILE IS GREEN.** *"Only `OperatorAlerts` may work out how
     * long the pager stays quiet"*, in
     * `tests/Feature/Architecture/ObservabilityTest.php` — no file under `app/`
     * outside this one, the registry's write door and the manifest may name
     * {@see self::QUIET_KEY} at all. **Anything that wants the number calls this
     * method, which is why it is public.**
     *
     * ⚠️ **IT CLAMPS WHERE {@see self::refuseUnworkableQuietWindow()} REFUSES,
     * AND THE TWO COVER DIFFERENT ROWS.** The guard stops a new mistake at the
     * moment it is typed. This stops an **old** one: a value written before that
     * guard existed, or through `tinker`, is unreachable from any write guard
     * and would otherwise keep a production pager muted for exactly as long as
     * it says — and `CLAUDE.md`'s own rule is that no document here may state
     * what a running install has set, so nothing in this repository can rule
     * that row out. ⛔ **A read that threw would be a bell that is a brake**
     * (R25), so it corrects rather than refuses.
     */
    public function quietMinutes(): int
    {
        return min(
            self::MAX_QUIET_MINUTES,
            max(self::MIN_QUIET_MINUTES, $this->registry->int(self::QUIET_KEY)),
        );
    }

    /**
     * The moment the current quiet window opened — the floor of the dedupe.
     *
     * ⛔ **IT IS A METHOD BECAUSE TWO CALLERS HAVE TO AGREE, AND ONE OF THEM
     * DELETES ROWS.** {@see self::alreadyRang()} reads it to decide whether to
     * fire; {@see self::prune()} reads it to decide what may never be removed.
     * A second copy of the clamp living in the pruner is the shape
     * `WatchPlatformHealth::warnIfNobodyIsListening()` had to be talked out of —
     * *"a second copy of the rule sitting where nothing compares the two"* — and
     * here the two disagreeing does not mislead a reader, it **re-arms the
     * pager**.
     *
     * ⚠️ **THE CLAMP ITSELF MOVED OUT TO {@see self::quietMinutes()} AND GAINED
     * A CEILING** (7580-7599). It read `max(1, int(QUIET_KEY))` inline until
     * 2026-08-22; the floor is unchanged and the reason for it is unchanged — a
     * quiet window of zero would make every sweep ring, which is 511's failure
     * arriving as a settings edit rather than as a code change.
     */
    private function quietSince(CarbonImmutable $now): CarbonImmutable
    {
        return $now->subMinutes($this->quietMinutes());
    }

    /**
     * Drop rung bells past the retention horizon — and never one the dedupe is
     * still standing on (7520–7539).
     *
     * ## ⛔ The trap, which is why this is not `PlatformHealth::prune()` copied
     *
     * ⛔ **THE ROW IS THE DE-DUPLICATION AS WELL AS THE RECORD**, which
     * {@see OperatorAlert}'s own docblock states in those words: *"deleting rows
     * here does not clean anything up: it re-arms every alert that was already
     * sent"*. So a retention measured in days and a quiet window measured in
     * minutes **can cross**, and a horizon applied blind would delete the row
     * that is the only thing stopping a still-broken platform texting a real
     * handset every five minutes. That is 511's *"the first outage sends two
     * hundred texts"* arriving as a cleanup job.
     *
     * ⛔ **THIS PARAGRAPH SAID `Admin\PlatformSettings` "PARSES ANY INTEGER AND
     * IMPOSES NO UPPER BOUND AT ALL", AND THAT STOPPED BEING TRUE ON 2026-08-22
     * — BOTH READINGS KEPT AND DATED** (7580-7599). It was correct when written
     * and it was also the finding: the sentence describes a pager an operator
     * could mute for two years from a text box. There is a ceiling now, at
     * {@see self::MAX_QUIET_MINUTES}, refused at the write by
     * {@see self::refuseUnworkableQuietWindow()} and clamped at the read by
     * {@see self::quietMinutes()}.
     *
     * ⛔ **AND THE CLAMP BELOW STAYS — THIS IS THE INVITATION TO REMOVE IT, AND
     * IT IS REFUSED IN WRITING.** With a ceiling of seven days and a horizon of
     * a year, the two can no longer cross on the scheduled path, so the clamp
     * now reads as redundant. It is not. **The ceiling is an outer guard and the
     * clamp is the inner one**, which is 398 exactly: an outer guard refusing
     * first is what makes an inner guard unfalsifiable, and deleting the inner
     * one leaves a green suite and a delete that re-arms the pager the moment
     * either constant moves — {@see $this->retentionDays()} shortened, or the
     * ceiling raised, both of which are one-line edits somebody will make.
     * ⚠️ **`$keepDays` is a caller's parameter rather than a constant read
     * here**, so the crossing is reachable today with entirely ordinary settings
     * and is driven directly by a test that passes a short horizon — the inner
     * guard is falsifiable, and stays that way.
     *
     * ⚠️ **SO THE CUT IS THE EARLIER OF THE TWO MOMENTS, NEVER THE HORIZON
     * ALONE.** Whatever the registry holds, no row inside the current quiet
     * window is reachable from here — which makes the clamp a property of this
     * method rather than a range of values the setting is trusted to stay
     * inside.
     *
     * ## What this is not
     *
     * ⛔ **IT IS NOT A WAY TO REMOVE A ROW, AND 7397(c) IS NOT REVERSED.** That
     * refusal was of an operator-facing delete, in answer to a *fabricated* row:
     * *"the answer to a fabricated row is not to build a way to remove real
     * ones"*, and it still stands — nobody, on any screen, in any command, can
     * choose a row and unmake it. A horizon chooses nothing; it says how long
     * the platform keeps its own operational record, which is the difference
     * between `platform_health_windows` (a horizon) and `audit_log` (append-only
     * for ever). That table is kept because it holds tenant data, personal data
     * and a compliance obligation under rule 42. ⛔ **This one holds none of the
     * three** — which is the whole of why the two answers differ, and is the
     * comparison to make before anybody proposes a horizon on the other.
     *
     * ## ⛔ It is contained, and the first draft was not — R25 by ordering does
     * not survive a prune on the table the alert was written to
     *
     * ⛔ **THIS DOCBLOCK SAID "AND IT IS NOT SWALLOWED" AND AN EXISTING TEST
     * DISPROVED IT INSIDE THE SLICE THAT WROTE IT.** The reasoning was the
     * precedent's, verbatim: `WatchPlatformHealth` calls `PlatformHealth::prune()`
     * **last** *"so a prune that fails cannot cost an alert"*, and ordering was
     * taken to be the containment. ⚠️ **IT IS NOT, BECAUSE THE PRECEDENT PRUNES
     * A DIFFERENT TABLE FROM THE ONE THE ALERT IS WRITTEN TO.** This prunes the
     * same one. So the single failure R25 exists for — `operator_alerts`
     * unwritable — went from *"the alert is lost and the sweep goes on working"*
     * to *"the sweep exits non-zero"*, and `an alert that cannot be recorded does
     * not fail the sweep that raised it` is the test that said so. **A bell may
     * never be a brake, and a broom on the bell's own table is the bell's
     * problem.**
     *
     * ⚠️ **CONTAINED AND LOGGED, WHICH IS NOT THE SAME AS SWALLOWED.** A prune
     * that failed silently for a year would be indistinguishable from one that
     * ran and matched nothing — `sending_health_windows`' shape with a delete in
     * it — so the failure is a `warning` carrying the exception class.
     * `PlatformHealth`'s own writers already do exactly this and say why: the
     * cost of the containment is stated rather than hidden.
     *
     * ⚠️ **ORDERING IS KEPT AS WELL, AND IS NOW THE SECOND LINE RATHER THAN THE
     * ONLY ONE.** It is still called after the sweep, so a slow or locking delete
     * cannot delay an alert that was already earned.
     *
     * ## ⛔ Chunked, because the first run that ever matches will match a year
     * at once (7760-7779)
     *
     * ⛔ **THIS DELETE HAS NEVER MATCHED A ROW AND WILL NOT UNTIL AUGUST 2027.**
     * `operator_alerts` was created on 2026-08-15 and the horizon is a year, so
     * every one of the ~105,000 runs between then and now has been a no-op —
     * which is exactly why an unchunked statement here would go unnoticed. **The
     * first run that ever matches matches everything the platform accumulated in
     * its first year**, in one statement, inside a command on
     * `everyFiveMinutes()` holding a ten-minute overlap lock. ⚠️ **The bound is
     * the de-duplication and it scales with tenants**: four kinds take a business
     * id as their subject, so the ceiling is one row per tenant per kind per
     * quiet window. On a platform of any size that is a multi-minute DELETE, and
     * a run that outlives its lock is `ScheduledRunOverranLock` — this feature's
     * own bell, rung by its own broom.
     *
     * ⚠️ **AND IT IS NOT ONLY THE FIRST RUN.** Any gap in the schedule — a box
     * down for a month, a scheduler stopped and restarted — makes the next run
     * match the whole gap. `PruneTrialOriginClaims`' 500 for
     * `PruneTrialOriginClaims`' reason.
     *
     * ⛔ **THE CLAMP IS OUTSIDE THE LOOP AND MUST STAY THERE.** `$cut` is
     * computed once, so a quiet window that opens *while* the loop is draining
     * cannot move the floor underneath it — and the registry read that could
     * throw stays inside the `try` and outside the delete, which is what stops a
     * partial sweep re-arming the pager.
     *
     * @param  int  $keepDays  {@see $this->retentionDays()}, passed by the caller
     *                         rather than read here, on `PlatformHealth::prune()`'s
     *                         shape.
     * @return int rows removed — `0` where the delete could not run at all,
     *             which is the one number this method cannot tell apart from a
     *             quiet day, and is why the log line exists
     */
    public function prune(int $keepDays, ?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();

        $horizon = $now->subDays(max(1, $keepDays));

        try {
            $quiet = $this->quietSince($now);

            // ⛔ **THE EARLIER OF THE TWO.** Ordinarily the horizon, by a year or
            // so; the moment somebody sets a quiet window wider than the horizon
            // it becomes the quiet window, and the dedupe keeps everything it
            // needs.
            $cut = $horizon->lessThan($quiet) ? $horizon : $quiet;

            $deleted = 0;

            do {
                // Postgres compiles a limited DELETE to
                // `delete from … where ctid in (select … limit N)` — verified in
                // `PostgresGrammar::compileDeleteWithJoinsOrLimit()` rather than
                // remembered — so the bound really is per statement.
                $batch = OperatorAlert::query()
                    ->where('fired_at', '<', $cut)
                    ->limit(self::CHUNK)
                    ->delete();

                $deleted += $batch;
            } while ($batch === self::CHUNK);

            return $deleted;
        } catch (Throwable $e) {
            // ⚠️ **THE REGISTRY READ IS INSIDE THE `try` DELIBERATELY.** If
            // `quietSince()` cannot answer, this method has no idea where the
            // dedupe floor is — and the one thing it may never do in that state
            // is fall back to the horizon alone and delete a row the pager is
            // standing on.
            Log::warning('operator alerts could not be pruned', [
                'keep_days' => $keepDays,
                'exception' => $e::class,
            ]);

            return 0;
        }
    }

    /**
     * The push channels an alert raised right now would reach.
     *
     * ⚠️ **CONFIGURED, NOT DELIVERED, AND THE DIFFERENCE IS NOT PEDANTRY.** This
     * is answerable *before* either send is attempted, which is what lets the
     * log line for every alert carry it; what it cannot say is whether the mail
     * was accepted or the text left the carrier. Those are `emailed_at` and
     * `texted_at` on the row, and they are a different question with a different
     * reader.
     *
     * ⛔ **AND THAT LAST SENTENCE WAS HALF FALSE FROM THE DAY IT WAS WRITTEN —
     * CORRECTED 2026-08-25 (9371).** `texted_at` answered it;
     * **`emailed_at` did not**, because {@see self::email()} called
     * `PlatformMailer::send()`, which is a bare `DeliverPlatformMail::dispatch()`
     * that cannot fail by design (702). So the stamp meant *"a row was written
     * to `jobs`"* and was set identically whether the transport accepted the
     * message or refused the credential three times. ⚠️ **The true sibling is
     * what made it read as considered**: the paragraph is exactly right about
     * the text, and the two columns sit side by side on one row and render
     * identically on one screen. ✅ **`email()` calls `deliverNow()` now**, so
     * both columns mean *the channel accepted it* and the sentence above is
     * true of both. **The paragraph is kept rather than rewritten** (4368) —
     * its distinction between *configured* and *delivered* is unchanged and is
     * still the whole reason this method exists.
     *
     * ⚠️ **AN SMS CHANNEL LISTED HERE IS STILL NOT A TEXT ARRIVING.**
     * `PlatformTexter::alertOperator()` reads {@see self::SMS_KEY} for the
     * recipient — the same row this reads — and then has to find a number to
     * send *from*; on a platform whose every number is retired it returns null
     * and this method has no way to know. Blank is the off switch and is the
     * only state this can report with certainty.
     *
     * @return list<string> `email`, `sms`, in that order — empty when nobody is
     *                      listening on either
     */
    public function pushChannels(): array
    {
        $channels = [];

        if ($this->alertAddress() !== null) {
            $channels[] = 'email';
        }

        if ($this->alertNumber() !== null) {
            $channels[] = 'sms';
        }

        return $channels;
    }

    /**
     * Send a test message down both push channels and report what happened.
     *
     * ⛔ **THIS WRITES NO ROW, LOGS NOTHING AT `critical`, AND RAISES NO
     * ALERT.** Until it existed, the only way to find out whether a page would
     * arrive was to borrow a real {@see OperatorAlertKind} and let
     * {@see self::fire()} do its job — and `fire()` writes to `operator_alerts`
     * **first and unconditionally**, before either channel is attempted.
     * ⛔ **THIS READ "ON A TABLE WITH NO DELETE PATH ANYWHERE IN THIS
     * APPLICATION" UNTIL 2026-08-22** — {@see self::prune()} is that path now,
     * at {@see $this->retentionDays()}. So proving the
     * pager worked left a record of an incident that never happened, for a year:
     * in the board's *still ringing* section for a day, at the top of its
     * severity band ahead of whatever real thing an operator should have opened
     * first, in the kind filter, and ⛔ **inside `ops:alert-channels`' own count
     * of alerts that reached nobody** — so a channel test that FAILED, which is
     * the case somebody runs this to detect, became indistinguishable from a
     * real incident nobody was told about.
     *
     * ⚠️ **DE-DUPLICATION WAS NEVER THE PROBLEM AND IS WORTH SAYING SO.** The
     * quiet window is keyed on `(kind, subject)`, so a borrowed kind with a
     * novel subject suppresses nothing and the test message goes out. The cost
     * was always the row, never a silenced send.
     *
     * ⚠️ **`info`, NOT `critical`.** A log drain keys on `critical` to find
     * incidents, and a probe is the opposite of one. The line is written
     * **after** both attempts rather than before them, which is the reverse of
     * `fire()`'s ordering and for the reverse reason: there the log line is the
     * one channel that cannot fail and has to be written before anything that
     * can, and here the outcome of the attempts *is* the thing worth recording.
     *
     * ⛔ **THE LOG CARRIES THE OUTCOME AND THE EXCEPTION CLASS, NEVER THE
     * EXCEPTION MESSAGE.** A log line is stored and drained; the message is
     * handed back to the caller for a console an operator is reading on their
     * own box. {@see ChannelProbeResult::$detail} carries the split.
     *
     * ⚠️ **`DeliverPlatformMail` IS NAMED IN BACKTICKS AND NEVER IN A `{@see}`,
     * AND THAT IS TWO LINTS RATHER THAN A STYLE.** Pint's
     * `fully_qualified_strict_types` turns a `{@see}` into a real `use`
     * statement, which survives comment stripping — so a docblock reference
     * put this file on `EmailMeteringTest`'s *"the mail transport is reachable
     * only through the mailer that meters"* and on `OutboundTest`'s
     * `deliverNow()` chokepoint, both of which reddened. `GbpTest` says the
     * same thing about the revocation log: an import added only to name a
     * class in a docblock is how an allowlist acquires an entry.
     *
     * ⛔ **THIS PARAGRAPH SAID THE PROBE WAS "ONE HOP SHORT OF WHAT A REAL
     * ALERT DOES" AND THE HOP HAD BEEN GONE SINCE 9371 — CORRECTED 2026-08-28
     * (wave 41 lane D, 11081).** It read: *"{@see self::email()} calls
     * `PlatformMailer::send()`, which **queues** `DeliverPlatformMail` — so an
     * alert email that is going to fail fails inside a worker, where nothing
     * synchronous can be told about it … This calls `deliverNow()` instead."*
     * **{@see self::email()} has called `deliverNow()` since that slice**, and
     * the paragraph twelve hundred lines above this one records the change in
     * detail — so the contrast this one is built on had **both sides equal**,
     * and it was arguing with itself. ⚠️ **4368's shape twice over**: a claim
     * corrected in one paragraph and left standing in another, in the same
     * file, which is what wave 39 recorded and wave 40 hit again one file over.
     *
     * ✅ **WHAT IS TRUE NOW IS SIMPLER AND STILL WORTH SAYING: THIS IS THE REAL
     * EMAIL PATH RATHER THAN A STAND-IN FOR IT.** Both this and
     * {@see self::email()} call `deliverNow()` — the same guards, the same
     * transport, the same synchronous answer — so *"the probe passed"* and
     * *"an alert email would be accepted"* are now one claim rather than one
     * standing in for the other. ⛔ **What it still does NOT prove is that the
     * queue is running.** That has stopped being about this method's own send
     * and is now about everything else a page needs, and it has its own bell
     * ({@see OperatorAlertKind::HeartbeatSilent}). The command that renders
     * this says so where a reader arrives.
     *
     * ⚠️ **THE SMS HALF IS THE REAL PATH EXACTLY**, because
     * {@see PlatformTexter::alertOperator()} is synchronous — including its
     * fall-through to the driver when the number inventory is empty (7252).
     *
     * ⛔ **IT LIVES HERE AND NOT ON A CONSOLE COMMAND BECAUSE IT CANNOT LIVE
     * ANYWHERE ELSE.** `MessagingTest`'s *"only the operator alerter may reach
     * the operator text path"* confines `alertOperator()` to this class and to
     * `PlatformTexter` itself, so a command that texted the operator directly
     * would redden the build — correctly, since that lint is what stops the
     * body being composed from something other than an alert summary.
     */
    public function probeChannels(): ChannelProbe
    {
        $email = $this->probeEmail();
        $sms = $this->probeSms();

        Log::info('operator alert channel probe', [
            'email' => $email->outcome(),
            'sms' => $sms->outcome(),
            // The class, never the message — see this method's docblock.
            'email_failure' => $email->failure,
            'sms_failure' => $sms->failure,
        ]);

        return new ChannelProbe($email, $sms);
    }

    /**
     * ⚠️ **EACH CHANNEL IS CONTAINED SEPARATELY, WHICH IS `fire()`'s RULE FOR A
     * DIFFERENT REASON.** There it is R25 — a broken mailer may not stop the
     * text, and neither may stop the thing being watched. Here nothing is being
     * watched and nothing depends on this; what containment buys is that a
     * channel which throws still lets the *other* channel's answer be reported,
     * and an operator running this on a broken platform needs both answers most.
     */
    private function probeEmail(): ChannelProbeResult
    {
        $address = $this->alertAddress();

        if ($address === null) {
            return ChannelProbeResult::notConfigured('email');
        }

        try {
            $this->mailer->deliverNow($address, new OperatorAlertProbeSent);

            return ChannelProbeResult::handedOver('email');
        } catch (Throwable $e) {
            return ChannelProbeResult::failed('email', $e::class, $this->redacted($e->getMessage()));
        }
    }

    private function probeSms(): ChannelProbeResult
    {
        if ($this->alertNumber() === null) {
            return ChannelProbeResult::notConfigured('sms');
        }

        try {
            $sent = $this->texter->alertOperator(self::PROBE_HEADLINE.': '.self::PROBE_SUMMARY);

            // ⚠️ **NULL IS NOT AN ERROR AND IS NOT "NOT SET" EITHER.** The
            // recipient row is filled in — this method already checked — so a
            // null here means `alertOperator()` declined before the transport,
            // which on a platform holding numbers means every one of them is
            // quarantined, retired or still registering. That state is itself
            // worth being paged about, and on every screen this application has
            // it looks exactly like a blank row.
            return $sent === null
                ? ChannelProbeResult::refused('sms')
                : ChannelProbeResult::handedOver('sms');
        } catch (Throwable $e) {
            return ChannelProbeResult::failed('sms', $e::class, $this->redacted($e->getMessage()));
        }
    }

    /**
     * The exception message with the operator's own address and number removed.
     *
     * ⚠️ **A MITIGATION AND NOT A GUARANTEE, SAID HERE RATHER THAN ASSUMED**
     * (314–316). A transport is free to echo a mangled, quoted or folded form
     * of the recipient that no `str_replace` can find — `550 5.1.1
     * <ops@…>: Recipient address rejected` is the ordinary shape and the
     * easy one, and a header-folded copy is not. What this rules out is the
     * common case, and 7245's *"costs nothing to withhold"* argument does not
     * reach the message itself: `MailNotDeliverable` alone has five causes and
     * only its text says which.
     */
    private function redacted(string $message): string
    {
        $secrets = array_values(array_filter([$this->alertAddress(), $this->alertNumber()]));

        if ($secrets === []) {
            return $message;
        }

        return str_replace($secrets, '[redacted]', $message);
    }

    /**
     * The operator's address, or null where the row is blank.
     *
     * ⛔ **BLANK IS THE OFF SWITCH AND THERE IS DELIBERATELY NO OTHER ONE.** See
     * {@see self::email()} and the seed's own description: a plausible-looking
     * `ops@` default would send every alert into a void that looks configured.
     */
    private function alertAddress(): ?string
    {
        $address = trim((string) $this->registry->stringOrNull(self::EMAIL_KEY));

        return $address === '' ? null : $address;
    }

    private function alertNumber(): ?string
    {
        $number = trim((string) $this->registry->stringOrNull(self::SMS_KEY));

        return $number === '' ? null : $number;
    }

    /**
     * @param  array<string, scalar|null>  $context
     */
    private function fire(
        OperatorAlertKind $kind,
        string $subject,
        string $summary,
        array $context,
        AlertOrigin $origin,
    ): ?OperatorAlert {
        $now = CarbonImmutable::now();

        if ($this->alreadyRang($kind, $subject, $now)) {
            // ⛔ **THE ONE DECISION IN THIS CLASS THAT USED TO LEAVE NO TRACE
            // ANYWHERE** (7580-7599). Every other outcome is recorded: a fired
            // alert writes a row and a `critical` line, a probe writes `info`, a
            // failed prune writes `warning`, and a raise that threw writes
            // `error`. A suppressed recurrence wrote nothing at all — so the
            // difference between *"quiet because nothing is wrong"* and *"quiet
            // because it was told not to speak"* was not recoverable after the
            // fact from any store this platform keeps.
            //
            // ⚠️ **`info`, NEVER `critical`.** A drain keys on `critical` to
            // find incidents, and the whole point of the dedupe is that this is
            // not a new one; writing `critical` here would page through the log
            // exactly the volume the quiet window exists to stop.
            //
            // ⚠️ **AND IT CARRIES THE WINDOW THAT DID THE SUPPRESSING**, which
            // is the figure an incident review actually needs: the row alone
            // says a bell was swallowed, and this says for how long the next one
            // will be too. It is {@see self::quietMinutes()}'s answer rather
            // than the registry's raw value, so a clamped row reports what the
            // platform did rather than what somebody typed.
            Log::info('operator alert suppressed by the quiet window', [
                'kind' => $kind->value,
                'subject' => $subject,
                'quiet_minutes' => $this->quietMinutes(),
            ]);

            return null;
        }

        // Clamped rather than refused. A summary two characters over the
        // column is not a reason to swallow an outage alert — see
        // {@see self::clamp()} for which end it now takes them from.
        $clamped = self::clamp($summary);

        if ($clamped !== $summary) {
            // ⛔ **A CUT USED TO LEAVE NO TRACE ANYWHERE, WHICH IS THE HALF
            // THAT MADE IT SURVIVE** (9274). The `critical` line below logs
            // `$alert->summary` — the **already-cut** value — so a bell whose
            // words did not fit read, in every store this platform keeps,
            // exactly like a bell whose words did fit. One kind's summary had
            // been over the column on every possible firing since the day it
            // shipped, with a green suite over it throughout.
            //
            // ⚠️ **`warning`, ON THE SIBLING ARM'S REASONING** (7594). `info`
            // is for a recurrence of something already reported and `critical`
            // is what a drain keys on to find an incident. This is neither: it
            // is a defect in *us*, discovered while reporting somebody else's,
            // and the action it calls for is somebody shortening a sentence.
            Log::warning('operator alert summary did not fit the column', [
                'kind' => $kind->value,
                'subject' => $subject,
                'limit' => app(self::class)->summaryLimit(),
                'length' => mb_strlen($summary),
            ]);

            // ⛔ **AFTER THE SPREAD, SO A CALLER'S KEY CANNOT MASK IT** —
            // `push_channels`' rule ten lines down, for the same reason. This
            // is the only store in the application that holds the words the
            // column could not: the email is composed from the row, the text is
            // composed from the row, and the log line logs the row.
            $context = [...$context, 'summary_full' => $summary];
        }

        $alert = OperatorAlert::query()->create([
            'kind' => $kind,
            'subject' => $subject,
            'summary' => $clamped,
            'context' => $context,
            'fired_at' => $now,
        ]);

        // ⚠️ **BEFORE EITHER CHANNEL, DELIBERATELY.** `critical` is what a log
        // drain keys on, it needs no credential, no vendor and no network, and
        // it is therefore the only part of this that works on the day the mail
        // account and the SMS driver are both misconfigured — which, on a fresh
        // install, is the ordinary state rather than the unlucky one.
        $channels = $this->pushChannels();

        // ⛔ **DECIDED BEFORE THE LOG LINE AND NOT AFTER IT, WHICH IS 314–316
        // RATHER THAN ORDERING** (7820–7839). `push_channels` is a claim about
        // what this alert is *about to* reach, and it is the announcement of the
        // deaf state that an unattended install depends on. Withholding the push
        // after writing that claim would leave the one line a drain reads saying
        // `email,sms` about an alert that reached neither — a paragraph asserting
        // a protection that had already been declined one statement later.
        $withheld = $origin === AlertOrigin::Request && $this->pushBudgetIsSpent($kind, $now);

        Log::critical('operator alert: '.$kind->headline(), [
            'kind' => $kind->value,
            'subject' => $subject,
            'summary' => $alert->summary,
            ...$context,
            // ⚠️ **AFTER THE SPREAD, SO A CALLER'S CONTEXT KEY CANNOT MASK IT.**
            // This is the announcement of the deaf state — the only one that
            // reaches an unattended production install — and it is worth exactly
            // as much as it is hard to overwrite by accident.
            'push_channels' => $withheld ? 'withheld' : ($channels === [] ? 'none' : implode(',', $channels)),
        ]);

        if ($withheld) {
            // ⚠️ **`warning`, AND THE SIBLING ARM TEN LINES UP SETTLES WHY IT IS
            // NOT `info`.** A quiet-window suppression is `info` because it is a
            // recurrence of something already reported (7594). This is not a
            // recurrence of anything: it is a **new** state — the pager is being
            // rung faster than it can usefully be read, from outside — and the
            // action it calls for is somebody looking at where the traffic is
            // coming from. ⛔ **And still not `critical`**: a drain keys on
            // `critical` to find incidents, and paging through the log about a
            // flood would deliver exactly the volume this is withholding.
            Log::warning('operator alert push withheld: the pager\'s daily budget for this kind is spent', [
                'kind' => $kind->value,
                'subject' => $subject,
                'origin' => $origin->value,
                'pushes_per_kind' => $this->pushBudgetPerKind(),
                'budget_hours' => $this->pushBudgetHours(),
            ]);

            // ⛔ **RECORDED ON THE ROW, BECAUSE TWO NULLS ALREADY MEANT SOMETHING
            // ELSE.** `emailed_at` and `texted_at` both null is how a **broken**
            // channel looks, and `ShowOperatorAlertChannels` counts exactly that
            // on every deploy. Without this stamp a bounded pager would have read
            // as a dead one — 7484's lesson with its sign flipped.
            $alert->forceFill(['push_withheld_at' => CarbonImmutable::now()])->save();

            // ⛔ **AND SOMETHING SAYS SO ON THE ONE CHANNEL THE FLOOD CANNOT
            // SATURATE** — 7839(a), which could not be built in the wave that
            // found it because the enum belonged to another lane. Everything
            // above this line is a record; a record is read by somebody who
            // already decided to look, and the operator this is about is
            // holding a handset that has just gone quiet.
            $this->ringFlood($kind, $origin, $now);

            return $alert;
        }

        $this->email($alert);
        $this->text($alert);

        return $alert;
    }

    /**
     * Page about the pager: one kind has spent its day and has stopped being
     * sent (7839(a), closed at 8120–8139).
     *
     * ## ⛔ Why a bell, when the whole finding is that a bell is being withheld
     *
     * ⛔ **BECAUSE A BOUNDED PAGER AND A FIXED PROBLEM ARE THE SAME SILENCE.**
     * The eleventh push of a kind is withheld on purpose, the handset goes
     * quiet, and quiet is exactly what it does when somebody fixes the thing.
     * **This is the message that says why the other ten stopped**, and it is not
     * available from any screen, because a screen is read by somebody who has
     * already decided to look.
     *
     * ⛔ **8042's REFUSAL DOES NOT REACH IT.** 7862 and 7863 were refused
     * because the pager has one recipient and both would have texted the person
     * who had just pressed the button; **a flood has nobody at the keyboard.**
     *
     * ## What bounds it, and what does not
     *
     * ⛔ **ITS OWN PER-KIND ALLOWANCE, WHICH IS THE MECHANISM RATHER THAN AN
     * EXEMPTION.** {@see OperatorAlertKind::PagerBudgetSpent} is a kind like any
     * other, so {@see self::pushBudgetIsSpent()} bounds it to ten pushes a day
     * exactly as it bounds the kind it is announcing — and 7827's per-kind
     * split is what stops the flood spending this one's allowance too.
     * ⚠️ **The cost is that 7827's *"forty messages a day"* is now fifty**; see
     * that constant's docblock, corrected in place.
     *
     * ⛔ **AND IT DOES NOT SOLVE THE PROBLEM, IT MOVES THE EDGE.** After ten of
     * these the pager is deaf about its own deafness, and nothing rings about
     * that — **deliberately**, because a bell reporting that a bell reporting a
     * bell has stopped is a loop with no reader. {@see self::ringFlood()} refuses
     * to ring about its own kind for that reason. **The residue belongs to the
     * screen**, where `Admin\OperatorAlertBoard`'s *what stopped reaching you*
     * section is true at every volume and needs no push at all.
     *
     * ## ⛔ Three containments, and each one is load-bearing
     *
     * ⛔ **(a) IT REFUSES TO RING ABOUT ITSELF.** Without the first guard a
     * spent budget on this kind would raise this kind, whose push is then
     * withheld, which raises it again — terminating only on the dedupe, after
     * writing rows about nothing.
     *
     * ⛔ **(b) IT ASKS THE DEDUPE BEFORE IT COMPOSES ANYTHING.** In a flood of
     * nine hundred accounts every alert passes {@see self::alreadyRang()} on its
     * own subject and reaches this method, so a count taken here unconditionally
     * would be nine hundred grouped reads inside nine hundred strangers'
     * requests. The pre-check is the **same method** the raise below will ask
     * again — not a second copy of the rule (7661) — and it turns that into nine
     * hundred indexed `exists()` calls and one count.
     *
     * ⛔ **(c) IT CATCHES ITS OWN THROW, LIKE {@see self::email()} AND
     * {@see self::text()}.** {@see self::raise()} contains the inner raise
     * already; what it does **not** contain is a throw from the guard or the
     * count, which would propagate out of {@see self::fire()} and make
     * `raise()` return null for an alert it had already written and stamped.
     * **A bell about the pager may not swallow the alert it is about** (R25).
     */
    private function ringFlood(OperatorAlertKind $flooded, AlertOrigin $origin, CarbonImmutable $now): void
    {
        try {
            if ($flooded === OperatorAlertKind::PagerBudgetSpent) {
                return;
            }

            if ($this->alreadyRang(OperatorAlertKind::PagerBudgetSpent, $flooded->value, $now)) {
                return;
            }

            $since = $now->subHours($this->pushBudgetHours());

            /** @var object{recorded: int|string, subjects: int|string}|null $totals */
            $totals = OperatorAlert::query()
                ->where('kind', $flooded->value)
                ->where('fired_at', '>=', $since)
                ->selectRaw('count(*) as recorded, count(distinct subject) as subjects')
                ->first();

            $recorded = max(1, (int) ($totals->recorded ?? 1));
            $subjects = max(1, (int) ($totals->subjects ?? 1));

            // ⛔ **THE PROVOKING ALERT'S OWN ORIGIN IS PASSED THROUGH, AND
            // AMBIENT DETECTION WAS TRIED FIRST AND IS WRONG.** Only a
            // `Request`-origin alert is ever withheld, so this is `Request` by
            // construction — but {@see AlertOrigin::detected()} answers by
            // asking whether a route is current, and that is a fact about the
            // *surroundings* rather than about the alert. It happens to agree in
            // production and disagrees the moment anything raises a
            // `Request`-origin alert from outside a request, at which point the
            // flood bell would be read as `Platform` and **nothing would bound
            // it at all** — a flood notice with no ceiling is the flood (511).
            // Inheriting is the one spelling that cannot come apart, and it is
            // {@see AlertOrigin}'s own rule: a raiser that knows better says so.
            //
            // ⚠️ **THE FIGURES ARE ROLLING, ON 7605's RULE.** Only the first
            // bell in a quiet window lands, so a count taken per withheld push
            // would be pinned at its first value for the length of the flood;
            // these are re-read each time the dedupe lets one through, so the
            // second text says whether it is still growing.
            $this->raise(
                OperatorAlertKind::PagerBudgetSpent,
                $flooded->value,
                sprintf(
                    '"%s" has already been emailed or texted %d times in %d hours, so it has stopped '
                    .'being sent. %s of that kind have been recorded in that time, about %s different '
                    .'things. Open the alert board.',
                    $flooded->headline(),
                    $this->pushBudgetPerKind(),
                    $this->pushBudgetHours(),
                    number_format($recorded),
                    number_format($subjects),
                ),
                [
                    // ⚠️ The kind's own value, never its headline: this is the
                    // key an incident review filters the board by.
                    'flooded_kind' => $flooded->value,
                    'pushes_per_kind' => $this->pushBudgetPerKind(),
                    'budget_hours' => $this->pushBudgetHours(),
                    'recorded' => $recorded,
                    // ⛔ **THE FIGURE THAT IS THE DIAGNOSIS** (7962). One kind
                    // across nine hundred accounts and nine hundred separate
                    // failures produce the same silence and are different
                    // nights, and this is the only number that tells them apart
                    // on a handset.
                    'subjects' => $subjects,
                ],
                $origin,
            );
        } catch (Throwable $e) {
            Log::warning('the pager could not report its own budget being spent', [
                'kind' => $flooded->value,
                'exception' => $e::class,
            ]);
        }
    }

    /**
     * Whether this kind has already pushed as much as it may in a day.
     *
     * ⛔ **IT COUNTS PUSHES THAT LEFT THE BUILDING, NOT ALERTS THAT FIRED**, and
     * that is what makes the budget mean something. A platform with no address
     * and no number configured pushes nothing, spends nothing and is never
     * withheld — the blank rows are the off switch and this must not turn them
     * into a second one. Equally, an alert whose mail dispatch and text both
     * threw reached nobody and has not spent anybody's allowance.
     *
     * ⛔ **IT DOES NOT ASK WHO CAUSED THE ROWS IT IS COUNTING, AND THAT IS THE
     * SAFE DIRECTION RATHER THAN AN OVERSIGHT.** A platform-origin push of the
     * same kind counts here, so a kind raised from both a sweep and a request
     * reaches its **request** ceiling sooner. Storing an origin per row and
     * filtering on it would let a stranger's ten sit alongside a sweep's ten,
     * which is the direction that pages more and reads better. **The alternative
     * spends a column to make the bound looser.**
     *
     * ⚠️ **THE READ IS ON `fired_at`, LIKE THE DEDUPE'S** — see
     * {@see self::alreadyRang()} for why not `created_at`. `['kind', 'subject',
     * 'fired_at']` leads on `kind`, so this is the same index the dedupe already
     * uses.
     *
     * ⚠️ **AND IT IS NOT ON A HOT PATH, WHICH IS WHY 7485 DOES NOT REACH IT.**
     * That row refused a round trip inside `SendingGuard::refusalFor()` because
     * that method is asked before **every** send; this is asked only past
     * {@see self::alreadyRang()}, so at most once per kind and subject per quiet
     * window, on a request that has already done a `SELECT` and an `INSERT`. ⛔ **A
     * throw here is caught by {@see self::raise()} and the alert is lost**, which
     * is the same containment every other statement in this method sits under and
     * is R25's answer rather than a gap.
     */
    private function pushBudgetIsSpent(OperatorAlertKind $kind, CarbonImmutable $now): bool
    {
        $pushed = OperatorAlert::query()
            ->where('kind', $kind->value)
            ->where('fired_at', '>=', $now->subHours($this->pushBudgetHours()))
            ->where(static function (Builder $query): void {
                $query->whereNotNull('emailed_at')->orWhereNotNull('texted_at');
            })
            ->count();

        return $pushed >= $this->pushBudgetPerKind();
    }

    /**
     * ⚠️ **A MISSING ADDRESS IS THE OFF SWITCH, AND IT IS THE ONLY ONE.** There
     * is deliberately no `ops.alert_email_enabled` beside it: a bell with a
     * switch is a bell somebody switches off, and `CLAUDE.md`'s rule against a
     * new toggle applies to operators as much as to tenants. Blank means no
     * address has been set.
     *
     * ⛔ **THIS DOCBLOCK ENDED "— THE WATCH COMMAND SAYS SO LOUDLY ON EVERY RUN
     * RATHER THAN LETTING A SILENT BELL LOOK LIKE A QUIET PLATFORM", AND THAT
     * WAS FALSE OF PRODUCTION FROM THE DAY IT WAS WRITTEN — CORRECTED
     * 2026-08-21.** The watch command does say so, to `stdout`, from a schedule
     * entry declared `runInBackground()` — which the framework redirects to
     * `/dev/null`, before the documented cron line redirects it again. So the
     * loud announcement was audible only to somebody running the command by
     * hand, which is the one case in which they did not need telling.
     * {@see self::pushChannels()} carries where it is announced now.
     */
    private function email(OperatorAlert $alert): void
    {
        $address = $this->alertAddress();

        if ($address === null) {
            return;
        }

        // ⛔ **THE WORDS THAT WERE WRITTEN, NOT THE WORDS THAT FITTED** (9274).
        // `summary` is bounded at {@see app(self::class)->summaryLimit()} because it has to
        // survive a text message; an email has no such column and no such
        // reader. Where {@see self::clamp()} had to elide, the whole sentence is
        // on the row already — `context.summary_full` — so this costs a read
        // rather than a second argument, and {@see OperatorAlertRaised}'s
        // *"constructed from scalars off the row"* rule is untouched.
        //
        // ⚠️ **THE TEXT MESSAGE CANNOT HAVE THIS AND IS NOT MEANT TO.**
        // `PlatformTexter::alertOperator()` is handed the clamped sentence
        // deliberately: the column exists because a pager text is read on a
        // handset at 3am, and what the clamp now guarantees is that the part it
        // keeps includes the part that says what to do.
        $full = $alert->context['summary_full'] ?? null;

        try {
            // ⛔ **`deliverNow()` RATHER THAN `send()`, AND THE COLUMN BELOW IS
            // WHY** (9371). `send()` is a bare `DeliverPlatformMail::dispatch()`
            // — it cannot fail, by design (702) — so the stamp two lines down
            // meant *"a row was written to `jobs`"* and was set identically
            // whether the transport accepted the message or refused the
            // credential three times and dropped it in `failed_jobs`. **The one
            // reader that matters is `ShowOperatorAlertChannels`' count of
            // alerts that reached no push channel**, which is this platform's
            // own answer to *did anybody hear the bell* — and during the
            // 2026-08-20 mail outage every alert would have read `emailed_at`
            // set. A column that cannot be null in the state it exists to
            // report is 256's vacuity wearing a timestamp.
            //
            // ⛔ **THE ASYMMETRY WITH {@see self::text()} IS WHAT MADE IT
            // INVISIBLE.** `PlatformTexter::alertOperator()` is synchronous
            // in-process HTTP, so `texted_at` has always meant *the carrier
            // accepted it*. Two columns side by side on one row, rendered
            // identically on one screen, meaning two different things — and
            // **the creating migration is the only artefact in the tree that
            // said so**, in a comment nobody greps.
            //
            // ⛔ **AND "HAS ALWAYS MEANT" WAS FALSE ON THE SHIPPED DEFAULT
            // — CORRECTED 2026-08-28 (11460).** It is true of
            // `SMS_DRIVER=infobip` and of nothing else. `LogTexter::send()`
            // returns a `SentText` for a message that reaches nobody — its own
            // docblock says *"IT NEVER FAILS"* — and `.env.example` ships
            // `SMS_DRIVER=log`, so on the default deployment
            // `alertOperator()` handed {@see self::text()} a fabricated
            // acceptance and that method stamped it. **This paragraph is the
            // one that reassured everybody the sibling column was the honest
            // one**, which is why it was the last place anybody looked.
            // ✅ **`PlatformTexter::alertOperator()` now refuses a transport
            // that does not declare `App\Contracts\ReachesRecipients`**, so the
            // claim below is true of both columns rather than of one.
            //
            // ⚠️ **LEVELLED UP RATHER THAN DOWN.** The tidy-minded edit is to
            // weaken `texted_at`'s meaning until the two match; this is the
            // other direction, and both columns now mean *the channel accepted
            // it*.
            //
            // ⛔ **AND IT REMOVES A RECURSION RATHER THAN ADDING A COST.** With
            // the dispatch, an alert about broken mail was queued, failed three
            // times and reached {@see \App\Jobs\DeliverPlatformMail::failed()},
            // which raises that same bell again — terminating on the dedupe,
            // having written three more rows into `failed_jobs` about a mail
            // path that was already down. Called directly, the refusal lands in
            // this method's own `catch` and no job is ever created.
            //
            // ⚠️ **WHAT IT COSTS IS STATED RATHER THAN HIDDEN.** This is now a
            // synchronous SMTP round trip inside `fire()`, which on the
            // `AlertOrigin::Request` raisers is inside an inbound request —
            // plus up to `MailSendRate::MAX_WAIT_SECONDS` of pacing where an
            // operator has set a per-second rate. `text()` has always made a
            // synchronous vendor call on that same path, the push budget bounds
            // those raisers to ten pushes a day per kind, and
            // {@see self::probeChannels()} already proved the shape by calling
            // `deliverNow()` for exactly this reason: *"queued is not an answer
            // to would a page arrive"*.
            $this->mailer->deliverNow($address, new OperatorAlertRaised(
                $alert->kind,
                $alert->subject,
                is_string($full) ? $full : (string) $alert->summary,
            ));

            $alert->forceFill(['emailed_at' => CarbonImmutable::now()])->save();
        } catch (Throwable $e) {
            // ⛔ **THE CLASS ALONE WAS SAFE AND WAS NOT ENOUGH, AND THIS IS THE
            // ONE CATCH IN THE APPLICATION WHERE THAT COSTS THE MOST** (11338).
            // `UnexpectedResponseException` is the class for **every** SMTP
            // refusal, so the class alone cannot separate a `421` an operator
            // waits out from a `554` that will never succeed until somebody
            // changes something in the AWS console. **The code is the field
            // that differs between the two and the field that carries no
            // personal data** — {@see MailFailure}'s own argument, which names
            // this method as the site it was written about and was then not
            // applied to.
            //
            // ⛔ **AND THIS IS THE PLATFORM'S OWN BELL.** `CLAUDE.md` records
            // that a bell whose only clapper is the mail system cannot ring
            // about the mail system (9377); on the fault where that is true,
            // this line is the whole of what anybody gets. **A bell that could
            // not ring owes the operator the reason**, and *"wait"* versus *"go
            // and change something"* is the decision it decides.
            //
            // ⚠️ **NOTHING NEW CAN LEAK BY IT.** `logContext()` carries a
            // message for {@see \App\Exceptions\MailNotDeliverable} only,
            // whose every factory is written in this repository — and the
            // recipient on this path is the operator's own alert address rather
            // than a tenant's or a customer's in any case.
            Log::warning('operator alert email could not be delivered', [
                'kind' => $alert->kind->value,
                ...MailFailure::logContext($e),
            ]);
        }
    }

    /**
     * ⚠️ **THE TEXT IS THE SECOND CHANNEL AND NOT A DUPLICATE OF THE FIRST.**
     * The failure this whole feature exists for is the one nobody is looking at,
     * and half of them — a dead scheduler, a queue that stopped, a mail quota
     * exhausted — are failures that stop email from arriving. A bell whose only
     * clapper is the mail system cannot ring about the mail system.
     *
     * ⛔ **AND THE SECOND CHANNEL WAS SILENTLY THE FIRST ONE'S SHAPE ON THE
     * SHIPPED DEFAULT UNTIL 11460.** `texted_at` is stamped from whatever
     * `PlatformTexter::alertOperator()` returns, and on `SMS_DRIVER=log` that
     * was a `SentText` for a message nobody received — so the column
     * `ShowOperatorAlertChannels` reads to answer *did anybody hear the
     * bell* said yes. ✅ **`alertOperator()` now throws
     * `App\Exceptions\TextNotDeliverable::transport()` on a driver that does
     * not declare `App\Contracts\ReachesRecipients`**, so the refusal lands in
     * this method's own `catch` and the column stays null — which is what makes
     * that count able to report this fault at all.
     *
     * ⚠️ **THE CATCH LOGS THE CLASS AND NOT THE MESSAGE, WHICH IS 11338's
     * SPLIT AND NOT AN OVERSIGHT HERE.** On that refusal the message is where
     * the remedy lives — it names the `SMS_DRIVER` value to change — and the
     * surface that prints it is `ProbeOperatorAlertChannels`, which is
     * read on a box and thrown away. **A log line is stored and drained**, and
     * `TextNotDeliverable`'s carrier arms can echo a vendor's own string; the
     * one place that string is safe to show is the console.
     */
    private function text(OperatorAlert $alert): void
    {
        try {
            $sent = $this->texter->alertOperator(
                $alert->kind->headline().': '.$alert->summary
            );

            if ($sent === null) {
                return;
            }

            $alert->forceFill(['texted_at' => CarbonImmutable::now()])->save();
        } catch (Throwable $e) {
            Log::warning('operator alert text could not be sent', [
                'kind' => $alert->kind->value,
                'exception' => $e::class,
            ]);
        }
    }
}
