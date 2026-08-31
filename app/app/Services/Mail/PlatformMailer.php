<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Contracts\Mail\ClassifiesUnderCanSpam;
use App\Enums\CanSpamClass;
use App\Enums\OutreachChannel;
use App\Exceptions\MailNotDeliverable;
use App\Jobs\DeliverPlatformMail;
use App\Models\OutreachMessage;
use App\Services\Config\DefaultsRegistry;
use App\Services\Consent\SendPermit;
use App\Support\MailFailure;
use App\Support\Tenancy;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as Notifications;
use LogicException;
use Throwable;

/**
 * The one place this application sends email.
 *
 * ⚠️ **TWO METHODS, TWO AUTHORISATION MODELS, AND THE TYPE SYSTEM IS WHAT KEEPS
 * THEM APART.**
 *
 *   `send()` is *platform* mail — a message from GO AI EZ to somebody who holds
 *   an account with us: a sign-in link, a notice that support was in their
 *   account. The recipient is a `User`, **the account relationship is the
 *   authorisation**, and no consent record exists or should. Asking
 *   `ConsentService` about an account holder would be asking the wrong question
 *   of the wrong table: `consent_records` is keyed on `customer_id`, and the
 *   owner of a business is not one of their own customers.
 *
 *   `sendToCustomer()` is a message to *a tenant's customer*, and it takes a
 *   `SendPermit` (285) — a type only `ConsentService` can mint. A caller who
 *   has not been through the consent gate cannot call it, because they cannot
 *   build the argument.
 *
 * ⚠️ **Decision 705 said the customer path would arrive with its first composer,
 * in the same slice, or not at all** — a path with no caller being the shape
 * this codebase has recorded thirteen times. It arrived with `ReviewInviteEmail`
 * and `ReviewInviteSender`, which is the condition it was written under.
 * **`send()` must never become the shortcut around the other one**: reaching for
 * it with a customer's address is one line, reviews as identical, and skips
 * consent, suppression, the Do Not Call registers and the mini-TCPA windows in
 * a single move.
 *
 * WHY A SERVICE RATHER THAN `Mail::to()` AT EACH CALL SITE. Two reasons, and
 * the second is the one that earns the class:
 *
 *   1. The transport is going to change. `CLAUDE.md` §Vendors is explicit that
 *      outbound mail sits behind a mailer driver "from the first send so that
 *      switch is config, not surgery" — and decision 701 records that
 *      Microsoft's own current guidance points an application sending to
 *      external recipients at Azure Communication Services rather than at
 *      Exchange Online. One chokepoint is what makes that a `.env` edit.
 *
 *   2. **An unconfigured mailer is a silent success**, and a silent success has
 *      to be refused somewhere. `assertDeliverable()` is that somewhere, and it
 *      is worth reading before assuming it is defensive clutter — the state it
 *      refuses is the state this application is in *right now* (700).
 *
 * WHY THE GUARD DOES NOT RUN HERE. `send()` queues; `DeliverPlatformMail` runs
 * the guard. That split is load-bearing and its reasoning is on the job.
 *
 * ## CAN-SPAM, and the one asymmetry it adds between the two methods (T176 P21)
 *
 * Every notification declares itself commercial or transactional
 * ({@see ClassifiesUnderCanSpam}), and an undeclared one is **refused** rather
 * than assumed. A commercial message acquires its opt-out and its footer in
 * `deliverNow()`, from the address it is actually going to.
 *
 * ⛔ **WHICH MEANS `send()` CANNOT CARRY A COMMERCIAL NOTIFICATION AT ALL.** The
 * account-holder path has no permit, no tenant and — the reason that matters —
 * no suppression store: `ConsentService`'s is keyed on a tenant's **customer**
 * and nothing equivalent exists for a `User`. An unsubscribe link there would be
 * a statutory requirement met by a button that writes nowhere, so the send is
 * refused instead. That refusal is the thing that will force the conversation
 * the day somebody writes an owner-facing marketing email.
 */
final class PlatformMailer
{
    public function __construct(
        private readonly MailDrivers $drivers,
        private readonly MailQuota $quota,
        private readonly MailSendRate $rate,
        private readonly MailTrackingCodes $codes,
        private readonly DefaultsRegistry $defaults,
        private readonly PlatformMailContext $context,
        private readonly CanSpamFooters $footers,
    ) {}

    /**
     * Which of CAN-SPAM's two classes a notification declares itself to be.
     *
     * ⛔ **AN UNDECLARED NOTIFICATION IS REFUSED, NOT ASSUMED TRANSACTIONAL**
     * (T176 P21). Assuming would be the cheap answer and it fails in the one
     * direction that costs something: a new marketing notification would go out
     * with no footer and no opt-out, and the omission would be invisible —
     * there is no symptom, because a message with no `List-Unsubscribe` still
     * sends. The lint in `tests/Feature/Architecture/MailTest.php` catches this
     * on the commit that adds the class; this catches it if the lint is ever
     * narrowed, and it is what makes the interface load-bearing rather than
     * advisory.
     *
     * @throws MailNotDeliverable
     */
    private function canSpamClassOf(Notification $notification): CanSpamClass
    {
        if (! $notification instanceof ClassifiesUnderCanSpam) {
            throw MailNotDeliverable::unclassifiedNotification($notification::class);
        }

        return $notification->canSpamClass();
    }

