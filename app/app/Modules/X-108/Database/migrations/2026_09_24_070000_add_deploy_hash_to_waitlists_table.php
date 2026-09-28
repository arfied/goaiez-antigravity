<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('waitlists', 'deploy_hash')) {
            Schema::table('waitlists', function (Blueprint $table): void {
                // which deployed page the request was posted from (deployments.deploy_hash, not a foreign key — a superseded or rolled-back deployment keeps its hash and the request keeps its provenance); null for requests that did not come through a deployed page (the owner's own screen, a demo filler).
                $table->string('deploy_hash')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('waitlists', 'deploy_hash')) {
            Schema::table('waitlists', function (Blueprint $table): void {
                $table->dropColumn('deploy_hash');
            });
        }
    }
};
