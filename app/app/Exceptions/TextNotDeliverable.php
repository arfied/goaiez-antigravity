<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Services\Sms\SentText;
use RuntimeException;

/**
 * Thrown when a text message was not handed to a carrier.
 *
 * ⚠️ **THE SAME REASONING AS {@see MailNotDeliverable}, ONE CHANNEL OVER, AND
 * THE FAILURE MODE IT GUARDS IS THE ONE THAT ALREADY HAPPENED HERE.** That class
 * exists because Laravel's `log` mailer accepts every message and returns
 * normally — *"nothing throws, nothing warns, no queue job fails, and every test
 * passes"*. This application now ships an SMS log driver of its own making, with
 * exactly that property by design, so the same silence is one misconfigured
 * environment variable away on a channel where the recipient is somebody else's
 * customer rather than an account holder.
 *
 * So a transport that could not send **throws**, and the driver that
 * deliberately sends nothing is chosen by name in configuration rather than
 * arrived at by an absent credential. A missing Infobip key is
 * `notConfigured()`, not a quiet no-op.
 *
 * ⚠️ **NOTHING IN A MESSAGE HERE MAY COME FROM A VENDOR RESPONSE BODY, AND
 * NOTHING MAY NAME THE RECIPIENT.** `VendorLog`'s rule, which is a privacy rule
 * rather than a tidiness one: a connection exception's message contains the full
 * request URI, and an Infobip error envelope can echo the destination number
 * back. These strings land in `failed_jobs` and in the log, both of which are
 * read by operators and neither of which has a retention policy that contemplates
 * a customer's phone number. Fixed labels and vendor *status names* only.
 *
 * The wording is written for whoever is reading `failed_jobs` at the time.
 *
 * ## Every instance answers one more question: could the carrier already have it?
 *
 * ⛔ **`$mayHaveReachedCarrier` EXISTS BECAUSE THE ANSWER DECIDES WHETHER A
 * QUEUED JOB IS ALLOWED TO TRY AGAIN, AND TRYING AGAIN IS A SECOND TEXT TO A
 * MEMBER OF THE PUBLIC** (7060). Until 2026-08-21 every failure on this
 * transport arrived at every caller as one outcome, so *"the request never left
 * this machine"* and *"the carrier may already have taken the message"* were
 * indistinguishable — and both released the idempotency claim, which is the
 * only thing standing between a retry and a duplicate.
 *
 * ⚠️ **IT DEFAULTS TO `true` ON THE PROMOTED PARAMETER ITSELF**, so a named
 * constructor written next year that does not think about this gets the safe
 * answer. Every `false` in this file is an argument written at its own
 * constructor, and each one is a *structural* fact — a throw that happens
 * before any socket exists, or a per-message refusal the vendor itself named —
 * never an inference from a third party's wording.
 *
 * ## And every instance that could have one carries the handle we put on the wire
 *
 * ⛔ **`$sendHandle` IS THE ONLY THING THAT MAKES *"WE DO NOT KNOW"*
 * ANSWERABLE** (7066, 7192(d), built at 7360). `mayHaveReachedCarrier` being
 * `true` is a question, and until this property existed nothing could ask it:
 * the transport gave the vendor no identifier of ours, so `GET /sms/3/logs`
 * had nothing to match on and every unknown outcome was unknown for ever. The
 * exception is where the handle has to travel because **the exception is the
 * only thing that survives the failure** — `PlatformMessageSender::transact()`
 * rolls the `outreach_messages` row, the credit debit and the `SendKey` claim
 * back on any throw, deliberately, so the object being thrown is the last
 * remaining witness that this send was ever attempted.
 *
 * ⚠️ **NULL IS THE HONEST ANSWER ON MOST ARMS AND IS NOT AN OMISSION.** Four
 * constructors are raised before a request body exists (`noBaseUrl`,
 * `foreignHost`, `noSender`, `notConfigured`), one asserts the request never
 * left the machine (`neverLeft`) and one is the vendor naming the message as
 * refused (`notAccepted`). None of the six has anything to ask about, and a
 * parameter on them would be 272's shape: a value with no writer that reads as
 * though somebody could use it.
 *
 * ⚠️ **IT IS OURS, NOT THE CARRIER'S, AND THE TWO MUST NOT BE CONFLATED.**
 * `SentText::$providerMessageId` is what the vendor answered; this is what we
 * offered. On the failure path the vendor answered nothing at all, which is the
 * whole problem, and a property named for the vendor would be the fourth
 * interpretation this class's other flag is careful about.
 *
 * ⚠️ **THE NAME IS NOT `mayHaveBeenSent`, WHICH IS THE SAME PROPERTY ONE VENDOR
 * OVER** (`GbpRequestFailed`, 7000). That spelling is pinned by a lint in
 * `tests/Feature/Architecture/GbpTest.php` to exactly two readers, and reusing
 * it here would redden that build with no defect behind it. **Two spellings for
 * one idea is a real cost and it is written down rather than hidden** — 7064
 * records the consolidation as owed, and the reason it was not done inside this
 * slice.
 *
 * ⚠️ **`{@see}` IS DELIBERATELY NOT USED FOR THE CLIENT, AND 7009 IS WHY.**
 * Pint's `fully_qualified_strict_types` promotes a fully-qualified `@see` tag
 * into a real `use` statement, and `MessagingTest`'s *"only the platform texter
 * reaches a transport"* lint reads imports as code — so a cross-reference
 * written the obvious way turns this file into a transport caller and fails the
 * build with `composer lint` and `composer stan` both green. The class is named
 * in prose.
 */
