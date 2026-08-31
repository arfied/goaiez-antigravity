<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The consent record (DATA-MODEL §5.6) — the thing a boolean can never be.
 *
 * Consent is a dated, versioned, provable event: which surface captured it,
 * which disclosure wording the person saw, and proof (ip_hash — never a raw
 * IP — timestamp, URL, user agent, checkbox state). `captured_by` routes the
 * messaging lane: platform-captured consent is Lane A; tenant-asserted is
 * Lane B. A contact with no row here can never be texted at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_records', function (Blueprint $table): void {
            $table->id();

            // business_id alongside customer_id so the RLS policy has its
            // column; filled from context by BelongsToTenant.
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // sms|email (whatsapp later) — cast to OutreachChannel.
            $table->string('channel');

            // express|express_written
            $table->string('consent_type')->nullable();

            // platform|tenant. NOT NULL per spec: a consent record that does
            // not say who captured it cannot route a lane.
            $table->string('captured_by');

            // feedback_page|chat|booking|qr|import|pos|call|manual
            $table->string('capture_surface');

            // The exact disclosure wording version shown. NOT NULL per spec —
            // consent to unknown wording proves nothing.
            $table->string('disclosure_version');

            $table->string('method')->nullable();

            // { ip_hash, ts, url, ua, checkbox_state, wording }. ip_hash only:
            // raw IP is never stored, anywhere (`29` §2).
            $table->jsonb('proof')->nullable();

            // Append-shaped: consent history is events, so created_at only.
            $table->timestamp('created_at')->nullable();

            $table->index(['business_id', 'customer_id']);
        });

        DB::statement('ALTER TABLE consent_records ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE consent_records FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON consent_records
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_records');
    }
};
