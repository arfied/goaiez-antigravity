<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The words that mean drop everything — T176 P5, R13's skill 9.
 *
 * §2.2 skill 9: *"tenant-defined urgent terms (lockout, leak, flood,
 * no-heat…) → immediate owner SMS+email ping; give the tenant's emergency line
 * if set; life-safety → advise emergency services, no advice beyond that"*, and
 * R13 grounds it on *"urgent-terms list (P5)"* — this table.
 *
 * ## ⛔ NOTHING SEEDS THIS, AND THE REASON IS NOT THE USUAL ONE
 *
 * Elsewhere the argument for an empty table is R13's (a missing fact means the
 * skill is absent). Here there is a second and stronger one: **urgency is a fact
 * about a trade, not about the English language.** "No heat" is an emergency for
 * a heating engineer and a Tuesday for a locksmith; "flood" is a plumber's
 * callout and a restoration firm's ordinary work. A seeded platform list would
 * page a business's owner at 3am over a word they never chose, and the second
 * time that happens they stop reading the pings — which disarms the escalation
 * for the terms they *did* choose.
 *
 * ⚠️ **THE LIFE-SAFETY HALF OF SKILL 9 IS NOT GATED ON THIS TABLE AND MUST NOT
 * BE.** *"Life-safety → advise emergency services"* is unconditional; it is the
 * one thing the assistant says without grounding, because the alternative is
 * withholding "call 911" from somebody describing a gas leak on the grounds that
 * their locksmith never typed the word "gas". That behaviour belongs to P4 and
 * is recorded here so the empty table is not read as licence for silence.
 *
 * ## The term is stored as typed and matched case-insensitively
 *
 * ⚠️ **NO SECOND `normalised` COLUMN.** A stored lowercase copy is a second
 * source of truth that drifts the first time somebody edits one of the two, and
 * Postgres indexes an expression directly — so uniqueness is on
 * `lower(btrim(term))` and the tenant's own capitalisation survives onto their
 * screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('urgent_terms', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // The tenant's own word or phrase. ⚠️ **UNTRUSTED** on the same
            // terms as every other tenant-authored string that reaches a model
            // prompt — see `price_list_items.label`.
            //
            // 60 characters: a term is a word or a short phrase ("no hot
            // water"), and a column that accepts a paragraph invites one, which
            // would match nothing and read on screen as a rule that is switched
            // on.
            $table->string('term', 60);

            $table->timestamps();

            $table->index('business_id');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE urgent_terms
                ADD CONSTRAINT urgent_terms_is_a_word
                CHECK (btrim(term) <> '')
        SQL);

        // ⛔ ONE ROW PER WORD PER BUSINESS, CASE-INSENSITIVELY. "Lockout" and
        // "lockout" are one rule; storing both would show an owner a duplicate
        // they cannot tell apart and would double-count nothing useful.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX urgent_terms_are_unique_per_tenant
                ON urgent_terms (business_id, lower(btrim(term)))
        SQL);

        DB::statement('ALTER TABLE urgent_terms ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE urgent_terms FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON urgent_terms
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('urgent_terms');
    }
};
