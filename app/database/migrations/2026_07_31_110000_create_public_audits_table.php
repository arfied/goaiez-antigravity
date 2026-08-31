<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The free instant audit — the one table that exists before a tenant does.
 *
 * A visitor types a business name on the marketing home, we run four cheap
 * deterministic checks against it, and we show the result. No account, no
 * login, nothing signed up. That is the premise of `29` §11.2 row 2, and it is
 * why this table looks unlike every other table in the schema.
 *
 * NOT TENANT-OWNED, AND THAT IS THE POINT. Every other table carrying business
 * information has a `business_id`, a global scope, and an RLS policy. This one
 * has none of the three, because at the moment the row is written there is no
 * business in our system to attach it to — the entire audience for this feature
 * is people who have not signed up. Decision 177 draws the line here explicitly:
 * `wizard_progress` is tenant-owned because `29` §6.2 orders signup as
 * register -> tenant auto-provision -> wizard, so a tenant exists by then, and
 * "genuinely pre-signup state lives in `public_audits`, which has no tenant by
 * design".
 *
 * THE CONSEQUENCE IS A SILENCE, SO IT IS WRITTEN DOWN. The RLS convention test
 * in ArchitectureTest walks the models carrying a tenancy trait. This model
 * carries neither, so that test never looks at this table and stays green
 * whether or not RLS is here. Nothing fails and nothing warns. `PublicAudit` is
 * therefore on the allowlist in that test with its rationale written beside it
 * (BUILD-PLAN §2.5.3) — a hole in a build-failing test has to be argued rather
 * than merely added.
 *
 * WHAT PROTECTS IT INSTEAD. Nothing here is private to a tenant: it describes a
 * public Google Business Profile, assembled from public sources, shown on a
 * page the visitor is invited to share. The controls are the ones that fit that
 * shape rather than the tenant boundary — an unguessable token, `noindex`, no
 * listing endpoint, a 90-day expiry, and no PII beyond `ip_hash` (`29` §6.2,
 * decision 192).
 *
 * ON `expires_at` BEING HERE ON DAY ONE. `29` §6.2 specifies no TTL, which
 * would make this a permanent record of businesses that never asked to be
 * audited. Decision 192 sets 90 days. The column ships in the creating
 * migration rather than as a later addition, because a retention policy added
 * after rows exist has no defensible start date: the rows already there either
 * get a backfilled expiry nobody agreed to, or they live forever.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_audits', function (Blueprint $table): void {
            // bigint, not UUID (decision 179). The public identifier is the
            // opaque `token` below — which is the property that actually
            // mattered about UUIDs here, the same split as
            // `businesses.pixel_tenant_id` and `plugins.embed_key`.
            $table->id();

            // The capability. `POST /api/public/audit` returns it, the client
            // polls `GET /api/public/audit/{token}` with it, and `/audit/{token}`
            // renders from it. Long and random enough not to be guessed or
            // walked — slice F asserts non-enumerability.
            //
            // STORED IN PLAIN TEXT, unlike `magic_link_tokens.token_hash`, and
            // the difference is worth stating because the surface reading is
            // that both are bearer tokens and one of them is hashed. A magic
            // link grants more than the row it points at — it grants an account
            // — so a database dump full of live ones is a breach by itself.
            // This token grants exactly the row it points at, and anyone
            // holding that dump already has the row. Hashing would protect
            // nothing, and would cost the ability to show a visitor their own
            // link again.
            $table->string('token', 64)->unique();

            // Google's identifier for the place. Nullable because `29` §6.2
            // accepts `{place_query | place_id}` — a row created from a typed
            // name has no place ID until the resolver (slice C) finds one, and
            // an audit that fails to resolve keeps its row, so the failure is
            // visible rather than absent.
            $table->string('place_id')->nullable();

            // The business name as it read at audit time. A snapshot, not a
            // reference: nothing here points at a live record, and the wizard
            // pre-fill in slice H copies this text rather than joining to it.
            $table->string('name_snapshot')->nullable();

            // Cast to App\Enums\AuditStatus. A string column, never a database
            // enum — CLAUDE.md, and the convention test one directory over.
            $table->string('status', 32);

            // 0-100, and null until the run completes.
            $table->unsignedSmallInteger('score')->nullable();

            // Findings accumulate here as each check completes, so the page can
            // stream them rather than block on the slowest one (`29` §6.2:
            // findings "appear as computed"). Defaulted rather than nullable —
            // an empty audit and a not-yet-started audit are both "no findings
            // yet", and code that has to tell those apart from the column type
            // is code that will get it wrong.
            $table->jsonb('findings')->default('[]');

            // Never the raw address. `29` §12.1 makes "no raw IP stored" a
            // build-failing test, and this column is the only reason the
            // marketing path touches an IP at all: it keys the 3/hr rate limit
            // in slice F. Nullable because a row created by anything other than
            // a visitor request has no address to hash, and a placeholder value
            // would be worse than a null.
            $table->string('ip_hash', 64)->nullable();

            // Decision 192, 90 days. Enforced by `audits:prune`.
            $table->timestamp('expires_at');

            // `29` §6.2 lists only created_at, but status, score and findings
            // all mutate across a run, so updated_at is meaningful here.
            $table->timestamps();

            // The 24h cache of `29` §6.2: "cache by place_id 24h (repeat
            // lookups free)". The lookup is for the most recent audit of a
            // place, so the timestamp belongs in the index rather than in a
            // sort after the fact.
            $table->index(['place_id', 'created_at']);

            // The pruning sweep, which is the only reader of this column.
            $table->index('expires_at');
        });

        // A database CHECK rather than a validation rule, because the score
        // drives a gauge on a public page: an out-of-range value draws the
        // needle off the dial instead of raising anything, which is the class
        // of defect that reaches production intact. `boost_score_history` in
        // DATA-MODEL §5.12 constrains its score the same way.
        //
        // No RLS statement accompanies this table, deliberately — see the
        // docblock above. It is the only creating migration in this schema
        // where that absence is correct.
        DB::statement(
            'ALTER TABLE public_audits
                 ADD CONSTRAINT public_audits_score_range
                 CHECK (score IS NULL OR score BETWEEN 0 AND 100)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('public_audits');
    }
};
