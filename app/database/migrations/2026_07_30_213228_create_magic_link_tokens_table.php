<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single-use login links — the passwordless route that is not passkeys.
 *
 * FOUND-04 requires magic-link login, and CLAUDE.md requires it to keep working
 * as the fallback rather than being replaced by passkeys, because
 * `laravel/passkeys` is v0.2.1, pre-1.0, and on the login path. If it breaks,
 * this is how people get in.
 *
 * WHY A TABLE RATHER THAN A SIGNED URL. `URL::temporarySignedRoute()` is the
 * one-line version and it is wrong here: a signed URL is replayable for its
 * whole lifetime by anyone who obtains it, and login links get forwarded,
 * quoted in replies, and indexed by mail scanners that follow every link they
 * see. A stored token can be consumed exactly once, and this one is.
 *
 * THE TOKEN IS STORED HASHED, exactly like `password_reset_tokens`. A database
 * dump must not be a set of live login links. SHA-256 rather than bcrypt on
 * purpose: the token is 32 bytes of CSPRNG output, so there is no dictionary to
 * attack and the deliberate slowness of bcrypt would only make the lookup
 * expensive — and the lookup is by hash, which bcrypt's per-row salt makes
 * impossible without scanning the table.
 *
 * NOT TENANT-OWNED, and it cannot be: this is pre-authentication, so no tenant
 * is established when the row is written or read. It is keyed on `email` rather
 * than `user_id` for the same reason the password broker is — asking for a link
 * must not reveal whether an account exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('magic_link_tokens', function (Blueprint $table): void {
            $table->id();

            $table->string('email')->index();

            // Unique both to make the lookup an index seek and to make a
            // collision a database error rather than a silent account swap.
            $table->string('token_hash', 64)->unique();

            $table->timestamp('expires_at');

            // Set on use. The row is kept rather than deleted so a replay is
            // distinguishable from an expiry in the logs, and so pruning is one
            // scheduled sweep rather than a delete on the login path.
            $table->timestamp('consumed_at')->nullable();

            // Never the raw address — `29` privacy rules forbid storing a raw IP
            // anywhere. A hash is enough to spot one source requesting many
            // links, which is the only thing this is for.
            $table->string('requested_ip_hash', 64)->nullable();

            $table->timestamp('created_at')->nullable();

            // Serves the pruning sweep and the "is this still live?" lookup.
            $table->index(['expires_at', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('magic_link_tokens');
    }
};
