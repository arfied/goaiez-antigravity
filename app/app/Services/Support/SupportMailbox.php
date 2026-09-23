<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Exceptions\GmailHistoryUnavailable;
use App\Exceptions\GmailMessageUnreadable;
use App\Jobs\PollSupportMailboxJob;
use App\Models\MailInboxCursor;
use App\Services\Mail\GmailApiClient;
use App\Services\Mail\GmailInbox;
use App\Services\Mail\MailReplyRouter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reading the goaiez **support** mailbox — T176 §3's *"Workspace internal-app
 * pull"*.
 *
 * ## What already existed, so that the next reader does not re-derive it
 *
 * T176 §3 says the support desk *"exists with nothing feeding it — no Gmail
 * poll/push"*. Half of that was already out of date when it was written:
 * `GmailApiClient`, `GmailInbox`, `GooglePushTokenVerifier`,
 * `IngestGmailPushJob` and `mail_inbox_cursors` all shipped on 2026-08-12. What
 * they feed is {@see MailReplyRouter} — a *marketing reply*
 * moving an `outreach_messages` row to `Replied` — and they cannot feed a
 * support desk, because the relay account holds `gmail.metadata` and under that
 * scope a message body cannot be fetched at all. **A support ticket with no body
 * is not a support ticket.** That, and not the absence of a Gmail client, is
 * what was missing.
 *
 * ## Three ways this could have gone wrong, and where each is refused
 *
 * ⛔ **ONE MAILBOX, TWO CONSUMERS.** `mail_inbox_cursors` is keyed
 * `(mailer, mailbox)` so that a second read path cannot silently share a
 * bookmark — but that only helps when the names differ. Point
 * `platform_mail.gmail.support.mailbox` at the relay and this class and
 * `IngestGmailPushJob` would each advance the other past mail it never saw,
 * losing it with nothing anywhere saying so. {@see self::isEnabled()} refuses
 * to run in that state, and it is the first thing it checks.
 *
 * ⛔ **A SECOND ANSWER TO "WHERE WERE WE".** This class is the only writer and
 * the only reader of its own cursor row, exactly as `GmailInbox` is of the
 * relay's, and `MailTest`'s chokepoint holds both there by name.
 *
 * ⛔ **A TENANT'S OWN MAILBOX.** This is the platform's support account (2072).
 * Nothing here touches the token vault, and `MicrosoftGraphService` and tenant
 * mailbox OAuth remain a different feature entirely.
 *
 * ## What is deliberately not built
 *
 * ⛔ **NO `users.watch`, SO THIS IS A POLL AND NOT A PUSH.** The relay's own
 * docblock records that a watch cannot be registered until a Pub/Sub topic
 * exists and this deployment has none — so a support path built on push would
 * be inert on arrival. A five-minute poll costs two quota units per empty run.
 *
 * ⛔ **NO GENERIC IMAP OR POP3 POLLER.** T176 §3 asks for one beside this.
 * PHP 8.4 unbundled `ext/imap` to PECL, it is not installed here, and no IMAP
 * client is in `composer.json` — so building it means either a new dependency or
 * a hand-rolled IMAP4rev1 client with its own MIME parser over untrusted input.
 * `CLAUDE.md` says to stop and say so rather than add one, and the size is
 * reported in the decisions block rather than absorbed quietly.
 */
final class SupportMailbox
{
    /**
     * Which mailer this cursor belongs to.
     *
     * ⚠️ **DELIBERATELY NOT {@see GmailInbox::MAILER}.** Same vendor, same API,
     * different account and different purpose — and the whole value of the
     * `(mailer, mailbox)` key is that these two strings are not equal.
     */
    public const string MAILER = 'gmail-support';

    /**
     * How much decoded text is carried out of a message.
     *
     * `app(SupportDesk::class)->bodyLimit()` is what actually gets stored; this is the bound
     * on what is decoded on the way there, so a fifty-megabyte plain-text part
     * cannot become fifty megabytes of PHP string before something trims it.
     */
    private const int DECODE_LIMIT = 100_000;

