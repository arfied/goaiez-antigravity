<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('funnels')) {
            Schema::table('funnels', function (Blueprint $table): void {
                if (Schema::hasIndex('funnels', 'funnels_short_slug_unique')) {
                    $table->dropUnique('funnels_short_slug_unique');
                }

                if (! Schema::hasIndex('funnels', 'funnels_business_id_short_slug_unique')) {
                    $table->unique(['business_id', 'short_slug']);
                }
            });
        }
    }

    public function down(): void
    {
        // Non-destructive rollback
    }
};
