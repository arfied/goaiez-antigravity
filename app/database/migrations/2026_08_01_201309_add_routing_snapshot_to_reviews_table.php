<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What routing decided, and what it decided it against — row 3 slice E.
 *
 * `reviews.routing_decision` already existed and had no writer. These two
 * columns are what make it legible after the fact.
 *
 * THE SNAPSHOT STORES NO URL, AND THAT IS DELIBERATE. Decision 308 makes
 * Google's review link derived from `location.google_place_id` at read time,
 * precisely so it cannot drift when a listing merge changes the id. A copy here
 * would be a second source of truth with a longer fuse than the one 308
 * removed, because this row is never revisited. Slice F resolves every link
 * through DestinationSettings::linkFor() and reads only the destination set
 * from this column.
 *
 * WHY STORE THE SET AT ALL rather than let the picker re-derive it. "Why was
 * this customer sent to Google" stops being answerable the moment anybody
 * changes a threshold, and re-deriving at render is two computations of one
 * truth that can disagree — the bug decision 258 designed away on the audit
 * gauge. ⚠️ `threshold_applied` used to ride along, because decision 290 made
 * it false for every tenant and a snapshot recording only the number would have
 * hidden the fail-open. **The router stopped writing that key on 2026-08-12**
 * (2074, 2662) — with the acknowledgement gone it is true on every row that can
 * be written. Rows written before then still carry it; the column is jsonb and
 * nothing reads the key.
 *
 * Both nullable, because they are null for every review written before this
 * slice and for every Google review forever — routing never touches a row it
 * is not allowed to hold or hide (`29` §2 rule 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->jsonb('routed_destinations')->nullable()->after('routing_decision');
            $table->timestamp('routed_at')->nullable()->after('routed_destinations');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropColumn(['routed_destinations', 'routed_at']);
        });
    }
};
