<?php

declare(strict_types=1);

use App\Enums\PixelBundleStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * §10's Delivery paragraph, the state half — `GOAIEZ_PIXEL_MASTER_BUILD.md`:
 * *"immutable `/v/<sha>/p.js` … `/p.js` pointer … Rollback = repoint, one
 * command … Canary 1% for 60 min, auto-halt on JS-error regression >0.5%."*
 *
 * ## Platform-scoped, no row-level security — `plan_offers`' argument exactly
 *
 * This is the bundle every tenant's page loads, not a tenant's data — nothing
 * on the row names a business, and a tenant-owned version table would mean each
 * business running its own copy of the sensor. It joins the named-exception
 * list in `tests/Feature/Architecture/TenancyTest.php`, with the argument
 * written there as well as here. What replaces the scope is a chokepoint lint
 * naming `App\Services\Pixel\PixelDelivery` as the only reader and writer.
 *
 * ## Bytes live in the row, not in a storage disk
 *
 * `GOAIEZ_PIXEL_MASTER_BUILD.md` §5.2's R2 archive is L0 — visitor *events*,
 * replayable for seven years. This is a JS file under the §10 budget's 14 KB
 * gzipped ceiling — a few kilobytes raw — and the two have nothing to do with
 * each other. Adding a filesystem disk for one file this small would be a new
 * piece of infrastructure to configure, monitor and back up for a value smaller
 * than the row that would point at it; Postgres already does all three for this
 * table. `CLAUDE.md`'s ambiguity rule: less support surface.
 *
 * ## `sha` is content-addressed and `build_token` is not the same thing
 *
 * `sha` is `sha256(published bytes)` and is what names the immutable route —
 * two publishes of byte-identical content collide on it by design, which is
 * what makes `/v/<sha>/p.js` cacheable for a year. `build_token` is a random
 * value stamped into the bytes *before* they are hashed (`PixelDelivery`'s own
 * docblock has the reason computing one from the other cannot work) and is what
 * `pixel.js` reports back on every batch, so `pixel_delivery_samples` can tell
 * which published version produced a given pageview or `js_error` without the
 * collector or the delivery route ever needing to agree on anything at request
 * time.
 *
 * ## At most one Active row and at most one Canary row, enforced here
 *
 * {@see PixelBundleStatus}'s own docblock: a check
 * `PixelDelivery::publish()` alone could pass twice under two concurrent
 * publishes is not a check — decision 350's shape, `pixel_keys`' own reason for
 * its `business_id` unique index. Postgres partial unique indexes are the
 * enforcement; nothing in the application layer may be trusted alone on a table
 * with no row-level security.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pixel_bundle_versions', function (Blueprint $table): void {
            $table->id();

            // sha256 hex — 64 characters, fixed width.
            $table->string('sha', 64)->unique();

            // 32 hex characters from `random_bytes(16)`, stamped into the bytes
            // before hashing. See the class docblock for why this cannot be
            // derived from `sha` instead.
            $table->string('build_token', 32)->unique();

            // ⚠️ `text()`, NOT `binary()`. The bytes are JavaScript source —
            // printable, no embedded null bytes — and Postgres `bytea` reads
            // back through PDO as a stream resource rather than a string, which
            // is extra handling for no gain over a type this content already
            // satisfies.
            $table->text('contents');

            $table->unsignedInteger('byte_size');
            $table->unsignedInteger('gzip_byte_size');

            $table->string('status', 20);

            $table->timestamp('published_at');
            $table->timestamp('canary_started_at')->nullable();
            $table->timestamp('promoted_at')->nullable();
            $table->timestamp('halted_at')->nullable();
            $table->string('halt_reason', 500)->nullable();

            // Free text, not a foreign key: `pixel:publish` and `pixel:rollback`
            // run from a shell with no signed-in user, `offers:close`'s own
            // reasoning for `PlanOffer.set_by`/`RegistryChange.actor`.
            $table->string('actor', 120);

            $table->timestamps();
        });

        // ⚠️ `WHERE status = …` rather than one index on `status` alone: two
        // Retired or RolledBack rows are the ordinary state of an application
        // that has published more than twice, and only Active and Canary are
        // singletons.
        DB::statement(
            "CREATE UNIQUE INDEX pixel_bundle_versions_one_active
                ON pixel_bundle_versions ((1)) WHERE status = 'active'"
        );

        DB::statement(
            "CREATE UNIQUE INDEX pixel_bundle_versions_one_canary
                ON pixel_bundle_versions ((1)) WHERE status = 'canary'"
        );

        // No RLS: platform-scoped, argued in the class docblock and in
        // TenancyTest's $exempt list, `plan_offers`' shape exactly.
    }

    public function down(): void
    {
        Schema::dropIfExists('pixel_bundle_versions');
    }
};
