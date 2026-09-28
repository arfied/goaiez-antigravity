<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('provider_review_id')->nullable();
            $table->string('recommendation')->nullable();
        });

        DB::statement("ALTER TABLE reviews ADD CONSTRAINT reviews_recommendation_facebook_only CHECK (recommendation IS NULL OR (source = 'facebook' AND recommendation IN ('positive','negative')))");
        DB::statement('CREATE UNIQUE INDEX reviews_location_source_provider_review_unique ON reviews (location_id, source, provider_review_id) WHERE provider_review_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX reviews_location_source_provider_review_unique');
        DB::statement('ALTER TABLE reviews DROP CONSTRAINT reviews_recommendation_facebook_only');

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['provider_review_id', 'recommendation']);
        });
    }
};
