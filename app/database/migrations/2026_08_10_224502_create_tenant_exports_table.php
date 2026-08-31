<?php

declare(strict_types=1);

use App\Enums\ExportSource;
use App\Enums\ExportStatus;
use App\Jobs\BuildTenantExportJob;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The built artifact behind `28` §3.7 / `44` §10 — "Download my data".
 *
 * Tenant-owned, unlike `data_requests`: an export is not a statutory ask that
 * has to outlive the account, it is a file the account currently owns. One row
 * per build; a retried or rebuilt export is a new row, never a row mutated back
 * to `queued` — see {@see BuildTenantExportJob}'s docblock for why the
 * job never does that itself.
 *
 * `data_request_id` is a soft reference to the platform-scoped queue, the same
 * shape `data_requests.deletion_request_id` uses in the other direction: no
 * foreign key, because a tenant table and a platform table cannot share a
 * cascade without one owning data the other's delete would take with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_exports', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();

            $table->string('source');
            $table->string('status');

            // Soft reference to the ops queue's ask, when this build came from
            // there. Null for an owner's own "Download my data" click.
            $table->unsignedBigInteger('data_request_id')->nullable();

            $table->string('storage_path')->nullable();
            $table->bigInteger('byte_size')->nullable();

            // What shipped and what was refused — the ZIP's own manifest.json,
            // kept alongside the row so the queue can show it without opening
            // the file.
            $table->jsonb('manifest')->nullable();

            $table->timestamp('requested_at');
            $table->timestamp('built_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestamps();

            $table->index(['business_id', 'id']);
        });

        DB::statement('ALTER TABLE tenant_exports ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE tenant_exports FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON tenant_exports
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);

        $sources = collect(ExportSource::cases())
            ->map(fn (ExportSource $source): string => "'".$source->value."'")
            ->implode(', ');

        $statuses = collect(ExportStatus::cases())
            ->map(fn (ExportStatus $status): string => "'".$status->value."'")
            ->implode(', ');

        DB::statement(<<<SQL
            ALTER TABLE tenant_exports
                ADD CONSTRAINT tenant_exports_source_is_known
                CHECK (source IN ({$sources}))
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE tenant_exports
                ADD CONSTRAINT tenant_exports_status_is_known
                CHECK (status IN ({$statuses}))
        SQL);

        // A built row carries a path, an expiry and nothing failed; anything
        // else carries neither. Ready is the only status this schema commits to
        // that pairing for — Building and Failed both legitimately have partial
        // state (a Building row may already hold nothing, a Failed retry may
        // hold a stale path from an earlier attempt this build overwrote) — so
        // the CHECK names the one status where the pairing is a promise rather
        // than a snapshot of work in progress.
        DB::statement(<<<'SQL'
            ALTER TABLE tenant_exports
                ADD CONSTRAINT tenant_exports_ready_is_whole
                CHECK (
                    status <> 'ready'
                    OR (storage_path IS NOT NULL AND built_at IS NOT NULL AND expires_at IS NOT NULL)
                )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE tenant_exports
                ADD CONSTRAINT tenant_exports_failure_is_attributed
                CHECK ((failed_at IS NULL) = (failure_reason IS NULL))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_exports');
    }
};
