<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ar_dunning_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('action');
            $table->string('reason');
            $table->timestamps();
        });
        
        DB::statement('ALTER TABLE ar_dunning_actions ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE ar_dunning_actions FORCE ROW LEVEL SECURITY');
        DB::statement(<<<SQL
            CREATE POLICY tenant_isolation ON ar_dunning_actions
                USING (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('ar_dunning_actions');
    }
};