    /**
     * The name this platform sends under, matching `PlatformMailContext`'s own
     * fallback so the `From:` display name and the footer cannot disagree.
     */
    private function platformName(): string
    {
        $name = config('mail.from.name');

        return is_string($name) && trim($name) !== '' ? trim($name) : 'GO AI EZ';
    }

    /**
     * Transports that accept a message and deliver nothing.
     *
     * `log` writes it to `storage/logs` and `array` keeps it in memory for the
     * duration of the process. Both are correct in their place and neither is a
     * transport in the sense that matters. `.env.example` ships `log`, which is
     * Laravel's own default and the reason this list is not hypothetical.
     *
     * ⚠️ **These are transport names, not mailer names, and the difference is
     * the whole reason the check is written the way it is.** A mailer key is
     * whatever the operator called it — `'primary' => ['transport' => 'log']` is
     * valid configuration and matching on the key `log` would sail straight past
     * it. `config/mail.php` happens to name every mailer after its transport
     * today, which is exactly the coincidence that would make a key-based check
     * look correct.
     *
     * ⚠️ `smtp` pointed at nothing is *not* here and must not be — it fails at
     * connect time, loudly, which is already the behaviour we want. This list is
     * specifically the transports that *succeed* at doing nothing.
     *
     * @var list<string>
     */
    public const array UNDELIVERABLE = ['log', 'array'];

    /**
     * Transports that are made of other transports.
     *
     * @var list<string>
     */
    private const array COMPOSITE = ['failover', 'roundrobin'];

    /**
     * The address Laravel's skeleton ships with.
     *
     * Refused by name rather than by pattern: an operator who has set a real
     * address on a real domain has answered the question, and a heuristic that
     * second-guesses them is a heuristic that eventually refuses a correct
     * configuration on a Friday.
     */
    public const string PLACEHOLDER_FROM = 'hello@example.com';

