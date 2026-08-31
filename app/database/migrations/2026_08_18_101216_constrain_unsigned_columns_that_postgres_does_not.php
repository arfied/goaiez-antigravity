<?php

declare(strict_types=1);

use App\Services\Ai\AiResponse;
use App\Services\Voice\VoiceCalls;
use App\Services\Warehouse\Replayer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ⛔ **`unsignedInteger()` IS DOCUMENTATION ON POSTGRES, NOT ENFORCEMENT.**
 *
 * Laravel's `unsignedInteger()`, `unsignedBigInteger()`, `unsignedSmallInteger()`
 * and `unsignedTinyInteger()` emit a plain `integer` / `bigint` / `smallint` on
 * Postgres, because **Postgres has no unsigned integer type at all.** The word
 * survives in the migration and nowhere in the database, so a negative value
 * stores without complaint. This is the exact opposite of MySQL, where `UNSIGNED`
 * genuinely rejects the row — and this project moved from MySQL to Postgres on
 * 2026-07-30 (decisions 131–134), so every `unsigned*` written before that date
 * carries a MySQL author's expectation that Postgres does not honour.
 *
 * ⚠️ **THE FINDING THAT PROMPTED THE AUDIT IS WHY THIS MATTERS MORE THAN IT
 * READS.** The w23 warehouse lane posted `active_ms: -100000` through the real
 * collector route and stored `active_s = -100`, silently. Its five hostile
 * siblings were *loud* — `SQLSTATE[22P02]` and `[22003]` — and that is precisely
 * what makes this one dangerous: **a negative duration is a plausible number on a
 * dashboard**, where a type error announces itself at the write.
 *
 * ## What this migration constrains, and what it deliberately does not
 *
 * Every `unsigned*` column in the schema was enumerated and classified by where
 * its value comes from:
 *
 *  · **Sequence-fed or foreign-key-fed** — `bigserial` ids and references to
 *    another table's id. Postgres supplies these itself and a negative is
 *    unreachable, so a CHECK on one could only ever be satisfied. **Not
 *    constrained**, on 256's rule: a lint that matches nothing passes vacuously.
 *  · **Derived from client or vendor input, or computed by arithmetic** —
 *    counts, durations, byte sizes, ordinals, token counts, basis points, cents.
 *    A negative is reachable and would render as a number rather than an error.
 *    **These are what follow.**
 *  · **Framework-owned** — `jobs`, `job_batches`, `cache`, `sessions`,
 *    `migrations`. Laravel's own tables, written by Laravel's own drivers.
 *    **Not constrained**: the queue driver's arithmetic is not ours to bound,
 *    and a CHECK that aborts a queue write is an outage rather than a guard.
 *  · **Aggregate-fed** — `l2_fact_source_daily`'s four counters are written by
 *    one `INSERT … SELECT count(*) FILTER (…)` in
 *    {@see Replayer}, so a negative is unreachable
 *    from today's writer. ⛔ **They are constrained anyway, reversing this
 *    migration's first draft** — see the entry itself for why "unreachable
 *    today" is the wrong test for a CHECK, and why the draft's own rule would
 *    have skipped two tables it constrained.
 *
 * ⚠️ **A CONSTRAINT WHOSE NAME MENTIONS A COLUMN IS NOT A CONSTRAINT ON THAT
 * COLUMN'S SIGN, AND THAT IS HOW ONE TABLE WAS NEARLY MISSED.**
 * `gsc_daily_snapshots` carried `gsc_daily_snapshots_clicks_within_impressions`
 * and read as covered; the predicate is `clicks <= impressions`, which
 * `(-5, -1)` satisfies. Every "already covered" verdict in this audit was
 * reached by reading `pg_get_constraintdef()` rather than `conname`.
 *
 * ## Why a CHECK here and a clamp there
 *
 * A CHECK aborts the write. That is the right answer when the row is wrong and
 * losing it is the lesser harm — a health counter, a rollup, a money figure
 * nobody should be able to hand-edit negative.
 *
 * It is the *wrong* answer when the row is a record of something that has
 * already happened and cannot be un-happened. Three writes are therefore
 * **clamped at the write as well**, and the clamp is what runs in production
 * while the CHECK below stands behind it for the hand-written `UPDATE`:
 *
 *  · `ai_calls.input_tokens` / `output_tokens` — see {@see AiResponse}.
 *  · `calls.ring_seconds` and `voicemails.recording_seconds` — see
 *    {@see VoiceCalls}.
 *
 * `voice_usage_events.billable_seconds` and `agent_nudges.armed_at_turns` were
 * **already** clamped with `max(0, …)` by their writers before this migration;
 * the CHECKs below are the backstop those clamps did not have.
 *
 * ## Tenancy
 *
 * No table is created and no policy is touched, so every table keeps the RLS
 * `ENABLE`+`FORCE` and the policy its creating migration gave it. A CHECK
 * constraint is orthogonal to row-level security: it is evaluated on the row
 * being written, after the policy has already decided the write is permitted.
 */
