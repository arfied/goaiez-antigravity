<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_calls', function (Blueprint $table) {
            $table->integer('prompt_version')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ai_calls', function (Blueprint $table) {
            $table->integer('prompt_version')->nullable(false)->default(1)->change();
        });
    }
};