    public function __construct(private readonly GmailApiClient $client) {}

    /**
     * Whether the support pull may run at all.
     *
     * ⛔ **THREE REFUSALS, AND THE THIRD IS THE ONE THAT IS NOT OBVIOUS.** Off by
     * config, off with no mailbox configured, and off when the configured
     * mailbox is the relay's — because two consumers on one bookmark lose mail
     * silently and the symptom is an empty support queue, which is also what
     * *working* looks like on a quiet week.
     */
    public function isEnabled(): bool
    {
        if (config('platform_mail.gmail.support.enabled') !== true) {
            return false;
        }

        $mailbox = $this->mailbox();

        if ($mailbox === '') {
            return false;
        }

        return strcasecmp($mailbox, $this->relayMailbox()) !== 0;
    }

    /**
     * The support account this application reads.
     *
     * ⚠️ **NO `me` FALLBACK.** `me` is Gmail's alias for whichever account the
     * token belongs to, so it names the relay just as readily as the support
     * account — and the comparison in {@see self::isEnabled()} could not tell.
     * An unset value is an empty string and disables the path.
     */
    public function mailbox(): string
    {
        $mailbox = config('platform_mail.gmail.support.mailbox');

        return is_string($mailbox) ? trim($mailbox) : '';
    }

    /**
     * The last history record this application finished with, or null.
     */
    public function cursor(): ?string
    {
        $row = MailInboxCursor::query()
            ->where('mailer', self::MAILER)
            ->where('mailbox', $this->mailbox())
            ->first();

        return $row instanceof MailInboxCursor && is_string($row->history_id) && $row->history_id !== ''
            ? $row->history_id
            : null;
    }

    /**
     * Move the bookmark.
     *
     * ⚠️ **CALLED ONLY AFTER EVERY MESSAGE IN THE RANGE HAS BEEN RECORDED.**
     * Advancing first would lose every support request in the range whenever the
     * worker died in between, permanently, because the bookmark is the only
     * record of where we were.
     */
    public function rememberCursor(string $historyId): void
    {
        MailInboxCursor::query()->updateOrCreate(
            ['mailer' => self::MAILER, 'mailbox' => $this->mailbox()],
            ['history_id' => $historyId],
        );
    }

    /**
     * Where the mailbox is now — `users.getProfile`'s `historyId`.
     *
     * Needed twice and for opposite reasons: to establish the first bookmark
     * (there is nothing to read a range *from* until one exists), and to
     * re-establish it after Google refuses an expired one. Costs one quota unit.
     */
    public function currentHistoryId(): ?string
    {
        $response = Http::withToken($this->client->supportAccessToken())
            ->timeout($this->timeout())
            ->get($this->endpoint().'/profile');

        if ($response->failed()) {
            return null;
        }

        $historyId = $response->json('historyId');

        return is_string($historyId) && $historyId !== '' ? $historyId : null;
    }

