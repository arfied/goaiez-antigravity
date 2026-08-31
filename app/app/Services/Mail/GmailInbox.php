<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Exceptions\GmailHistoryUnavailable;
use App\Exceptions\GmailMessageUnreadable;
use App\Models\MailInboxCursor;
use Illuminate\Support\Facades\Http;

/**
 * Reading the platform relay mailbox — the inbound half of T137 §3 rail 3.
 *
 * ⚠️ **A GMAIL PUSH NOTIFICATION CONTAINS NO MAIL, AND THAT IS THE ONE THING A
 * FROM-MEMORY IMPLEMENTATION GETS WRONG.** Verified against
 * `developers.google.com/workspace/gmail/api/guides/push` on 2026-08-12 (page
 * dated 2026-07-22): the decoded Pub/Sub payload is
 * `{"emailAddress": "user@example.com", "historyId": "9876543210"}` — an address
 * and a counter. No sender, no subject, no recipient, no body. Everything this
 * application needs is read back afterwards, over its own authenticated
 * connection, which is also what makes the unsigned push body harmless.
 *
 * ## Three calls, and the scope none of them shares with the sender
 *
 * ⛔ **`gmail.send` AUTHORISES NONE OF THIS.** `users.watch`,
 * `users.history.list` and `users.messages.get` each list exactly four
 * authorising scopes in Google's reference — `https://mail.google.com/`,
 * `gmail.modify`, `gmail.readonly`, `gmail.metadata` — and the send scope is on
 * none of them. `GmailApiClient`'s docblock says *"`gmail.send` and nothing
 * wider"* and is right about the call it describes; the internal app's grant
 * needs a second scope for this one, and the narrowest that works is
 * `gmail.metadata`.
 *
 * ✅ **`gmail.metadata` MAKES A PRIVACY DECISION UNFORGEABLE RATHER THAN MERELY
 * WRITTEN DOWN.** Google's scope page: *"View your email message metadata such
 * as labels and headers, but not the email body."* The `Format` reference
 * (dated 2025-03-24) adds that `full` and `raw` *"cannot be used when accessing
 * the API with the `gmail.metadata` scope"*. `MailReplyRouter` already refuses
 * to store the text of a reply; under this scope the text is not stored
 * **because it cannot be fetched**, and changing that would take a new grant
 * from Google rather than a one-line edit here.
 *
 * ⚠️ **AND ONLY THREE HEADERS ARE ASKED FOR.** `metadataHeaders[]` restricts
 * what comes back, so `To`, `Delivered-To` and `Cc` is the whole of it. `From`
 * and `Subject` are available and are not requested: the tracking code rides in
 * the address the reply was sent *to* (2097), and a header nobody asks for is
 * personal data that never crosses the boundary.
 *
 * ## What this class deliberately does not do
 *
 * ⛔ **IT DOES NOT CALL `users.watch`, SO NOTHING HERE RENEWS THE SUBSCRIPTION.**
 * The push guide is explicit — *"You must call the watch at least once every 7
 * days or you'll stop receiving updates for the user"* — and a watch that
 * lapses is a reply path that stops with no error anywhere. The column exists
 * (`mail_inbox_cursors.watch_expires_at`) and **nothing writes it**, which is
 * `CLAUDE.md`'s first recurring failure shape; it is named here and in the
 * decisions block rather than papered over, because the watch cannot be
 * registered at all until a Pub/Sub topic exists and this deployment has none.
 *
 * ⛔ **IT DOES NOT ANSWER BOUNCES.** A Gmail delivery-status notification is an
 * ordinary email in this mailbox, and reading one would mean parsing free-form
 * NDR text — the heuristic 1192 and 1193 rejected and 2094 re-opened. Open
 * question H is untouched by this file.
 */
final class GmailInbox
{
    /**
     * Which mailer this cursor belongs to.
     *
     * A literal rather than `MailDrivers::active()`: the bookmark belongs to the
     * *Gmail* mailbox, and an operator switching `MAIL_MAILER` to `smtp` for an
     * afternoon must not make this class start reading — or writing — a
     * different row.
     */
    public const string MAILER = 'gmail';

    public function __construct(private readonly GmailApiClient $client) {}

    /**
     * Whether the inbound path may run at all.
     *
     * ⛔ **SEEDS OFF.** Until the internal app has been granted
     * `gmail.metadata` and `users.watch` has been called, nothing genuine can
     * arrive — so an enabled endpoint would only ever be answering forgeries.
     */
    public function isEnabled(): bool
    {
        return config('platform_mail.gmail.inbox.enabled') === true;
    }

    /**
     * The mailbox this application watches.
     */
    public function mailbox(): string
    {
        $mailbox = config('platform_mail.gmail.inbox.mailbox');

        return is_string($mailbox) && trim($mailbox) !== '' ? trim($mailbox) : 'me';
    }

