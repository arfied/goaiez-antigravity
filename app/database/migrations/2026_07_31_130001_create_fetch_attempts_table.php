<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every fetch the gateway made, and every one it refused to make.
 *
 * `40` §6.3's shape. It feeds the per-source tier usage, block rates and cost
 * that §6.2 puts on the ops Integrations screen, and it is what makes the
 * cool-down of §6.1 possible — "most 'blocks' are rate limits that patience
 * fixes for free" is only actionable if the last block is written down.
 *
 * REFUSALS ARE RECORDED TOO, and that is ours rather than `40`'s. A
 * `guided_only` source that was never fetched leaves no trace in a table of
 * successful requests, which makes the policy invisible in exactly the audit
 * where someone asks "do you scrape Yelp?". A refusal row is the evidence that
 * the answer is no, and it costs one insert on a path that was not going to make
 * a network call anyway.
 *
 * THE URL IS HASHED, NOT STORED. A fetched URL can carry a business name, a
 * customer-facing path, or a query string somebody pasted, and this table has no
 * tenant and no retention policy of its own. The hash is enough for the two
 * things the column is for — cool-down lookups and "have we tried this recently"
 * — and it is not enough to reconstruct where we went.
 *
 * NOT TENANT-OWNED, for the same reason `fetch_sources` is not: these are our
 * outbound requests under our policy. Row 2's are made before any tenant exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fetch_attempts', function (Blueprint $table): void {
            $table->id();

            // No foreign key to fetch_sources on purpose: an attempt is a
            // historical fact and must survive a source row being renamed or
            // removed, the same argument places_api_calls makes for business_id.
            $table->string('source_key');

            // SHA-256 of the absolute URL. See the docblock.
            $table->string('url_hash', 64);

            // Cast to App\Enums\FetchTier.
            $table->string('tier', 8);

            // Cast to App\Enums\FetchOutcome.
            $table->string('outcome', 16);

            $table->unsignedSmallInteger('http_status')->nullable();

            // Set when the outcome triggers a cool-down. Null on a refusal:
            // waiting changes nothing about a policy.
            $table->timestamp('cooldown_until')->nullable();

            // Proxy spend, once F2 exists. Micros because proxy pricing is
            // quoted per-request at fractions of a cent, where integer cents
            // would round every request to zero.
            $table->unsignedBigInteger('cost_micros')->nullable();

            $table->timestamp('created_at');

            // "Is this source cooling down, and until when?" — the hot read.
            $table->index(['source_key', 'created_at']);

            // "Have we hit this exact URL recently?"
            $table->index(['url_hash', 'created_at']);

            // The cool-down sweep and the ops board's block-rate panel.
            $table->index('cooldown_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fetch_attempts');
    }
};
