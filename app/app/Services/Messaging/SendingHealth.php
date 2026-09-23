<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Console\Commands\PruneSendingHealth;
use App\Enums\OutreachChannel;
use App\Models\SendingHealthWindow;
use App\Services\Config\DefaultsRegistry;
use App\Services\Mail\MailSendingHealth;
use App\Services\Mail\MailSettlement;
use App\Services\Messaging\Outbound\SendSettlement;
use App\Services\Sms\DeliveryReceipts;
use App\Services\Sms\InboundMessages;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Per-tenant delivery, opt-out and complaint counters — T137 §3.2.
 *
 * *"Per-tenant observability — delivery rate, opt-out rate, bounce/complaint per
 * tenant number, on the admin dashboard, with alert thresholds; this is the
 * operational form of 'each tenant has their own number.'"*
 *
 * This is the **writer and the reader**. {@see SendingGuard} turns what this
 * reports into a decision; nothing else may.
 *
 * ## Who actually calls the increments — and the eleven days nobody did
 *
 * ⛔ **THIS CLASS SHIPPED WITH NO CALLER ANYWHERE IN `app/` AND TWO SLICES WERE
 * BUILT ON TOP OF IT BEFORE ANYBODY NOTICED** (2496–2499). Every counter was
 * zero in production and could only ever be zero, so `SendingGuard::shouldTrip()`
 * asked for a rate, `hasEnoughVolume()` answered false against an empty window,
 * and the guard returned *"may send"* forever — with a green suite over it,
 * because every test seeds these columns by hand. `CLAUDE.md`'s first recurring
 * failure shape, arriving on a containment rather than a feature.
 *
 * The writers, wired 2026-08-12 and named here so the next reader can check they
 * are still there rather than assume it:
 *
 *   `delivered`, `failed`  {@see DeliveryReceipts::apply()} —
 *                          the Infobip DLR webhook, once per genuine transition.
 *   `opted_out`,
 *   `complaints`           {@see InboundMessages} — a carrier
 *                          STOP, charged to the tenant whose number it arrived
 *                          on. **Counted nowhere when it arrives on the shared
 *                          pool number**, which under-reports the rate.
 *   `sent`                 {@see SendSettlement}
 *                          — the moment a row acquires a `provider_msg_id`,
 *                          the one event both send paths share. Wired
 *                          2026-08-13 (3030–3040); this entry read
 *                          **"⛔ STILL NOTHING"** until then.
 *
 * ⚠️ **`sent` TOOK TWO REFUSALS TO PLACE, WHICH IS WORTH KNOWING BEFORE MOVING
 * IT.** 2499 refused the campaign runner and 2977 refused the invite path, both
 * because **a counter written by one send path out of two is worse than one
 * written by none** — a partial denominator renders as a confident percentage
 * where {@see RateReading} would otherwise show an honest em dash. What resolved
 * it was not a better caller but a rule (3032): **the denominator must have the
 * same membership as the numerator.** `delivered` is only ever written for a row
 * {@see DeliveryReceipts::apply()} can find, and it finds rows by
 * `provider_msg_id` — so `sent` counts exactly the rows that get one.
 * ⛔ **"TWO LIVE SEND PATHS ARE OUTSIDE THIS FIGURE — COMPLIANCE AUTO-REPLIES
 * AND ALL EMAIL" WAS TRUE OF THE SECOND AND IS NOT — CORRECTED 2026-08-20
 * (6360).** Compliance auto-replies are still outside it and always will be:
 * they write no row at all and often have no tenant to charge. **Email is
 * inside it now**, on the same terms as SMS — the id is written when the
 * transport names the message, and `delivered` is only ever written for a row
 * that id can find. ⚠️ **The counters are still per channel**, so this is
 * a carrier-SMS delivery rate when read for `OutreachChannel::Sms` and an email
 * one when read for `Email`; neither is the platform's overall rate, and
 * nothing here ever adds them together.
 *
 * ⛔ **"AND EMAIL WRITES NOTHING AT ALL" WAS TRUE AND IS NOT — CORRECTED
 * 2026-08-20 (6360).** This paragraph read: *"`MailFeedback` receives typed SES
 * bounce and complaint events, and there is no way back from one to a tenant:
 * SMS carries the business id out as Infobip's `callbackData` and reads it home,
 * and mail has no equivalent — nothing stores an SES message id against an
 * `outreach_messages` row. So `OutreachChannel::Email` has no window in this
 * table and no complaint rate. A per-channel threshold set on it today is set
 * on nothing."*
 *
 * ✅ **THE JOIN EXISTS AND ALL FOUR EMAIL COUNTERS HAVE WRITERS.** The SES
 * message id is written at send time onto `mail_tracking_codes` — the
 * un-tenanted row that already answers *whose message was this* for an inbound
 * reply — so a feedback event resolves a tenant and then finds the message
 * under it:
 *
 *   `sent`                 {@see MailSettlement} → this
 *                          same {@see SendSettlement}, on Laravel's
 *                          `MessageSent` event.
 *   `delivered`,
 *   `failed`,
 *   `complaints`           {@see MailSendingHealth}, from
 *                          the SES `Delivery`, `Bounce` and `Complaint` events
 *                          that reach `MailFeedback`.
 *
 * ⛔ **`delivered` IS THE ONE THAT DECIDED WHETHER ANY OF IT WORKED.** The
 * complaint rate divides by it and {@see SendingRates::hasEnoughVolume()} reads
 * it, so wiring the complaint event without the delivery event would have
 * produced a rate that is permanently zero **with complaints in the numerator**
 * — this table's own founding defect, rebuilt one channel over, with a green
 * suite over it for the same reason as last time.
 *
 * ⚠️ **NO REAL SES EVENT HAS EVER REACHED THIS APPLICATION.** Open question H:
 * an SES account, production access and a signed DPA are the owner's, and until
 * they exist every email counter here is exercised only by tests. The mechanism
 * is proven; no traffic has flowed through it.
 *
 * ⛔ **`opted_out` IS THE ONE EMAIL COUNTER WITH NO WRITER, AND IT IS NOT IN
 * THE LIST ABOVE BECAUSE THERE ARE FOUR OF THEM AND FIVE COUNTERS** (6662).
 * {@see self::recordOptOut()} has exactly one caller in `app/` —
 * {@see InboundMessages} — and it names `OutreachChannel::Sms` as a **literal**,
 * so no email unsubscribe ever reaches this column. **The consequence is worse
 * than a missing figure and it lands on a reader rather than here**: `sent`,
 * `delivered` and `complaints` all move for email now, so a screen rendering an
 * email opt-out rate would divide a permanently-zero numerator by a denominator
 * that is genuinely growing — and {@see RateReading} would correctly call that
 * *measured*, printing a confident **0.0%** against real volume. That is
 * 2496's defect rebuilt as a rendering rather than as a counter. The sending-
 * controls screen therefore renders **no** email opt-out card and says why
 * (6662); the card comes back when a writer does.
 * ⚠️ **It feeds no trip either way** — {@see SendingGuard} reads only
 * `complaints` over `delivered` — so this is a missing figure and not a missing
 * containment, which is the one thing that makes it survivable.
 *
 * ⚠️ **AND THE THREE PATHS THAT ARE STILL OUTSIDE THE FIGURE ARE UNCHANGED**:
 * compliance auto-replies, which write no row at all; platform mail to an
 * account holder, which has no tenant, no consent record and no outreach row;
 * and any customer email tracked without an `outreach_messages` row. All three
 * are outside the numerator too, which is what keeps 3032's membership rule
 * true.
 *
 * ## Every write is an atomic increment, never a read-modify-write
 *
 * ⚠️ **THE OBVIOUS IMPLEMENTATION UNDERCOUNTS AND THE UNDERCOUNT SUPPRESSES THE
 * TRIP.** `$window->sent++; $window->save()` across two queue workers in the
 * same hour loses one of the two increments — and a complaint counter that reads
 * low is a containment that does not fire. So every write is a single
 * `INSERT … ON CONFLICT DO UPDATE SET col = col + 1`, which is correct under any
 * amount of concurrency because the database does the addition.
 *
 * ⚠️ **AND THE UPSERT IS WHY THE UNIQUE INDEX IS LOAD-BEARING RATHER THAN
 * TIDY.** Without `(business_id, window_start, channel)` unique, `ON CONFLICT`
 * has nothing to conflict on, every send inserts a fresh row, and every rate
 * divides by a denominator scattered across hundreds of rows.
 *
 * ## Rates are basis points, never floats
 *
 * A rate crosses a threshold, gets stored on a pause row, and is compared
 * against a configured number. `0.02` as a float compares badly against itself
 * across a serialisation boundary, and this codebase already stores money as
 * integer cents for the same reason. **200 basis points is 2%.**
 *
 * ## The complaint signal, and the honest thing to say about it
 *
 * ⛔ **THIS PLATFORM HAS NO CARRIER FEEDBACK LOOP AND THEREFORE NO TRUE
 * COMPLAINT SIGNAL.** A 10DLC complaint is reported by the carrier out of band;
 * nothing in this repository receives one. What {@see self::recordComplaint()}
 * is actually called with today is **a STOP that arrived in reply to a message
 * we sent** — the closest observable proxy, and a real one: an unsubscribe
 * immediately following an unsolicited message is what a complaint is made of.
 *
 * ⚠️ **IT IS DELIBERATELY NOT EVERY OPT-OUT** (and `opted_out` counts those
 * separately). Somebody unsubscribing from a list they joined is exercising a
 * right the product is required to offer; counting it as a complaint would trip
 * the halt on a healthy list and teach an operator to raise the threshold until
 * it never fires — decision 511's failure, applied to a kill switch.
 *
 * **The name is `complaints` rather than `stops_after_send` on purpose**: when a
 * real carrier feedback loop is wired, it writes to this same counter and every
 * threshold, screen and trip keeps working. What must not happen is a second
 * complaint counter beside this one, because then the trip reads one of them.
 */