    /**
     * Send one message to one address, later.
     *
     * Returns nothing and throws nothing. Every caller of this method is doing
     * something else — issuing a login link, closing a support session — and
     * none of them should fail because of the mail system's configuration. The
     * refusal happens where it can be seen without being in the way.
     *
     * ## ⛔ "Throws nothing" was a claim about a `.env` line until 9500
     *
     * ⛔ **THE SENTENCE ABOVE WAS TRUE ON `database` AND FALSE ON `sync`, AND
     * DECISION 709 IS WHERE IT WAS PROMISED TO BE NEITHER.** 709 closed 702's
     * account-enumeration oracle by putting `$this->fail($e)` in
     * {@see DeliverPlatformMail::handle()} rather than a throw, and recorded
     * that this made 702 *"a property of the code rather than of one
     * environment's `.env`"*. It did so for exactly one exception type. That
     * `catch` names {@see MailNotDeliverable} and nothing else, and
     * `SyncQueue::handleException()` calls `$job->fail($e)` and then
     * **rethrows** — so on `QUEUE_CONNECTION=sync` a Symfony
     * `TransportException` from a wrong SMTP password walked back out of
     * `dispatch()` into this method's callers. `App\Services\MagicLinkService`
     * — named in backticks rather than `{@see}` because Pint turns one into a
     * `use`, which is `OperatorAlertKind`'s recorded reason for the same choice
     * — returns early and silently for an address with no account and only
     * reaches a send for one that has, so the login page answered **500 for
     * yes and 302 for no**. Measured at the route, not reasoned about:
     * `LoginMethodsTest`'s *"a transport that refuses never reveals whether an
     * account exists"*.
     *
     * ## ⛔ Why the containment is here and not in the job
     *
     * ⛔ **WIDENING `handle()`'s `catch` TO `Throwable` WOULD CLOSE THE ORACLE
     * AND DELETE THE RETRY LADDER, AND THE LADDER IS THE THING THAT ARM EXISTS
     * FOR.** `fail()` skips the remaining attempts — deliberately, for a
     * *configuration* refusal, which retrying cannot fix and which
     * {@see MailNotDeliverable} is precisely the type of. A refused SMTP
     * connection is the opposite: transient, ordinary, and what
     * `DeliverPlatformMail::$tries` and `$backoff` were written for. Catching
     * it in the job would make every transient fault permanent on every driver,
     * to fix a leak that exists on one.
     *
     * ⚠️ **AND `release()` IS NOT THE THIRD OPTION IT LOOKS LIKE.** Under
     * `sync`, `SyncJob::release()` sets a flag nothing reads and `executeJob()`
     * returns — so the message vanishes with **no** `failed()` call, which is
     * the bell 9370 was built to ring. A shape that silences the reporting to
     * protect the caller is worse than the defect.
     *
     * ✅ **So the containment goes where the promise is: at the call site.**
     * `handle()` still throws, the worker still retries on any real driver, and
     * `failed()` still fires on the last attempt. On `sync` the job runs
     * in-process, `SyncQueue` fails it — which reaches `failed()` and rings —
     * and then rethrows into this `catch`, where it stops. **The caller is
     * unaffected on every driver and the ladder is untouched on every driver.**
     *
     * ⛔ **NO BELL IS RUNG FROM HERE AND THAT IS NOT AN OVERSIGHT** (9503).
     * Since 9371 `App\Services\Ops\OperatorAlerts::email()` delivers
     * **synchronously**, so raising from this `catch` would put an SMTP round
     * trip on the unauthenticated login path — for the addresses that have an
     * account and for no others. That is 702 again as a *timing* oracle, which
     * is the one this method's own callers cannot see and a stopwatch can.
     *
     * @see DeliverPlatformMail::failed() the bell, on the arm that has one
     */
    public function send(string $address, Notification $notification): void
    {
        try {
            DeliverPlatformMail::dispatch($address, $notification);
        } catch (Throwable $e) {
            // ⚠️ **TWO POPULATIONS REACH THIS LINE AND ONLY ONE OF THEM HAS
            // ALREADY BEEN REPORTED**, which is why the line is written at all
            // rather than the `catch` being empty.
            //
            //   in-process   `sync` ran the job, it threw, `SyncQueue` called
            //                `fail()` — so `DeliverPlatformMail::failed()` has
            //                already rung `PlatformMailUndeliverable` and this
            //                line is the second record of one fault.
            //
            //   never queued the dispatch itself failed, on any driver: the
            //                `jobs` table unwritable under the `database`
            //                connection, Redis refusing a connection. **No job
            //                ever existed, so `failed()` was never called and
            //                nothing else anywhere records this at all.**
            //
            // ⚠️ **THIS APPLICATION CANNOT TELL THEM APART FROM HERE** — the
            // job that `sync` executed is a deserialised copy, so it cannot
            // report back — and of the two available errors, a duplicate line
            // about a fault that also rang a bell is cheaper than silence about
            // one that did not. ⚠️ **The second population is not an
            // enumeration oracle**: a queue store this application cannot write
            // to fails identically for an address with an account and one
            // without, because the failure is upstream of the lookup's
            // consequences. It is a **lost message**, which is a different
            // complaint with a different remedy.
            Log::error('A platform email could not be handed to the queue, and the caller was not told.', [
                // ⛔ **THE COMMENT REASONED ABOUT `$address` AND THE LINE BELOW
                // IT WROTE `$e` — 11336, and it is 11298 verbatim.** It read:
                // *"The address is deliberately absent, on
                // `DeliverPlatformMail::handle()`'s own reasoning: this is the
                // line that would otherwise put an account holder's email
                // address into the log file for every failed send."* True of
                // the variable it named, and **the argument does not transfer
                // to the throwable**, which is what `getMessage()` was carrying
                // one line down. ⚠️ **A justification narrower than the code it
                // sits on is confirmed by every reader who checks it against
                // the case it names.**
                //
                // ⛔ **AND BOTH POPULATIONS NAMED ABOVE LEAK, NOT ONE — THE
                // SECOND IS THE ONE THAT ONLY EXISTS ON A REAL QUEUE DRIVER**
                // (11337). *In-process*: `sync` ran the job and a Symfony
                // `UnexpectedResponseException` carries the server's verbatim
                // reply. *Never queued*: the `jobs` insert failed under the
                // `database` connection, and
                // `Illuminate\Database\QueryException::formatMessage()` is
                // `…', SQL: '.Str::replaceArray('?', $bindings, $sql)` —
                // **it interpolates the bindings**, and the binding here is the
                // serialised payload, which holds the recipient's address and
                // whatever the notification carries, a sign-in URL included.
                // `TenantDeletion` already refuses to log that class's message
                // for the same reason.
                //
                // ✅ **{@see MailFailure::logContext()} answers all three arms.**
                // A `QueryException` is neither of the two it recognises, so it
                // takes the default and only the class name is written.
                ...MailFailure::logContext($e),
                'notification' => $notification::class,
            ]);
        }
    }

