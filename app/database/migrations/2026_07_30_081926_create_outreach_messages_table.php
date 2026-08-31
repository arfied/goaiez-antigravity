<?php

declare(strict_types=1);

use App\Enums\OutreachStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every outbound message the platform sends (DATA-MODEL §5.7).
 *
 * `lane` records which consent lane the send actually used — a historical
 * fact frozen at send time, unlike customers.messaging_lane which is derived
 * current state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_messages', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // Nullable: owner alerts and billing notices are business-level.
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();

            // Nullable: an owner alert has no customer.
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('campaign_id')->nullable()
                ->constrained('review_request_campaigns')->nullOnDelete();

            $table->string('channel');

            // review_request|triage|reminder|owner_alert|fill
            $table->string('purpose')->nullable();

            $table->string('lane')->nullable();

            $table->text('body')->nullable();

            $table->string('status')->default(OutreachStatus::Queued->value);

            $table->string('provider_msg_id')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['business_id', 'status']);
            $table->index('customer_id');
        });

        DB::statement('ALTER TABLE outreach_messages ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE outreach_messages FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON outreach_messages
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_messages');
    }
};
