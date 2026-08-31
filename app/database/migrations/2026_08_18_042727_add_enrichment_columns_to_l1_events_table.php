<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `GOAIEZ_PIXEL_MASTER_BUILD` §11 row 8 — the four columns L1Derivation's own
 * docblock predicted and refused to add without a writer.
 *
 * That docblock said, verbatim: *"Absent: `ip_hash` and every geo/ASN column,
 * because there is no collector to hash an address and discard it in the same
 * scope … `browser`, `browser_ver` and `os`, which need the User-Agent the
 * collector sees and the payload does not carry. A nullable column nothing
 * writes is decision 272's shape, so those columns are not in the table
 * either — they arrive with the component that can fill them."* This is that
 * migration, and `App\Services\Pixel\PixelEnrichment` is that component.
 *
 * ⚠️ **GEO/ASN IS DELIBERATELY NOT HERE.** No GeoLite2 (or any) IP-geolocation
 * database is available in this environment — verified, not assumed:
 * `composer.json` names no MaxMind/geoip package and no `.mmdb` file exists
 * anywhere in the checkout. Adding `country`/`region`/`asn`/`asn_org` columns
 * with no real data source would be exactly the trap this migration's own
 * epigraph names: a column with a writer that can only ever write "unknown" is
 * a decoration wearing a schema. `App\Contracts\GeoIpLookup` is the seam a
 * real implementation binds to later; nothing here depends on it existing.
 *
 * ⚠️ **`schema_version` IS BUMPED IN THE SAME COMMIT** (`config/warehouse.php`)
 * and `App\Services\Warehouse\L0Line` is now version-aware: a version-1 line
 * carries none of these four keys and a version-2 line carries all four,
 * *before* `payload` in both cases. `App\Services\Warehouse\L1Derivation`
 * reads the line's own `schema_version` and fills these columns with `null`
 * for a version-1 line — which is the archive's actual invariant ("must only
 * ever be incremented in a commit that also teaches the L1 derivation to read
 * the old version"), not a guess about what old data looked like.
 *
 * ⚠️ **NULLABLE, NEVER A DEFAULT.** A default would satisfy the reproducible-DDL
 * lint's letter while breaking its point: `WarehouseTest`'s first assertion
 * refuses any column whose value a rebuild cannot reproduce, and a constant
 * default is reproducible only because it never varies — which would silently
 * hide a version-1 row's absence of a real value behind a value that looks
 * chosen. `null` says "not known for this line" and stays that for ever.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('l1_events', function (Blueprint $table): void {
            // Keyed HMAC of the request's client address — never the address
            // itself. `29` §2: "Never store raw IP" — satisfied because the raw
            // value is never in a column that could hold it.
            //
            // ⛔ **THE RETENTION ARGUMENT THIS COMMENT MADE HAS BEEN FALSIFIED
            // TWICE AND IS KEPT RATHER THAN DELETED (4368's rule).** It read:
            // *"`App\Support\HashedIp::hash()`'s own docblock is the precedent
            // this reuses: `public_audits.ip_hash` and `magic_link_tokens.ip_hash`
            // already store this same construction long-term, so storing it here
            // for 400 days is not a new class of risk."* **Both cited precedents
            // are gone.** The column is `magic_link_tokens.requested_ip_hash`
            // and never was `ip_hash` (7903), and 7888 pruned that table
            // *precisely because* storing this construction long-term beside a
            // table that expires keeps a join alive indefinitely —
            // `public_audits` has been ninety days since decision 192, so it was
            // never the long-term precedent either.
            //
            // ⚠️ **WHAT IS NOT CHANGED HERE, AND BY WHOM.** Wave 14 lane B
            // scoped the five `proof->ip_hash` blobs away from this value
            // ({@see \App\Enums\ProofHashDomain}) and deliberately did not
            // touch the warehouse. **So this column and the L0 archive behind it
            // are now the longest-lived unscoped copies of the construction in
            // the schema** — 400 days here, and no retention period at all on
            // L0. That is a live finding for whoever holds the warehouse and the
            // pixel, not a claim that it is wrong: `PublicAuditController`
            // matches on the same construction and this column may want to.
            $table->string('ip_hash', 64)->nullable();

            // §12's UA-derived dimensions, `LowCardinality(String)` in the
            // specification's own DDL — a small, closed-ish vocabulary
            // (chrome/safari/firefox/edge/bot/unknown), never a raw
            // User-Agent string. Storing the raw UA would be a step toward the
            // fingerprinting surface `29` §2 forbids; the classified family is
            // not one, on the same reasoning `pixel.js`'s device signals rely
            // on — informative in aggregate, never assembled into an
            // identifier.
            $table->string('browser', 32)->nullable();
            $table->string('browser_version', 16)->nullable();
            $table->string('os', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('l1_events', function (Blueprint $table): void {
            $table->dropColumn(['ip_hash', 'browser', 'browser_version', 'os']);
        });
    }
};