    /**
     * Send to a tenant's customer, which needs authorisation this class cannot mint.
     *
     * ⚠️ **THE PERMIT IS A REQUIRED PARAMETER AND THAT IS THE WHOLE MECHANISM**
     * (285). `ConsentService` is the only thing that can produce a `SendPermit`
     * — private constructor, one factory, an `ArchitectureTest` lint confining
     * `grant()` to `app/Services/Consent/` — so a caller who has not been
     * through the consent gate cannot call this method at all. Not "will fail a
     * check": **cannot construct the argument.** Decision 220 chose the same
     * shape for the place resolver's confirmation.
     *
     * ⚠️ **IT SENDS TO `$permit->identifier`, NEVER TO AN ADDRESS READ OFF THE
     * MODEL**, and `SendPermit`'s own docblock is explicit about why: the
     * identifier on the permit is the one suppression and the compliance
     * registers were checked against. A sender that re-reads `$customer->email`
     * can read a *different* address from the one consent was decided on — a
     * record edited between the permit and the send, or simply a second address
     * — and every gate upstream would have been asked about somebody else.
     *
     * ⚠️ **THE CHANNEL IS CHECKED, because a permit is per channel.** An SMS
     * permit type-checks here perfectly and authorises nothing about email:
     * consent is per `(customer, channel)` and `24` §3.4's registers answer
     * differently for each. This is the one thing the type system cannot say on
     * its own, so it is said here.
     *
     * ⛔ **THIS METHOD DELIBERATELY DOES NOT DEBIT THE EMAIL METER, AND THE
     * OMISSION IS THE DESIGN** (3299). Every send through here is metered — the
     * rate is $20 per 1,000 and the meter is `App\Services\Billing\EmailCredits`
     * — but the debit belongs to the **caller**, for two reasons that are not
     * negotiable. First, `29` §3 rail 1 wants the debit *transactional with the
     * send*, and the thing a debit points at is the `outreach_messages` row the
     * caller writes; this class never sees that row's id being created. Second,
     * an exhausted balance has to degrade rather than throw (2904, 3294), and
     * only the caller knows what its own "a gate said no" looks like — for
     * `ReviewInviteSender` that is `null`, for a campaign it is a typed refusal.
     * ⚠️ **A debit added here as well would charge twice**, and a *check* added
     * here would be 398's outer guard making the caller's inner one
     * unfalsifiable. **What keeps this honest is a lint rather than this
     * paragraph** — `EmailMeteringTest` fails the build when a file in `app/`
     * calls this method without going through the meter, which is the mechanism
     * 314–316 says has to exist before the claim is written.
     *
     * @throws LogicException when handed a permit for another channel
     */
    public function sendToCustomer(
        SendPermit $permit,
        Notification $notification,
        ?string $businessName = null,
        ?OutreachMessage $message = null,
    ): void {
        if ($permit->channel !== OutreachChannel::Email) {
            throw new LogicException(
                "A {$permit->channel->value} permit does not authorise an email. Consent is per "
                .'channel, and so are the Do Not Call and mini-TCPA registers behind it.'
            );
        }

        $this->assertCustomerMailPermitted();

        // ⛔ **ASKED HERE FOR ITS THROW, INSIDE THE CALLER'S TRANSACTION** (T176
        // P21). `ReviewInviteSender` writes the `outreach_messages` row and
        // debits the email meter in one transaction and calls this method from
        // inside it. A commercial message with no `mail.postal_address` set
        // cannot go out — and discovering that in `deliverNow()` three seconds
        // later would leave a committed row saying `Queued`, and a debited
        // credit, for a message the job refuses for ever.
        //
        // ⚠️ **AND `deliverNow()` STILL CHECKS**, because this one is 398's
        // outer guard: it does not run on the account-holder path at all, and
        // deleting the inner one would leave the suite green. Both are driven
        // directly.
        if ($this->canSpamClassOf($notification)->owesOptOut()) {
            $this->footers->postalAddress();
        }

        // ⚠️ **MINTED HERE AND NOT IN THE JOB, BECAUSE THIS IS WHERE THE TENANT
        // IS.** `MailTrackingCodes::mint()` calls `Tenancy::idOrFail()`, and a
        // queued job runs with no tenant established unless it establishes one.
        // Minting on the request side also means a code exists before the
        // message is queued, so a payload that is retried three times carries
        // one code rather than three.
        $code = $this->codes->mint($permit, $message);

        DeliverPlatformMail::dispatch(
            $permit->identifier,
            $notification,
            // ⚠️ **THE TENANT TRAVELS AS AN ID AND THE UNSUBSCRIBE TOKEN DOES
            // NOT TRAVEL AT ALL.** The token seals the recipient's address, and
            // this payload is written to `jobs` and survives into
            // `failed_jobs`; `deliverNow()` mints it after the hop.
            new PlatformMailIdentity($businessName, $code->code, Tenancy::idOrFail()),
        );
    }

    /**
     * Refuse customer mail on a transport that cannot tell us it went wrong.
     *
     * ⛔ **THIS IS OPEN QUESTION H, ENFORCED RATHER THAN REMEMBERED.** `CLAUDE.md`
     * has said since Stage 0 that the first customer-facing send is blocked on
     * bounce and complaint handling; the blocker has now survived five vendor
     * reversals as a sentence in a document, and 2094 records that the Workspace
     * ruling *"makes the blocker harder rather than removing it"*. A sentence
     * cannot survive a sixth. A refusal on the send path can.
     *
     * ⚠️ **IT THROWS SYNCHRONOUSLY, WHICH IS THE OPPOSITE OF WHAT `send()`
     * DOES, AND THE ASYMMETRY IS THE POINT.** 702's account-enumeration oracle
     * is an argument about an *unauthenticated* path reached by typing an
     * address into a login form. This path is reached by an internal job acting
     * on a tenant's behalf, there is no stranger to leak anything to, and the
     * caller has usually just written an `outreach_messages` row inside a
     * transaction — so throwing rolls that row back rather than leaving a
     * message recorded as `Queued` for ever. `ReviewInviteSender` documents the
     * same reasoning for its own rollback.
     *
     * ⚠️ **AND IT IS UNREACHABLE IN PRODUCTION TODAY, WHICH IS 398's HAZARD AND
     * IS STATED RATHER THAN HIDDEN.** `review_invite.email_enabled` seeds false,
     * so the only caller refuses one gate earlier and this one never runs. It is
     * driven directly by its own tests, with that switch on, for exactly that
     * reason — an outer guard refusing first makes the inner one unfalsifiable,
     * and a green suite would say nothing.
     *
     * ⛔ **AND IT IS UNREACHABLE FOR A SECOND REASON SINCE 10040, WHICH IS THE
     * SAME HAZARD ARRIVING FROM THE OTHER SIDE.** {@see self::customerMailRefusal()}
     * is now asked by `ReviewInviteSender` **before** it opens its transaction,
     * so on the one caller in `app/` this throw cannot fire at all — not because
     * a switch is off, but because the same question was already asked
     * non-destructively. **It stays for `sendToCustomer()`'s own sake**: that
     * method is a public chokepoint whose whole mechanism is that a caller
     * cannot get past it, and the next caller (a campaign, dunning) must not
     * inherit an outer gate somebody else wrote. `PlatformMailerTest` drives
     * every arm of it directly, which is the arrangement `deliverNow()`'s own
     * two headroom checks already describe: *both are driven directly.*
     *
     * @throws MailNotDeliverable
     */
    private function assertCustomerMailPermitted(): void
    {
        $refusal = $this->customerMailRefusal();

        if ($refusal instanceof MailNotDeliverable) {
            throw $refusal;
        }
    }

