<?php

declare(strict_types=1);

use App\Enums\DataClassification;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The tenant root.
 *
 * Authority: docs/DATA-MODEL.md §5.2. Row-level security is created here rather
 * than in a follow-up migration, per decision 133 — the gap between the two
 * would be a tenant-owned table with no second layer and nothing to flag it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();

            $table->string('name');
            $table->string('legal_name')->nullable();

            // Employer Identification Number. Nullable because brand
            // registration defers rather than fails when an EIN is under 15 days
            // old (`29` §19.5), so a business exists before it has one.
            $table->string('ein')->nullable();
            $table->string('entity_type')->nullable();

            $table->jsonb('address')->nullable();

            // ISO 4217. Paired with integer cents everywhere money is stored —
            // an amount without its currency is not a money value.
            $table->char('currency', 3)->default('USD');

            $table->string('vertical')->nullable();
            $table->string('size_band')->nullable();
            $table->string('metro_band')->nullable();

            // DATA-MODEL declares this as the `data_class` Postgres enum type.
            // A string cast to App\Enums\DataClassification instead: a database
            // enum is a second source of truth that drifts from the PHP one, and
            // Postgres cannot drop or reorder its values once added. A convention
            // test greps migrations for ->enum( and fails the build.
            $table->string('data_classification')
                ->default(DataClassification::Pii->value);

            // Join key to the pixel system. Globally unique rather than
            // composite, because this table's row *is* the tenant — there is no
            // business_id to compose with.
            $table->uuid('pixel_tenant_id')->nullable()->unique();

            // ⛔ BOTH OF THE NEXT TWO COLUMNS ARE DROPPED — 2026-08-23, owner
            // ruling 8390. They are `26` §1.2 step 2 verbatim, one line inside
            // a `SwapNumberJob` that was never built, and neither ever had a
            // writer. `dedicated_number_id` could not have held its reference
            // in any case: `phone_numbers.id` is a `bigint` (8295). The lines
            // stay because this migration is history and a fresh install must
            // replay it, and because a reader who greps either name must land
            // on the correction rather than on the column. What tenant-
            // dedicated numbering actually runs on is `phone_numbers.
            // business_id`. See
            // 2026_08_23_125906_drop_messaging_mode_and_dedicated_number_id_from_businesses.
            $table->string('messaging_mode')->default('platform');
            $table->uuid('dedicated_number_id')->nullable();

            // ⛔ THIS COMMENT DESCRIBED A COMPLIANCE GATE THAT DOES NOT EXIST,
            // AND THE CORRECTION IS THE POINT OF LEAVING IT — 8584. It read:
            // *"Ungated defaults are the rule (`29` §2 rule 37), but this one
            // gates spending on the customer's behalf and stays false until
            // consent ownership is established."* **`marketing_sends_enabled`
            // has no reader.** Its every occurrence in this repository is this
            // line, the cast on `Business`, `BusinessFactory`'s `=> false`, one
            // passing mention in the 2026-08-23 drop migration above, and the
            // schema listings in `DATA-MODEL.md` and the archived spec.
            // **Nothing in `app/` consults it before any send, ever.**
            //
            // ⚠️ THE RISK IS NOT UNGATED MARKETING, AND SAYING SO PRECISELY IS
            // WHAT KEEPS THIS COMMENT FROM REPEATING THE ORIGINAL'S MISTAKE IN
            // THE OTHER DIRECTION. The reactivation engine is live and
            // scheduled (`campaigns:run-due`, every fifteen minutes), and what
            // actually refuses a send is `Consent\ConsentService::decide()`,
            // per recipient, per channel — `NoConsentRecord`, `OptedOut`,
            // `DoNotCall`, `Litigator`, `NumberReassigned`, `QuietHours`,
            // `ConsentTooWeakForState` and the rest of `SendRefusalReason`. A
            // per-business boolean could not do that job in any case: consent
            // is a fact about a person, not about a tenant.
            //
            // ⛔ THE RISK IS THIS COMMENT. A column named
            // `marketing_sends_enabled`, defaulting `false`, with a paragraph
            // above it citing rule 37 and *"consent ownership"*, is the first
            // thing the next lane or operator reaches for — and flipping it
            // would permit nothing, refuse nothing, and read in a diff as
            // enabling marketing for a tenant. That is 314-316 exactly: the
            // paragraph explaining the protection is what stops the next
            // reviewer looking for the mechanism.
            //
            // ⚠️ NOT DROPPED, AND NOT BY THIS LANE. 8390's drop of the two
            // columns above took an explicit owner ruling; this one is
            // reported, not removed (8585). The line stays regardless, because
            // this migration is history and a fresh install replays it — the
            // annotation is 8397's rule, so a grep for the name lands on the
            // correction rather than on the column.
            $table->boolean('marketing_sends_enabled')->default(false);

            $table->timestamps();
        });

        // ENABLE alone is not enough: PostgreSQL exempts a table's owner from its
        // policies unless the table is also FORCEd, and migrations run as the
        // owner. Without FORCE this policy would exist, look correct, and do
        // nothing. A convention test asserts both flags plus >=1 policy.
        DB::statement('ALTER TABLE businesses ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE businesses FORCE ROW LEVEL SECURITY');

        // The root is scoped on its own key.
        //
        // The session variable has three states, and the policy has to survive
        // all of them. current_setting's second argument (missing_ok) covers only
        // the first:
        //
        //   undefined     -> NULL          -> matches nothing
        //   empty string  -> ''::bigint    -> RAISES, without nullif
        //   a value       -> the tenant id -> matches that tenant
        //
        // Tenancy::forget() sets the variable to '' rather than undefining it, so
        // the middle state is the normal one between requests — and without
        // nullif() every query outside a tenant fails with "invalid input syntax
        // for type bigint" instead of returning no rows. nullif(x, '') maps both
        // empty states to NULL before the cast, which is what makes this
        // fail-closed rather than fail-loud.
        //
        // WITH CHECK as well as USING — USING filters what can be read, WITH
        // CHECK constrains what can be written. Without it a tenant could update
        // a row into another tenant's ownership.
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON businesses
                USING      (id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);

        // One policy, no signup exception — see Business::provision().
        //
        // The obvious alternative is a second, INSERT-only policy permitting a
        // write when no tenant is established. It does not work, and the reason
        // is worth recording because the failure is not where you would look:
        // Laravel obtains a new model's id with INSERT ... RETURNING id, and
        // PostgreSQL applies the *SELECT* policy to a RETURNING clause. With no
        // tenant set, USING evaluates to NULL, the new row is not visible, and
        // the statement is rejected — reported as "new row violates row-level
        // security policy", which points at WITH CHECK and sends you the wrong
        // way entirely. A plain INSERT with no RETURNING succeeds under exactly
        // the same policies.
        //
        // So a table whose policy is keyed on its own generated id cannot be
        // populated by a statement that generates it. Business::provision()
        // takes the id from the sequence first and establishes tenancy before
        // inserting, which satisfies both USING and WITH CHECK and leaves this
        // one policy sufficient.
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
