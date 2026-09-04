<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments') && !Schema::hasColumn('payments', 'invoice_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->unsignedBigInteger('invoice_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'invoice_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('invoice_id');
            });
        }
    }
};