    /**
     * Why customer mail cannot go out right now, or null when it can — 10040.
     *
     * ⛔ **THE ONE IMPLEMENTATION OF THE QUESTION `assertCustomerMailPermitted()`
     * USED TO ASK BY THROWING, AND THAT METHOD NOW ASKS THIS ONE.** It is not a
     * second copy of the three refusals: a guard holding its own twin of the
     * pattern its caller reads is 8460's shape *even when both copies are
     * correct today*, and this one has three arms with three different
     * operator-facing sentences, which is three chances to drift.
     *
     * ⛔ **IT RETURNS AN UNTHROWN EXCEPTION RATHER THAN A BOOLEAN OR A STRING,
     * AND THE ODD SHAPE IS WHAT MAKES THE SINGLE IMPLEMENTATION POSSIBLE.** A
     * boolean loses the reason, which is the only thing an operator can act on;
     * a string cannot be re-thrown as the right refusal, so the assert above
     * would have had to re-decide which of the three it was, from the sentence,
     * which is the drift this exists to remove. The construction is free of side
     * effects — {@see MailNotDeliverable} is a plain `RuntimeException` with a
     * private constructor and no reporting of its own.
     *
     * ## ⚠️ Two callers, two audiences, and neither of them is the customer
     *
     * `ReviewInviteSender` asks before it opens its transaction, so a refusal
     * costs no `outreach_messages` row, no short link, no credit debit and no
     * rollback — and, since it no longer throws out of `send()`, the SMS channel
     * below it is still attempted. **That is the defect this method was written
     * for**: a mail-transport refusal was aborting the whole invite, including
     * the text message that had nothing to do with mail.
     *
     * `Account\Messages` asks so that a tenant reading their message log is told
     * the invites are being held rather than reading a day-one sentence.
     * ⛔ **THE STRING THIS RETURNS IS FOR AN OPERATOR AND MUST NEVER REACH A
     * TENANT** — it names our mailer, our registry rows and open question H.
     * The screen reads only whether it is null; the owner's words are the
     * screen's own, on `MessageLog::statusLabel()`'s rule that a stored vendor
     * string is never what somebody outside this company reads.
     *
     * ⚠️ **IT ANSWERS ABOUT *NOW*, NEVER ABOUT WHAT HAPPENED**, and no caller
     * may present it as history. All three arms are platform conditions — the
     * transport's feedback signal, an unstated ceiling, the customer reserve —
     * so the answer is the same for every tenant and clears for every tenant at
     * once.
     *
     * ⚠️ **IT SHARES A NAME WITH `MailFeedbackSignal::customerMailRefusal()`,
     * WHICH IT CALLS ON ITS FIRST ARM, AND THE COLLISION IS DELIBERATE.** They
     * answer the same question at two widths: the enum knows only about the
     * feedback signal, and this knows about the signal, the ceiling and the
     * reserve. Naming them apart would suggest they were different questions,
     * and the body below is the one place a reader can see that the narrower one
     * is a component of the wider.
     */
    public function customerMailRefusal(): ?MailNotDeliverable
    {
        $signal = $this->drivers->feedbackSignal();

        if (! $signal->permitsCustomerMail()) {
            return MailNotDeliverable::noFeedbackSignal(
                $this->drivers->active(),
                (string) $signal->customerMailRefusal(),
            );
        }

        $ceiling = $this->quota->ceiling();

        if ($ceiling === null) {
            return MailNotDeliverable::ceilingNotStated(
                $this->drivers->active(),
                $this->quota->ceilingKey(),
            );
        }

        // ⚠️ THE RESERVE, NOT THE CEILING. Customer mail stops with headroom
        // left so that a sign-in link still goes out on an account a batch has
        // otherwise consumed — see `MailQuota`, and 2095 for why the alert
        // beside it is the mechanism and the meter is only the explanation.
        if (! $this->quota->hasCustomerHeadroom()) {
            return MailNotDeliverable::customerCeilingReached(
                $this->quota->reading()['used'],
                $ceiling,
            );
        }

        return null;
    }

