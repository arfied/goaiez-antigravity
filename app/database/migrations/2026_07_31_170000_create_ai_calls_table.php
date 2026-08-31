<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every model call this platform makes, and what it cost.
 *
 * TENANT-OWNED, WITH A NON-NULLABLE KEY — unlike `places_api_calls`, which is
 * the deliberate opposite. That table records spend from the free public audit,
 * which runs *before any tenant exists*, so its `business_id` is nullable and it
 * sits on the TenancyTest allowlist with that written down. Nothing here
 * runs before a tenant exists: every AI call in this system serves a review, a
 * reply, or a piece of content belonging to one business.
 *
 * The non-nullable key is the enforcement, not a description. `CLAUDE.md` rule
 * 43 makes per-tenant cost caps mandatory and server-side, and a cap is checked
 * by summing this table — so a row that cannot be attributed to a tenant is
 * spend that cannot be capped. Making that unrepresentable at the column is
 * cheaper than a test asserting nobody ever writes one.
 *
 * WHY THE MODEL AND THE TASK ARE BOTH STORED, when the task implies the model
 * today: because it will not. AiRouter reads `platform_settings` to pick the
 * model for a task, so the mapping changes without a deploy — and the question
 * this table exists to answer after a surprising bill is "which model was
 * serving analysis last Tuesday", which a derived answer cannot give.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_calls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // Strings cast to backed enums in the model, never a database enum
            // (CLAUDE.md §Critical rules). Both churn: the model list moves when
            // a vendor retires a model, the task list grows with every feature.
            $table->string('task');
            $table->string('provider');
            $table->string('model');

            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);

            /*
             * Hundredths of a cent, and priced at write time rather than derived
             * at read time.
             *
             * A ledger that recomputes cost from today's price list silently
             * rewrites history the day a price changes — last month's spend
             * moves, and the figure the owner accepted stops matching the figure
             * the system reports. `places_api_calls` stores its unit price for
             * the same reason.
             *
             * The unit is the one AiModel uses: OpenAI's $0.15/MTok is 15 cents,
             * and 9.2c per Places audit already proved that integer cents floors
             * real prices to something 2% wrong.
             */
            $table->unsignedInteger('cost_hundredths_cents')->default(0);

            /*
             * The three outcomes of AiResponse, flattened.
             *
             * `refused` is not a failure and must not be counted as one: a model
             * declining to write a reply to an abusive review is the system
             * working, and the review goes to a human. Storing it in the same
             * column as "the vendor 500'd" would make every reliability figure
             * built on this table wrong in the direction of alarm.
             */
            $table->boolean('refused')->default(false);
            $table->string('failure_reason')->nullable();

            $table->timestamps();

            // The cap query: sum a tenant's cost over a calendar month.
            $table->index(['business_id', 'created_at']);
        });

        DB::statement('ALTER TABLE ai_calls ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE ai_calls FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON ai_calls
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_calls');
    }
};
