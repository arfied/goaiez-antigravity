<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Enums\OwnerNotificationKind;
use App\Livewire\Admin\NumberLookup;
use App\Models\OwnerNotification;
use App\Models\OwnerReply;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The durable record of an owner-directed text, and the one question a stored
 * reply can ask of it — wave 40 lane A, decisions 10820 and 10823.
 *
 * ⛔ **THE WRITE IS CALLED FROM INSIDE {@see PlatformTexter::sendToOwner()},
 * NOT FROM THE CALL SITE, AND THAT PLACEMENT IS THE WHOLE MECHANISM.** An
 * owner-directed send that leaves no trace is exactly what shipped at 10540,
 * and putting the recorder at the one call site that existed would have left
 * the next sender — the lifecycle-ladder SMS rungs `CLAUDE.md` names as owed —
 * silently unrecorded again the moment it landed. 1566's own argument, one axis
 * over: **the property has to be impossible to omit rather than remembered.**
 * `sendToOwner()` therefore takes the kind and the occasion as **required**
 * parameters, so a sender that has not said what it is texting about cannot
 * call it at all.
 *
 * ⚠️ **AND THE ROW IS STILL BEST-EFFORT, WHICH IS A NARROWER CLAIM THAN THE
 * PARAGRAPH ABOVE.** What is structural is that the recorder is *called*; the
 * insert itself is wrapped and swallowed, because {@see self::record()} runs
 * after the carrier has already accepted the message. Letting a database error
 * escape there would turn a completed send into a failed job, and
 * `App\Jobs\EscalateUrgentThreadJob::page()` sets its no-double-page flag
 * **after** the text — so the retry would re-mail and re-text an owner over a
 * logging failure. **A send we could not record is still a send**; the honest
 * failure is a warning in the log, never a second page.
 *
 * ## ⚠️ The correlation is an INFERENCE and the column name says so
 *
 * {@see self::latestFor()} answers *"what is the most recent thing we texted
 * this owner about"*, and `owner_replies.in_reply_to_notification_id` stores
 * that answer. **It is not a claim that the owner meant it.** No owner-directed
 * message this platform sends carries a reply token, a thread id or a numbered
 * option (decision **10832** refuses the grammar and states why — ⛔ **this line
 * cited 10824 until wave 41 lane E**, which is *"one row per send, never one row
 * per occasion"* and is a different subject; corrected at 11105), so recency is the
 * only signal there is — and it is stored at the one moment both halves are in
 * hand rather than re-derived later, which is `InboundMessages`' own stated
 * rule for `campaign_replies.conversation_id` (4236).
 */
final class OwnerNotifications
{
    /**
     * How long after a send a reply is still treated as answering it.
     *
     * ⚠️ **A CONSTANT AND DELIBERATELY NOT A REGISTRY KEY.** This is a
     * correlation heuristic, not a policy: an operator moving it changes what
     * one nullable column infers and nothing else, and `CLAUDE.md`'s *less
     * support surface* tiebreaker refuses a settings row for that. It is also
     * not a retention period — `owner_channel.retention_days` is, and that one
     * IS an operator's, because it decides how long an account holder's own
     * words are kept.
     *
     * ⚠️ **72 RATHER THAN A WEEK, AND THE DIRECTION OF THE ERROR IS THE
     * ARGUMENT.** The one owner-directed message that exists says *"check your
     * inbox to reply"* about something urgent; a reply to it arrives in hours,
     * and every hour of window past that is a window in which an owner's
     * unrelated text gets attached to an old escalation. Too short leaves a
     * null, which reads as *"we do not know"*; too long invents an answer.
     * ⛔ **DO NOT WIDEN THIS TO FIT A WEEKLY CADENCE.** The day a weekly digest
     * is textable, the right move is for that message to carry its own occasion
     * and for the correlation to be re-argued for it — not for every kind to
     * inherit the loosest kind's window.
     */
    public const int CORRELATION_WINDOW_HOURS = 72;

    /**
     * How many of each kind {@see self::ledgerFor()} reads by default.
     *
     * ⚠️ **A CEILING ON A PAGE, NOT A RETENTION PERIOD.** Nothing deletes
     * either table on any deployment that exists — `owner_channel.retention_days`
     * ships with no seed and an unset period is a no-op (10834) — so an
     * uncapped read is a screen that gets slower for ever and eventually times
     * out on the busiest account. **The screen states what it is showing**
     * rather than implying it shows everything, which is the half that matters:
     * a truncated list nobody is told about is worse than a short one.
     */
    public const int LEDGER_LIMIT = 50;

    public function correlationWindowHours(): int
    {
        return app(DefaultsRegistry::class)->int('sms.owner_notifications.correlation_window_hours');
    }

    public function ledgerLimit(): int
    {
        return app(DefaultsRegistry::class)->int('sms.owner_notifications.ledger_limit');
    }

    /**
     * Record that a carrier accepted an owner-directed message.
     *
     * ⚠️ **`Tenancy::actingAs()` RATHER THAN THE AMBIENT TENANT.** The permit
     * names exactly one business and is the authority on which; a scheduled
     * sender walking accounts, or a job whose tenant was established for a
     * different reason, must not be able to file this row against the wrong
     * one. The previous tenant is restored either way.
     *
     * @param  string  $occasion  The event this send was about, in the sender's
     *                            own idempotency vocabulary — never a value
     *                            derived here, for
     *                            `AnswerAgentTurnJob::idempotencyKey()`'s
     *                            reason: a string this method invented would
     *                            differ on every attempt.
     */
    public function record(
        int $businessId,
        OwnerNotificationKind $kind,
        string $occasion,
        string $providerMessageId,
    ): void {
        try {
            Tenancy::actingAs($businessId, function () use ($kind, $occasion, $providerMessageId): void {
                // ⛔ **THE SAVEPOINT IS NOT A TEST ARTEFACT — IT IS WHAT MAKES
                // THE SWALLOW BELOW SAFE ON POSTGRES.** A constraint violation
                // aborts the whole transaction it happened in
                // (`SQLSTATE[25P02]`, *"commands ignored until end of
                // transaction block"*), so an insert that fails inside a
                // CALLER's open transaction poisons every statement after it —
                // and this method catches the exception, so the caller would go
                // on running against a connection that refuses everything. A
                // caught error that destroys its caller is worse than a thrown
                // one. `DB::transaction()` opens a savepoint when a transaction
                // is already in flight and rolls back to it, which is what
                // confines the damage to this insert.
                DB::transaction(function () use ($kind, $occasion, $providerMessageId): void {
                    OwnerNotification::query()->create([
                        'kind' => $kind,
                        'occasion' => $occasion,
                        'provider_message_id' => $providerMessageId,
                        'sent_at' => CarbonImmutable::now(),
                        'created_at' => CarbonImmutable::now(),
                    ]);
                });
            });
        } catch (Throwable $e) {
            // ⚠️ **THE CLASS NAME AND OUR OWN IDS, NEVER THE NUMBER AND NEVER
            // THE BODY** — every writer on the owner path swallows its own
            // failure this way, and the two things this one holds that must
            // not reach a log file are the account holder's mobile and what
            // was said to them.
            Log::warning('An owner-directed text was sent but could not be recorded.', [
                'reason' => $e::class,
                'business_id' => $businessId,
                'kind' => $kind->value,
                'provider_message_id' => $providerMessageId,
            ]);
        }
    }

    /**
     * The most recent owner-directed message to this business, if one is
     * recent enough for a reply to plausibly be answering it.
     *
     * ⚠️ **NULL IS A REAL AND COMMON ANSWER**, and it means *"we do not know
     * what this is a reply to"* — never *"this is not a reply"*. Every owner
     * who has said something unprompted, and every owner whose consent
     * predates this table, lands here.
     */
    public function latestFor(int $businessId, ?CarbonImmutable $asOf = null): ?OwnerNotification
    {
        $now = $asOf ?? CarbonImmutable::now();

        return Tenancy::actingAs($businessId, fn (): ?OwnerNotification => OwnerNotification::query()
            ->where('sent_at', '<=', $now)
            ->where('sent_at', '>=', $now->subHours($this->correlationWindowHours()))
            // ⚠️ **`id` RATHER THAN `sent_at`, AND IT IS NOT A CONCESSION TO A
            // LINT.** `ConventionsTest`'s *"no descending order relies on
            // Postgres putting NULLs first"* is right about the shape, and the
            // honest fix is the one it prescribes rather than an allowlist
            // entry: the primary key is `NOT NULL` by construction where any
            // timestamp column is one migration from being nullable.
            // ⚠️ **THE TWO ORDERS CANNOT DISAGREE ON REAL DATA** —
            // {@see self::record()} stamps `sent_at` with `now()` at the moment
            // it inserts, so insertion order *is* send order. A factory can
            // write them out of step in a fixture; nothing in `app/` can.
            ->orderByDesc('id')
            ->first());
    }

    /**
     * Everything this platform has texted one account holder, and everything
     * they texted back — wave 41 lane E, decision 11110.
     *
     * ⛔ **THE READ LIVES HERE AND NOT ON THE SCREEN, AND THAT IS A
     * CHOKEPOINT RATHER THAN A LAYER.** `OwnerChannelTest.php` fails the build
     * on any file in `app/` outside a named list that so much as spells
     * `OwnerNotification::` or `owner_replies` — the first because a second
     * writer is how `owner_notifications` stops meaning *"every owner-directed
     * send"*, the second because 10722's *"nothing reads what the owner said"*
     * is a property somebody could end by accident. Putting the query in
     * `App\Livewire\Admin\OwnerChannelTexts` would have needed **two** entries
     * on those lists; putting it here needs one, and keeps this class the one
     * file in the application that touches either table.
     * {@see NumberLookup} reads `owner_notify_numbers`
     * through `OwnerConsentService` for the identical reason.
     *
     * ⛔ **AND A CHOKEPOINT OVER THE TABLE IS NOT A CHOKEPOINT OVER THE WORDS —
     * WAVE 42, DECISION 11230.** The paragraph above is true and was read as
     * more than it says. This method returns `owner_replies.body` as a **plain
     * array value**, so a caller obtains an account holder's own text without
     * spelling `OwnerReply` or `owner_replies` anywhere: the census described
     * above could not see its own consumer, and a job planted in `app/Jobs/`
     * that branched on `str_contains($reply['body'], …)` — a machine acting on
     * what an account holder typed, which is the half of **10722** still
     * claimed — left the whole of `tests/Feature/Architecture/` green.
     * **A second census now stands over the CALL** (`OwnerChannelTest.php`,
     * *"nothing in app/ reaches an owner reply through the method that hands
     * the words out"*), over `app/`, `routes/` and `resources/views/`, with the
     * public surface of this class pinned exactly so a second accessor cannot
     * be added under a name that census does not know.
     * ⚠️ **So the arithmetic in the paragraph above has changed and the choice
     * has not**: keeping the query here costs one entry on the table census and
     * two on the accessor census; moving it to the screen would cost two and
     * two. **Adding a public method to this class is a build failure until it
     * is argued.**
     *
     * ⛔ **PLAIN ARRAYS AND NEVER MODELS, AND HERE IT IS LOAD-BEARING TWICE.**
     * {@see NumberLookup::timeline()}'s reason is the first: both models refuse
     * `updating` in `booted()`, so handing a Blade file a live instance is one
     * `->save()` away from an exception in a view. The second is the lint above
     * — a `use App\Models\OwnerReply` on the screen for a type hint alone is an
     * offender, and a type hint is not worth an entry on a list whose whole job
     * is to be short.
     *
     * ⚠️ **CAPPED, AND THE CAP IS VISIBLE IN THE ANSWER.** Nothing bounds
     * either table on a running install: `owner_channel.retention_days` ships
     * with no seed and an unset period is a no-op rather than a zero (10834),
     * so an unbounded `get()` is a page that gets slower for ever. The screen
     * says which rows it is showing rather than implying it shows all of them.
     *
     * ⚠️ **`orderByDesc('id')` FOR {@see self::latestFor()}'s STATED REASON** —
     * the primary key is `NOT NULL` by construction where a timestamp column is
     * one migration from being nullable, and both writers stamp their timestamp
     * at insert, so insertion order is event order on real data.
     *
     * @return array{
     *     sends: list<array{id: int, kind: OwnerNotificationKind, occasion: string, carrierReference: string, at: CarbonImmutable}>,
     *     replies: list<array{id: int, answering: ?int, body: string, at: ?CarbonImmutable}>,
     * }
     */
    public function ledgerFor(int $businessId, ?int $limit = null): array
    {
        $limit ??= $this->ledgerLimit();
        return Tenancy::actingAs($businessId, function () use ($limit): array {
            $sends = array_values(OwnerNotification::query()
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->map(static fn (OwnerNotification $sent): array => [
                    'id' => (int) $sent->getKey(),
                    'kind' => $sent->kind,
                    'occasion' => $sent->occasion,
                    'carrierReference' => $sent->provider_message_id,
                    'at' => CarbonImmutable::instance($sent->sent_at),
                ])
                ->all());

            $replies = array_values(OwnerReply::query()
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->map(static fn (OwnerReply $reply): array => [
                    'id' => (int) $reply->getKey(),
                    'answering' => $reply->in_reply_to_notification_id,
                    'body' => $reply->body,
                    // ⚠️ **THE CARRIER'S OWN TIMESTAMP FIRST.** `received_at` is
                    // when the account holder's phone sent it and `created_at`
                    // is when our webhook got round to it; a redelivered
                    // webhook hours later would otherwise render the reply as
                    // arriving hours after it did.
                    'at' => match (true) {
                        $reply->received_at !== null => CarbonImmutable::instance($reply->received_at),
                        $reply->created_at !== null => CarbonImmutable::instance($reply->created_at),
                        default => null,
                    },
                ])
                ->all());

            return ['sends' => $sends, 'replies' => $replies];
        });
    }
}