final class TextNotDeliverable extends RuntimeException
{
    /**
     * @param  bool  $mayHaveReachedCarrier  ⛔ **`true` MEANS *"NOT POSITIVELY
     *                                       KNOWN TO HAVE STAYED HERE"*, NEVER
     *                                       *"IT WAS SENT"* — the property is
     *                                       named for the direction it fails
     *                                       in. Read it as a licence to retry
     *                                       only when it is `false`; the
     *                                       negation at a call site would read
     *                                       as a claim this flag cannot make.
     */
    private function __construct(
        string $message,
        public readonly bool $mayHaveReachedCarrier = true,
        /**
         * The id this application put on the wire for this message, when there
         * was one — {@see self::$sendHandle}'s paragraph in the class docblock.
         */
        public readonly ?string $sendHandle = null,
    ) {
        parent::__construct($message);
    }

    /**
     * The account's base URL is missing, so there is no host to call.
     *
     * ⚠️ Deliberately not a guessable default. `config/services.php` says why:
     * Infobip issues each account its own host, and *"a guessed host either
     * fails to resolve or, worse, resolves to somebody else's account
     * boundary."*
     */
    public static function noBaseUrl(): self
    {
        return new self(
            'No Infobip base URL is configured, so there is nowhere to send. Set INFOBIP_BASE_URL to '
            .'the account-specific host on your Infobip portal home page, with its scheme — it looks '
            .'like https://xxxxx.api-us.infobip.com, and the region segment is part of it. There is '
            .'no default and there must not be one. Then re-run `composer deploy`.',
            // Thrown before `baseUrl()` returns, so no socket was opened. This
            // is provable from the control flow rather than from a vendor code.
            mayHaveReachedCarrier: false,
        );
    }