    /**
     * The active mailer's 24-hour ceiling, refusing when nobody has stated one.
     *
     * ⛔ **ASKED ON BOTH PATHS RATHER THAN ONCE, BECAUSE THEY ARE REACHED
     * SEPARATELY** (4604). `sendToCustomer()` asks synchronously so the
     * caller's transaction rolls back rather than leaving an
     * `outreach_messages` row reading `Queued` for ever; `deliverNow()` asks on
     * the queue, where a job replayed out of `failed_jobs` arrives without
     * having passed the first one. Neither is a duplicate of the other, which
     * is the argument the two headroom checks beside them already make.
     *
     * ⚠️ **THIS METHOD IS `deliverNow()`'s HALF OF THAT PAIR ONLY, SINCE 10040.**
     * The customer half moved into {@see self::customerMailRefusal()} so it
     * could be asked without throwing; 4604's argument is untouched and the two
     * asks are still two asks. **Do not "tidy" one into the other** — the queue
     * side must keep throwing, because a job replayed out of `failed_jobs` has
     * nowhere to return a refusal to.
     */
    private function assertCeilingIsStated(): int
    {
        $ceiling = $this->quota->ceiling();

        if ($ceiling === null) {
            throw MailNotDeliverable::ceilingNotStated(
                $this->drivers->active(),
                $this->quota->ceilingKey(),
            );
        }

        return $ceiling;
    }

    /**
     * Deliver now, having established that delivery is possible.
     *
     * ⛔ **THE CALLER LIST IS GONE RATHER THAN LENGTHENED (8861), AND IT WAS
     * SHORT TWICE — ONCE ON THE DAY IT WAS WRITTEN (11131).** It said *"called
     * by `DeliverPlatformMail` and by nothing else"* while four callers already
     * reached it; wave 40 replaced that with a five-name list (10973); wave 41
     * added **six more in one night** — two content-hold notices, three billing
     * and lifecycle senders, and an event-driven voicemail job, which is a kind
     * the corrected list had no category for. **A list rewritten twice in two
     * waves is not a list that wants a third entry.**
     * ✅ **`php artisan ops:method-callers --suspect` resolves calls rather than
     * matching text and answers about the tree in front of you.**
     * ⛔ **WHAT IS LOAD-BEARING IS THE PROPERTY, NOT THE NAMES**: this method
     * signals a refusal by **throwing**, where `send()` swallows — so it is what
     * a caller must use when it is about to write state asserting the message
     * went out. A scheduled sweep advances a cursor on it, and a caller that
     * treats it as fire-and-forget loses a week of a real tenant's history
     * permanently (10852). ⚠️ **A sole-caller claim in a docblock is a census
     * with no census behind it**, and this paragraph is the evidence.
     * `notifyNow` rather
     * than `notify`: the queue hop already happened, and letting a `ShouldQueue`
     * notification queue itself a second time would put the message back in the
     * queue *past* the guard that just ran.
     */
    public function deliverNow(
        string $address,
        Notification $notification,
        ?PlatformMailIdentity $identity = null,
    ): void {
        $this->assertDeliverable();

        // ⚠️ **THE CEILING IS CHECKED HERE AND NOT ONLY AT THE CALL SITE**, and
        // the two checks are not duplicates. `sendToCustomer()` refuses at the
        // reserve so a batch cannot eat the account; this refuses at the actual
        // ceiling, for every message including platform mail, because a queue
        // that drained overnight can reach it long after the job was queued.
        // Exceeding Google's limit stops the account accepting mail for up to
        // 24 hours, so the send that trips it costs far more than itself.
        $ceiling = $this->assertCeilingIsStated();

        if (! $this->quota->hasHeadroom()) {
            throw MailNotDeliverable::ceilingReached(
                $this->quota->reading()['used'],
                $ceiling,
            );
        }

        $identity ??= new PlatformMailIdentity;

        // ⛔ **THE INNER GUARD, AND IT RUNS FOR EVERY MESSAGE ON EVERY PATH**
        // (T176 P21). `sendToCustomer()` asks the same question earlier so a
        // caller's transaction can roll back, but it is not reached by
        // `send()` — the account-holder path — and it is not reached by a job
        // replayed from `failed_jobs`. This is where a commercial message
        // actually acquires its opt-out, so this is where it is refused if it
        // cannot.
        if ($this->canSpamClassOf($notification)->owesOptOut()) {
            $identity = $identity->with(
                $this->footers->for($address, $identity, $this->platformName()),
            );
        }

        // ⚠️ **THE LAST THING BEFORE THE TRANSPORT, AND IT REFUSES NOTHING**
        // (4432, built at 4648). Every guard above answers *may this go at all*
        // and throws; this one answers *may it go this second* and only ever
        // waits. It sits here rather than in `DeliverPlatformMail` because this
        // is the one choke point every outbound message passes — the same
        // argument `record()` makes below — and a pacer in the job would miss
        // `deliverNow()`'s other reachable path entirely, which is a job
        // replayed out of `failed_jobs`.
        //
        // ⛔ **AND IT IS BEFORE THE SEND WHERE THE METER IS AFTER IT.** The
        // meter records what the vendor accepted; this reserves the right to
        // hand it over at all, and a slot taken after the fact would pace the
        // message behind this one instead of this one.
        $this->rate->pace();

        $this->context->during(
            $identity,
            fn () => Notifications::route('mail', $address)->notifyNow($notification),
        );

        // ⚠️ **AFTER THE SEND, NEVER BEFORE IT.** A message the transport
        // refused was not accepted by the vendor and is not charged against the
        // vendor's ceiling; counting it first would make the meter drift high on
        // the day something is broken, and a meter that over-reports during an
        // incident is a meter that refuses sends during one.
        $this->quota->record();
    }

