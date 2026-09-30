<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('migration_rejects', 'resolved_at')) {
            Schema::table('migration_rejects', function (Blueprint $table) {
                $table->timestamp('resolved_at')->nullable();
            });
        }
    }
};
