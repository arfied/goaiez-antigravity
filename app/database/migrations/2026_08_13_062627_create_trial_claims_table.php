<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The identities a signup has already claimed, so a second one cannot claim them
 * again (decision 2066, owed at 3117).
 *
 * ⚠️ **WHY A PLATFORM-SCOPED TABLE AND NOT A COLUMN ON `businesses`.** The only
 * question this table exists to answer is a cross-tenant one — *has any **other**
 * account already claimed this identity?* — and `businesses` and `locations` are
 * both `ENABLE`+`FORCE` row-level security on `app.business_id`, so a reader
 * asking that question gets **zero rows rather than an error** (569, restated at
 * 682). `withoutGlobalScopes()` does not help, because the policy is the
 * database's. This is the fifth time that wall has been met and it takes the same
 * answer the other four took: the question is served from a store that has no
 * tenant, and **no policy on any tenant-owned table changed**.
 *
 * ⚠️ **RLS IS STILL `ENABLE`d AND `FORCE`d, WITH A PERMISSIVE POLICY** — the
 * `stripe_customers` / `impersonation_sessions` shape (562, 682). The flags say
 * out loud that the table was considered rather than forgotten, and the policy
 * says the openness is deliberate. It also keeps this table out of
 * `TenancyTest`'s `$exempt` census (3146–3152), whose third assertion fails on an
 * exemption named for a table that does have row-level security.
 *
 * ## What a row is allowed to contain
 *
 * A **keyed HMAC of the identity and nothing else**. Two rules meet here:
 *
 *   - `29`'s "never store raw IP". The signup-origin claim is hashed from
 *     `HashedIp`, which is already keyed, and then domain-separated again.
 *   - A cross-tenant readable table must hold nothing a tenant could be harmed by
 *     another reader seeing — `stripe_customers`' rule. A Google `place_id` is
 *     public information about a public listing, but *which listing belongs to
 *     which of our accounts* is a tenant fact, and this is the one table in the
 *     schema where a stray read of it is not refused by the database. So the
 *     place id is hashed too, and the clear value stays on `locations` behind
 *     RLS.
 *
 * There is no name, no email, no address, no URL and no amount here — the most a
 * reader who should not have this table can learn is that two account numbers
 * share *something*, without learning what.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trial_claims', function (Blueprint $table): void {
            $table->id();

            // CASCADE rather than RESTRICT, and the difference from
            // `stripe_customers` one table over is the direction the mistake
            // runs. A dangling Stripe customer means an inbound event that
            // silently resolves to nobody on the vendor that takes money; a
            // dangling trial claim means an identity stays burned after the
            // account holding it is gone, which refuses a *legitimate* later
            // signup with nothing on any screen able to say why. Deleting a
            // tenant releases its claims.
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // ⛔ WHICH LOCATION THIS CLAIM CAME FROM, AND IT IS WHAT MAKES A
            // MISPASTED LISTING RECOVERABLE (3234).
            //
            // Without it a listing claim was permanent and unreleasable: an
            // owner who picked the wrong business off a Maps results page wrote
            // a claim on a stranger's place id, and correcting
            // `locations.google_place_id` afterwards released nothing, because
            // the register scored **every** historical claim. The real owner of
            // that listing was then refused by somebody else's typo, forever.
            //
            // ⚠️ **SUPERSEDING STAYS APPEND-ONLY: A RE-CONFIRMATION WRITES A NEW
            // ROW, AND THE HIGHEST ROW FOR A LOCATION IS THE LIVE ONE.** No
            // column is ever updated and nothing is deleted, so the trigger
            // below still holds — the history stays readable and "current" is
            // derived from it rather than stored.
            //
            // ⚠️ **AND IT IS DERIVABLE WITHOUT READING `locations`, WHICH IS WHY
            // IT IS A COLUMN HERE RATHER THAN A JOIN.** "Is another tenant's
            // claim still current?" cannot be answered from `locations` — that
            // table is `FORCE` RLS and returns zero rows for anybody else's row.
            // Keeping the location id on the claim keeps the question inside
            // this un-tenanted table, which is the same argument that created
            // the table.
            //
            // Nullable, because a signup-origin claim has no location.
            $table->foreignId('location_id')->nullable()->constrained()->cascadeOnDelete();

            // `App\Enums\TrialClaimKind`. A string cast to a PHP backed enum,
            // never a database enum (CLAUDE.md) — and the churn argument is live
            // rather than theoretical here: today's two kinds are the only two
            // identities this codebase can verify at all, and the list grows the
            // moment anything else can be.
            $table->string('kind');

            // hash_hmac('sha256', kind|value, app.key) — 64 hex characters. See
            // the docblock above for why the clear value never lands here, and
            // TrialEligibility::identityHash() for the domain separation that
            // stops a place id and an address hash ever colliding.
            //
            // ⚠️ **`identity_hash` AND DELIBERATELY NOT `fingerprint`** (3238).
            // The first draft used the obvious word, which collides head-on with
            // `CLAUDE.md`'s top-level privacy rule — *"no fingerprinting … never
            // concatenated into a stable identifier"*. The substance here is the
            // opposite of what that rule forbids: one keyed hash of one declared
            // value, never assembled from device signals. But an auditor greps
            // for the word before reading the argument, and a column that reads
            // as a violation costs somebody a wrong turn every time. Renamed
            // before merge, while it was still free.
            $table->string('identity_hash', 64);

            // ⚠️ NO `updated_at`. A claim is an event — this account claimed this
            // identity at this moment — and the timestamp is load-bearing for the
            // velocity window, so a row whose clock an ordinary save could move
            // is a window that can be walked out of.
            $table->timestamp('created_at');

            // The duplicate lookup: who else holds this identity?
            $table->index(['kind', 'identity_hash']);

            // The supersede lookup: which claim is a location's newest?
            $table->index(['location_id', 'id']);
        });

        // ⛔ **A PARTIAL UNIQUE, AND THE PARTIALITY IS THE SUPERSEDE RULE.** One
        // signup-origin claim per account: an account registers once, and
        // `insertOrIgnore` leans on this so two simultaneous posts cannot both
        // write (350's lesson).
        //
        // ⚠️ **LISTING CLAIMS ARE DELIBERATELY *NOT* UNIQUE**, and the case that
        // forces it is a location confirming X, then Y, then X again. Under a
        // unique on (business, kind, hash) that third write is swallowed, the
        // newest row for the location stays Y, and the register concludes the
        // tenant's live listing is one they moved away from — so a rival on X is
        // let through and this tenant is refused on a listing they do not hold.
        // Superseding needs every confirmation to leave a row.
        //
        // Raw SQL because the Blueprint cannot express a partial unique index,
        // and `rawIndex()` parenthesises the whole expression, which puts the
        // WHERE inside the index term and fails to parse.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX trial_claims_one_origin_per_business
                ON trial_claims (business_id)
             WHERE kind = 'signup_origin'
        SQL);

        DB::statement('ALTER TABLE trial_claims ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE trial_claims FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY platform_scoped ON trial_claims
                FOR ALL USING (true) WITH CHECK (true)
        SQL);

        // ⚠️ APPEND-ONLY AT THE DATABASE, NOT ONLY IN THE MODEL. `TrialClaim`
        // refuses an update in PHP and this refuses one underneath — because the
        // whole value of the table is that an identity a fraudulent signup burned
        // stays burned, and a repair script or a support tool reaching in with
        // raw SQL is exactly the writer the model layer cannot see. It is the
        // second trigger in this schema, after `legal_documents`' (417–420).
        //
        // ⛔ **DELETE IS FROZEN IN THE MODEL AND DELIBERATELY *NOT* HERE, AND THE
        // ASYMMETRY IS LOAD-BEARING RATHER THAN AN OVERSIGHT** (3237). The first
        // draft of this comment said freezing DELETE "would make deleting a
        // tenant impossible" and used that to leave it open in *both* layers.
        // The claim is true of a trigger — the foreign key above cascades, which
        // is a database-level delete, so a `BEFORE DELETE` trigger here makes a
        // tenant undeletable — and false of a model hook, because Eloquent's
        // `deleting` event never fires for a cascade. So the model carries the
        // guard, this layer does not, and a test drives both halves: the model
        // refuses `TrialClaim::query()->delete()` and a tenant delete still
        // cascades its claims away.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION trial_claims_are_append_only()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'trial_claims is append-only: a claim records that an identity was used at a moment in time, and rewriting one un-burns it.';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trial_claims_no_update
                BEFORE UPDATE ON trial_claims
                FOR EACH ROW EXECUTE FUNCTION trial_claims_are_append_only();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trial_claims_no_update ON trial_claims');
        DB::unprepared('DROP FUNCTION IF EXISTS trial_claims_are_append_only()');

        Schema::dropIfExists('trial_claims');
    }
};
