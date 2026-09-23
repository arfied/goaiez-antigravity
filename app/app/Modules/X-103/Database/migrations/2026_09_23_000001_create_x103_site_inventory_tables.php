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
        if (! Schema::hasTable('site_inventory_pages')) {
            Schema::create('site_inventory_pages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
                $table->string('url');
                $table->string('title')->nullable();
                $table->jsonb('headings')->nullable();
                $table->text('text')->nullable();
                $table->jsonb('image_urls')->nullable();
                $table->jsonb('phones')->nullable();
                $table->jsonb('emails')->nullable();
                $table->jsonb('links_out')->nullable();
                $table->timestamp('fetched_at')->nullable();
                $table->string('status')->default('fetched');
                $table->text('refusal_reason')->nullable();
                $table->timestamps();

                $table->unique(['business_id', 'url']);
            });

            $table = 'site_inventory_pages';
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");

            DB::statement(<<<SQL
                CREATE POLICY tenant_isolation ON {$table}
                    USING (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                    WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_inventory_pages');
    }
};
