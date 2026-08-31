<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The write credential for one location's WordPress site — `BUILD-PLAN` §2.11.3
 * slice F1, decisions 5584–5599.
 *
 * ⛔ **THIS IS THE MOST SENSITIVE TABLE IN THE SCHEMA THAT HOLDS NO PERSONAL
 * DATA.** A row here is a working login to a stranger's website. Everything
 * about its shape is chosen on that basis: the secret is encrypted at rest and
 * `$hidden` on the model, `App\Services\Actuation\WordPress\WordPressCredentials`
 * is its only reader and writer behind a chokepoint lint, and the credential
 * never appears in a URL, a log line or an exception message.
 *
 * ## Why its own table rather than the token vault or `platform_credentials`
 *
 * ⚠️ **The vault is OAuth-shaped and this is not an OAuth grant.**
 * `oauth_connections` carries an access token, a refresh token, an expiry and a
 * scope list, and `App\Services\Oauth\TokenService` exists to refresh them. An
 * Application Password has none of those: it does not expire, there is nothing
 * to refresh, and — decision 5582 — **it carries no scope at all**. Storing it
 * in a row shaped for scopes would invite the next reader to believe a `scopes`
 * column meant something here, which is the exact confusion §19.7's gate
 * already suffers from.
 *
 * ⚠️ **And `platform_credentials` is platform-scoped by construction** — its own
 * docblock says "Nothing here should ever grow a `business_id`". This is a
 * tenant's credential, so it is tenant-owned, RLS-`FORCE`d and globally scoped
 * like every other tenant-owned table.
 *
 * ## One row per location, not per business
 *
 * `29` §2 rule 40 makes jobs location-scoped and `locations.website_url` is
 * where a site lives, so a multi-location tenant with three sites has three
 * credentials. The unique index is on `location_id` alone: a location has one
 * website, and a second credential for the same site is not a second
 * connection, it is a stale one nobody revoked.
 *
 * ## The HTTPS CHECK is the mechanism, not the reminder
 *
 * ⛔ Application Passwords are transmitted with **Basic Auth / RFC 7617**, which
 * is the username and the secret base64-encoded in a header — reversible by
 * anybody on the path. WordPress's own documentation puts the requirement in the
 * same sentence as the mechanism: *"The credentials can be passed along to REST
 * API requests served over https:// using Basic Auth / RFC 7617"*
 * (`developer.wordpress.org/rest-api/using-the-rest-api/authentication/`, page
 * last updated 2025-06-04, fetched 2026-08-19). The service refuses an `http://`
 * site with a reason; this CHECK is what makes the refusal a property of the
 * store rather than of the one code path that happens to be in front of it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wordpress_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();

            // The site as the owner confirmed it, normalised to scheme + host +
            // optional path with no trailing slash. Long because a WordPress in
            // a subdirectory is ordinary.
            $table->string('site_url', 512);

            // ⚠️ **THE REST ROOT IS STORED RATHER THAN DERIVED ON EVERY CALL,
            // AND IT IS NOT ALWAYS `site_url` + `/wp-json/`.** A site with plain
            // permalinks answers on `?rest_route=/` instead, and WordPress's own
            // discovery documentation tells clients to "ensure that both routes
            // can be handled seamlessly". Deriving it per request would mean two
            // probes for every write; storing it means a site that changes its
            // permalink structure reconnects, which is a visible act rather than
            // a silent 404 on every change.
            $table->string('rest_root', 512);

            // The WordPress user the Application Password belongs to. Not a
            // secret — it is half of a Basic Auth pair and useless alone — but
            // it is still somebody's login name, so it never reaches a log.
            $table->string('username', 191);

            // ⛔ **CIPHERTEXT. `encrypted` cast on the model, `$hidden` beside
            // it, and a chokepoint lint making the store the only reader.** See
            // the model docblock for why the cast rather than
            // `OauthConnection`'s explicit-encryption shape.
            $table->text('application_password');

            // ⚠️ **THE EVIDENCE FOR §19.7's GATE, RECORDED AT THE MOMENT IT WAS
            // CHECKED.** Decision 5582: an Application Password is not a scope,
            // so "this credential cannot install plugins" is a claim about the
            // WordPress *user* and is only true for as long as that user's role
            // is unchanged. These two columns are what a later probe compares
            // against, so a role escalated inside WordPress after connection is
            // a difference somebody can see rather than an assumption nobody
            // rechecked.
            $table->unsignedBigInteger('wp_user_id');
            $table->jsonb('wp_roles');

            // When the least-privilege probe last passed against the live
            // credential. Written by the connect flow and by every health check;
            // read by both to say how old that answer is.
            $table->timestamp('verified_at');

            $table->timestamps();

            // One website per location. See the class docblock.
            $table->unique('location_id');
            $table->index(['business_id', 'location_id']);
        });

        // ⛔ **HTTPS OR NOTHING, AT THE DATABASE.** Both columns, because the
        // REST root is what requests are actually built from and a discovery
        // answer is a *vendor-supplied* string — WordPress puts the root in a
        // `Link` header on the site's own front page, so a site reached over TLS
        // can still hand back an `http://` root.
        DB::statement(<<<'SQL'
            ALTER TABLE wordpress_credentials
                ADD CONSTRAINT wordpress_credentials_are_https_only CHECK (
                    site_url LIKE 'https://%' AND rest_root LIKE 'https://%'
                )
        SQL);

        DB::statement('ALTER TABLE wordpress_credentials ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE wordpress_credentials FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON wordpress_credentials
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('wordpress_credentials');
    }
};
