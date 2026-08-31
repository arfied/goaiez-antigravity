<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One redirect, minted per send — T137 `SL-5`.
 *
 * *"Self-hosted redirector on the owned short domain · per-send tokens · click →
 * CRM timeline · used by SMS (159 law) + email. Rate-limited + bot-filtered."*
 *
 * ## Why the redirector is ours rather than a vendor's
 *
 * A third-party shortener puts a domain we do not control in front of every
 * message a tenant sends, and carrier link filtering scores that domain. It also
 * hands a vendor the click stream — who opened what, when — which is exactly the
 * behavioural data `29` §2's privacy rules exist about. **The short domain is
 * `goaiez.ai` (2116), already owned and already aged**, which is the property
 * T137 §5 wanted a same-day purchase to start earning.
 *
 * ## Two policies, not one — 318's shape for 400's reason
 *
 * ⚠️ **THE REDIRECTOR HOLDS A TOKEN AND NOTHING ELSE.** It is an anonymous
 * request from somebody's phone, arriving with no session, no tenant, and no way
 * to establish one until *this row* has been read — the row is what says which
 * business the link belongs to. A single `tenant_isolation` policy makes that
 * lookup impossible: with `app.business_id` unset the predicate is NULL and every
 * redirect 404s.
 *
 * So this takes `feedback_pages`' and `plugins`' answer rather than an exemption:
 * `public_read` for the resolve, `tenant_write` for everything else.
 * ⚠️ **`public_read` is not "this table is public."** It means a row is fetchable
 * *if you already hold its token*, and the token is twelve base62 characters —
 * about 71 bits — precisely so that holding one is not a capability anybody can
 * guess into. Nothing lists short links on a public path: the resolver takes one
 * token and returns one row, and the only other unscoped read in the application
 * returns a boolean.
 *
 * ⚠️ **AND THE MODEL KEEPS ITS GLOBAL SCOPE**, which is 401's asymmetry and its
 * reasoning verbatim: `Tenancy::idOrFail()` **throws** rather than filtering to
 * nothing, so a scoped model fails loudly on any path that forgot a tenant, while
 * an unscoped one quietly returns whatever `public_read` permits — which is every
 * row of every tenant. One audited opt-out in one documented method beats another
 * allowlist entry.
 *
 * ## The token is per send, and that is what makes a click mean anything
 *
 * A per-tenant or per-campaign link answers "somebody clicked". A per-send token
 * answers "this contact clicked", which is the only version worth putting on a
 * CRM timeline. It is also what lets a link be revoked without touching anybody
 * else's message.
 *
 * ⚠️ **`customer_id` IS NULLABLE AND THE NULL CASE IS REAL** — a link in a
 * message to somebody who is not a contact yet. A click then records that the
 * link was opened and attributes it to nobody, which is honest; inventing a
 * contact from a click would be the alternative and is worse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('short_links', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // ⚠️ **UNIQUE ACROSS THE PLATFORM, NOT PER TENANT.** The redirector
            // resolves by token alone — there is no tenant in the URL and there
            // cannot be, because the URL has to fit inside an SMS. A token
            // unique only within a tenant would resolve to two rows the moment
            // two tenants minted the same one, and the resolve has no predicate
            // to disambiguate with.
            $table->string('token', 32)->nullable()->unique();

            // Where the click goes. ⚠️ Stored whole rather than assembled from
            // parts at redirect time: a link that was minted against one
            // destination must keep pointing there even if the thing that
            // generated it changes its mind, or a message already in somebody's
            // inbox silently starts meaning something else.
            $table->text('target_url')->nullable();

            // Nullable on purpose — see the class docblock.
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            // What this link was for, cast to a PHP backed enum. Never a
            // database enum (CLAUDE.md, enforced by a convention test).
            $table->string('purpose')->nullable();

            // ⚠️ **A LINK IN A TEXT MESSAGE OUTLIVES THE REASON IT WAS SENT.**
            // An expiry is what stops a review invite from 2027 still resolving
            // in 2031, and it is nullable because not every purpose has one.
            $table->timestamp('expires_at')->nullable();

            // Set when a link is deliberately killed — a campaign recalled, a
            // contact who asked. ⚠️ Nulled rather than deleted so a later click
            // can still be told apart from a token that never existed.
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            // The tenant's own list, newest first.
            $table->index(['business_id', 'created_at']);

            // The timeline's read: this contact's links.
            $table->index(['business_id', 'customer_id']);
        });

        DB::statement('ALTER TABLE short_links ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE short_links FORCE ROW LEVEL SECURITY');

        // 318/400's pair. The read is what an anonymous phone does; every write
        // still names a tenant.
        DB::statement(<<<'SQL'
            CREATE POLICY public_read ON short_links
                FOR SELECT USING (true)
        SQL);

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_write ON short_links
                FOR ALL
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('short_links');
    }
};