    /**
     * Whether a notification's `emailAddress` is the mailbox we watch.
     *
     * ⚠️ **`me` MATCHES ANY ADDRESS, AND THAT IS NOT A HOLE.** `me` is Gmail's
     * own alias for *the authorised user*, so on a single-account install the
     * configured value cannot be compared with the address Google sends —
     * they are the same mailbox spelled two ways, and refusing on the mismatch
     * would refuse every genuine notification. The moment an explicit address is
     * configured, this compares.
     */
    public function watches(string $emailAddress): bool
    {
        $mailbox = $this->mailbox();

        if ($mailbox === 'me') {
            return true;
        }

        return strcasecmp($mailbox, $emailAddress) === 0;
    }

    /**
     * The last history record this application finished with, or null.
     */
    public function cursor(): ?string
    {
        $row = $this->row();

        return $row instanceof MailInboxCursor && is_string($row->history_id) && $row->history_id !== ''
            ? $row->history_id
            : null;
    }

    /**
     * Move the bookmark.
     *
     * ⚠️ **CALLED ONLY AFTER THE MESSAGES IN THAT RANGE HAVE BEEN ROUTED.**
     * Advancing first and routing afterwards would lose every reply in the range
     * whenever the worker dies in between — and lose it permanently, because the
     * bookmark is the only record of where we were.
     */
    public function rememberCursor(string $historyId): void
    {
        MailInboxCursor::query()->updateOrCreate(
            ['mailer' => self::MAILER, 'mailbox' => $this->mailbox()],
            ['history_id' => $historyId],
        );
    }

    /**
     * The ids of messages added since `$startHistoryId`, and where we got to.
     *
     * @return array{ids: list<string>, historyId: ?string}
     *
     * @throws GmailHistoryUnavailable when the bookmark is older than Gmail keeps
     */
    public function messagesAddedSince(string $startHistoryId): array
    {
        $pageLimit = max(1, (int) config('platform_mail.gmail.inbox.max_history_pages', 3));
        $messageLimit = max(1, (int) config('platform_mail.gmail.inbox.max_messages_per_run', 100));

        $ids = [];
        $historyId = null;
        $pageToken = null;

        for ($page = 0; $page < $pageLimit; $page++) {
            $query = [
                'startHistoryId' => $startHistoryId,
                // ⚠️ **THE ONLY CHANGE TYPE ASKED FOR.** The enum also carries
                // `messageDeleted`, `labelAdded` and `labelRemoved`; a reply
                // arriving is `messageAdded` and the other three would make this
                // walk a mailbox's whole housekeeping for nothing.
                'historyTypes' => 'messageAdded',
                // Restricted to the inbox, so a message *we* sent — which lands
                // in SENT and is also an added message — is not read back and
                // routed as though somebody had replied to it.
                'labelId' => 'INBOX',
            ];

            // ⚠️ **NO `!== ''` HERE, AND THE ANALYSER IS RIGHT ABOUT WHY.** The
            // loop's own exit below breaks on an empty or non-string token, so
            // the only values that reach a second iteration are null (the first
            // pass) and a non-empty string. A redundant check would read as a
            // guard and defend nothing.
            if (is_string($pageToken)) {
                $query['pageToken'] = $pageToken;
            }

            $response = Http::withToken($this->client->accessToken())
                ->timeout($this->timeout())
                ->get($this->endpoint().'/history', $query);

            if ($response->status() === 404) {
                throw GmailHistoryUnavailable::cursorTooOld($startHistoryId);
            }

            if ($response->failed()) {
                // ⚠️ NO BODY IN THE MESSAGE. A Gmail error body can quote the
                // request, and this string reaches `failed_jobs` and the log —
                // `GmailApiClient` refuses the same thing for the same reason.
                throw new \RuntimeException(
                    'Gmail refused a mailbox history read with HTTP '.$response->status().'.'
                );
            }

            $current = $response->json('historyId');

            if (is_string($current) && $current !== '') {
                $historyId = $current;
            }

            foreach ($this->addedIds($response->json('history')) as $id) {
                $ids[] = $id;

                if (count($ids) >= $messageLimit) {
                    // ⚠️ **THE BOOKMARK IS DROPPED WHEN THE CAP IS HIT, NOT
                    // ADVANCED.** Returning the page's `historyId` here would
                    // skip past every message beyond the cap. A null tells the
                    // caller to leave the bookmark alone, so the next
                    // notification re-reads the same range and finishes it.
                    return ['ids' => array_values(array_unique($ids)), 'historyId' => null];
                }
            }

            $pageToken = $response->json('nextPageToken');

            if (! is_string($pageToken) || $pageToken === '') {
                break;
            }
        }

        // ⚠️ **DE-DUPLICATED, BECAUSE ONE MESSAGE APPEARS IN SEVERAL RECORDS.**
        // A `messageAdded` and a later `labelAdded` on the same message are two
        // history records naming one id, and asking Google for it twice costs 20
        // quota units to learn the same thing.
        return ['ids' => array_values(array_unique($ids)), 'historyId' => $historyId];
    }