final class SendingHealth
{
    /**
     * How many hours of history a rate is computed over.
     *
     * ⚠️ **NOT CONFIGURABLE, DELIBERATELY.** A tenant-facing knob here would be
     * a tenant tuning their own containment, and an operator-facing one is a
     * number somebody widens after an alert until the alert stops. Twenty-four
     * hours is long enough that a small list cannot trip on one unlucky
     * recipient and short enough that yesterday's fixed problem stops
     * suppressing today's sending.
     */
    public const int WINDOW_HOURS = 24;

    public function windowHours(): int
    {
        return app(DefaultsRegistry::class)->int('messaging.health.window_hours');
    }

    public function retentionDays(): int
    {
        return app(DefaultsRegistry::class)->int('messaging.health.retention_days');
    }

    /**
     * How long a tenant's hourly buckets are kept — thirty days (7760-7779).
     *
     * ⛔ **`platform_health_windows`' THIRTY, TAKEN DELIBERATELY, BECAUSE THAT
     * TABLE'S CREATING MIGRATION CALLS THIS ONE ITS OWN PER-TENANT TWIN.**
     * `WatchPlatformHealth::KEEP_DAYS`' argument transfers word for word:
     * *"a configurable retention here would be a setting whose only possible
     * effect is to make the table bigger"*, and thirty days is long enough for
     * an incident review to look at last week.
     *
     * ## ⛔ THE FLOOR IS ONE DAY, IT IS DERIVED, AND IT IS ENFORCED BY THE CLAMP
     * RATHER THAN BY THIS NUMBER
     *
     * {@see self::rates()} sums {@see $this->windowHours()} of buckets and is the
     * **only** reader this table has: {@see SendingGuard},
     * {@see PlatformComplaintRate} and `Admin\SendingControls` all reach it
     * through that one method and none of them asks for a wider window. So the
     * horizon is a decision about disk and about what a person can still look
     * up; **what the complaint trip can see is twenty-four hours by
     * construction and no horizon above a day changes it.** The clamp in
     * {@see self::prune()} is what guarantees that, not this constant.
     *
     * ## Thirty is a choice above that floor and is written as one
     *
     * ⚠️ **NOTHING IN `app/` READS A BUCKET OLDER THAN A DAY**, so every day
     * above the first is for a person. Two of them exist. An operator reading
     * `Admin\SendingControls` after a pause wants last week, and the pause row
     * itself already keeps the figure that tripped it —
     * `sending_pauses.observed_rate_bp`, 2119(a) — which is `operator_alerts.
     * context` one table over: **the surviving copy of the evidence is on the
     * incident, not in the counters**, and that is what makes a thirty-day
     * horizon on the counters safe.
     *
     * ⚠️ **NOT A REGISTRY KEY, AND `storage.retention_days.*`'s "THE OWNER MUST
     * RULE" REASONING DOES NOT REACH IT** (4941). That one exists because those
     * objects are other people's personal data. **Five integers per tenant per
     * hour per channel are not personal data and are not anybody's but ours** —
     * no name, no number, no message, nothing a person could be picked out of.
     * This is the same footing `OperatorAlerts::RETENTION_DAYS` stands on.
     *
     * ⛔ **A YEAR WAS CONSIDERED AND THE ARGUMENT FOR IT DOES NOT TRANSFER.**
     * `OperatorAlerts::RETENTION_DAYS` is 365 so somebody can ask *"is this the
     * third time this year"* about a bell that rang. Nothing asks that of a
     * counter: the recurrence question is asked of `sending_pauses`, which is
     * append-only and untouched by this.
     */
    public const int RETENTION_DAYS = 30;

