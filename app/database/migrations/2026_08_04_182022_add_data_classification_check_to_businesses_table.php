<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The third layer under `businesses.data_classification`.
 *
 * Decisions 314–316, 359 and 383's pattern, on the column that drives the
 * hardest boundary in this system after tenancy. The same rule is enforced in
 * three places on purpose, because each catches what the others cannot:
 *
 *   - THE PHP ENUM stops a bad value reaching the model. `App\Enums\
 *     DataClassification` is what the cast resolves through, so a typo throws
 *     before anything is written.
 *   - THE SERVICE AND THE GUARD stop a request. `TenantClassification` is the
 *     only decider and `Business::$guarded` refuses request-shaped mass
 *     assignment of this column.
 *   - THIS CHECK stops the repair script, the seeder and the psql session that
 *     reached neither. `Business::provision()` writes through forceFill(), and
 *     an UPDATE typed by hand reaches no PHP layer at all.
 *
 * ⚠️ THE THREE VALUES ARE THE EXACT CASES OF `App\Enums\DataClassification` —
 * `pii`, `phi`, `none` — read off that file rather than remembered. A CHECK that
 * drifts from the enum is worse than none: it would refuse a value the
 * application legitimately writes, at the far end of a signup transaction.
 *
 * ⚠️ IT DELIBERATELY DOES NOT FOLLOW `DATA-MODEL` §5.1, which declares this
 * column as a `data_class` Postgres enum type listing `public`/`internal`
 * instead. Two departures, both already made: the creating migration wrote a
 * `string` because CLAUDE.md forbids a database enum outright — a second source
 * of truth that drifts from the PHP one, whose values Postgres cannot drop or
 * reorder once added — and the PHP enum's cases are the ones the application
 * actually uses. The enum is the source of truth; this constraint follows it.
 *
 * ⚠️ AND IT IS A LITERAL LIST, NOT A REFERENCE TO THE PHP ENUM. A CHECK is a
 * database object that outlives any class renamed around it (the same reasoning
 * as `reviews_google_is_never_routed`). Adding a fourth case to the enum means
 * writing a migration, which is the intended friction on a classification the
 * PHI gate reads.
 *
 * No NOT NULL clause is added: the column already carries `DEFAULT 'pii'` and
 * has been non-null since it was created. `CHECK (col IN (…))` is satisfied by
 * NULL rather than violated by it — stated so nobody reads this as covering that
 * case.
 *
 * Specification: `29` §2 rule 24, CLAUDE.md §Critical rules.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE businesses
                ADD CONSTRAINT businesses_data_classification_valid
                CHECK (data_classification IN ('pii', 'phi', 'none'))
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE businesses DROP CONSTRAINT businesses_data_classification_valid');
    }
};
