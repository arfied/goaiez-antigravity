<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform index: Zernio account id → our location.
 *
 * WHY THIS TABLE EXISTS. `gbp_connections` is RLS-FORCE'd on `business_id`. A
 * Zernio webhook arrives with an account id and no tenant, and without a session
 * `app.business_id` every SELECT on that table returns zero rows. Walking every
 * tenant to find one account is the wrong answer for every delivery.
 *
 * So this is a platform-scoped mirror written by GbpConnections on connect and
 * cleared on disconnect — the same shape as inbound_messages: no tenant, no RLS,
 * because a policy admitting NULL would admit every row. The model joins the
 * TenancyTest allowlist with its reasoning there.
 *
 * ⚠️ It carries business_id and location_id as ordinary columns, not as a
 * tenancy trait. The trait would call Tenancy::idOrFail() on a path that has no
 * tenant yet — FeedbackPage's circularity one layer earlier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gbp_account_bindings', function (Blueprint $table): void {
            $table->id();
            $table->string('account_ref')->unique();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('location_id');
            $table->timestamps();

            $table->index('business_id');
            $table->index('location_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gbp_account_bindings');
    }
};
