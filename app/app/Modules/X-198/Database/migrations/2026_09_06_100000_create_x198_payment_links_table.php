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
        if (! Schema::hasTable('payment_links')) {
            Schema::create('payment_links', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
                $table->string('provider_link_id');
                $table->string('url');
                $table->timestamps();

                $table->unique(['business_id', 'payment_id']);
            });
        }

        $tables = ['payment_links'];

        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'business_id')) {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_links');
    }
};
