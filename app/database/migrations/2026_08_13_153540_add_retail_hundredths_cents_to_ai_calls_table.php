<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What an AI call charges the tenant, beside what it cost us (decision 3358).
 *
 * ⚠️ **TWO NUMBERS ON ONE ROW, AND THEY ARE NOT THE SAME NUMBER.**
 * `cost_hundredths_cents` is what the provider bills *us*, priced from `AiModel`
 * at write time. This column is what the *tenant* is charged, which decision
 * 3304 fixes at eight times our cost — *"Ai credits are priced 8 to 1 what it
 * cost us."* One column cannot carry both, and deriving the retail figure at
 * read time is the mistake the cost column's own comment already argues against:
 * a multiplier that moves would silently reprice every month that has already
 * been reported.
 *
 * ⚠️ **THIS IS THE DEBIT SIDE OF THE AI CREDIT ACCOUNT, AND ON THE DAY IT LANDS
 * THERE IS NO CREDIT SIDE.** Decision 3297 requires every path that spends money
 * to debit before the dollar cost cap comes out (3293, 3295), and requires that
 * ordering explicitly — *"removing the cap first leaves a window in which nothing
 * bounds anything."* So the charge is recorded first, in its own commit, and the
 * balance that consults it arrives in the next one. **Nothing reads this column
 * yet**, which is written here rather than left for a reader to discover,
 * because a column described as feeding a ceiling that does not exist is exactly
 * `CLAUDE.md`'s 314–316.
 *
 * ⚠️ **BACK-FILLED RATHER THAN LEFT AT ZERO.** Every existing row has a real
 * provider cost, so its retail charge is knowable and is written here. A zero
 * would be indistinguishable from a call that genuinely charged nothing, and the
 * balance built on this column would read a tenant's history as free — 3105's
 * *"no rate is configured and no messages were sent both render as no rows"*
 * aimed at money that has already been spent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_calls', function (Blueprint $table): void {
            $table->unsignedInteger('retail_hundredths_cents')
                ->default(0)
                ->after('cost_hundredths_cents');
        });

        // 8 is decision 3304's multiple, written out rather than referenced from
        // AiCredits::RETAIL_MULTIPLE: a migration is a historical record and has
        // to keep saying what it actually did after the constant moves.
        DB::statement('UPDATE ai_calls SET retail_hundredths_cents = cost_hundredths_cents * 8');
    }

    public function down(): void
    {
        Schema::table('ai_calls', function (Blueprint $table): void {
            $table->dropColumn('retail_hundredths_cents');
        });
    }
};
