<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cursor columns for Google review sync — decision 1348.
 *
 * Deliberately absent from the creating migration: a nullable column with no
 * writer reads as a feature that is not working rather than one that is not
 * built. They land with SyncGoogleReviewsJob, the only thing that advances them,
 * and GbpConnections remains the only writer (architecture lint).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gbp_connections', function (Blueprint $table): void {
            // Opaque provider page token. Null means "start of list" on the next
            // backfill resume, or "idle" after a finished sync.
            $table->text('sync_cursor')->nullable()->after('last_error');

            $table->timestampTz('last_synced_at')->nullable()->after('sync_cursor');
        });
    }

    public function down(): void
    {
        Schema::table('gbp_connections', function (Blueprint $table): void {
            $table->dropColumn(['sync_cursor', 'last_synced_at']);
        });
    }
};
