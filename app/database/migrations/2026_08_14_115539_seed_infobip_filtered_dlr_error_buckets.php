<?php

declare(strict_types=1);

use App\Enums\ErrorBucket;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Infobip DLR error vocabulary `dlr_error_buckets` shipped without — row 4
 * slice 6 phase 3 (AG6), doc `51` §4.2, §10.
 *
 * ⛔ **THE TABLE HAD NO `filtered` ROW AT ALL, WHICH MADE DOC 51 §5.2's FIRST
 * QUARANTINE TRIGGER UNABLE TO FIRE IN PRODUCTION** (1650, `BUILD-PLAN` §2.10.3
 * row 6c). `NumberHealthService::outreachCounts()` buckets a failure by joining
 * `outreach_messages.error_message` against this table and defaulting an
 * unmatched name to {@see ErrorBucket::Other}; with one seeded row —
 * `EC_ABSENT_SUBSCRIBER` → `other` — `failed_filtered` was **zero for every
 * number for ever**. The score's `w_f·(1−filtered)` term contributed a flat
 * 0.25 always, and a trigger reading a filtered rate would have read a
 * permanent 0%. `CLAUDE.md`'s first recurring failure shape — a control with no
 * writer — sitting directly under a containment, and green in every test,
 * because the tests insert synthetic error names directly (1637).
 *
 * ## Where these strings come from, and how they were checked
 *
 * Infobip, *"Response status and error codes"*,
 * `https://www.infobip.com/docs/essentials/response-status-and-error-codes`,
 * **read 2026-08-14**. Every `error_name` below was verified against the raw
 * HTML of that page rather than against a rendering, a summary or memory —
 * `CLAUDE.md`'s rule, and 1349's `googlebusiness` is what getting it wrong
 * looks like. The page's own tables are the artefact: each row carries an ID, a
 * `permanent` flag, the error name and Infobip's description, grouped by error
 * group (0 OK · 1 Handset errors · 2 User errors · 3 Operator errors).
 *
 * ⚠️ **IT IS THE `error.name` FIELD OF THE DELIVERY REPORT, NOT `status.name`.**
 * `InfobipDeliveryController` reads `$report['error']['name']` and
 * `DeliveryReceipts` stores exactly that in `outreach_messages.error_message`,
 * so `EC_*` is the vocabulary this table can ever match. The DLR's *status*
 * name — `REJECTED_SENDER`, `REJECTED_FLOODING_FILTER`, `REJECTED_DND` — is a
 * different enumeration on the same payload and **nothing in this schema stores
 * it**, so no mapping here can reach those. Recorded as owed rather than
 * guessed at: whether a `REJECTED`-group report also carries a populated
 * `error` object is not stated on the page above, and building a column on that
 * assumption would be inventing a fact.
 *
 * ## Two `filtered` rows, and the argument for each one
 *
 * Doc 51 §4.2 defines the pile precisely: `filtered` is *"carrier-block/spam
 * classes — the signal that matters"*, as against `other`, *"invalid number,
 * handset off — noise that must not poison the score."* The question each
 * candidate has to answer is **does this failure implicate the sending number,
 * or the destination** — and only two names on Infobip's whole page answer it
 * unambiguously in the vendor's own words:
 *
 *  - `EC_REJECTED_SPAM_BY_OPERATOR` (607, User errors, permanent) — *"This
 *    message was identified as spam and cannot be delivered."* The canonical
 *    carrier spam block; nothing about it is about the handset.
 *  - `EC_BLACKLISTED_SENDERADDRESS` (2053, User errors, permanent) — *"The
 *    sender number has been blocklisted either at the operator's request or on
 *    your account…"* It names **our number**. Both halves of the "either"
 *    mean the same operational thing: this number must stop sending.
 *
 * ⛔ **AND THE CLOSE CALL WAS LEFT OUT, DELIBERATELY.**
 * `EC_CONTENT_BLOCKED_BY_OPERATOR` (561, Operator errors) reads *"Content
 * blocked by the mobile operator for this end user."* That is either a carrier
 * content filter — in which case it belongs in `filtered` — or a per-subscriber
 * content bar, in which case it is a fact about the destination and putting it
 * in `filtered` would condemn a healthy number for a recipient's settings. The
 * vendor's one sentence does not say which, and {@see ErrorBucket::Other}'s own
 * docblock settles ties in one direction: *"the cost of under-reacting to a
 * real filter is a slower quarantine, and the cost of over-reacting to an
 * unrecognised name is condemning a number for noise."* On the shared Lane A
 * number, over-reacting halts every text on the platform at once. It stays
 * unmapped until an observed production DLR or Infobip support settles it.
 *
 * **Six more were considered and refused, each for a stated reason:**
 *
 *  - `EC_SOURCE_ADDRESS_IS_BLOCKED` (375) — the vendor's own description
 *    contradicts the name: *"Source address (recipient) is blocked…"*. Source
 *    is the sender; the parenthesis says recipient. A string whose meaning the
 *    vendor's page cannot state is exactly what must not be mapped.
 *  - `EC_INVALIDMSCADDRESS` (2051) — the name says MSC address, the description
 *    says *"Text is blocklisted."* Same contradiction, opposite direction.
 *  - `EC_BLOCKED_BY_CAMPAIGN_BLACKLIST` (604) — *"Content blocked by campaign
 *    blocklist."* Whose blocklist — the carrier's, Infobip's, or one we
 *    configured — is not stated.
 *  - `EC_LIMIT_REACHED` (541), `EC_QUOTA_REACHED` (542), `EC_10DLC_LIMIT_REACHED`
 *    (4202) — throughput ceilings (AT&T TPS, T-Mobile daily brand volume, 10DLC
 *    operator limit). Hitting a cap is a capacity fact, not a reputation one,
 *    and quarantining a number for sending its allowance is the inversion of
 *    what §5.2 is for.
 *  - `EC_DESTINATION_FLOODING` (4103), `EC_DESTINATION_TXT_FLOODING` (4104) —
 *    Infobip's *own* per-destination flooding filter (6 identical / 20 varied
 *    messages per hour to one number), not a carrier judgement about us.
 *  - Every `EC_SC_*` short-code name (`EC_SC_BLOCKED`, `EC_SC_BLOCKED_FOR_END_USER`,
 *    `EC_CANNOT_RECEIVE_SC`, …) — several are genuine sender-side filters, and
 *    **which number type this platform sends from is the open lane question**
 *    (`BUILD-PLAN` §2.10.5, 1563), which belongs to slice 6d. Seeding a
 *    short-code vocabulary here would answer it by accident.
 *
 * ## The `other` rows are not redundant with the default
 *
 * An unmapped name already resolves to `other`, so every row below in that pile
 * changes no arithmetic. They are seeded because **four of them have "BLOCKED"
 * or "BLACKLISTED" in the name and are about the destination**, which is
 * precisely the mistake the next person adding a row is most likely to make.
 * A row that says so, with the vendor's sentence beside it, is cheaper than the
 * comment nobody reads.
 */
