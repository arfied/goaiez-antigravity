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
        if (! Schema::hasTable('mail_pieces')) {
            Schema::create('mail_pieces', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('recipient_address');
                $table->string('postcard_format')->default('4x6');
                $table->unsignedInteger('cost_cents')->default(72);
                $table->string('status')->default('composed'); // composed, proposed, approved, sent, refused_dnm
                $table->string('refusal_reason')->nullable();
                $table->string('lob_api_key_source')->default('tenant_vault'); // TEST ANCHOR: key read from tenant vault
                $table->timestamps();
            });
        }

        $tables = ['mail_pieces'];

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
        Schema::dropIfExists('mail_pieces');
    }
};