    /**
     * The ids of messages added since `$startHistoryId`, and where we got to.
     *
     * The same walk `GmailInbox` makes over the relay, against a different
     * account: `messageAdded` only, `INBOX` only, de-duplicated because one
     * message appears in several history records, and **the bookmark dropped
     * rather than advanced when the per-run cap is hit**, so the remainder is
     * finished by the next poll instead of being skipped.
     *
     * @return array{ids: list<string>, historyId: ?string}
     *
     * @throws GmailHistoryUnavailable when the bookmark is older than Gmail keeps
     */
    public function messagesAddedSince(string $startHistoryId): array
    {
        $pageLimit = max(1, (int) config('platform_mail.gmail.support.max_history_pages', 3));
        $messageLimit = max(1, (int) config('platform_mail.gmail.support.max_messages_per_run', 50));

        $ids = [];
        $historyId = null;
        $pageToken = null;

        for ($page = 0; $page < $pageLimit; $page++) {
            $query = [
                'startHistoryId' => $startHistoryId,
                'historyTypes' => 'messageAdded',
                // Restricted to the inbox, so an answer *we* sent — which lands
                // in SENT and is also an added message — is not read back and
                // recorded as though the tenant had written it.
                'labelId' => 'INBOX',
            ];

            if (is_string($pageToken)) {
                $query['pageToken'] = $pageToken;
            }

            $response = Http::withToken($this->client->supportAccessToken())
                ->timeout($this->timeout())
                ->get($this->endpoint().'/history', $query);

            if ($response->status() === 404) {
                throw GmailHistoryUnavailable::cursorTooOld($startHistoryId);
            }

            if ($response->failed()) {
                // ⚠️ NO BODY IN THE MESSAGE. A Gmail error body can quote the
                // request, and this string reaches `failed_jobs` and the log.
                throw new RuntimeException(
                    'Gmail refused a support mailbox history read with HTTP '.$response->status().'.'
                );
            }

            $current = $response->json('historyId');

            if (is_string($current) && $current !== '') {
                $historyId = $current;
            }

            foreach ($this->addedIds($response->json('history')) as $id) {
                $ids[] = $id;

                if (count($ids) >= $messageLimit) {
                    return ['ids' => array_values(array_unique($ids)), 'historyId' => null];
                }
            }

            $pageToken = $response->json('nextPageToken');

            if (! is_string($pageToken) || $pageToken === '') {
                break;
            }
        }

        return ['ids' => array_values(array_unique($ids)), 'historyId' => $historyId];
    }

    /**
     * One support email, or null when there is nothing usable to record.
     *
     * ⚠️ **NULL IS THE ORDINARY ANSWER FOR THREE DIFFERENT THINGS**, and none of
     * them may cost us the message sitting beside it in the same batch: the
     * message was deleted between the history record and this call, it has no
     * sender we can read, or it carries no `text/plain` part.
     *
     * ⛔ **AND IT WAS THE ANSWER FOR A FOURTH UNTIL 9480–9499, WHICH IS NOT
     * ORDINARY AT ALL.** The guard was `$response->failed()` — every status from
     * 400 up — so a 429, a 500 or a 503 was handed to the caller as *"nothing to
     * record"*, the caller's own comment called it ordinary, and
     * {@see PollSupportMailboxJob} then **advanced the bookmark past
     * it**. A customer's request for help was destroyed permanently with no row,
     * no log line and no counter — a run in which every read failed logged
     * literally nothing, because the report returns early when both its counts
     * are zero. The list of three above is the population the paragraph was
     * written about and it keeps exactly that population;
     * {@see GmailMessageUnreadable} carries the rest.
     *
     *
     * ⛔ **`text/plain` ONLY, AND NO HTML IS PARSED OR STRIPPED.** Inbound mail
     * is untrusted and an HTML-to-text pass over it is a parser this desk does
     * not need: a mail client that sends `multipart/alternative` sends the plain
     * part too. A message with none is left in the mailbox for a person, which
     * is {@see SupportInbox}'s own instruction for anything that cannot be
     * handled automatically.
     *
     * @throws GmailMessageUnreadable when Gmail refused the read for any reason
     *                                other than the message being gone
     */
    public function fetch(string $messageId): ?FetchedSupportMail
    {
        $response = Http::withToken($this->client->supportAccessToken())
            ->timeout($this->timeout())
            ->get($this->endpoint().'/messages/'.rawurlencode($messageId), ['format' => 'full']);

        // ⚠️ **THE SAME STATUS `messagesAddedSince()` TREATS AS MEANING SOMETHING
        // SPECIFIC, TWENTY LINES ABOVE.** A 404 here is the message being gone,
        // which Gmail also answers 410 to for a purged mailbox — both are *"we
        // looked and it is not there"*, and neither is worth holding a bookmark
        // for, because no amount of waiting brings a deleted message back.
        if ($response->status() === 404 || $response->status() === 410) {
            return null;
        }

        if ($response->failed()) {
            throw GmailMessageUnreadable::status($response->status());
        }

        $headers = $response->json('payload.headers');

        $from = $this->addressIn($this->headerValue($headers, 'From'));

        if ($from === null) {
            return null;
        }

        $body = $this->plainTextIn($response->json('payload'));

        if ($body === null || trim($body) === '') {
            return null;
        }

        return new FetchedSupportMail(
            // ⚠️ **GMAIL'S OWN ID, PREFIXED — NOT THE RFC 5322 `Message-ID`.**
            // The header is written by the sender and is therefore forgeable and
            // sometimes absent; Gmail's id is assigned by the mailbox and is
            // what makes a re-read of the same mailbox idempotent. The prefix is
            // what stops a later transport's numbering colliding with this one's
            // inside `support_messages.external_ref`.
            externalRef: 'gmail:'.$messageId,
            fromAddress: $from,
            subject: $this->headerValue($headers, 'Subject') ?? '',
            body: $body,
            receivedAt: $this->receivedAt($response->json('internalDate')),
            senderIsAuthenticated: $this->senderIsAuthenticated($headers, $from),
        );
    }

