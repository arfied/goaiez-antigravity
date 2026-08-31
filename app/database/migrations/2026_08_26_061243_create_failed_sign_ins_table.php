<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A failed credential check, bucketed by the address tried and the source that
 * tried it — decisions 9860–9879, closing 9723's second half.
 *
 * ⛔ **THE FIRST WRITER OF ANY KIND FOR A FAILED AUTHENTICATION IN THIS
 * APPLICATION.** Until this table there was none: no row, no counter, no log
 * line and no event listener. `app/Listeners` held `RecordSuccessfulLogin` and
 * nothing for the other outcome, and 9723 measured what that costs — 200
 * accepted guesses in one minute from one address across 40 addresses, 300,000
 * an hour against a thousand-address list, **leaving nothing behind at all.**
 *
 * ⛔ **IT BOUNDS NOTHING AND IS NOT MEANT TO.** 9723 refused a lockout, a
 * captcha and a stuffing detector by arithmetic rather than by deference:
 * spraying is *defined* by being low volume per address, so no per-source
 * ceiling separates it from a large tenant's office at nine in the morning.
 * All three are a product decision and a support surface, which
 * `CLAUDE.md`'s first tiebreaker makes the owner's. **This table makes the
 * attack visible; it refuses nobody.**
 *
 * ## ⛔ NO CLEARTEXT ADDRESS, AND THE REASON IS NOT SYMMETRY WITH `HashedIp`
 *
 * The addresses that reach this table are mostly **not our users'**. A
 * credential-stuffing list is somebody else's breach corpus, and a person on it
 * who has never heard of this platform has consented to nothing and has no
 * relationship with us at all. Storing the address in cleartext would build a
 * register of *people who were tried*, growing at the attacker's chosen rate,
 * on a table with no tenant, no RLS and no erasure path — because there is no
 * account to erase. `CLAUDE.md`'s tiebreaker (2) is *less stored PII* and it
 * points one way here.
 *
 * ⚠️ **A KEYED HASH IS WEAKER HERE THAN IT IS FOR AN IP, AND THAT IS SAID
 * RATHER THAN GLOSSED.** `HashedIp`'s argument is that the IPv4 space is 2^32
 * and a laptop enumerates it, so only the key makes the mapping one-way. The
 * email space is not enumerable at all, so the key buys *less* — but it still
 * buys the thing that matters: an attacker holding this table and not
 * `APP_KEY` cannot read an address out of it, and can only **confirm** ones
 * they already hold. Confirming an address they already hold tells them
 * nothing they did not know, because they are the party that tried it.
 *
 * ⛔ **AND THE OPERATOR QUESTION SURVIVES THE HASH INTACT, WHICH IS WHY THIS
 * IS NOT A TRADE.** *"Was one address hammered or were a thousand sprayed?"*
 * is a question about **distinctness**, and equal hashes are equal addresses.
 * The one question a hash cannot answer — *"which of OUR accounts was hit?"* —
 * is answered by `account_existed`, resolved at write time from the event
 * rather than by joining anything afterwards.
 *
 * ## ⚠️ A BUCKETED COUNTER, NOT A ROW PER ATTEMPT
 *
 * A row per attempt would let an attacker choose our insert rate: 9723's
 * measured 300,000 attempts an hour is 300,000 rows an hour, from traffic we
 * are refusing. One row per (address, source, hour) makes the write volume
 * bounded by *distinct addresses tried* rather than by attempts, which is a
 * ~300× reduction at that rate — and it is the shape the question already
 * wants: one hammered address is one row with a large `attempts`, a spray is
 * many rows with `attempts` of one. **Nothing is lost that anybody asks for.**
 *
 * ## ⚠️ NOT TENANT-OWNED, AND UNLIKE MOST OF THAT LIST IT COULD NOT BE
 *
 * On `TenancyTest`'s `$exempt` census and its model allowlist. A failed
 * sign-in happens **before** anybody is authenticated and most often names no
 * account at all, so there is no tenant to scope by and no tenant to fill a
 * `business_id` from — `magic_link_tokens`' position exactly (*"issued to an
 * email, read before auth"*), reached the same way. Filing it under the
 * targeted account's business would be worse than not filing it: on the
 * commonest row there is no account, and on the rest it would put a record of
 * an attack **on our platform** inside one customer's compliance trail.
 *
 * ⚠️ **WHAT REPLACES THE SCOPE**: a chokepoint lint naming
 * `App\Services\Auth\FailedSignIns` as the only file in `app/` allowed to read
 * or write one — reading included, on decision 624's rule, because nothing
 * beneath the application layer refuses a stray read of which addresses were
 * tried — the model's `updating`/`deleting` refusals, the CHECK constraints
 * below, and a retention horizon (`TableHorizons`) so the register does not
 * outlive the join it can be made against.
 *
 * ## ⚠️ NO USER FOREIGN KEY, DELIBERATELY
 *
 * `account_existed` is a boolean and not a `subject_user_id`. A key would name
 * the account for the majority-negative case with a null, which is the same
 * value an unknown address writes, so the column could not tell *"no such
 * account"* from *"the account was deleted since"* — and a `RESTRICT` would let
 * an attacker make one of our users undeletable by guessing at them, while a
 * `SET NULL` would silently rewrite history at erasure time. The boolean states
 * exactly what was true when the guess was made and nothing else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failed_sign_ins', function (Blueprint $table): void {
            $table->id();

            // ⚠️ HMAC-SHA256 UNDER `APP_KEY`, DOMAIN-SEPARATED — the
            // construction `TrialEligibility::identityHash()` uses and
            // `ProofHashDomain` argues for. `App\Services\Auth\FailedSignIns`
            // is where it is built. 64 hex characters, pinned by the CHECK
            // below so a truncated or un-hashed value cannot be written here by
            // a repair script.
            $table->string('email_hash', 64);

            // ⚠️ NULL IS A REAL ANSWER AND `HashedIp` SAYS SO. A request with
            // no client address — a console-driven attempt — gets null rather
            // than a placeholder, so "we could not tell" and "we chose not to
            // say" stay distinguishable. The unique index below is
            // NULLS NOT DISTINCT precisely so those rows still bucket.
            //
            // ⛔ AND `trustProxies` IS UNCONFIGURED ON THIS DEPLOYMENT (336).
            // Behind a CDN every request would carry the edge's address, so
            // this column would collapse to one value platform-wide and the
            // "how wide is the spray" half of the report would read as one
            // source. `email_hash` is unaffected, which is why the report leads
            // with the address tried. That is a deployment change and not this
            // table's to make.
            $table->string('ip_hash', 64)->nullable();

            // The hour this bucket covers, truncated by the writer.
            $table->timestamp('window_start');

            // ⛔ DID THE SUBMITTED ADDRESS NAME AN ACCOUNT OF OURS. Resolved at
            // write time from `Illuminate\Auth\Events\Failed::$user`, which is
            // populated when `retrieveByCredentials()` found somebody and the
            // password was wrong, and null when the address matched nobody.
            //
            // ⚠️ BOTH VALUES ARE REACHABLE AND A TEST DRIVES BOTH, on 9371's
            // rule: a column that cannot take its negative value in the state
            // it exists to report is a decoration wearing a timestamp.
            $table->boolean('account_existed');

            // ⚠️ AT LEAST ONE BY CHECK. A bucket exists because something was
            // tried, so zero is not a quieter row — it is a row written by code
            // that thought it was doing something else.
            $table->unsignedBigInteger('attempts')->default(1);

            // Not nullable, on 289's rule: Postgres sorts NULL first on a DESC
            // order, so one undated row in a security register sits above every
            // dated one for ever.
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
        });

        // ⛔ `NULLS NOT DISTINCT` IS LOAD-BEARING AND IS NOT A STYLE CHOICE.
        // Postgres treats NULLs as distinct in a unique index by default, so
        // without it every row whose `ip_hash` is null would miss `ON CONFLICT`
        // and insert afresh — the one population that would go back to a row
        // per attempt would be the one we can say least about. Postgres 15+.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX failed_sign_ins_bucket_unique
                ON failed_sign_ins (email_hash, ip_hash, window_start)
                NULLS NOT DISTINCT
        SQL);

        // "What has this source been doing" — the spray-width read.
        DB::statement(<<<'SQL'
            CREATE INDEX failed_sign_ins_source_window_index
                ON failed_sign_ins (ip_hash, window_start DESC)
        SQL);

        // "What has happened since X" — the report's own range, and the
        // pruner's predicate.
        DB::statement(<<<'SQL'
            CREATE INDEX failed_sign_ins_window_index
                ON failed_sign_ins (window_start DESC)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE failed_sign_ins
                ADD CONSTRAINT failed_sign_ins_shape_is_a_bucket
                CHECK (
                    attempts >= 1
                AND last_seen_at >= first_seen_at
                AND first_seen_at >= window_start
                AND email_hash ~ '^[0-9a-f]{64}$'
                AND (ip_hash IS NULL OR ip_hash ~ '^[0-9a-f]{64}$')
                )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_sign_ins');
    }
};
