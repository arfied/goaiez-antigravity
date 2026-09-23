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
        if (! Schema::hasTable('site_inventory_images')) {
            Schema::create('site_inventory_images', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('page_id')->constrained('site_inventory_pages')->cascadeOnDelete();
                $table->string('source_url');
                $table->string('path')->nullable();
                $table->string('mime')->nullable();
                $table->integer('bytes')->nullable();
                $table->string('status'); // stored|refused|failed|skipped
                $table->text('refusal_reason')->nullable();
                $table->string('attribution'); // the source host
                $table->timestamps();

                $table->unique(['business_id', 'source_url']);
            });

            $table = 'site_inventory_images';
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
        Schema::dropIfExists('site_inventory_images');
    }
};