    /**
     * Whether the mailbox provider itself proved this message's `From` domain.
     *
     * ⛔ **`From` IS WRITTEN BY THE SENDER AND WAS ROUTING TENANTS' SUPPORT MAIL
     * UNTIL 2026-08-17** (4606). `accountOfSender()` mapped it to a
     * `business_id` *and* an `author_user_id`, so anybody who knew a tenant
     * owner's email address could post text into that tenant's support thread
     * attributed to the owner — with no SPF, DKIM or DMARC check anywhere on the
     * path. ⚠️ **And `fetch()` explains ten lines above that a sender-written
     * header is forgeable** — about `Message-ID`, which is routed to nothing —
     * which is 314–316's shape at its most exact: the protection asserted beside
     * the place it is missing.
     *
     * ## What is checked, and why it is DMARC or aligned DKIM rather than SPF
     *
     * RFC 8601 §2.2, fetched from `rfc-editor.org` 2026-08-17 and not recalled:
     * `authres-payload = [CFWS] authserv-id [CFWS authres-version] (no-result /
     * 1*resinfo)`, `resinfo = [CFWS] ";" methodspec [CFWS reasonspec] [CFWS
     * 1*propspec]`, `methodspec = method "=" result`, `method = Keyword [ "/"
     * method-version ]`, `propspec = ptype "." property "=" pvalue`. That is the
     * whole grammar this parser implements.
     *
     * **`dmarc=pass`** is accepted because DMARC's own definition is *SPF or
     * DKIM passed **and** was aligned with the `From` domain* — exactly the
     * property being asked for, and the only one that authenticates the header
     * this router reads.
     *
     * **`dkim=pass` with `header.d` equal to the `From` domain** is the second
     * branch, and it is not a weakening: it is DMARC's DKIM half evaluated
     * directly. It exists because most tenants here are small local businesses
     * whose domains publish **no DMARC record at all**, which yields
     * `dmarc=none` rather than `dmarc=pass` — requiring DMARC alone would route
     * nothing for most of the customer base, and a desk that silently files
     * nothing looks exactly like a quiet week.
     *
     * ⛔ **`spf=pass` IS NOT ACCEPTED AND IS NOT REQUIRED IN ADDITION.** SPF
     * authenticates the *envelope* sender, not the `From` header, and forwarded
     * mail routinely fails it while being perfectly genuine. On its own it
     * proves nothing about the address being routed; added beside aligned DKIM
     * it refuses forwarded mail and adds no security, because aligned DKIM alone
     * is already a DMARC pass condition.
     *
     * ## Which header field is believed
     *
     * ⚠️ **ONLY ONE BEARING A TRUSTED `authserv-id`, AND EXACTLY ONE.** RFC 8601
     * §5: *"any MTA conforming to this specification MUST delete any discovered
     * instance of this header field that claims, by virtue of its authentication
     * service identifier, to have been added within its trust boundary but that
     * did not come directly from another trusted MTA."* And §2.1/§4: it *"MUST
     * NOT be reordered and MUST be prepended"*. So on a conforming provider
     * there is exactly one field bearing `mx.google.com` and it is the
     * provider's own.
     *
     * ⛔ **TWO OF THEM IS A REFUSAL RATHER THAN A CHOICE, AND THAT IS WHAT MAKES
     * THIS INDEPENDENT OF HEADER ORDERING.** Believing "the first" would rest on
     * the Gmail API returning `payload.headers` in message order, and **Google's
     * own reference does not say that it does.** `users.messages`, fetched from
     * `developers.google.com` 2026-08-17, defines the field only as *"List of
     * headers on this message part. For the top-level message part, representing
     * the entire message payload, it will contain the standard RFC 2822 email
     * headers such as `To`, `From`, and `Subject`"*, with `Header` being
     * `{"name": string, "value": string}` — repeated fields therefore arrive as
     * repeated entries, which is why {@see self::headerValue()} is the wrong
     * reader here, and **there is no ordering guarantee anywhere on the page**.
     * If that order were ever the reverse, believing the first would believe a
     * forgery over the genuine field. Refusing the ambiguous case costs a
     * support email that a person still reads in the mailbox; guessing costs a
     * tenant's thread.
     *
     * ⛔ **THIS IS NOT PROVEN AGAINST A LIVE WORKSPACE MAILBOX AND MUST NOT BE
     * WRITTEN UP AS THOUGH IT WERE** (4455's rule, applied at 4608). The tests
     * build the header from RFC 8601's grammar and serve it through
     * `Http::fake`, so they prove this parser and say nothing about what Gmail
     * actually writes. This whole path is behind
     * `PLATFORM_MAIL_SUPPORT_INBOUND_ENABLED`, which seeds **off**, and no
     * support account has been authorised — so no real `Authentication-Results`
     * has ever reached this code. **The first real message is the
     * verification**, and if the `authserv-id` turns out to differ then every
     * sender reads as unrecognised and their mail waits in the mailbox for a
     * person, which is this path's designed fallback rather than a new failure.
     */
    private function senderIsAuthenticated(mixed $headers, string $fromAddress): bool
    {
        $domain = $this->domainOf($fromAddress);

        if ($domain === null) {
            return false;
        }

        $trusted = [];

        foreach ($this->headerValues($headers, 'Authentication-Results') as $value) {
            if ($this->authservIdIsTrusted($value)) {
                $trusted[] = $value;
            }
        }

        // Exactly one, or nothing is believed — see above.
        if (count($trusted) !== 1) {
            return false;
        }

        return $this->provesFromDomain($trusted[0], $domain);
    }

