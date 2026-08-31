<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A review-request campaign per location (DATA-MODEL §5.7). Created before
 * outreach_messages so its foreign key can resolve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_request_campaigns', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('status')->default('active');

            // TEXT[] in DATA-MODEL; jsonb list here.
            $table->jsonb('channels')->default('["sms","email"]');

            $table->jsonb('schedule')->nullable();
            $table->jsonb('audience_filter')->nullable();

            // The re-ask frequency cap. 90 days is DATA-MODEL's own default,
            // not an invention.
            $table->integer('frequency_cap_days')->default(90);

            $table->integer('requests_sent')->default(0);

            $table->timestamps();

            $table->index(['business_id', 'status']);
        });

        DB::statement('ALTER TABLE review_request_campaigns ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE review_request_campaigns FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON review_request_campaigns
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('review_request_campaigns');
    }
};