    /**
     * The configured base URL is not a URL, so no host could be read from it.
     *
     * ⛔ **THIS EXISTED AS A BRANCH AND NOT AS A MESSAGE UNTIL 2026-08-24, AND
     * THE BORROWED SENTENCE COST A PRODUCTION MISDIAGNOSIS** (9120). The
     * unparseable case threw `foreignHost()`, so a schemeless
     * `yp96dp.api-us.infobip.com` — the real production host, correct in every
     * respect but its scheme — was reported as *"not an Infobip host"*. The
     * host was impeccable; there was no host, because `parse_url()` reads a
     * schemeless string as a **path**. Two readers in a row concluded the guard
     * was broken before reading it.
     *
     * ⚠️ **THE SHAPE IS WHAT GENERALISES**: a guard with two refusal branches
     * and one sentence between them names the wrong cause on whichever branch
     * did not write the sentence — and it is *more* misleading than no message,
     * because it is specific and confident. The guard was right both times.
     *
     * ⚠️ **`noBaseUrl()`'s guidance taught the failing shape**, illustrating the
     * host as `xxxxx.api-us.infobip.com` with no scheme. Corrected in the same
     * change: an example an operator copies is an instruction.
     */
    public static function unparseableBaseUrl(string $base): self
    {
        return new self(
            "The configured Infobip base URL [{$base}] is not a URL — no host could be read from it, "
            .'so there is nothing to check and nowhere to send. The usual cause is a missing scheme: '
            .'INFOBIP_BASE_URL must start with https:// and look like https://xxxxx.api-us.infobip.com. '
            .'This is not a complaint about the host, which may be perfectly correct. '
            .'Then re-run `composer deploy`, because a credential edited after the config cache is '
            .'built reads back null.',
            // Thrown before the suffix comparison and long before any socket.
            mayHaveReachedCarrier: false,
        );
    }

    /**
     * The configured base URL is not an Infobip host.
     *
     * ⚠️ **THE ONE CONSTRAINT THAT CAN BE PLACED ON A PER-ACCOUNT HOST**, and it
     * is a suffix rather than a pattern for a reason this project learned the
     * expensive way twice. Decision 431 recorded that `api.infobip.com` was a
     * guess; 1593 recorded that the *real* production host is
     * `yp96dp.api-us.infobip.com` — **region-segmented**, so a tightened
     * `.api.infobip.com` check would have refused a correct configuration.
     * Anything narrower than the registrable domain is a guess about somebody
     * else's infrastructure.
     */
    public static function foreignHost(string $host): self
    {
        return new self(
            "The configured Infobip base URL points at [{$host}], which is not an Infobip host. "
            .'INFOBIP_BASE_URL must end with .infobip.com — it carries our API key and our '
            .'customers\' phone numbers, so it is refused rather than dialled.',
            // Refused rather than dialled — the method's own last word, and the
            // reason nothing can have reached anybody.
            mayHaveReachedCarrier: false,
        );
    }

    /**
     * No sender was configured, so the carrier has nothing to send *from*.
     */
    public static function noSender(): self
    {
        return new self(
            'No Infobip sender is configured. Set INFOBIP_SENDER to the number the 10DLC campaign was '
            .'registered against; a message sent from an unregistered number is filtered by the carrier '
            .'rather than refused by us, which looks like delivery and is not.',
            // Thrown while the request body is being built, before the post.
            mayHaveReachedCarrier: false,
        );
    }

    /**
     * The bound SMS driver cannot carry a message off this platform.
     *
     * ⛔ **`MailNotDeliverable::transport()`'s TWIN, AND THE REASON IT EXISTS IS
     * THAT IT DID NOT** (11300). `PlatformMailer::UNDELIVERABLE` refuses a `log`
     * or `array` mail transport structurally, and the SMS half had no
     * equivalent: `LogTexter::send()` answers every call with a `SentText`
     * carrying `LOG_DRIVER_NOT_SENT`, so the two channels joined into one
     * boolean by `EscalateUrgentThreadJob::page()` answered a non-delivering
     * deployment in opposite ways — mail refused, SMS said yes, and the union
     * of a real answer and a constant `true` is `true`.
     *
     * ⚠️ **IT NAMES THE DRIVER AND NOT THE CLASS**, so an operator reads the
     * `.env` value they would change. The class name would be the accurate
     * thing and the useless one.
     */
    public static function transport(string $driver): self
    {
        return new self(
            "The SMS driver [{$driver}] reaches nobody, so this message was not sent. It does not "
            .'implement App\Contracts\ReachesRecipients; set SMS_DRIVER to a driver that does.',
            // Raised before any request is built, on a driver that has no
            // carrier behind it at all.
            mayHaveReachedCarrier: false,
        );
    }

