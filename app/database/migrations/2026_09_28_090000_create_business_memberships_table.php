<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('role');
            $table->timestamp('invited_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE business_memberships ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE business_memberships FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON business_memberships
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);

        DB::statement(<<<'SQL'
            CREATE POLICY member_lookup ON business_memberships FOR SELECT
                USING (user_id = nullif(current_setting('app.user_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('business_memberships');
    }
};
