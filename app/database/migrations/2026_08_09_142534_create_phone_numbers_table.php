<?php

declare(strict_types=1);

use App\Enums\NumberRole;
use App\Enums\NumberState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The numbers this platform sends from — row 4 slice 6 phase 1, doc 51 §10.
 *
 * ⚠️ **PHASE 1 IS THE SEAM AND NOTHING MORE, AND THIS DOCBLOCK SAID OTHERWISE
 * UNTIL IT WAS FIXED** (1623). It named three writers — `NumberSelector`,
 * `NumberLifecycle` and `NumberHealthService` — on the day none of the three
 * existed, which is `docs/FAILURE-SHAPES.md`'s *"a protection layer asserted
 * before it is true"*: a claim written ahead of its mechanism is what stops the
 * next reviewer
 * looking. Two of them are built by this slice and are named below. **The third
 * is not built and is not named**, because health scoring, `number_health_daily`
 * and quarantine *triggers* are phase 2 and phase 3.
 *
 * ⚠️ **THIS IS THE TABLE `DATA-MODEL.md:265` ALREADY SPECS, AND `BUILD-PLAN`
 * §2.10.3's "`numbers` (new)" ROW IS SUPERSEDED BY IT.** Doc 51's own
 * consolidation rule: *"if the Master schema already carries a tenant-number
 * table under another name, these are column additions and a rename, not a
 * second table."* `phone_numbers` is that table, unbuilt until now — this
 * migration is the addition, not a fresh design.
 *
 * ⚠️ **`business_id` IS NULLABLE, AND THE TABLE FOLLOWS `impersonation_sessions`'
 * SHAPE, NOT `opt_outs`'.** A number can genuinely belong to nobody — the shared
 * Lane A pool, doc 51 §10's "NULL = system/shared pool" — so the trait cannot
 * go on (a global scope would hide exactly those rows). But unlike `opt_outs`
 * and `inbound_messages`, this table's other rows *do* name a real tenant, and
 * its readers include both a console command running under no tenant at all (the
 * bootstrap registration below) and a per-tenant selection (`NumberSelector`,
 * inside an established tenant). RLS is therefore ENABLE+FORCEd with
 * `USING (true)` — the `impersonation_sessions` posture (562): the predicate
 * cannot be `app.business_id` because the platform-scoped reads have none, and
 * what bounds this table is the application layer — **`NumberLifecycle`, which
 * is the only writer, and `NumberSelector`, which is the only reader on a send
 * path** — rather than the database. See `TenancyTest`'s allowlist entry for the
 * same argument in the place a reviewer of that lint will find it, and
 * `MessagingTest`'s chokepoint lint for what holds the "only" in both.
 *
 * ⚠️ **`e164` IS THE PLATFORM'S OWN SENDING NUMBER, NOT A CUSTOMER'S.** Unlike
 * `opt_outs.value_hash` and `inbound_messages.value_hash`, this is not PII and
 * is not hashed — it is one of our own purchased numbers, printed on invoices,
 * named in the Ops console, and it has to be dialable and comparable in plain
 * text to do its job: it is the string handed to the carrier as the `from` of
 * every send, and the string an inbound webhook's `to` is compared against.
 * Said here so nobody "fixes" it toward a hash in either direction.
 *
 * ⚠️ **`lane` IS NULLABLE AND SEEDED NULL FOR THE BOOTSTRAP ROW, DELIBERATELY.**
 * `BUILD-PLAN` §2.10.5: which lane the registered 10DLC brand serves —
 * a local number or a toll-free Lane A number — is unresolved, and this row is,
 * today, the one number every send uses regardless of the permit's lane. Writing
 * a specific lane here would answer that open question by accident rather than
 * by ruling.
 *
 * Columns not present here — `tcr_campaign_ref`, `warmup_day`,
 * `quarantine_count_30d`, `area_code` — are doc 51 §10's, and are left out
 * rather than added unwritten: nothing in phases 1–3 has a writer for them
 * (`docs/FAILURE-SHAPES.md`'s "a table, column or control with no writer"
 * shape). The
 * warmup curve (phase 4) is explicitly not built this slice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_numbers', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();

            $table->string('e164');
            $table->string('provider_number_id')->nullable();

            // Cast to MessagingLane — 'platform' (Lane A) | 'tenant' (Lane B).
            // Nullable; see the class docblock for why the bootstrap row leaves
            // it unset. A string, never a database enum (CLAUDE.md).
            $table->string('lane')->nullable();

            // Cast to NumberRole. A string, never a database enum.
            $table->string('role');

            // Cast to NumberState. A string, never a database enum.
            $table->string('state');
            $table->string('state_reason')->nullable();

            // ⚠️ NO WRITER IN PHASE 1, SAID HERE RATHER THAN IMPLIED. Doc 51
            // §4.3's "a new number scores 100" is the default, and nothing
            // recomputes it: the scoring service and `number_health_daily` are
            // phase 2 and are deliberately not built. Nothing reads this column
            // either — `NumberSelector` picks on `state`, never on score — so
            // the inert column is inert in both directions rather than being a
            // number a decision is quietly made on (272's shape).
            $table->unsignedTinyInteger('health_score')->default(100);

            // Each of these is set by `NumberLifecycle` on entering the state it
            // names, and `quarantined_at` is cleared on leaving `quarantined` —
            // a stale timestamp beside an `active` state reads as "this number
            // is quarantined right now" to anybody who checks the column instead
            // of the state.
            //
            // ⚠️ `purchased_at` IS THE ONE WITH NO WRITER, AND IT IS NAMED. The
            // purchase flow is doc 51 §2.3 and phase 4; the bootstrap row below
            // records a number this platform already had, so there is no
            // purchase moment to record for it. Left nullable and unwritten
            // rather than filled with the row's creation time, which would be a
            // fact nobody established.
            $table->timestamp('quarantined_at')->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->timestamp('released_at')->nullable();

            $table->timestamps();

            $table->index(['business_id', 'state']);
        });

        // Doc 51 §10: "UNIQUE(e164 active-partial)". A released number's e164
        // may in principle be reissued to us by the carrier later; a retired
        // (parked) one may not be reused by a fresh purchase while it is still
        // sitting out its park window, so the partial excludes only `released`.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX phone_numbers_e164_active_unique
                ON phone_numbers (e164)
                WHERE state <> 'released'
        SQL);

        // Doc 51 §10: "one primary per location (partial unique)". Nothing in
        // phases 1–3 assigns a primary to a location, but the constraint is
        // schema-cheap and the doc names it as an acceptance gate for this
        // phase ("one-primary constraint proven") — proven now rather than
        // added the day a purchase flow needs it.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX phone_numbers_one_primary_per_location
                ON phone_numbers (location_id)
                WHERE role = 'primary' AND location_id IS NOT NULL
        SQL);

        $states = collect(NumberState::cases())
            ->map(fn (NumberState $state): string => "'{$state->value}'")
            ->implode(', ');

        DB::statement(<<<SQL
            ALTER TABLE phone_numbers
                ADD CONSTRAINT phone_numbers_state_is_known
                CHECK (state IN ({$states}))
        SQL);

        $roles = collect(NumberRole::cases())
            ->map(fn (NumberRole $role): string => "'{$role->value}'")
            ->implode(', ');

        DB::statement(<<<SQL
            ALTER TABLE phone_numbers
                ADD CONSTRAINT phone_numbers_role_is_known
                CHECK (role IN ({$roles}))
        SQL);

        // I40: a number belongs to exactly one tenant forever; the shared pool
        // is the platform "tenant". A shared_pool row naming a business would
        // let a platform number be read as belonging to somebody, and a
        // primary/extension row naming nobody would be unselectable by
        // NumberSelector's tenant-first branch. Cross-tenant reuse is what this
        // guards against becoming possible by schema.
        DB::statement(<<<'SQL'
            ALTER TABLE phone_numbers
                ADD CONSTRAINT phone_numbers_shared_pool_has_no_business
                CHECK (
                    (role = 'shared_pool' AND business_id IS NULL)
                    OR (role <> 'shared_pool' AND business_id IS NOT NULL)
                )
        SQL);

        DB::statement('ALTER TABLE phone_numbers ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE phone_numbers FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY platform_and_tenant_readable ON phone_numbers
                FOR ALL USING (true) WITH CHECK (true)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_numbers');
    }
};