    /**
     * The credential is absent.
     *
     * Distinct from `noBaseUrl()` on purpose: they are two different missing
     * things with two different places to fix them — one is `.env`, the other is
     * Ops → Platform → Credentials (doc `38` D-149).
     */
    public static function notConfigured(string $reason): self
    {
        return new self(
            "Infobip is not configured to send: {$reason}",
            // ⚠️ **THE MOST LOAD-BEARING `false` OF THE FOUR.** A missing
            // credential is the likeliest failure on a fresh install, and it is
            // raised by `request()` before a `PendingRequest` exists at all. If
            // this were the safe default the first misconfigured deployment
            // would burn every send's idempotency claim on its first attempt
            // and lose messages that had provably never left the machine.
            mayHaveReachedCarrier: false,
        );
    }

    /**
     * The request did not complete and **this application cannot say whether
     * the carrier took the message.**
     *
     * ⛔ **THE WORDING CHANGED ON 2026-08-21 AND THE OLD ONE WAS A CLAIM THE
     * CODE COULD NOT MAKE** (7060). It read *"Infobip could not be reached
     * ({reason}). The job may retry."* — a sentence that lands in
     * `automation_runs.error` and in `failed_jobs`, that an operator reads as
     * *"nothing went"*, and that is false of every timeout after the carrier
     * accepted the submission. **The reason string is unchanged** so a reader
     * diffing an old row against a new one sees the same token for the same
     * outcome; only the sentence around it stopped over-claiming.
     *
     * ⛔ **THE HALF OF THAT SENTENCE THAT LANDS IN `automation_runs.error` IS
     * GONE SINCE 11330, AND IT IS A REAL LOSS RATHER THAN A STALE
     * SENTENCE** (11347, 11457). That column now holds the exception class, so
     * the careful wording reaches `failed_jobs.exception` **only** — and an
     * operator reading the run row sees `TextNotDeliverable` with no way to
     * tell this arm from `noSender()` or `rejected()`. ⚠️ **The safe unit is
     * the FACTORY and not the class**, and nothing in this application can ask
     * which factory built an exception, so allowlisting this class would
     * readmit the arms that quote a vendor. **The gap is named here rather
     * than closed.**
     *
     * ⚠️ **THIS IS THE DEFAULT ARM AND IT KEEPS THE DEFAULT FLAG.** Anything
     * `InfobipClient` cannot positively place in its
     * never-left allowlist arrives here, so a Guzzle upgrade that empties the
     * handler context, a message format that changes, or an exception a fake
     * raised with no cause attached all land on *"we do not know"*.
     */
    public static function unreachable(string $reason, ?string $handle = null): self
    {
        return new self(
            "Infobip did not answer ({$reason}), so this send has no outcome: the message may "
            .'already be with the carrier. It is not retried automatically.',
            sendHandle: $handle,
        );
    }

    /**
     * The request provably never left this process.
     *
     * ⛔ **THE ONLY CONSTRUCTOR HERE THAT ASSERTS A NEGATIVE ABOUT THE WIRE, AND
     * IT IS ONLY AS GOOD AS THE LIBCURL CODE BEHIND IT** (7061). Its one caller
     * is the classifier in `InfobipClient`, and a lint
     * in `tests/Feature/Architecture/MessagingTest.php` keeps it that way: a
     * caller that has not read an errno has nothing to base the assertion on,
     * and the method name reads right enough that somebody will call it anyway.
     * **Getting it wrong is a second text to a member of the public.**
     */
    public static function neverLeft(string $reason): self
    {
        return new self(
            "Infobip was never reached ({$reason}), so nothing was sent and this send may be tried "
            .'again.',
            mayHaveReachedCarrier: false,
        );
    }

