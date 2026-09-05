<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('work_orders', 'person_id')) {
                $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('work_orders', 'person_id')) {
                $table->dropForeign(['person_id']);
                $table->dropColumn('person_id');
            }
        });
    }
};
