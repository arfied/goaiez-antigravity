<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citations', function (Blueprint $table): void {
            if (! Schema::hasColumn('citations', 'directory')) {
                $table->string('directory')->default('Directory');
            }
            if (! Schema::hasColumn('citations', 'directory_url')) {
                $table->text('directory_url')->nullable();
            }
            if (! Schema::hasColumn('citations', 'nap_status')) {
                $table->string('nap_status')->default('pending');
            }
            if (! Schema::hasColumn('citations', 'listing_name')) {
                $table->string('listing_name')->nullable();
            }
            if (! Schema::hasColumn('citations', 'listing_address')) {
                $table->string('listing_address')->nullable();
            }
            if (! Schema::hasColumn('citations', 'listing_phone')) {
                $table->string('listing_phone')->nullable();
            }
            if (! Schema::hasColumn('citations', 'mismatch_details')) {
                $table->jsonb('mismatch_details')->nullable();
            }
            if (! Schema::hasColumn('citations', 'last_checked_at')) {
                $table->timestamp('last_checked_at')->nullable();
            }
        });

        // Make legacy columns nullable
        DB::statement('ALTER TABLE citations ALTER COLUMN nap_business_name DROP NOT NULL;');
        DB::statement('ALTER TABLE citations ALTER COLUMN nap_phone DROP NOT NULL;');
        DB::statement('ALTER TABLE citations ALTER COLUMN nap_address DROP NOT NULL;');
    }

    public function down(): void {}
};
