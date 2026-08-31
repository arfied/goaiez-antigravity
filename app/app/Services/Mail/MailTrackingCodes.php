<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\MailTrackingCode;
use App\Models\OutreachMessage;
use App\Services\Consent\SendPermit;
use App\Support\Tenancy;
use Illuminate\Database\QueryException;
use RuntimeException;

/**
 * The only writer and the only reader of `mail_tracking_codes` (2097).
 *
 * ⚠️ **THE CODE IS WHAT MAKES A SHARED RELAY WORKABLE, AND IT IS THE HALF OF
 * 2097 MOST EASILY LOST.** The owner's own message describes a platform mailbox
 * used for every client whose own email is not configured; a reply to one of
 * those messages arrives at one mailbox belonging to nobody, from an address
 * that may not be the one we mailed, quoting a subject anybody could forge.
 * **The only thing that survives the round trip unchanged is the address the
 * reply was addressed to**, which is why the code travels in `Reply-To` rather
 * than in a header, a footer or the subject line.
 *
 * ## Eight characters, and what that buys
 *
 * `MailTrackingCodes::ALPHABET` is 32 symbols, so eight characters is 40 bits —
 * about a million million combinations. At a million codes issued, the chance of
 * a random guess hitting a live one is roughly one in a million. **That is a
 * respectable number and it is not the security argument**, because the code is
 * not a secret: it rides in a header through every relay between here and the
 * recipient and back again. The argument is the one in the migration — what a
 * guessed code authorises is *filing a reply against a contact*, never a
 * consent, a suppression or a send.
 *
 * ⚠️ **THE ALPHABET DROPS `I`, `L`, `O`, `U` AND `0`/`1`.** Codes get read aloud
 * to support, typed out of a screenshot and copied out of a mail client's
 * rendering of a long address. Crockford's exclusions are the cheapest way to
 * stop `INVOICE0` and `1NVO1CEO` being two different live codes.
 *
 * ⚠️ **UNIQUENESS IS THE INDEX AND THE RETRY, NOT AN `exists()` CHECK.** Two
 * concurrent sends both pass a lookup and both insert; only the database refuses
 * the second, which is `inbound_messages`' own recorded reasoning about a
 * redelivered webhook.
 */
final class MailTrackingCodes
{
    /**
     * Crockford's base32 without the four confusable letters.
     */
    public const string ALPHABET = '23456789ABCDEFGHJKMNPQRSTVWXYZ';

    public const int LENGTH = 8;

    /**
     * How many times a collision is retried before giving up.
     *
     * ⚠️ **IT THROWS RATHER THAN SENDING WITHOUT A CODE.** A message sent with
     * no code is a message whose reply can never be threaded — it lands in a
     * shared mailbox belonging to nobody and stays there. Failing the send is
     * recoverable; sending an untraceable one is not.
     */
    private const int ATTEMPTS = 5;

    /**
     * Mint a code for one message to one customer.
     *
     * ⚠️ **THE CUSTOMER COMES OFF THE PERMIT AND IS NOT A SEPARATE ARGUMENT**,
     * which is `PlatformMailer::sendToCustomer()`'s own rule about the address
     * applied to the contact: the permit names the customer consent was decided
     * about, and a caller passing a different one would thread a reply onto
     * somebody nobody checked. A permit is also the only thing that reaches
     * this method, because it is the only thing that reaches the send.
     *
     * ⚠️ **REQUIRES A RESOLVED TENANT.** `Tenancy::idOrFail()` rather than a
     * nullable `business_id`: a code that resolves to no business resolves to no
     * tenant, and the row's whole purpose is to establish one.
     */
    public function mint(SendPermit $permit, ?OutreachMessage $message = null): MailTrackingCode
    {
        $businessId = Tenancy::idOrFail();

        for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
            try {
                return MailTrackingCode::query()->create([
                    'code' => $this->generate(),
                    'business_id' => $businessId,
                    'customer_id' => $permit->customerId,
                    'outreach_message_id' => $message?->id,
                    'created_at' => now(),
                ]);
            } catch (QueryException $e) {
                // ⚠️ ONLY A UNIQUE VIOLATION IS RETRIED. Swallowing every
                // QueryException would retry a missing table five times and
                // then report a collision, which is the wrong sentence in the
                // one place somebody is debugging a send that will not go.
                if (! $this->isUniqueViolation($e)) {
                    throw $e;
                }
            }
        }