    /**
     * The carrier answered and refused.
     *
     * ⚠️ **A 4xx IS A REFUSAL OF THE ENVELOPE AND A 5xx IS AN UNKNOWN, AND THE
     * VENDOR'S OWN DESIGN IS WHAT SPLITS THEM** (7062). Infobip reports a
     * refused *message* inside a 200 — see {@see self::notAccepted()} — so a
     * non-2xx is the request never becoming a message at all. Its OpenAPI
     * specification documents exactly four failure responses for
     * `POST /sms/3/messages`: **400, 401, 403 and 500** (raw specification
     * `https://api.infobip.com/platform/1/openapi/sms`, version `3.222.1`,
     * `x-generatedAt` 2026-08-20T08:26:36Z, fetched 2026-08-21). The three
     * client errors are a malformed body, a bad key and a forbidden sender:
     * nothing was submitted, and a retry is both safe and the only thing that
     * recovers a **429**, which the specification does not document but which a
     * rate limiter answers with.
     *
     * ⛔ **A 5xx IS NOT IN THAT SET.** A server error may be raised anywhere
     * behind the edge, including after the submission was durable, so it keeps
     * the safe default and is not retried.
     *
     * @param  int  $status  The HTTP status. Not the body — see the class docblock.
     */
    public static function rejected(int $status, ?string $handle = null): self
    {
        return new self(
            "Infobip refused the message with HTTP {$status}.",
            mayHaveReachedCarrier: $status >= 500,
            // ⚠️ **CARRIED ON BOTH ARMS EVEN THOUGH ONLY THE 5xx ONE CAN USE
            // IT.** A 4xx says nothing was submitted, so nothing will ever ask
            // about it — but the flag and the handle are set from two different
            // facts, and a handle withheld on the arm that happens not to need
            // it today is a second place the two could stop agreeing.
            sendHandle: $handle,
        );
    }

    /**
     * The carrier accepted the message and named a state that is not a send.
     *
     * ⚠️ **A 200 IS NOT AN ACCEPTANCE.** Infobip answers per-message inside a
     * 200 envelope: a rejected destination comes back with `status.groupName`
     * of `REJECTED` or `UNDELIVERABLE` while the HTTP status stays 200. Treating
     * the transport code as the answer is how a blocked number reports as sent.
     *
     * `$groupName` is the vendor's own status vocabulary — a fixed enumeration,
     * not free text and not derived from anything the recipient supplied.
     */
    public static function notAccepted(string $groupName): self
    {
        return new self(
            "Infobip accepted the request and did not accept the message ({$groupName}).",
            // The vendor named this message and said it was refused, so it is
            // not on its way to a handset. This is the one arm where a *200*
            // establishes a negative, and it does so because the refusal is
            // per-message rather than per-request.
            mayHaveReachedCarrier: false,
        );
    }

    /**
     * A success we cannot read.
     *
     * Refused rather than papered over, because {@see SentText}'s
     * message id is the only handle slice 3's delivery receipt can match on.
     *
     * ⛔ **THIS ARM IS A DELIVERED MESSAGE WE CANNOT NAME, AND IT KEEPS THE
     * SAFE DEFAULT FOR THAT REASON** (7063). It is reached from
     * `InfobipClient::accepted()` — **past** the HTTP
     * check and past the per-message status check — so the carrier answered
     * 200 and did not refuse the destination. The message is at Infobip; the
     * only thing missing is the handle. Retrying it sends a second one, and
     * until 7060 that is exactly what happened: this was the second
     * double-send path on this transport and nothing had named it.
     */
    public static function unreadable(string $reason, ?string $handle = null): self
    {
        return new self(
            "Infobip returned a response this application could not read ({$reason}).",
            sendHandle: $handle,
        );
    }
}
