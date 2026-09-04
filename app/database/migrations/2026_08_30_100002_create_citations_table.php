<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('citations')) {
            Schema::create('citations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->string('directory');
                $table->text('directory_url')->nullable();
                $table->string('nap_status')->default('pending');
                $table->string('listing_name')->nullable();
                $table->string('listing_address')->nullable();
                $table->string('listing_phone')->nullable();
                $table->jsonb('mismatch_details')->nullable();
                $table->timestamp('last_checked_at')->nullable();
                $table->timestamps();

                $table->index(['business_id', 'directory']);
                $table->index(['business_id', 'nap_status']);
            });

            if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE citations ENABLE ROW LEVEL SECURITY');
                DB::statement('ALTER TABLE citations FORCE ROW LEVEL SECURITY');
                DB::statement("
                    DO $$
                    BEGIN
                        IF NOT EXISTS (
                            SELECT 1 FROM pg_policies WHERE tablename = 'citations' AND policyname = 'tenant_isolation'
                        ) THEN
                            CREATE POLICY tenant_isolation ON citations
                            FOR ALL
                            USING (business_id = NULLIF(current_setting('app.current_tenant_id', true), '')::bigint)
                            WITH CHECK (business_id = NULLIF(current_setting('app.current_tenant_id', true), '')::bigint);
                        END IF;
                    END
                    $$;
                ");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('citations');
    }
};
