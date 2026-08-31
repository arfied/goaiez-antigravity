<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zernio webhook delivery receipts — at-least-once dedupe.
 *
 * Verified against Zernio's live webhooks overview on 2026-08-10: deliveries are
 * at-least-once; every payload carries a stable event `id` (also
 * `X-Zernio-Event-Id`). Insert-or-skip on that id is the documented pattern.
 *
 * Platform-scoped, no RLS — same reasoning as inbound_messages: a webhook has
 * no tenant until we resolve one, and the receipt must be recordable either way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zernio_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('event_type');
            $table->timestampTz('received_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zernio_webhook_events');
    }
};
