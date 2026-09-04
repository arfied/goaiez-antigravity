<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_exports')) {
            // Schema::create removed for tenant_exports to fix duplicates
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_exports');
    }
};
