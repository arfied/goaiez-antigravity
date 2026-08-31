<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The index behind "captcha after the first audit" (`29` §6.2, decision 194).
 *
 * Slice A indexed `(place_id, created_at)` because it could see the 24h reuse
 * lookup coming. It could not see this one: the abuse control asks a different
 * question of the same table — *has this visitor run an audit in the last hour?*
 * — and answers it by counting rows for one `ip_hash` inside a window.
 *
 * Composite and in this order, for the same reason the place_id index is
 * composite: `ip_hash` is the equality predicate and `created_at` is the range,
 * so equality first lets one index seek serve the whole clause. Reversed, every
 * check would scan an hour of every visitor's audits to find one visitor's.
 *
 * WHY IT MATTERS MORE THAN A NORMAL INDEX. This query runs on the *unauthenticated*
 * path, before any rate limit has decided anything — it is part of how the rate
 * limit decides. An unindexed scan here is reachable by anyone with a browser and
 * gets slower as the table grows, which makes it a denial-of-service surface
 * rather than a slow page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_audits', function (Blueprint $table): void {
            $table->index(['ip_hash', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('public_audits', function (Blueprint $table): void {
            $table->dropIndex(['ip_hash', 'created_at']);
        });
    }
};