    /**
     * Rows per DELETE — `PruneTrialOriginClaims`' figure and its reasoning.
     */
    public const int CHUNK = 500;

    public function recordSent(OutreachChannel $channel): void
    {
        $this->increment($channel, 'sent');
    }

    public function recordDelivered(OutreachChannel $channel): void
    {
        $this->increment($channel, 'delivered');
    }

    public function recordFailed(OutreachChannel $channel): void
    {
        $this->increment($channel, 'failed');
    }

    public function recordOptOut(OutreachChannel $channel): void
    {
        $this->increment($channel, 'opted_out');
    }

    /**
     * A complaint, or today's honest proxy for one. See the class docblock.
     */
    public function recordComplaint(OutreachChannel $channel): void
    {
        $this->increment($channel, 'complaints');
    }

    /**
     * This tenant's rates over the rolling window.
     */
    public function rates(OutreachChannel $channel, ?CarbonImmutable $now = null): SendingRates
    {
        $now ??= CarbonImmutable::now();
        $since = $this->bucket($now)->subHours($this->windowHours() - 1);

        /** @var object{sent: int|string|null, delivered: int|string|null, failed: int|string|null, opted_out: int|string|null, complaints: int|string|null}|null $totals */
        $totals = SendingHealthWindow::query()
            ->where('channel', $channel->value)
            ->where('window_start', '>=', $since)
            ->selectRaw('COALESCE(SUM(sent), 0) AS sent')
            ->selectRaw('COALESCE(SUM(delivered), 0) AS delivered')
            ->selectRaw('COALESCE(SUM(failed), 0) AS failed')
            ->selectRaw('COALESCE(SUM(opted_out), 0) AS opted_out')
            ->selectRaw('COALESCE(SUM(complaints), 0) AS complaints')
            ->first();

        return new SendingRates(
            sent: (int) ($totals->sent ?? 0),
            delivered: (int) ($totals->delivered ?? 0),
            failed: (int) ($totals->failed ?? 0),
            optedOut: (int) ($totals->opted_out ?? 0),
            complaints: (int) ($totals->complaints ?? 0),
        );
    }