    /**
     * The same three checks, answered rather than thrown.
     *
     * ⚠️ **THIS IS NOT THE GUARD AND MUST NEVER BE USED AS ONE.**
     * `DeliverPlatformMail` still runs `assertDeliverable()` and still fails the
     * job, exactly as before — the split this class's own docblock calls
     * load-bearing is untouched, because that split exists to keep a
     * *synchronous throw* off the login path (702's account-enumeration oracle),
     * and this method throws nothing at all. **Do not call it from `send()`**:
     * a `send()` that silently returned on a false would turn a failed job an
     * operator can read into no evidence whatsoever.
     *
     * It exists for one narrow kind of caller: **something that writes state
     * asserting a message went out.** `FirstWeekPath` is the first — it records
     * `win_type`, `win_at` and `completed_at` and writes "Sent you an update" to
     * the owner's activity feed, and with the relay unpicked (`CLAUDE.md`
     * §Vendors) every one of those claims was being made about a send that
     * `DeliverPlatformMail` had already failed. Asking first lets that caller
     * decline in the same shape it declines for its own feature switch, rather
     * than advance a state machine on a message nobody received.
     *
     * ⚠️ **TRUE HERE IS NOT A DELIVERY, AND A CALLER THAT READS IT AS ONE IS
     * WRONG.** This answers "is the mail system configured to send at all" —
     * transport, from address, decision 30's domain rule. A refused SMTP
     * connection, a bounce, a rejected recipient and a full mailbox all still
     * happen downstream, and nothing in this application can currently observe
     * any of them (open question H, the relay's bounce webhook). The honest
     * reading is *"not knowably undeliverable"*.
     */
    public function canDeliver(): bool
    {
        try {
            $this->assertDeliverable();
        } catch (MailNotDeliverable) {
            return false;
        }

        return true;
    }

    /**
     * Refuse to pretend.
     *
     * Three checks, in the order a misconfiguration is most likely to take.
     *
     * @throws MailNotDeliverable
     */
    public function assertDeliverable(): void
    {
        $mailer = (string) config('mail.default');

        if (! $this->delivers($mailer)) {
            throw MailNotDeliverable::transport($mailer);
        }

        $from = config('mail.from.address');

        if (! is_string($from) || trim($from) === '') {
            throw MailNotDeliverable::noFromAddress();
        }

        $from = trim($from);

        if ($from === self::PLACEHOLDER_FROM) {
            throw MailNotDeliverable::placeholderFromAddress($from);
        }

        $this->assertNotPrimaryDomain($from);
        $this->assertSendingDomain($from);
    }

    /**
     * Decision 5500, enforced rather than remembered — and 2114 moved.
     *
     * ⛔ **THE SENDING DOMAIN IS NOW `goaieasy.net`, A SEPARATELY REGISTERED
     * DOMAIN, SUPERSEDING 2114's `mail.goaiez.com` — BOTH POSITIONS KEPT AND
     * DATED.** Email domain architecture named `mail.goaiez.com` and the owner ruled for it on
     * 2026-08-11 (2114); on 2026-08-19 the ruling was that a **subdomain of
     * `goaiez.com` is not separation at all**, because reputation is tracked at
     * the organizational domain as well as the exact host, DMARC alignment is
     * organizational, and blocklists list registered domains. **2114 was not a
     * mistake**: it kept decision 30's load-bearing half — never the primary
     * domain — and that is `assertNotPrimaryDomain()` one method up, unchanged
     * and still enforced. What moved is not which subdomain but whether a
     * subdomain counts as separation.
     *
     * ⚠️ **THE VALUE IS A REGISTRY SEED AND NOT A LITERAL, WHICH IS WHAT 2096
     * ASKED FOR AND IS THE REASON THIS METHOD LOOKS INDIRECT.** The question was
     * open between two answers for a day; it has now been answered once and
     * every previous email decision in this repository has been reversed at
     * least once. An Ops row is the cost of the next reversal. A literal would
     * be a deploy, and the deploy would be the moment somebody noticed the DNS
     * records were on the other name.
     *
     * ⚠️ **A SUBDOMAIN OF THE SENDING DOMAIN PASSES AND THE BARE DOMAIN DOES
     * NOT — NO, THE OTHER WAY ROUND.** The address must be *on* the sending
     * domain exactly. `assertNotPrimaryDomain()` allows subdomains because its
     * rule is "not this one"; this rule is "this one", and a permissive suffix
     * match here would accept `goaieasy.net.evil.test` — which is a domain
     * somebody else can register. ⚠️ **5500 makes that hazard the only thing
     * standing between us and a stranger's mail**, where under 2114 the
     * lookalike would at least have had to end in a name we own; the rule was
     * already exact, and this is why it stays exact.
     *
     * @throws MailNotDeliverable
     */
    private function assertSendingDomain(string $address): void
    {
        $configured = $this->defaults->value('mail.sending_domain');

        if (! is_string($configured) || trim($configured) === '') {
            // Nothing configured is nothing to enforce. The seed always answers,
            // so reaching this means an operator blanked the row deliberately —
            // and inventing a domain to enforce against would be worse than not
            // enforcing, which is `assertNotPrimaryDomain()`'s own conclusion
            // about a missing `app.url`.
            return;
        }

        $expected = mb_strtolower(trim($configured));
        $host = mb_strtolower((string) mb_substr($address, (int) mb_strrpos($address, '@') + 1));

        if ($host !== $expected) {
            throw MailNotDeliverable::wrongSendingDomain($address, $host, $expected);
        }
    }

