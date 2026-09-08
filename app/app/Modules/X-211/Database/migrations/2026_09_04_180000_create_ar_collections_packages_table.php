<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ar_collections_packages')) {
            Schema::create('ar_collections_packages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
                // G1-65: the package is BUILT autonomously; TRANSMISSION is a human action — the human is recorded here.
                // null = an engine-level call with no principal; the screen always records one.
                $table->foreignId('packaged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('contents'); // the evidence bundle: invoice, lines, payments, actions, message count
                $table->string('bundle_url')->nullable();
                $table->string('partner')->nullable(); // null = no collections agency yet: the preview's waiting state (OWNER ACTION 14)
                $table->timestamp('transmitted_at')->nullable();
                $table->timestamps();
            });
        }

        DB::statement('ALTER TABLE ar_collections_packages ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE ar_collections_packages FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON ar_collections_packages');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON ar_collections_packages
                USING (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('ar_collections_packages');
    }
};