    /**
     * Whether this header field was added by a provider we trust to have
     * checked.
     *
     * The `authserv-id` is everything before the first `;` (RFC 8601 §2.2),
     * optionally followed by an `authres-version` — which is why the first
     * token is taken rather than the whole run.
     */
    private function authservIdIsTrusted(string $value): bool
    {
        $head = strtok($value, ';');

        if ($head === false) {
            return false;
        }

        $id = strtolower(trim((string) strtok(trim($head), " \t")));

        if ($id === '') {
            return false;
        }

        foreach ($this->trustedAuthservIds() as $trusted) {
            if ($id === strtolower(trim($trusted))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether this header field proves the `From` domain.
     */
    private function provesFromDomain(string $value, string $domain): bool
    {
        // ⚠️ CFWS COMMENTS FIRST. Gmail writes `dmarc=fail (p=NONE sp=NONE
        // dis=NONE)`, and a comment may hold anything at all — including the
        // literal text this method is looking for.
        $value = (string) preg_replace('/\([^()]*\)/', ' ', $value);

        $segments = explode(';', $value);

        // The authserv-id, already checked by the caller.
        array_shift($segments);

        foreach ($segments as $segment) {
            $tokens = preg_split('/\s+/', trim($segment), -1, PREG_SPLIT_NO_EMPTY);

            if ($tokens === false || $tokens === []) {
                continue;
            }

            $methodspec = (string) array_shift($tokens);

            [$method, $result] = array_pad(explode('=', strtolower($methodspec), 2), 2, '');

            if ($result !== 'pass') {
                continue;
            }

            $method = (string) strtok($method, '/');

            if ($method === 'dmarc') {
                return true;
            }

            if ($method !== 'dkim') {
                continue;
            }

            foreach ($tokens as $token) {
                // ⚠️ ALIGNMENT, EVALUATED RATHER THAN ASSUMED. A `dkim=pass` for
                // a signature by somebody else's domain proves that somebody
                // else sent something, and nothing whatever about the address
                // this router is about to trust.
                if ($this->dkimPropertyAligns(trim($token, '"'), $domain)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether one DKIM propspec aligns the signature with the `From` domain.
     *
     * ⚠️ **BOTH `header.d` AND `header.i` ARE ACCEPTED, AND THE SECOND IS NOT A
     * RELAXATION.** RFC 8601 registers both as DKIM properties, and a verifier
     * may report either — this codebase has no way to observe which one Gmail
     * writes, so reading only `header.d` would risk a check that never passes
     * and a support desk that silently routes nothing.
     *
     * RFC 6376 §3.5, fetched from `rfc-editor.org` 2026-08-17: the `i=` tag's
     * *"domain part of the address **MUST be the same as, or a subdomain of,
     * the value of the `d=` tag**"*. So `header.i` ending `@example.test`
     * establishes that the signing domain is `example.test` or a **parent** of
     * it — which is DMARC's relaxed alignment at worst, never a signature by an
     * unrelated domain.
     *
     * ⚠️ **THE RESIDUAL IS THE PARENT CASE AND IT IS DMARC'S TOO**: somebody who
     * controlled a parent of a tenant's domain could align against it. That is
     * true of every DMARC-relaxed evaluation on the internet and is not a hole
     * this check introduces.
     */
    private function dkimPropertyAligns(string $token, string $domain): bool
    {
        if (strcasecmp($token, 'header.d='.$domain) === 0) {
            return true;
        }

        if (stripos($token, 'header.i=') !== 0) {
            return false;
        }

        $auid = $this->domainOf(substr($token, strlen('header.i=')));

        return $auid !== null && $auid === $domain;
    }

    /**
     * The domain half of an address, lower-cased.
     */
    private function domainOf(string $address): ?string
    {
        $at = strrpos($address, '@');

        if ($at === false) {
            return null;
        }

        $domain = strtolower(trim(substr($address, $at + 1)));

        return $domain === '' ? null : $domain;
    }

    /**
     * The `authserv-id`s this application believes.
     *
     * @return list<string>
     */
    private function trustedAuthservIds(): array
    {
        $configured = config('platform_mail.gmail.support.trusted_authserv_ids');

        if (! is_array($configured)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn (mixed $id): string => is_string($id) ? $id : '', $configured),
            fn (string $id): bool => trim($id) !== '',
        ));
    }

    /**
     * Every value for one header name, in the order the provider returned them.
     *
     * `Authentication-Results` is a trace field and a message may legitimately
     * carry several — one per MTA that checked — so the single-value
     * {@see self::headerValue()} is the wrong reader for it, and using it here
     * would silently hide the ambiguous case
     * {@see self::senderIsAuthenticated()} refuses on.
     *
     * @return list<string>
     */
    private function headerValues(mixed $headers, string $wanted): array
    {
        if (! is_array($headers)) {
            return [];
        }

        $values = [];

        foreach ($headers as $header) {
            if (! is_array($header)) {
                continue;
            }

            $name = $header['name'] ?? null;
            $value = $header['value'] ?? null;

            if (is_string($name) && is_string($value) && strcasecmp($name, $wanted) === 0) {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * Every `messagesAdded` id in a history response.
     *
     * @return list<string>
     */
    private function addedIds(mixed $history): array
    {
        if (! is_array($history)) {
            return [];
        }

        $ids = [];

        foreach ($history as $record) {
            if (! is_array($record) || ! is_array($record['messagesAdded'] ?? null)) {
                continue;
            }

            foreach ($record['messagesAdded'] as $added) {
                $message = is_array($added) ? ($added['message'] ?? null) : null;
                $id = is_array($message) ? ($message['id'] ?? null) : null;

                if (is_string($id) && $id !== '') {
                    $ids[] = $id;
                }
            }
        }

        return $ids;
    }

    /**
     * One header's value, matched case-insensitively.
     *
     * RFC 5322 field names are case-insensitive and Gmail returns them as the
     * sender wrote them, so an exact match is invisible to a hand-rolled client
     * that sends `from:` — and the symptom is a support request that silently
     * routes nowhere.
     */
    private function headerValue(mixed $headers, string $wanted): ?string
    {
        if (! is_array($headers)) {
            return null;
        }

        foreach ($headers as $header) {
            if (! is_array($header)) {
                continue;
            }

            $name = $header['name'] ?? null;
            $value = $header['value'] ?? null;

            if (is_string($name) && is_string($value) && strcasecmp($name, $wanted) === 0) {
                return $value;
            }
        }

        return null;
    }

    /**
     * The bare address out of an RFC 5322 header value.
     *
     * `From: Jo Owner <jo@example.test>` is one address wrapped in a display
     * name; a bare address is the other shape. Anything else yields null rather
     * than a guess, because the value decides which tenant this belongs to.
     */
    private function addressIn(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (preg_match('/<([^>]+)>/', $value, $matches) === 1) {
            $value = $matches[1];
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * The first `text/plain` part, decoded — walking nested parts.
     *
     * `multipart/mixed` wrapping a `multipart/alternative` is what an ordinary
     * mail client with an attachment produces, so the walk has to recurse; a
     * scan of `parts[]` one level deep finds the wrapper and no text.
     */
    private function plainTextIn(mixed $payload): ?string
    {
        if (! is_array($payload)) {
            return null;
        }

        $mimeType = $payload['mimeType'] ?? null;

        if ($mimeType === 'text/plain') {
            $body = $payload['body'] ?? null;
            $data = is_array($body) ? ($body['data'] ?? null) : null;

            if (is_string($data) && $data !== '') {
                return $this->decode($data);
            }
        }

        $parts = $payload['parts'] ?? null;

        if (! is_array($parts)) {
            return null;
        }

        foreach ($parts as $part) {
            $text = $this->plainTextIn($part);

            if ($text !== null) {
                return $text;
            }
        }

        return null;
    }

    /**
     * Gmail's base64url, decoded and bounded.
     *
     * ⚠️ **BASE64URL, NOT BASE64** — `-` and `_` for `+` and `/`, and the padding
     * stripped. `GmailApiClient::send()` makes the same substitution in the other
     * direction, and getting it wrong fails on some messages and not others
     * depending on the bytes.
     */
    private function decode(string $data): ?string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        if ($decoded === false) {
            return null;
        }

        return mb_strlen($decoded) > self::DECODE_LIMIT
            ? mb_substr($decoded, 0, self::DECODE_LIMIT)
            : $decoded;
    }

    /**
     * When it arrived, from Gmail's `internalDate`.
     *
     * Milliseconds since the epoch, as a string. Anything unreadable falls back
     * to now rather than to the epoch: a support request dated 1970 sorts to the
     * bottom of the queue for ever, which is the failure that looks like nothing
     * happening.
     */
    private function receivedAt(mixed $internalDate): CarbonImmutable
    {
        if (is_string($internalDate) && ctype_digit($internalDate)) {
            return CarbonImmutable::createFromTimestampMs((int) $internalDate);
        }

        return CarbonImmutable::now();
    }

    private function relayMailbox(): string
    {
        $mailbox = config('platform_mail.gmail.inbox.mailbox');

        return is_string($mailbox) ? trim($mailbox) : 'me';
    }

    private function endpoint(): string
    {
        $base = (string) config('platform_mail.gmail.support.read_endpoint');

        return $base.'/'.rawurlencode($this->mailbox());
    }

    private function timeout(): int
    {
        return (int) config('platform_mail.gmail.timeout', 15);
    }
}
