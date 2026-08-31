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
        if (! Schema::hasTable('businesses')) {
            Schema::create('businesses', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name');
                $table->string('legal_name')->nullable();
                $table->char('currency', 3)->default('USD');
                $table->string('timezone')->default('UTC');
                $table->string('data_classification')->default('pii');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('people')) {
            Schema::create('people', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->jsonb('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('conversations')) {
            Schema::create('conversations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
                $table->string('channel')->default('sms');
                $table->string('status')->default('open');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('messages')) {
            Schema::create('messages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
                $table->string('sender_type')->default('system');
                $table->string('sender_id')->nullable();
                $table->text('body');
                $table->string('direction')->default('outbound');
                $table->string('status')->default('sent');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('work_orders')) {
            Schema::create('work_orders', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('status')->default('pending');
                $table->bigInteger('price_cents')->default(0);
                $table->string('actor_type')->nullable();
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('reviews')) {
            Schema::create('reviews', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
                $table->unsignedTinyInteger('rating')->default(5);
                $table->text('body')->nullable();
                $table->string('status')->default('published');
                $table->string('source')->default('direct');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('campaigns')) {
            Schema::create('campaigns', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('name');
                $table->string('status')->default('draft');
                $table->string('channel')->default('sms');
                $table->string('type')->default('broadcast');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('assets')) {
            Schema::create('assets', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('name');
                $table->string('path');
                $table->string('mime_type')->default('application/octet-stream');
                $table->unsignedInteger('version')->default(1);
                $table->string('checksum')->nullable();
                $table->jsonb('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ledger_entries')) {
            Schema::create('ledger_entries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('entry_type');
                $table->bigInteger('amount_cents');
                $table->char('currency', 3)->default('USD');
                $table->bigInteger('balance_after_cents')->default(0);
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('facts')) {
            Schema::create('facts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('key');
                $table->text('value');
                $table->unsignedInteger('version')->default(1);
                $table->boolean('is_valid')->default(true);
                $table->string('commit_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sites')) {
            Schema::create('sites', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('domain');
                $table->string('status')->default('active');
                $table->boolean('ssl_enabled')->default(true);
                $table->timestamp('last_crawled_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('numbers')) {
            Schema::create('numbers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('e164');
                $table->string('provider')->default('infobip');
                $table->string('status')->default('active');
                $table->timestamp('provisioned_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('entity_history')) {
            Schema::create('entity_history', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('entity_type');
                $table->unsignedBigInteger('entity_id');
                $table->unsignedInteger('version');
                $table->jsonb('field_deltas')->nullable();
                $table->jsonb('snapshot')->nullable();
                $table->string('reversal_action')->nullable();
                $table->string('created_by')->nullable();
                $table->string('commit_id')->nullable();
                $table->timestamps();
            });
        }

        $tables = [
            'businesses', 'people', 'conversations', 'messages', 'work_orders', 'reviews',
            'campaigns', 'assets', 'ledger_entries', 'facts', 'sites', 'numbers', 'entity_history',
        ];

        foreach ($tables as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");

            $col = ($table === 'businesses') ? 'id' : 'business_id';
            DB::statement(<<<SQL
                CREATE POLICY tenant_isolation ON {$table}
                    USING ({$col} = nullif(current_setting('app.business_id', true), '')::bigint)
                    WITH CHECK ({$col} = nullif(current_setting('app.business_id', true), '')::bigint)
            SQL);
        }
    }

    public function down(): void
    {
        $tables = [
            'entity_history', 'numbers', 'sites', 'facts', 'ledger_entries', 'assets',
            'campaigns', 'reviews', 'messages', 'conversations', 'people', 'businesses',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }
};
