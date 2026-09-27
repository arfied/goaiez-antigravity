<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fetch_attempts', function (Blueprint $t) {
            // sha256 of the lower-cased host, so a tenant site's cool-down can be
            // scoped to the origin that blocked us. Nullable: rows from before
            // this column carry no host and match no host scope.
            $t->string('host_hash', 64)->nullable()->after('url_hash');
            $t->index(['source_key', 'host_hash', 'cooldown_until'], 'fetch_attempts_source_host_cooldown');
        });
    }

    public function down(): void
    {
        Schema::table('fetch_attempts', function (Blueprint $t) {
            $t->dropIndex('fetch_attempts_source_host_cooldown');
            $t->dropColumn('host_hash');
        });
    }
};
