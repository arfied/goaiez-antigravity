<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitors', function (Blueprint $table): void {
            // The peer's own website as Google lists it (Places `websiteUri`), so a
            // business with no site can learn from the top 5 nearby. Input for fresh
            // copy, never a source to copy from. Null when Google has none.
            $table->string('website_url', 2048)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('competitors', function (Blueprint $table): void {
            $table->dropColumn('website_url');
        });
    }
};