    /**
     * The addresses one message was sent to.
     *
     * ⚠️ **HEADERS ONLY, AND THE FORMAT SAYS SO OUT LOUD.** `format=metadata`
     * with an explicit `metadataHeaders[]` list is what keeps this call inside
     * `gmail.metadata` — and `Format`'s own reference is explicit that `full`
     * and `raw` are unavailable under that scope, so a later "just grab the
     * body" edit fails at the vendor rather than silently succeeding.
     *
     * ⚠️ **A MESSAGE THAT CANNOT BE READ RETURNS NOTHING RATHER THAN THROWING.**
     * It may have been deleted between the history record and this call, which
     * is ordinary rather than hostile, and one unreadable message must not cost
     * us the reply sitting beside it in the same batch —
     * `InfobipInboundController` makes the identical choice for the identical
     * reason.
     *
     * ⛔ **THAT ARGUMENT IS RIGHT AND ITS POPULATION WAS WRONG UNTIL 9480–9499.**
     * The guard was `$response->failed()`, which is every status from 400 up, so
     * a 429 or a 503 from Gmail also came back as an empty recipient list — and
     * an empty list routes nothing, after which `IngestGmailPushJob` advanced
     * the bookmark past the message **and kept its day-long `Cache::add` claim**,
     * so even a re-delivery of the same notification would skip it. The reply
     * was lost permanently and the contact went on receiving outreach they had
     * already answered. The swallow now covers exactly the case this paragraph
     * describes.
     *
     * @return list<string>
     *
     * @throws GmailMessageUnreadable when Gmail refused the read for any reason
     *                                other than the message being gone
     */
    public function recipientsOf(string $messageId): array
    {
        $headers = config('platform_mail.gmail.inbox.recipient_headers');
        $headers = is_array($headers) ? array_values(array_filter($headers, 'is_string')) : ['To'];

        $response = Http::withToken($this->client->accessToken())
            ->timeout($this->timeout())
            ->get($this->endpoint().'/messages/'.rawurlencode($messageId), [
                'format' => 'metadata',
                'metadataHeaders' => $headers,
            ]);

        // ⚠️ **THE STATUSES THAT MEAN THE MESSAGE IS GONE**, which is what the
        // paragraph above is about and is the same reading `messagesAddedSince()`
        // gives a 404 twenty lines up. Waiting cannot bring back a deleted
        // message, so there is nothing to hold a bookmark for.
        if ($response->status() === 404 || $response->status() === 410) {
            return [];
        }

        if ($response->failed()) {
            throw GmailMessageUnreadable::status($response->status());
        }

        $found = $this->headerValues($response->json('payload.headers'), $headers);

        return $this->addresses($found);
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
     * The values of the headers we asked for, in the order Gmail returned them.
     *
     * ⚠️ **HEADER NAMES ARE COMPARED CASE-INSENSITIVELY.** RFC 5322 field names
     * are case-insensitive and Gmail returns them as the sender wrote them, so a
     * `to:` in lower case from a hand-rolled client would be invisible to an
     * exact match — and the symptom is a reply that routes nowhere.
     *
     * @param  list<string>  $wanted
     * @return list<string>
     */
    private function headerValues(mixed $headers, array $wanted): array
    {
        if (! is_array($headers)) {
            return [];
        }

        $wanted = array_map('mb_strtolower', $wanted);
        $values = [];

        foreach ($headers as $header) {
            if (! is_array($header)) {
                continue;
            }

            $name = $header['name'] ?? null;
            $value = $header['value'] ?? null;

            if (! is_string($name) || ! is_string($value)) {
                continue;
            }

            if (in_array(mb_strtolower($name), $wanted, true)) {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * Bare addresses out of RFC 5322 header values.
     *
     * ⚠️ **A HEADER IS A LIST AND AN ENTRY MAY CARRY A DISPLAY NAME.**
     * `To: Support <reply+ABCD2345@goaieasy.net>, someone@else.test` is one
     * header, two recipients, one of them wrapped in angle brackets — and
     * `MailTrackingCodes::codeIn()` parses a local part, so it needs the address
     * and not the whole field. Splitting on commas is imperfect for a display
     * name that contains one (`"Doe, John" <a@b.test>`); the angle brackets are
     * preserved through the split for that case, and a mangled entry yields no
     * code rather than the wrong one.
     *
     * ⛔ **NOTHING HERE IS STORED.** These addresses are handed straight to the
     * router, which reads a code out of them and keeps neither.
     *
     * @param  list<string>  $values
     * @return list<string>
     */
    private function addresses(array $values): array
    {
        $addresses = [];

        foreach ($values as $value) {
            foreach (explode(',', $value) as $entry) {
                $entry = trim($entry);

                if ($entry === '') {
                    continue;
                }

                if (preg_match('/<([^>]+)>/', $entry, $matches) === 1) {
                    $entry = trim($matches[1]);
                }

                if ($entry !== '') {
                    $addresses[] = $entry;
                }
            }
        }

        return array_values(array_unique($addresses));
    }

    private function row(): ?MailInboxCursor
    {
        return MailInboxCursor::query()
            ->where('mailer', self::MAILER)
            ->where('mailbox', $this->mailbox())
            ->first();
    }

    private function endpoint(): string
    {
        $base = (string) config('platform_mail.gmail.inbox.read_endpoint');

        return $base.'/'.rawurlencode($this->mailbox());
    }

    private function timeout(): int
    {
        return (int) config('platform_mail.gmail.timeout', 15);
    }
}
