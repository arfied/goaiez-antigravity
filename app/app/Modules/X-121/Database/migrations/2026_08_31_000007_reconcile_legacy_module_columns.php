<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reconcile legacy module columns (Phase 1 / bf65152).
 *
 * Re-applies every column addition behind hasColumn guards so that
 * fresh environments get the canonical columns through standard migrate
 * without relying on out-of-band ALTER statements.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. conversations.person_id
        if (Schema::hasTable('conversations') && ! Schema::hasColumn('conversations', 'person_id')) {
            Schema::table('conversations', function (Blueprint $table): void {
                $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
            });
        }

        // 2. knowledge_chunks
        if (Schema::hasTable('knowledge_chunks')) {
            Schema::table('knowledge_chunks', function (Blueprint $table): void {
                if (! Schema::hasColumn('knowledge_chunks', 'document_id')) {
                    $table->unsignedBigInteger('document_id')->nullable()->index();
                }
                if (! Schema::hasColumn('knowledge_chunks', 'title')) {
                    $table->string('title')->nullable();
                }
                if (! Schema::hasColumn('knowledge_chunks', 'chunk_text')) {
                    $table->text('chunk_text')->nullable();
                }
                if (! Schema::hasColumn('knowledge_chunks', 'embedding_vector')) {
                    $table->jsonb('embedding_vector')->nullable();
                }
                if (! Schema::hasColumn('knowledge_chunks', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });
        }

        // 3. ai_calls
        if (Schema::hasTable('ai_calls')) {
            Schema::table('ai_calls', function (Blueprint $table): void {
                if (! Schema::hasColumn('ai_calls', 'task_id')) {
                    $table->foreignId('task_id')->nullable()->constrained('ai_tasks')->nullOnDelete();
                }
                if (! Schema::hasColumn('ai_calls', 'model_requested')) {
                    $table->string('model_requested')->nullable();
                }
                if (! Schema::hasColumn('ai_calls', 'model_served')) {
                    $table->string('model_served')->nullable();
                }
                if (! Schema::hasColumn('ai_calls', 'fallback_reason')) {
                    $table->string('fallback_reason')->nullable();
                }
                if (! Schema::hasColumn('ai_calls', 'prompt_id')) {
                    $table->unsignedBigInteger('prompt_id')->nullable();
                }
                if (! Schema::hasColumn('ai_calls', 'prompt_version')) {
                    $table->unsignedInteger('prompt_version')->default(1);
                }
                if (! Schema::hasColumn('ai_calls', 'tokens_in')) {
                    $table->unsignedInteger('tokens_in')->default(0);
                }
                if (! Schema::hasColumn('ai_calls', 'tokens_out')) {
                    $table->unsignedInteger('tokens_out')->default(0);
                }
                if (! Schema::hasColumn('ai_calls', 'cost_cents')) {
                    $table->bigInteger('cost_cents')->default(0);
                }
                if (! Schema::hasColumn('ai_calls', 'usage_unavailable')) {
                    $table->boolean('usage_unavailable')->default(false);
                }
                if (! Schema::hasColumn('ai_calls', 'ttft_ms')) {
                    $table->unsignedInteger('ttft_ms')->default(0);
                }
                if (! Schema::hasColumn('ai_calls', 'latency_ms')) {
                    $table->unsignedInteger('latency_ms')->default(0);
                }
            });
        }

        // 4. gbp_connections
        if (Schema::hasTable('gbp_connections')) {
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

        // 5. short_links
        if (Schema::hasTable('short_links')) {
            Schema::table('short_links', function (Blueprint $table): void {
                if (! Schema::hasColumn('short_links', 'short_code')) {
                    $table->string('short_code')->nullable()->unique();
                }
                if (! Schema::hasColumn('short_links', 'destination_url')) {
                    $table->text('destination_url')->nullable();
                }
                if (! Schema::hasColumn('short_links', 'qr_svg')) {
                    $table->text('qr_svg')->nullable();
                }
                if (! Schema::hasColumn('short_links', 'campaign')) {
                    $table->string('campaign')->nullable();
                }
            });
        }

        // 6. brand_registrations
        if (Schema::hasTable('brand_registrations')) {
            Schema::table('brand_registrations', function (Blueprint $table): void {
                if (! Schema::hasColumn('brand_registrations', 'brand_name')) {
                    $table->string('brand_name')->nullable();
                }
                if (! Schema::hasColumn('brand_registrations', 'tcr_brand_id')) {
                    $table->string('tcr_brand_id')->nullable();
                }
                if (! Schema::hasColumn('brand_registrations', 'registration_status')) {
                    $table->string('registration_status')->default('pending');
                }
                if (! Schema::hasColumn('brand_registrations', 'brand_type')) {
                    $table->string('brand_type')->default('shared');
                }
            });
        }

        // 7. operator_alerts
        if (Schema::hasTable('operator_alerts')) {
            Schema::table('operator_alerts', function (Blueprint $table): void {
                if (! Schema::hasColumn('operator_alerts', 'business_id')) {
                    $table->foreignId('business_id')->nullable()->constrained('businesses')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('operator_alerts', 'severity')) {
                    $table->string('severity')->default('warning');
                }
                if (! Schema::hasColumn('operator_alerts', 'action_verb_message')) {
                    $table->text('action_verb_message')->nullable();
                }
                if (! Schema::hasColumn('operator_alerts', 'status')) {
                    $table->string('status')->default('open');
                }
            });
        }

        // 8. voicemails
        if (Schema::hasTable('voicemails')) {
            Schema::table('voicemails', function (Blueprint $table): void {
                if (! Schema::hasColumn('voicemails', 'call_session_id')) {
                    $table->foreignId('call_session_id')->nullable()->constrained('call_sessions')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('voicemails', 'audio_url')) {
                    $table->string('audio_url')->nullable();
                }
                if (! Schema::hasColumn('voicemails', 'transcription')) {
                    $table->text('transcription')->nullable();
                }
                if (! Schema::hasColumn('voicemails', 'duration_seconds')) {
                    $table->unsignedInteger('duration_seconds')->default(0);
                }
            });
        }

        // 9. carrier_credentials table & RLS
        if (! Schema::hasTable('carrier_credentials')) {
            Schema::create('carrier_credentials', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('carrier_name');
                $table->text('api_key')->nullable();
                $table->text('api_secret')->nullable();
                $table->timestamps();
            });

            DB::statement('ALTER TABLE carrier_credentials ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE carrier_credentials FORCE ROW LEVEL SECURITY');
            DB::statement(<<<'SQL'
                CREATE POLICY tenant_isolation ON carrier_credentials
                    USING (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                    WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
            SQL);
        }

        // 10. payments.gateway_charge_id nullable
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'gateway_charge_id')) {
            DB::statement('ALTER TABLE payments ALTER COLUMN gateway_charge_id DROP NOT NULL');
        }
    }

    public function down(): void
    {
        // Non-destructive rollback
    }
};