    /**
     * Drop buckets past the retention horizon — T137 §3.5's prune, and 2199's
     * missing caller (7760-7779).
     *
     * ## ⛔ THIS SAID THE DELETE "STEPS OUTSIDE THE TENANT SCOPE" AND THE
     * DATABASE HAS REFUSED TO LET IT SINCE THE DAY IT WAS WRITTEN — BOTH
     * READINGS KEPT AND DATED
     *
     * It read: *"A RANGE DELETE ON AN INDEXED COLUMN, AND NOT TENANT BY TENANT.
     * This runs as maintenance across the platform, so it deliberately steps
     * outside the tenant scope — which is the one operation on this table that
     * may, and is why it is here rather than in a job somebody might copy."*
     *
     * ⛔ **`DB::table()` steps outside the Eloquent global scope and outside
     * nothing else.** This table is `ENABLE`+`FORCE` row-level secured on
     * `app.business_id` and the application connects as a non-owner role, so the
     * platform-wide delete that sentence describes, issued from a command with
     * no tenant established, matches **zero rows and exits 0** — 7626 exactly,
     * one table over. ⚠️ **The sentence is what would have stopped the next
     * reader checking** (314-316), and there was no caller to disprove it with:
     * for eleven months the only callers of this method anywhere were two lines
     * of `SendingHealthTest`, both inside `Tenancy::actingAs()`, where the
     * policy passes and the delete works perfectly.
     *
     * ✅ **So the sweep is {@see PruneSendingHealth}, `RefreshOauthTokens`' owner
     * walk for the eighth time**, and the delete runs under an established
     * tenant with the ordinary global scope on and row-level security beneath
     * it — which is why this now goes through {@see SendingHealthWindow} rather
     * than through `DB::table()`. Two layers, neither substituting for the
     * other.
     *
     * ## ⛔ THE CLAMP, AND IT IS A CONTAINMENT RATHER THAN TIDINESS
     *
     * ⛔ **THE BUCKETS THIS COULD CUT INTO ARE THE COMPLAINT TRIP'S OWN
     * DENOMINATOR.** {@see self::rates()} sums `window_start >= bucket(now) -
     * (WINDOW_HOURS - 1)` and every reader of this table reaches it through that
     * one method — {@see SendingGuard::shouldTrip()},
     * {@see PlatformComplaintRate} and `Admin\SendingControls` included. A cut
     * inside that window does not lose history; it lowers a **live** complaint
     * rate towards zero, and a rate that reads low is a containment that does not
     * fire (2101, 2102, 2113). `sending_health_windows`' founding defect,
     * rebuilt with a DELETE in it.
     *
     * ⚠️ **SO THE CUT IS THE EARLIER OF THE HORIZON AND THE READER'S OWN FLOOR**,
     * `OperatorAlerts::prune()`'s quiet-window rule and `IngestRejects::prune()`'s
     * `RECENT_WINDOW_HOURS` rule for the third reader in the family. The floor is
     * derived from {@see self::rates()}'s own expression rather than restated, so
     * the two cannot disagree about where the boundary is.
     *
     * ⛔ **AND THERE IS DELIBERATELY NO `max(1, $keepDays)` IN FRONT OF IT**,
     * which both siblings carry. With one there the clamp could never bite —
     * `bucket(now)->subDays(1)` is always earlier than
     * `bucket(now)->subHours(23)` — so the guard that actually protects the trip
     * would be unfalsifiable and every test of it would pass against a deleted
     * line (398). The clamp subsumes the floor: a `$keepDays` of `0`, or a
     * negative one, resolves to the reader's floor rather than to the current
     * hour, and `a horizon of zero cannot cut into the window the trip divides
     * by` drives it directly.
     *
     * ## Chunked, because the first cut is not the ordinary one
     *
     * ⚠️ **ON AN ORDINARY NIGHT THIS MATCHES 48 ROWS PER TENANT** — two channels
     * times twenty-four hours, one day falling off a thirty-day horizon. **The
     * run that matters is the first one**, which on a platform that has been
     * sending since before this shipped matches every bucket ever written, in one
     * statement, per tenant. `PruneTrialOriginClaims`' 500 for
     * `PruneTrialOriginClaims`' reason.
     *
     * ## ⚠️ A FAILED PRUNE IS NOT A FAILED RUN (7630)
     *
     * The caller is a walk, so an unreachable row for tenant seventeen must not
     * cost tenants eighteen onward their sweep. ⛔ **The cost is stated rather
     * than hidden: `0` is the one number this cannot tell apart from a tenant
     * with nothing to delete**, which is what the `warning` is for — a prune
     * failing silently for a year is this table's own shape with a DELETE in it.
     *
     * @param  int  $keepDays  {@see $this->retentionDays()}, passed by the caller
     *                         rather than read here, on `IngestRejects::prune()`'s
     *                         shape
     * @return int rows removed
     */
    public function prune(int $keepDays, ?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();

        try {
            $horizon = $this->bucket($now)->subDays($keepDays);

            // The oldest bucket `rates()` still sums, written as that method
            // writes it. Derived from the reader rather than restated, so a
            // change to the rolling window moves this floor with it.
            $reader = $this->bucket($now)->subHours($this->windowHours() - 1);

            $cut = $horizon->lessThan($reader) ? $horizon : $reader;

            $deleted = 0;

            do {
                // Through the model, so the global scope adds `business_id` and
                // row-level security sits under it. Postgres compiles a limited
                // DELETE to `delete from … where ctid in (select … limit N)` —
                // verified in `PostgresGrammar::compileDeleteWithJoinsOrLimit()`
                // rather than remembered — and the inner select carries the
                // scope's predicate.
                $batch = SendingHealthWindow::query()
                    ->where('window_start', '<', $cut)
                    ->limit(self::CHUNK)
                    ->delete();

                $deleted += $batch;
            } while ($batch === self::CHUNK);

            return $deleted;
        } catch (Throwable $e) {
            Log::warning('sending health windows could not be pruned', [
                'business_id' => Tenancy::id(),
                'keep_days' => $keepDays,
                'exception' => $e::class,
            ]);

            return 0;
        }
    }