return new class extends Migration
{
    /**
     * Infobip error name → bucket, with the vendor's own words for each.
     *
     * @var array<string, array{bucket: ErrorBucket, why: string}>
     */
    private const array MAPPING = [
        // ── filtered: the failure implicates OUR number ────────────────────
        'EC_REJECTED_SPAM_BY_OPERATOR' => [
            'bucket' => ErrorBucket::Filtered,
            'why' => '607, User errors, permanent: "This message was identified as spam and cannot be delivered."',
        ],
        'EC_BLACKLISTED_SENDERADDRESS' => [
            'bucket' => ErrorBucket::Filtered,
            'why' => '2053, User errors, permanent: "The sender number has been blocklisted either at the '
                .'operator`s request or on your account the web interface."',
        ],

        // ── other: the failure is about the DESTINATION ────────────────────
        'EC_ABSENT_SUBSCRIBER_SM' => [
            'bucket' => ErrorBucket::Other,
            'why' => '6, Handset errors: "Indicates the destination numbers were unreachable, powered off, or '
                .'in an area with limited coverage." The same fact as EC_ABSENT_SUBSCRIBER (27), already seeded.',
        ],
        'EC_INVALID_DESTINATION_ADDRESS' => [
            'bucket' => ErrorBucket::Other,
            'why' => '351, User errors: "Invalid destination address." Doc 51 §4.2 names "invalid number" as '
                .'this pile by example. Its long description ends with an AT&T note that the number "could '
                .'be blocked due to a spam complaint" — one possibility among six the vendor lists for one '
                .'code, which is not a spam signal about the sending number.',
        ],
        'EC_CONTENT_BLOCKED' => [
            'bucket' => ErrorBucket::Other,
            'why' => '603, User errors, permanent: "Content blocked by user opt-out (MO: STOP)." An opt-out, '
                .'not a filter — the recipient asked their carrier to stop, which says nothing about the '
                .'number`s health. It is also a suppression signal this platform cannot currently see, '
                .'because nothing reads a DLR error as a STOP; recorded as owed.',
        ],
        'EC_SIGNALS_BLOCKED' => [
            'bucket' => ErrorBucket::Other,
            'why' => '608, User errors: "rejected due to an anti-fraud mechanism … the risk score of the '
                .'DESTINATION hit the defined threshold." Infobip`s own Signals product, judging the '
                .'recipient, not a carrier judging us.',
        ],
        'EC_DEST_ADDRESS_BLACKLISTED' => [
            'bucket' => ErrorBucket::Other,
            'why' => '2050, Operator errors, permanent: "The numbers were identified as blocklisted in the '
                .'operator`s DND (Do Not Disturb) database." The destination is on a do-not-disturb list.',
        ],
        'EC_BLACKLISTED_DESTINATIONADDRESS' => [
            'bucket' => ErrorBucket::Other,
            'why' => '2052, User errors, permanent: "The destination number has been blocklisted either at the '
                .'operator`s request or on your account through the web interface." The mirror image of '
                .'EC_BLACKLISTED_SENDERADDRESS above, and the reason both are spelled out.',
        ],
        'EC_DEACTIVATED_LIST' => [
            'bucket' => ErrorBucket::Other,
            'why' => '3041, User errors, permanent: "The phone number you provided is listed as deactivated." '
                .'A hard invalid — doc 51 §4.2 routes these to the doc 46 bounce-heal path, which is not built.',
        ],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::MAPPING as $errorName => $row) {
            // updateOrInsert rather than insert: `EC_ABSENT_SUBSCRIBER` was
            // seeded by the creating migration and Ops may edit a bucket
            // (I44 — the mapping is a table so it needs no deploy), so this
            // must not collide with the unique index on a database that
            // already holds a name below.
            DB::table('dlr_error_buckets')->updateOrInsert(
                ['error_name' => $errorName],
                ['bucket' => $row['bucket']->value, 'updated_at' => $now, 'created_at' => $now],
            );
        }
    }

    public function down(): void
    {
        DB::table('dlr_error_buckets')
            ->whereIn('error_name', array_keys(self::MAPPING))
            ->delete();
    }
};
