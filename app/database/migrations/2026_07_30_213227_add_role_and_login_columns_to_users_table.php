<?php

declare(strict_types=1);

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authorization, and the columns DATA-MODEL §5.2 gives `users`.
 *
 * `role` is a string cast to App\Enums\UserRole, never a database enum — the set
 * is expected to grow (`28` §9.1 adds five internal-staff roles) and PostgreSQL
 * enum values can never be dropped or reordered once added. A convention test
 * greps migrations for `->enum(` and fails the build.
 *
 * NO RLS HERE, and that is correct rather than an omission. `users` is on the
 * TenancyTest allowlist as a non-tenant model: a person exists before a
 * business does — `businesses.owner_user_id` is a foreign key *to* this table —
 * so a policy keyed on the session tenant would make signup unresolvable. The
 * `owner_lookup` policy on `businesses` is the deliberate, SELECT-only bridge
 * across that gap.
 *
 * `password` becomes nullable, and that is the substantive change. Three of the
 * four login methods FOUND-04 requires — Google SSO, Microsoft SSO, magic link —
 * never establish one, and passkeys are meant to replace it outright. A NOT NULL
 * password column forces every SSO signup to invent a random secret nobody
 * holds: a credential that exists in the database only to satisfy a constraint,
 * and one more thing to leak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default(UserRole::Owner->value)->after('email');

            // DATA-MODEL §5.2 columns not yet present.
            $table->string('avatar_url')->nullable()->after('role');
            $table->string('locale', 12)->nullable()->after('avatar_url');
            $table->string('timezone')->nullable()->after('locale');
            $table->timestamp('last_login_at')->nullable()->after('timezone');

            $table->index('role');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'avatar_url', 'locale', 'timezone', 'last_login_at']);
        });
    }
};
