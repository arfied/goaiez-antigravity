<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One subscription row per business (DATA-MODEL §5.12).
 *
 * This is DATA-MODEL's shape, not Cashier's — Cashier 16 only publishes its
 * migrations on demand (verified in vendor), so there is no collision today.
 * Reconciling the two schemas is the billing ticket's decision, not this one's.
 *
 * No money columns here: prices live in Stripe, and amounts anywhere in this
 * schema are integer cents plus a currency code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();

            // Stripe ids are tenant-owned data and live behind RLS like
            // everything else on this row.
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();

            $table->string('plan')->nullable();
            $table->string('status')->nullable();

            // Nullable with NO default, on purpose: the included SMS allowance
            // is undecided (DECISIONS.md open question F), and a default here
            // would silently become the price sheet. Fails closed until the
            // owner sets it.
            $table->integer('sms_credits_included')->nullable();

            // A counter, not a policy number — zero is bookkeeping, not a
            // decision.
            $table->integer('sms_credits_used')->default(0);

            $table->timestamp('current_period_end')->nullable();

            $table->timestamps();
        });

        DB::statement('ALTER TABLE subscriptions ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE subscriptions FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON subscriptions
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
