<?php

declare(strict_types=1);

use App\Services\Storage\StorageFootprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The two object kinds this application stored without recording how big they
 * were — decision 4762.
 *
 * `tenant_exports.byte_size`, `inbound_media.byte_size` and
 * `voicemails.recording_bytes` have carried a size since the day each landed.
 * `knowledge_sources` and `campaign_recipients` did not, so a per-tenant
 * footprint assembled from rows was **silently short by two whole kinds** — and
 * one of them is the largest writer in the set, because `CampaignMedia` renders
 * and stores **one image per recipient**: a thousand-recipient campaign is a
 * thousand objects of up to 500 KB where an export is one.
 *
 * ⚠️ **NULLABLE, AND THE NULL IS NOT A ZERO.** Rows written before this
 * migration name an object whose size nobody recorded, and
 * {@see StorageFootprint} reports them as *unmeasured*
 * rather than folding them into the total. A backfill would have to read the
 * bucket object by object; quietly treating an unknown size as zero would make
 * the oldest accounts — the ones with the most stored — look the emptiest.
 *
 * ⚠️ **NO NEW POLICY AND NO NEW `ENABLE`/`FORCE`.** Both tables are already
 * tenant-owned with row-level security enabled and forced by their creating
 * migrations, and a column added to a table inherits the table's policy. The
 * RLS lints derive their subject from `app/Models` and from `pg_class`, so
 * neither moves here.
 *
 * ⚠️ **`unsignedInteger` MATCHES ITS NEIGHBOURS RATHER THAN `bigInteger`.**
 * `inbound_media.byte_size` is an `unsignedInteger` and both new columns are
 * bounded far below 4 GB by ceilings already in code — `KnowledgeUploads::MAX_KILOBYTES`
 * is 2 MB and `CampaignMedia::MAX_BYTES` is 500 KB. `tenant_exports.byte_size`
 * is a `bigInteger` for the opposite reason: an account ZIP has no ceiling at
 * all, by `28` §3.7.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_sources', function (Blueprint $table): void {
            $table->unsignedInteger('byte_size')->nullable()->after('file_path');
        });

        Schema::table('campaign_recipients', function (Blueprint $table): void {
            $table->unsignedInteger('media_bytes')->nullable()->after('media_path');
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_sources', function (Blueprint $table): void {
            $table->dropColumn('byte_size');
        });

        Schema::table('campaign_recipients', function (Blueprint $table): void {
            $table->dropColumn('media_bytes');
        });
    }
};
