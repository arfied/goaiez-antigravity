<?php

declare(strict_types=1);

use App\Services\Sms\NumberLifecycle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only lifecycle history of one `phone_numbers` row — doc 51 §10, §2.4.
 *
 * NOT TENANT-OWNED, AND NO RLS — the `opt_outs`/`inbound_messages` shape, not
 * `phone_numbers`': the row carries `number_id`, never a `business_id`, so
 * there is no tenant predicate to write and a policy admitting NULL would admit
 * every row anyway. Its writer, {@see NumberLifecycle}, is
 * the only thing in `app/` that inserts here — a chokepoint lint in
 * `MessagingTest` holds that.
 *
 * ⚠️ **APPEND-ONLY, `InboundMessage`'s shape.** A history that can be rewritten
 * after the fact is not history — the model's own `booted()` throws on update
 * and delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_state_changes', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('number_id')->constrained('phone_numbers')->cascadeOnDelete();

            // Nullable: the very first row (provisioning) has no prior state.
            // Cast to NumberState. A string, never a database enum.
            $table->string('from_state')->nullable();
            $table->string('to_state');

            // `system:trigger-name` or a user id — doc 51 §2.4's actor column,
            // the Doc 38 override pattern already used by every typed-reason
            // write in this schema.
            $table->string('actor');
            $table->string('reason')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['number_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_state_changes');
    }
};