        throw new RuntimeException(
            'Could not mint a unique mail tracking code in '.self::ATTEMPTS.' attempts. Sending '
            .'without one would produce a message whose reply can never be threaded back to the '
            .'contact, so the send is refused instead.'
        );
    }

    /**
     * The code carried by an address, or null.
     *
     * ⚠️ **PARSES THE LOCAL PART, NOT THE WHOLE ADDRESS.** A reply comes back to
     * `reply+8CHARCODE@goaieasy.net`, and the domain is deliberately not
     * checked here: the mailbox that received the message is what decides the
     * domain, and re-deciding it from configuration would refuse mail that
     * genuinely arrived at our own relay under a hostname somebody changed.
     *
     * ⚠️ **UPPER-CASED BEFORE THE LOOKUP.** RFC 5321 leaves local-part case
     * handling to the receiving server, and mail clients do case-fold addresses
     * in practice. The alphabet is uppercase-only, so folding here costs nothing
     * and a code that came back lower-cased still resolves.
     */
    public function codeIn(string $address): ?string
    {
        $localPart = mb_strstr($address, '@', true);

        if ($localPart === false) {
            $localPart = $address;
        }

        $expected = config('platform_mail.reply.local_part');
        $expected = is_string($expected) && trim($expected) !== '' ? trim($expected) : 'reply';

        $prefix = $expected.'+';

        if (! str_starts_with(mb_strtolower($localPart), mb_strtolower($prefix))) {
            return null;
        }

        $code = mb_strtoupper(mb_substr($localPart, mb_strlen($prefix)));

        return $this->isWellFormed($code) ? $code : null;
    }

    /**
     * The row a code names, or null when nothing does.
     *
     * ⚠️ **THIS RUNS WITH NO TENANT ESTABLISHED, WHICH IS WHY THE TABLE CANNOT
     * BE SCOPED.** The caller establishes one *from* the answer. A code that
     * matches nothing is the ordinary case rather than an attack — a bounce of a
     * bounce, a mailing-list expansion, somebody replying to a message from
     * before this table existed — so it is a null, never an exception.
     */
    public function resolve(string $code): ?MailTrackingCode
    {
        $code = mb_strtoupper(trim($code));

        if (! $this->isWellFormed($code)) {
            return null;
        }

        $found = MailTrackingCode::query()->where('code', $code)->first();

        return $found instanceof MailTrackingCode ? $found : null;
    }

    /**
     * Write down the handle the transport gave this message (6361).
     *
     * ⛔ **THIS IS THE ONLY WAY BACK FROM AN SES EVENT TO A TENANT, AND WITHOUT
     * IT THE EMAIL COMPLAINT TRIP CANNOT FIRE AT ALL.** `outreach_messages` is
     * FORCE row-level security keyed on `business_id`, so a lookup by message id
     * with no tenant established returns nothing however it is written — and a
     * bounce, a complaint or a delivery from SES names a recipient, a message id
     * and nothing that names a business. This row is un-tenanted for exactly
     * that reason, so it is where the mapping can live.
     *
     * ⚠️ **LAST WRITE WINS, WHICH IS THE OPPOSITE OF {@see self::markReplied()}
     * AND IS DELIBERATE.** `replied_at` answers *"did this person ever write
     * back"*, so the first answer is the true one. This column is a **join key**,
     * and its job is to agree with `outreach_messages.provider_msg_id`, which
     * `SendSettlement::settle()` also overwrites on a re-send. A retried
     * delivery job hands the customer a second message with a second SES id;
     * keeping the first would leave the row pointing at a message the tenant no
     * longer has a record of, and the two columns disagreeing is worse than
     * either of two duplicates being the one that is counted.
     *
     * ⚠️ **NO-OP WHEN NOTHING CHANGED**, so a listener that runs twice over one
     * unchanged send writes nothing.
     *
     * ⛔ **THE COLUMN IS `transport_message_id` AND NOT `provider_msg_id`, AND
     * THAT IS LOAD-BEARING RATHER THAN TASTE** (6369). `MessagingTest`'s
     * single-writer lint on `outreach_messages.provider_msg_id` matches text and
     * cannot tell which model an assignment targets, so a same-named column
     * here would have forced that lint to permit **this** file to assign the
     * outreach one too. See the creating migration for the whole argument; the
     * short version is that the two columns hold one string and answer two
     * questions, and only one of them defines a measured population.
     */
    public function recordTransportMessageId(MailTrackingCode $code, string $providerMessageId): void
    {
        $providerMessageId = trim($providerMessageId);

        if ($providerMessageId === '' || $code->transport_message_id === $providerMessageId) {
            return;
        }

        $code->transport_message_id = $providerMessageId;
        $code->save();
    }

    /**
     * The row an SES message id names, or null when nothing does.
     *
     * ⚠️ **THIS RUNS WITH NO TENANT ESTABLISHED, EXACTLY AS {@see self::resolve()}
     * DOES, AND FOR THE SAME REASON.** The caller establishes one *from* the
     * answer. A message id that matches nothing is the ordinary case rather than
     * an attack — platform mail to an account holder is never tracked, a message
     * sent before this column existed has no row, and SES publishes events for
     * anything the account sends — so it is a null, never an exception.
     *
     * ⚠️ **AND A MATCH IS NOT PROOF OF ANYTHING BEYOND WHOSE MESSAGE IT WAS.**
     * `resolve()`'s rule applies unchanged: the lookup decides which rows are
     * visible, and the work that follows happens under RLS with that tenant set.
     */
    public function resolveByTransportMessageId(string $providerMessageId): ?MailTrackingCode
    {
        $providerMessageId = trim($providerMessageId);

        if ($providerMessageId === '') {
            return null;
        }

        $found = MailTrackingCode::query()->where('transport_message_id', $providerMessageId)->first();

        return $found instanceof MailTrackingCode ? $found : null;
    }

    /**
     * The address a reply on this code comes back to.
     */
    public function replyAddress(string $code, string $sendingDomain): string
    {
        $localPart = config('platform_mail.reply.local_part');
        $localPart = is_string($localPart) && trim($localPart) !== '' ? trim($localPart) : 'reply';

        return $localPart.'+'.$code.'@'.$sendingDomain;
    }

    /**
     * Record that a reply came back on this code.
     *
     * ⚠️ **FIRST REPLY ONLY.** A thread produces several replies and the column
     * answers *"did this person ever write back"*; overwriting it on the third
     * message would move the timestamp away from the fact it records. Nothing
     * about the reply itself is stored — see the migration for why.
     */
    public function markReplied(MailTrackingCode $code): void
    {
        if ($code->replied_at !== null) {
            return;
        }

        // `toImmutable()` because the cast on the model is `immutable_datetime`
        // and `now()` is a mutable `Illuminate\Support\Carbon`. Assigning the
        // mutable one works at runtime and is a type mismatch the analyser is
        // right about — the column reads back immutable either way, so a caller
        // holding the model between the write and a re-read would see two
        // different types for one value.
        $code->replied_at = now()->toImmutable();
        $code->save();
    }

    private function generate(): string
    {
        $alphabet = self::ALPHABET;
        $max = mb_strlen($alphabet) - 1;
        $code = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            // ⚠️ `random_int`, NEVER `rand`/`mt_rand`/`Str::random`'s cousins.
            // A sequential or seedable code is enumerable, and an enumerable
            // code lets somebody walk every message this platform has sent.
            $code .= $alphabet[random_int(0, $max)];
        }

        return $code;
    }

    private function isWellFormed(string $code): bool
    {
        return preg_match('/^['.self::ALPHABET.']{'.self::LENGTH.'}$/', $code) === 1;
    }

    /**
     * PostgreSQL's unique-violation SQLSTATE.
     */
    private function isUniqueViolation(QueryException $e): bool
    {
        return $e->getCode() === '23505';
    }
}
