<?php

declare(strict_types=1);

use App\Enums\UsState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The shape check becomes a list check — `customers.region_code`, slice 5.
 *
 * ⚠️ **`^[A-Z]{2}$` ADMITS `ZZ`, AND A BOGUS CODE PERMITS RATHER THAN REFUSES.**
 * That is the whole reason this runs. `ConsentService::stateRefusal()` looks the
 * code up in `state_messaging_rules`, and *no row* means "no state rule stricter
 * than federal here" — a real answer for the forty-odd states with no
 * mini-TCPA, and the wrong one for a code no legislature has ever used. So the
 * column's own constraint was the permissive branch hiding one layer beneath the
 * writer this slice adds (1596), and it would have reported as compliant.
 *
 * ⚠️ **THE CONSTRAINT KEEPS ITS NAME.** `customers_region_code_is_usps_code` is
 * what the original migration called it and what an existing test asserts on;
 * renaming it would move the failure an operator sees for no gain. A lowercase
 * `fl` still violates it, for the same reason it always did.
 *
 * ⚠️ **THE LIST IS BUILT FROM `App\Enums\UsState`, AND A TEST PARSES THIS FILE
 * BACK.** A hand-typed IN list is a second source of truth that drifts from the
 * PHP one — CLAUDE.md's argument against a database `enum` column applies to a
 * CHECK spelling out the same values. Generating it here removes the drift at
 * write time, and `tests/Feature/CustomerRegionTest.php` reads the constraint
 * out of `pg_constraint` and compares it against the enum on every run, which
 * removes it at read time too. ⚠️ **That pointer named
 * `tests/Feature/Architecture/ConsentTest.php` until 1615 and no such test was
 * ever there** — a reader following it would have concluded the drift was
 * unguarded and either written a second copy or removed the generation.
 *
 * ⚠️ **NOT A DATABASE `enum` TYPE, WHICH IS STILL FORBIDDEN.** The column stays
 * `varchar(2)`. What changes is which values it accepts.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE customers DROP CONSTRAINT customers_region_code_is_usps_code');

        DB::statement(sprintf(
            'ALTER TABLE customers ADD CONSTRAINT customers_region_code_is_usps_code '
            .'CHECK (region_code IS NULL OR region_code IN (%s))',
            $this->codeList(),
        ));
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE customers DROP CONSTRAINT customers_region_code_is_usps_code');

        DB::statement(<<<'SQL'
            ALTER TABLE customers
                ADD CONSTRAINT customers_region_code_is_usps_code
                CHECK (region_code IS NULL OR region_code ~ '^[A-Z]{2}$')
        SQL);
    }

    /**
     * Every USPS code, quoted for SQL.
     *
     * The values are two uppercase letters from a PHP enum, so there is nothing
     * to escape — quoted through the same helper anyway, because a literal built
     * by concatenation is a habit rather than a decision.
     */
    private function codeList(): string
    {
        return implode(', ', array_map(
            static fn (string $code): string => "'".str_replace("'", "''", $code)."'",
            UsState::codes(),
        ));
    }
};