    /**
     * The atomic increment.
     *
     * ⚠️ **`DB::table()` RATHER THAN THE MODEL, AND THE TENANT PREDICATE IS
     * WRITTEN BY HAND BECAUSE OF IT.** Eloquent has no upsert-with-increment,
     * and a global scope does not reach a raw upsert — so `business_id` is set
     * explicitly from `Tenancy::idOrFail()`, which throws rather than writing a
     * null. **RLS is the second layer here and it is doing real work**: the
     * `WITH CHECK` on the policy refuses an insert naming another tenant even
     * though this query bypasses the scope entirely.
     *
     * @param  'sent'|'delivered'|'failed'|'opted_out'|'complaints'  $column
     */
    private function increment(OutreachChannel $channel, string $column): void
    {
        $now = CarbonImmutable::now();

        DB::table('sending_health_windows')->upsert(
            [[
                'business_id' => Tenancy::idOrFail(),
                'window_start' => $this->bucket($now),
                'channel' => $channel->value,
                $column => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['business_id', 'window_start', 'channel'],
            // ⚠️ The raw expression is the whole point: `[$column => 1]` would
            // SET the counter to one on every conflict, which reads as "this
            // tenant has sent exactly one message, ever" and makes every rate
            // meaningless while looking entirely plausible on the screen.
            [$column => DB::raw("sending_health_windows.{$column} + 1"), 'updated_at' => $now],
        );
    }

    /**
     * The hour a moment belongs to. Always UTC — see the creating migration.
     */
    private function bucket(CarbonImmutable $at): CarbonImmutable
    {
        return $at->utc()->startOfHour();
    }
}
