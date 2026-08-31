<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * In-app/staff notifications (DATA-MODEL §5.12).
 *
 * DATA-MODEL's own shape, not Laravel's database notification channel — the
 * spec's table is tenant-owned and RLS'd, which the framework's morphs-based
 * table is not. If the framework channel is ever wanted, that is a separate
 * decision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // Nullable: a notification may address the whole business's staff.
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('type');
            $table->string('channel')->nullable();

            $table->string('title');
            $table->text('body')->nullable();

            $table->timestamp('read_at')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['business_id', 'user_id']);
        });

        DB::statement('ALTER TABLE notifications ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE notifications FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON notifications
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
