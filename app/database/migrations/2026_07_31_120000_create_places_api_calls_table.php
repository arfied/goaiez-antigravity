<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every metered Google Places request, and what it cost.
 *
 * `29` §11.2 row 2's gate requires "Places spend counted against the daily
 * budget and the per-tenant cost cap", and `BUILD-PLAN` §6 names the risk
 * plainly: "a free, unauthenticated endpoint spending metered Places calls" on
 * "real money on someone else's meter". A counter alone cannot answer the
 * question that matters after a bad day — *what did we spend it on* — so this
 * is a ledger, one row per request.
 *
 * NOT TENANT-OWNED, and it cannot be: the audit runs before anyone signs up, so
 * there is no business_id to attribute a call to. `business_id` is nullable and
 * present anyway, because the same client will later serve GBP-01b inside a
 * tenant (row 3) and that spend must land against the per-tenant cost cap.
 * Nullable-with-a-purpose, not nullable-by-omission — and because it is
 * nullable, this table is NOT tenant-owned and carries no RLS policy. A row with
 * a business_id is attributable; a row without one is platform spend.
 *
 * THE PRICE IS SNAPSHOT ON THE ROW, not looked up at read time. Google has
 * already restructured Places pricing once (the shared $200 monthly credit
 * became per-SKU free allowances). A ledger that recomputed history against
 * today's price list would silently rewrite what last quarter cost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('places_api_calls', function (Blueprint $table): void {
            $table->id();

            // Cast to App\Enums\PlacesSku. A string column, never a database
            // enum — and this one churns by definition, because the SKU set is
            // Google's to change.
            $table->string('sku', 48);

            // Null when the call was not about one specific place — a text
            // search resolving a typed name, or a nearby sweep.
            $table->string('place_id')->nullable();

            // Whether this request actually left the building. A cache hit is
            // recorded so that "the cache is working" is a query rather than a
            // belief, and BUILD-PLAN §2.5.3's "a cache hit costs nothing and is
            // proven to cost nothing" has something to assert against.
            $table->boolean('served_from_cache')->default(false);

            // List price at the moment of the call, integer cents per 1,000
            // requests. Cents-per-thousand rather than per call because every
            // published Google figure is exact at that scale and a per-call
            // integer would round 3.2 cents to 3.
            $table->unsignedInteger('unit_cents_per_thousand');

            // Present for row 3 and after, when the same client runs inside a
            // tenant and the spend belongs to a cost cap. Null for every public
            // audit, which is all of row 2. No foreign key: a business may be
            // erased under a data request while its historical spend must
            // survive as an aggregate.
            $table->unsignedBigInteger('business_id')->nullable();

            // What asked for the call — 'public_audit', later 'gbp_resolve'.
            // A short label, not a class name: class names move.
            $table->string('purpose', 32);

            $table->timestamp('called_at');

            // The daily budget question: "how much have we spent today?"
            $table->index(['called_at', 'sku']);

            // The cost-cap question, once row 3 attributes calls to a tenant.
            $table->index(['business_id', 'called_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('places_api_calls');
    }
};
