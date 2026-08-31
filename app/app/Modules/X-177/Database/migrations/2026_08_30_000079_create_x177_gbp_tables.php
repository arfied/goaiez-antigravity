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
        if (! Schema::hasTable('gbp_connections')) {
            Schema::create('gbp_connections', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('account_id');
                $table->string('location_id')->index();
                $table->string('profile_status')->default('active'); // active, suspended, verification_required
                $table->timestamps();
            });
        } else {
            Schema::table('gbp_connections', function (Blueprint $table): void {
                if (! Schema::hasColumn('gbp_connections', 'account_id')) {
                    $table->string('account_id')->nullable();
                }
                if (! Schema::hasColumn('gbp_connections', 'location_id')) {
                    $table->string('location_id')->nullable()->index();
                }
                if (! Schema::hasColumn('gbp_connections', 'profile_status')) {
                    $table->string('profile_status')->default('active');
                }
            });
        }

        if (! Schema::hasTable('gbp_posts')) {
            Schema::create('gbp_posts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('connection_id')->constrained('gbp_connections')->cascadeOnDelete();
                $table->string('post_type')->default('update'); // update, event, offer
                $table->text('content');
                $table->string('status')->default('posted'); // posted, blocked_by_suspension, rejected_risk
                $table->string('zernio_dispatch_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gbp_state_log')) {
            Schema::create('gbp_state_log', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('connection_id')->constrained('gbp_connections')->cascadeOnDelete();
                $table->string('event_type'); // state_read, webhook_received, suspension_detected, reinstated
                $table->jsonb('details')->nullable();
                $table->timestamps();
            });
        }

        $tables = ['gbp_connections', 'gbp_posts', 'gbp_state_log'];

        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'business_id')) {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
                DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");

                DB::statement(<<<SQL
                    CREATE POLICY tenant_isolation ON {$table}
                        USING (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                        WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                SQL);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gbp_state_log');
        Schema::dropIfExists('gbp_posts');
        Schema::dropIfExists('gbp_connections');
    }
};
