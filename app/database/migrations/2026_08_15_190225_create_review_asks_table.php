<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The one record that skill 13 was offered on a thread — T176 §2.2 row 13, P12.
 *
 * ⛔ **THIS IS NOT A REVIEW AND NOTHING HERE MAY EVER BE READ AS ONE.** It
 * records that the assistant handed somebody the business's own feedback page
 * address — `/f/{slug}` — inside a text they were already owed. It carries no
 * rating, no destination, no outcome and no click. Decision 113 is why the
 * outcome column a reader expects is absent: **no destination platform gives us
 * a completion callback**, so a `reviewed_at` here could only ever be a guess
 * wearing a fact's name.
 *
 * ⚠️ **THE UNIQUE INDEX IS THE PRODUCT PROMISE, NOT AN OPTIMISATION.** §2.2 says
 * *"offer the /f/{slug} link once"*. A `SELECT`-then-`INSERT` in the service is
 * the ordinary case; this is what holds when two turns of the same thread run
 * concurrently, which on a queue is not a hypothetical.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_asks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();

            // ⛔ **ONE ASK PER THREAD, ARBITRATED BY THE DATABASE.** See above.
            $table->foreignId('conversation_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            // The link the assistant was actually given, so a click on the
            // timeline traces back to the turn that offered it. Nullable because
            // a link can be revoked and this row must survive that.
            $table->foreignId('short_link_id')->nullable()->constrained()->nullOnDelete();

            // ⚠️ **WHEN IT WENT, NEVER WHAT WAS SAID.** The words live on the
            // `outreach_messages` row the sender wrote, which is the one record
            // of them (627's rule, and `AgentThreadStates::record()`'s).
            $table->timestamp('offered_at');

            $table->timestamps();

            $table->index(['business_id', 'customer_id']);
        });

        DB::statement('ALTER TABLE review_asks ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE review_asks FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON review_asks
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('review_asks');
    }
};
