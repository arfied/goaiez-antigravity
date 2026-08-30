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
        if (! Schema::hasTable('call_tokens')) {
            Schema::create('call_tokens', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('visitor_session_token')->index();
                $table->unsignedBigInteger('phone_number_id')->nullable();
                $table->string('allocated_number')->index();
                $table->string('campaign_source')->default('website_direct'); // G18-17
                $table->string('whisper_text')->default('Call from website direct'); // G18-17, G18-24
                $table->timestamp('expires_at'); // TEST ANCHOR: TTL window
                $table->unsignedBigInteger('joined_call_id')->nullable();
                $table->string('status')->default('active'); // active, joined, expired_unattributed
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('short_links')) {
            Schema::create('short_links', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('short_code')->unique();
                $table->text('destination_url');
                $table->text('qr_svg')->nullable();
                $table->string('campaign')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('link_clicks')) {
            Schema::create('link_clicks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('short_link_id')->constrained('short_links')->cascadeOnDelete();
                $table->string('ip_address')->nullable();
                $table->string('user_agent')->nullable();
                $table->timestamp('clicked_at')->useCurrent();
            });
        }

        $tables = ['call_tokens', 'short_links', 'link_clicks'];

        foreach ($tables as $table) {
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

    public function down(): void
    {
        Schema::dropIfExists('link_clicks');
        Schema::dropIfExists('short_links');
        Schema::dropIfExists('call_tokens');
    }
};