    /**
     * Whether a configured mailer resolves to something that actually sends.
     *
     * ⚠️ **A composite is judged by whether *every* leg is a no-op, not by
     * whether any is.** A `failover` whose last leg is `log` — deliberately
     * swallowing what the real transports could not deliver — is a
     * configuration somebody chose, and refusing it would refuse a working
     * system. A `failover` made entirely of `log` and `array` delivers nothing
     * at all, and that is the case worth catching.
     *
     * ⚠️ **What this does not cover, stated rather than left to be discovered:**
     * a failover whose first leg is `log` never reaches the real transport
     * beneath it and this returns true. Detecting that means modelling
     * Symfony's failover semantics, and a guard that models a vendor's internals
     * is a guard that goes wrong quietly when the vendor changes them.
     *
     * @param  list<string>  $seen  cycle guard — a composite naming itself, or
     *                              two naming each other, is bad configuration
     *                              rather than a reason to recurse forever.
     */
    private function delivers(string $mailer, array $seen = []): bool
    {
        if (in_array($mailer, $seen, true)) {
            return false;
        }

        $transport = config("mail.mailers.{$mailer}.transport");

        if (! is_string($transport) || $transport === '') {
            // An unknown mailer name. Laravel will throw its own InvalidArgument
            // when it tries to resolve this, which is a clearer error than
            // anything invented here — so this is not our refusal to make.
            return true;
        }

        if (in_array($transport, self::COMPOSITE, true)) {
            $legs = config("mail.mailers.{$mailer}.mailers");

            if (! is_array($legs) || $legs === []) {
                return false;
            }

            foreach ($legs as $leg) {
                if (is_string($leg) && $this->delivers($leg, [...$seen, $mailer])) {
                    return true;
                }
            }

            return false;
        }

        return ! in_array($transport, self::UNDELIVERABLE, true);
    }

    /**
     * Decision 30, encoded rather than remembered.
     *
     * *"Email from `reports.goaiez.com`, never the primary domain. Protects
     * primary-domain reputation."* A rule stated in a table is a rule somebody
     * configuring a server at 11pm has not read.
     *
     * ⚠️ **The primary domain is derived from `app.url`, never written here.**
     * Hardcoding `goaiez.com` would make this lint wrong in every worktree,
     * every CI run and every future domain, and a check that is wrong locally is
     * a check somebody deletes. It also means the rule keeps working if the
     * primary domain ever changes, which is the case decision 369 says is live —
     * `goaiez.site` turned out not to be owned.
     *
     * ⚠️ **Subdomains pass, the bare domain and `www` do not.** That asymmetry
     * *is* the rule: `reports.goaiez.com` was the intended answer and it ends
     * with the primary domain, so a naive `str_contains` would have refused the
     * one address decision 30 asked for.
     *
     * ⚠️ **THE SENDING DOMAIN IS NO LONGER A SUBDOMAIN OF THE PRIMARY ONE
     * (5500), SO THIS RULE NOW PASSES TRIVIALLY — AND IT IS KEPT ANYWAY.**
     * `goaieasy.net` shares nothing with `goaiez.com`, so the asymmetry above
     * has no live instance today. It is not decoration: what this method
     * refuses is a from address on whatever `app.url` answers on, and the one
     * way that becomes reachable again is somebody setting `MAIL_FROM_ADDRESS`
     * to the primary domain by hand — which is exactly the 11pm edit decision
     * 30 exists for. `assertSendingDomain()` would also refuse it, and two
     * rules refusing the same address is why both of their tests pin their
     * message rather than the exception class (398's shape).
     *
     * @throws MailNotDeliverable
     */
    private function assertNotPrimaryDomain(string $address): void
    {
        $primary = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! is_string($primary) || $primary === '') {
            // No app URL to compare against. Nothing to enforce, and inventing
            // a domain to enforce against would be worse than not enforcing.
            return;
        }

        $primary = mb_strtolower(preg_replace('/^www\./', '', $primary) ?? $primary);

        $host = mb_strtolower((string) mb_substr($address, (int) mb_strrpos($address, '@') + 1));
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        if ($host === $primary) {
            throw MailNotDeliverable::primaryDomainFromAddress($address, $host);
        }
    }
}
