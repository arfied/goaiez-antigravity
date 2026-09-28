<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The OWNER's stated opening hours for the site's contact section (not the support desk's).
 *
 * Stored as a list of `{day, open, close}` rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', fn (Blueprint $t) => $t->jsonb('opening_hours')->nullable()->after('website_url'));
    }

    public function down(): void
    {
        Schema::table('locations', fn (Blueprint $t) => $t->dropColumn('opening_hours'));
    }
};
