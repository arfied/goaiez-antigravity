<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('price_book_items', function (Blueprint $table) {
            $table->string('service_key', 255)->nullable();
            $table->index(['business_id', 'service_key']);
        });

        // Backfill existing rows
        foreach (\Illuminate\Support\Facades\DB::table('price_book_items')->cursor() as $row) {
            $key = substr(mb_strtolower(preg_replace('/\s+/', ' ', trim($row->service_name))), 0, 255);
            \Illuminate\Support\Facades\DB::table('price_book_items')
                ->where('id', $row->id)
                ->update(['service_key' => $key]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('price_book_items', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'service_key']);
            $table->dropColumn('service_key');
        });
    }
};
