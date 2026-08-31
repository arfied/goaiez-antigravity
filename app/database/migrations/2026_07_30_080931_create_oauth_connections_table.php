<?php

declare(strict_types=1);

use App\Enums\ConnectionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The multi-provider token vault (DATA-MODEL §5.2).
 *
 * Token columns hold ciphertext only: FOUND-03 encrypts at the application
 * layer before insert, and tokens are never returned to the frontend
 * (DATA-MODEL §5.14).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_connections', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // DATA-MODEL declares the `oauth_provider` Postgres enum type; a
            // string cast to App\Enums\OauthProvider instead, per the
            // convention test.
            $table->string('provider');

            // Nullable: the provider's account id arrives with the first token
            // exchange, and a connection row may exist momentarily before it.
            $table->string('external_account_id')->nullable();

            $table->string('display_label')->nullable();

            // Ciphertext, encrypted at the application layer before insert.
            // Nullable because revocation clears them rather than deleting the
            // row — the row keeps the error and drives the Reconnect prompt.
            $table->text('access_token_enc')->nullable();
            $table->text('refresh_token_enc')->nullable();

            $table->timestamp('token_expires_at')->nullable();

            // TEXT[] in DATA-MODEL; jsonb here. Laravel has no native Postgres
            // array column or cast, and jsonb keeps the list semantics while
            // working with an ordinary 'array' cast.
            $table->jsonb('scopes')->nullable();

            $table->string('status')->default(ConnectionStatus::Active->value);

            $table->timestamp('last_refreshed_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();

            // Composite with business_id, never global: two businesses may
            // connect the same Google account (an agency managing both), and a
            // global unique would reveal one tenant's connection to another.
            $table->unique(['business_id', 'provider', 'external_account_id']);
        });

        DB::statement('ALTER TABLE oauth_connections ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE oauth_connections FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON oauth_connections
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_connections');
    }
};
