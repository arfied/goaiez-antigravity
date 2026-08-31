<?php

declare(strict_types=1);

use App\Enums\MessagingLane;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The CRM contact (DATA-MODEL §5.5).
 *
 * `messaging_lane` is derived, never set by hand (§5.6, §5.14): our surface +
 * disclosure + proof → platform; tenant-asserted → tenant; no valid record →
 * none, which is unsendable. The column defaults to none and is guarded on
 * the model; jobs refresh it from consent_records.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();

            // Unconstrained until the companies table lands (V3 group, a later
            // FOUND-02 slice); the foreign key is added with it.
            $table->unsignedBigInteger('company_id')->nullable();

            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            // Denormalized "has a valid record" flags. The consent itself is
            // never a boolean — it is a consent_records row carrying wording
            // version, timestamp, URL, ip_hash, and user agent. These exist so
            // list queries need not join.
            $table->boolean('sms_consent')->default(false);
            $table->boolean('email_consent')->default(false);

            $table->string('messaging_lane')->default(MessagingLane::None->value);
            $table->string('consent_source')->nullable();
            $table->boolean('is_suppressed')->default(false);

            $table->string('lifecycle_stage')->default('lead');

            $table->jsonb('tags')->nullable();
            $table->jsonb('custom_fields')->default('{}');

            $table->smallInteger('score_advocate')->nullable();
            $table->smallInteger('score_churn_risk')->nullable();

            // Integer cents; the currency code is the business's own
            // (businesses.currency).
            $table->bigInteger('value_to_date_cents')->default(0);

            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('external_ref')->nullable();

            // Self-reference: set when this row was merged into another.
            $table->foreignId('merged_into_id')->nullable()->constrained('customers')->nullOnDelete();

            $table->timestamp('last_requested_at')->nullable();
            $table->integer('request_count')->default(0);

            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();

            // §5.5 gives this table created_at only — last_activity_at plays
            // the updated_at role. The model sets UPDATED_AT = null.
            $table->timestamp('created_at')->nullable();

            // Composite with business_id, never global: two tenants will both
            // have (555) 123-4567 in their books, and a global unique would
            // block the second while revealing the first.
            $table->unique(['business_id', 'phone']);
            $table->unique(['business_id', 'email']);

            // DATA-MODEL §5.13 idx_customers_business_lane.
            $table->index(['business_id', 'messaging_lane']);
        });

        DB::statement('ALTER TABLE customers ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE customers FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON customers
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
