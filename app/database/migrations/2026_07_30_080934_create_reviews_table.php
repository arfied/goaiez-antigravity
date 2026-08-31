<?php

declare(strict_types=1);

use App\Enums\ReviewSource;
use App\Enums\ReviewStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reviews — both pipelines in one table, never confused (DATA-MODEL §5.3).
 *
 * First-party reviews flow through routing, gating, and triage. Google reviews
 * are ingested facts: never held, hidden, approved, or moderated (`29` §2
 * rule 1). `source` tells them apart; status gates only the first-party path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();

            // DATA-MODEL keys this table by location_id alone; business_id is
            // added because every RLS policy compares it, and a tenant-owned
            // table without it has no second layer. BelongsToTenant fills it
            // from context, exactly as for location_id's own table.
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();

            // Nullable: first-party reviews have none until matched
            // (DATA-MODEL §5.14).
            $table->string('google_review_id')->nullable();

            $table->string('source')->default(ReviewSource::FirstParty->value);
            $table->boolean('is_platform')->default(false);

            // api|email|manual
            $table->string('ingest_method')->nullable();

            // Unconstrained until slice D creates customers; the foreign key
            // is added there, in the same branch stack.
            $table->unsignedBigInteger('customer_id')->nullable();

            $table->smallInteger('rating');

            $table->text('comment')->nullable();
            $table->string('reviewer_name')->nullable();
            $table->string('reviewer_photo_url')->nullable();

            $table->timestamp('review_create_time')->nullable();
            $table->timestamp('review_update_time')->nullable();

            $table->string('status')->default(ReviewStatus::Pending->value);

            $table->string('sentiment')->nullable();
            $table->jsonb('themes')->nullable();
            $table->jsonb('moderation_flags')->nullable();

            $table->string('routing_decision')->nullable();
            $table->timestamp('google_invite_sent_at')->nullable();

            $table->boolean('display_on_website')->default(false);
            $table->boolean('display_on_facebook')->default(false);

            $table->timestamp('approved_at')->nullable();

            // A string, not a users foreign key: autopilot approves most
            // reviews, so the approver is an actor label, not always a person.
            $table->string('approved_by')->nullable();

            $table->timestamp('flagged_at')->nullable();

            $table->jsonb('raw_payload')->nullable();

            $table->timestamps();

            // Per location, not global: the same google_review_id under two
            // tenants must never collide (that collision would reveal the
            // other tenant exists). Nullable column — Postgres treats NULLs as
            // distinct, so unmatched first-party reviews never conflict.
            $table->unique(['location_id', 'google_review_id']);

            $table->index(['location_id', 'status']);
            $table->index('google_review_id');
            $table->index(['business_id', 'status']);
        });

        // DATA-MODEL: CHECK (rating BETWEEN 1 AND 5).
        DB::statement(
            'ALTER TABLE reviews ADD CONSTRAINT reviews_rating_between_1_and_5
                 CHECK (rating BETWEEN 1 AND 5)'
        );

        DB::statement('ALTER TABLE reviews ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE reviews FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON reviews
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