return new class extends Migration
{
    /**
     * Every constraint this migration adds, as `[table, name, predicate]`.
     *
     * One list rather than sixty `DB::statement()` calls, because `down()` has to
     * name each of them again and two hand-maintained lists of sixty drift.
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function constraints(): array
    {
        return [
            /*
             * The turn count a nudge was armed at. `AgentNudges` already writes
             * `max(0, $conversation->agent_turns_used)`; this is the backstop.
             */
            ['agent_nudges', 'agent_nudges_armed_at_turns_is_not_negative', 'armed_at_turns >= 0'],

            /*
             * ⛔ **THE SHARPEST ONE IN THIS MIGRATION, AND IT IS A COST CEILING
             * RATHER THAN A TIDY-UP.**
             *
             * Both AI clients read the provider's own JSON —
             * `(int) data_get($payload, 'usage.input_tokens', 0)` in
             * `AnthropicClient`, `usage.prompt_tokens` in `OpenAiClient` — and
             * hand it to `AiModel::costOf()`, which multiplies. A negative token
             * count therefore produces a **negative cost**, and
             * `AiSpend::allows()` bounds an unfunded tenant by
             * `sum('cost_hundredths_cents')` against `ai.monthly_cap_per_tenant`.
             * One negative row does not merely misreport spend: **it raises the
             * effective cap**, by exactly the amount it lies about.
             *
             * ⚠️ The ledger itself is safe and was checked rather than assumed —
             * `AiCredits::debitForCall()` returns early on `< 1`, so no negative
             * debit reaches `credit_ledger`. The damage is confined to this table
             * and to the two sums that read it, which is enough.
             */
            ['ai_calls', 'ai_calls_tokens_and_costs_are_not_negative', 'input_tokens >= 0 AND output_tokens >= 0 AND cost_hundredths_cents >= 0 AND retail_hundredths_cents >= 0'],

            /* A count of consecutive failures. Reset to 0, incremented by 1. */
            ['auto_topup_arrangements', 'auto_topup_arrangements_consecutive_failures_is_not_negative', 'consecutive_failures >= 0'],

            /*
             * How long the phone rang, from the vendor's webhook body. Clamped at
             * `VoiceCalls` too — a call record is worth more than a duration, and
             * aborting the write to reject the duration loses both.
             */
            ['calls', 'calls_ring_seconds_is_not_negative', 'ring_seconds IS NULL OR ring_seconds >= 0'],

            /* `strlen()` of the fetched bytes. Cannot be negative today; one future writer away from it. */
            ['campaign_recipients', 'campaign_recipients_media_bytes_is_not_negative', 'media_bytes IS NULL OR media_bytes >= 0'],

            /* A competitor's review count, scraped from somebody else's page. */
            ['competitor_snapshots', 'competitor_snapshots_review_count_is_not_negative', 'review_count >= 0'],

            /*
             * ⚠️ `agent_turn_cap` IS BOUNDED BELOW BY 0 AND NOT BY 1. A cap of
             * zero is a real setting — it is "this conversation takes no agent
             * turns at all" — and `AgentTurns` compares `used >= cap`, which is
             * already true at zero. A `> 0` here would forbid the one value that
             * turns the assistant off for a thread.
             */
            ['conversations', 'conversations_agent_turns_are_not_negative', 'agent_turns_used >= 0 AND (agent_turn_cap IS NULL OR agent_turn_cap >= 0)'],

            /*
             * `Dunning` writes `max('sequence') + 1`, so the first attempt is 1
             * and there is no attempt zero. Constrained at `>= 1` rather than
             * `>= 0` because the seed is the maximum of an empty set coalesced to
             * zero, and a stored `0` would mean that coalesce leaked into a row.
             */
            ['dunning_attempts', 'dunning_attempts_sequence_is_positive', 'sequence >= 1'],

            /*
             * The ETL run's own counts of what it read and wrote.
             *
             * ⚠️ `l0_rejected` IS THE ONE A READER ACTS ON. `warehouse:replay`
             * prints `⚠️ N rejected` only when it is `> 0`, so a negative value
             * silences the warning about the lines a replay could not parse —
             * and this is the table row 1's byte-identical replay gate reads.
             */
            ['etl_runs', 'etl_runs_counts_are_not_negative', 'l0_objects >= 0 AND l0_lines >= 0 AND l1_rows >= 0 AND l2_rows >= 0 AND l0_rejected >= 0'],

            /*
             * A FLOOR AND DELIBERATELY NOT A CEILING. A stored `0` — the shape a
             * transport failure takes when somebody casts a null — reads on a
             * screen as a status the server sent, and `2` reads as nothing at
             * all, so `>= 100` is worth having.
             *
             * ⛔ **AN UPPER BOUND OF 599 WAS DRAFTED AND IS REFUSED.** RFC 9110
             * §15 defines 1xx–5xx, but this gateway fetches *arbitrary third-party
             * URLs* and real servers answer outside that range — LinkedIn's `999`
             * is the standing example. `fetch_attempts` is not a report, it is
             * the **cooldown ledger**: `nextCooldownHours()` counts these rows
             * and `isRateLimited()` reads them, so a CHECK that aborted the
             * insert would drop the record of the very fetch that misbehaved and
             * leave us hammering the site that produced it. A stored `999` is
             * visibly not a success; a missing cooldown row is invisible and
             * makes us the problem. A value large enough to matter overflows
             * `smallint` and raises `SQLSTATE[22003]` loudly on its own.
             */
            ['fetch_attempts', 'fetch_attempts_http_status_is_a_real_status', 'http_status IS NULL OR http_status >= 100'],

            /* What the fetch cost us, in millionths of a cent. */
            ['fetch_attempts', 'fetch_attempts_cost_micros_is_not_negative', 'cost_micros IS NULL OR cost_micros >= 0'],

            /*
             * ⛔ **THE ONE THIS AUDIT NEARLY MISSED, AND IT IS THE w23 SHAPE
             * EXACTLY.**
             *
             * `gsc_daily_snapshots` already carried a CHECK — but it is
             * `gsc_daily_snapshots_clicks_within_impressions`, `clicks <=
             * impressions`, and **`(-5, -1)` satisfies it perfectly**. A
             * constraint whose name mentions a column is not a constraint on
             * that column's sign, and reading the name rather than the predicate
             * is how this table read as already covered.
             *
             * Both values come from Google's Search Analytics JSON through
             * {@see \App\Services\Gsc\DailyMetrics::count()}, which is
             * `is_numeric($v) ? (int) round((float) $v) : 0` — a `-5` on the wire
             * is a `-5` in the column, and the discovery document types these as
             * `double` rather than integers, so a negative is a well-formed value
             * of the declared wire type rather than a malformed one.
             *
             * ⚠️ **AND IT PROPAGATES INTO A FIGURE THE TENANT IS SHOWN.**
             * `VisibilityTotals` sums both and divides `position * impressions`
             * by `impressions`, so a negative day does not merely subtract — it
             * moves the impression-weighted average position **the wrong way**,
             * and average position is the number decisions 1084/1085 are most
             * careful about.
             *
             * ⚠️ **THE WRITE IS ALSO CLAMPED, AND THAT IS THE HALF THAT RUNS.**
             * `VisibilityReadings::record()` upserts every day of the sync in one
             * statement, so a CHECK here would discard an entire location's sync
             * because of one bad day — the batch-abort case. `count()` clamps;
             * this stands behind it for the hand-written `UPDATE`.
             */
            ['gsc_daily_snapshots', 'gsc_daily_snapshots_counts_are_not_negative', 'clicks >= 0 AND impressions >= 0'],

            /*
             * What a member of staff did while impersonating a tenant.
             *
             * ⚠️ THIS IS AN AUDIT SURFACE AND THAT IS WHY IT IS HERE. The count
             * of writes performed under somebody else's account is the figure a
             * dispute turns on, and "0 writes" is the answer everybody wants to
             * be true.
             */
            ['impersonation_sessions', 'impersonation_sessions_counters_are_not_negative', 'page_views >= 0 AND writes >= 0'],

            /*
             * Position of one attachment within an inbound MMS, from the vendor's
             * media array. Zero-based, so `>= 0`.
             */
            ['inbound_media', 'inbound_media_ordinal_is_not_negative', 'ordinal >= 0'],

            /* `strlen()` of an uploaded knowledge document. */
            ['knowledge_sources', 'knowledge_sources_byte_size_is_not_negative', 'byte_size IS NULL OR byte_size >= 0'],

            /*
             * ⚠️ `>= 1`, AND THE REASON IS THE REPLAY GATE RATHER THAN TIDINESS.
             * It is written from `(int) config('warehouse.schema_version')`, and
             * an unset or unparseable config casts to `0` — so `0` is precisely
             * the value that means "nobody stated a version", which is the one
             * thing a byte-identical replay may not silently accept.
             */
            ['l1_events', 'l1_events_schema_version_is_positive', 'schema_version >= 1'],

            /*
             * ⚠️ **CONSTRAINED, REVERSING THIS MIGRATION'S OWN FIRST DRAFT.**
             *
             * The draft skipped this table because {@see \App\Services\Warehouse\Replayer}
             * writes it with one `INSERT … SELECT count(*) FILTER (…)` and a
             * Postgres `count()` cannot be negative — so a CHECK "could only ever
             * be satisfied", 256's vacuity rule.
             *
             * ⛔ **256 IS A RULE ABOUT LINTS AND THIS IS NOT A LINT.** A lint runs
             * against the code that exists today and passes; a CHECK runs against
             * every write this table will ever take, including the backfill and
             * the Ops `UPDATE` nobody has written yet. Applying the vacuity rule
             * here would also have skipped `sending_health_windows` and
             * `platform_health_windows` below — both fed by atomic increments
             * from zero, both equally unreachable, both constrained two paragraphs
             * on. **The draft applied one rule two ways**, and the resolution is
             * that "unreachable by today's writer" is the wrong test for a
             * constraint: the right one is whether a negative would be
             * *meaningful*, which for a count it never is.
             *
             * ⚠️ The genuinely redundant set is narrower and stays skipped:
             * sequence-fed ids and foreign keys, where Postgres's own sequence and
             * the referential constraint already answer.
             *
             * ⚠️ This table is row 1's byte-identical replay subject, and
             * `WarehouseSnapshot` hashes these four columns.
             */
            ['l2_fact_source_daily', 'l2_fact_source_daily_counters_are_not_negative', 'events >= 0 AND sessions >= 0 AND visitors >= 0 AND bot_events >= 0'],

            /*
             * How many SMS segments were billed. Nullable — the column is null
             * for kinds that are not billed per segment — but a message that has
             * segments has at least one.
             */
            ['message_cost_entries', 'message_cost_entries_segments_is_positive', 'segments IS NULL OR segments >= 1'],

            /* One number's daily delivery counters. */
            ['number_health_daily', 'number_health_daily_counters_are_not_negative', 'sends >= 0 AND accepted >= 0 AND delivered >= 0 AND failed_filtered >= 0 AND failed_other >= 0 AND stops >= 0 AND replies >= 0'],

            /*
             * ⚠️ A RANGE, AND IT IS SATISFIABLE BECAUSE THE WRITER ALREADY CLAMPS.
             * `NumberHealthService::blend()` returns
             * `max(0, min(100, round($blended)))`, verified rather than assumed
             * before this constraint was written — doc 51 §4.3's formula is a
             * weighted sum of four normalised terms times 100, so the clamp is
             * belt-and-braces and this is the third layer. A score of 300 or −5
             * is the plausible-number failure: it sorts, it renders, and it puts
             * a number at the top of a health screen that no formula produced.
             */
            ['number_health_daily', 'number_health_daily_score_is_a_percentage', 'score >= 0 AND score <= 100'],

            /*
             * The generation counter that makes a suppression lift auditable.
             * Three columns across three tables, one meaning: a lift bumps the
             * generation, and a comparison decides whether a suppression is still
             * in force. A negative generation compares wrong in both directions.
             */
            ['opt_outs', 'opt_outs_lift_generation_is_not_negative', 'lift_generation >= 0'],
            ['suppression_list', 'suppression_list_lift_generation_is_not_negative', 'lift_generation >= 0'],
            ['suppression_lifts', 'suppression_lifts_generation_is_not_negative', 'generation >= 0'],

            /* `phone_numbers.health_score`, same clamp and same argument as `number_health_daily.score`. */
            ['phone_numbers', 'phone_numbers_health_score_is_a_percentage', 'health_score >= 0 AND health_score <= 100'],

            /* What one thousand Places calls cost us at this SKU. Zero when served from cache. */
            ['places_api_calls', 'places_api_calls_unit_cents_is_not_negative', 'unit_cents_per_thousand >= 0'],

            /*
             * ⛔ **THE CONTAINMENT'S OWN EVIDENCE ROW.** A halt incident records
             * the numbers the platform halted on, and decisions 2101/2102/2113
             * make that trip a precondition of sending at all. A negative
             * `complaints_in_window` or `rate_basis_points` on the row that
             * *justifies* a halt is the audit trail disagreeing with the action.
             *
             * ⚠️ `window_hours > 0` AND NOT `>= 0`. A window of zero hours is not
             * a short window, it is a division by zero waiting to be reintroduced
             * — the rate is complaints over a window, and a zero-length one
             * describes no measurement at all.
             */
            ['platform_halt_incidents', 'platform_halt_incidents_measures_are_not_negative', 'delivered_in_window >= 0 AND complaints_in_window >= 0 AND rate_basis_points >= 0 AND threshold_basis_points >= 0 AND tenant_count >= 0'],
            ['platform_halt_incidents', 'platform_halt_incidents_window_hours_is_positive', 'window_hours > 0'],

            /* The platform-wide delivery window the halt is computed from. */
            ['platform_health_windows', 'platform_health_windows_counters_are_not_negative', 'total >= 0 AND failures >= 0'],

            /* The three numbers a tenant is shown as proof the system worked. */
            ['proof_numbers', 'proof_numbers_counters_are_not_negative', 'google_reviews >= 0 AND leads >= 0 AND recovered >= 0'],

            /*
             * ⛔ **THIS TABLE IS `CLAUDE.md`'s OWN CAUTIONARY TALE AND THE
             * CONSTRAINT IS THE POINT OF IT.** `sending_health_windows` shipped
             * with no writer at all (2496–2499), so the per-tenant complaint trip
             * and the global halt read a rate that was permanently zero while two
             * slices were built on top of it. The writers exist now and are
             * atomic increments, so a negative cannot arrive from the app — but
             * the lesson recorded there is that *a set threshold on a dead counter
             * is a decoration*, and a negative `complaints` is the same
             * decoration reached a different way: the trip never fires and every
             * rate screen agrees that nothing is wrong.
             */
            ['sending_health_windows', 'sending_health_windows_counters_are_not_negative', 'sent >= 0 AND delivered >= 0 AND failed >= 0 AND opted_out >= 0 AND complaints >= 0'],

            /* The observed complaint rate, in basis points, that justified a pause. */
            ['sending_pauses', 'sending_pauses_observed_rate_is_not_negative', 'observed_rate_bp IS NULL OR observed_rate_bp >= 0'],

            /*
             * The metered half of the inbound-voice path. `VoiceSpend::record()`
             * already writes `max(0, $seconds)`; this is the backstop that clamp
             * did not have, on the one uncapped path a stranger can trigger.
             */
            ['voice_usage_events', 'voice_usage_events_billable_seconds_is_not_negative', 'billable_seconds >= 0'],

            /*
             * Voicemail duration and size. `recording_bytes` is `strlen()` and
             * cannot go negative; `recording_seconds` comes from the vendor and
             * is clamped at `VoiceCalls` for the same reason `ring_seconds` is.
             */
            ['voicemails', 'voicemails_recording_measures_are_not_negative', '(recording_bytes IS NULL OR recording_bytes >= 0) AND (recording_seconds IS NULL OR recording_seconds >= 0)'],
        ];
    }

    /**
     * ⚠️ **THE PRE-FLIGHT IS NOT DEFENSIVENESS, IT IS THE DIAGNOSTIC.**
     *
     * Postgres answers an `ADD CONSTRAINT` that existing rows violate with
     * `check constraint "…" of relation "…" is violated by some row` — it names
     * the constraint and stops, and says neither how many rows nor anything that
     * would let an operator decide what to do about them, on a deploy that has
     * already half-applied the rest of this list.
     *
     * ⛔ **AND A ROW ALREADY HOLDING A NEGATIVE IS A LARGER FINDING THAN THE
     * MISSING CONSTRAINT**: it means the value has been rendered on a screen,
     * summed into a total, or compared against a threshold, and nothing said so.
     * Counting first turns "the deploy failed" into a sentence naming the table,
     * the predicate and how many rows disagree with it.
     *
     * ⚠️ **This ran clean against a schema with ZERO ROWS in every audited
     * table**, which is a fact about this checkout and not about production.
     * The count below is what makes the real answer legible when it is not zero.
     */
    public function up(): void
    {
        foreach ($this->constraints() as [$table, $name, $predicate]) {
            /*
             * ⚠️ **RAW `SELECT`, ON PURPOSE, RATHER THAN `DB::table(…)`.** The
             * count has to run on the same connection as the `ALTER` beneath it
             * or it proves nothing: the runtime role is under FORCE row-level
             * security, so a count taken there would answer `0` for a table full
             * of violations while the `ALTER` two lines down failed for
             * `permission denied`. Going through the same `DB::` default as the
             * DDL is what makes the two incapable of disagreeing.
             *
             * ⚠️ **`NOT (predicate)` IS EXACTLY THE CHECK'S OWN REJECTION
             * RULE**, including its `NULL` handling: a CHECK refuses a row only
             * when its predicate evaluates to `FALSE`, and `WHERE NOT (…)` with
             * a `NULL` predicate is `NULL` and matches nothing. So a nullable
             * column's empty rows are counted by neither, which is correct.
             */
            $offending = (int) DB::selectOne(
                sprintf('SELECT count(*) AS offending FROM %s WHERE NOT (%s)', $table, $predicate)
            )->offending;

            if ($offending > 0) {
                throw new RuntimeException(sprintf(
                    '%s already holds %d row(s) that violate %s — CHECK (%s). '
                    .'A stored value outside this range has already been read somewhere; '
                    .'decide what those rows should say before constraining the column.',
                    $table,
                    $offending,
                    $name,
                    $predicate,
                ));
            }

            DB::statement(sprintf('ALTER TABLE %s ADD CONSTRAINT %s CHECK (%s)', $table, $name, $predicate));
        }
    }

    public function down(): void
    {
        foreach ($this->constraints() as [$table, $name]) {
            DB::statement(sprintf('ALTER TABLE %s DROP CONSTRAINT IF EXISTS %s', $table, $name));
        }
    }
};
