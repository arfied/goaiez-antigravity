<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The boss, 2026-10-02: read competitors' full page text, not just headings, as a checklist for the AI designer.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitor_site_notes', function (Blueprint $table): void {
            $table->text('text')->nullable()->after('headings');
        });
    }

    public function down(): void
    {
        Schema::table('competitor_site_notes', function (Blueprint $table): void {
            $table->dropColumn('text');
        });
    }
};
