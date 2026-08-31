<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * R8 in the schema: one live number per tenant (decision 4821).
 *
 * ⛔ **THE TABLE GUARDED ONE DIRECTION AND NOT THE OTHER, AND THE UNGUARDED ONE
 * IS THE BILLABLE ONE.** `phone_numbers_e164_active_unique` makes one number
 * belong to at most one tenant — the direction that would send a HELP reply
 * naming the wrong business — and nothing at all stopped one tenant holding four
 * numbers. That invariant lived in a single `if` in
 * `TenantNumbers::assign()`, which `NumberLifecycle::record()` walks straight
 * past: it is public, it takes a `businessId`, and until 4821 it asked only I40's
 * role-versus-tenant question.
 *
 * ⚠️ **THE APPLICATION-LAYER REFUSAL STAYS AND IS NOT MADE REDUNDANT BY THIS.**
 * `NumberLifecycle::record()`'s own comment about I40 says why, and it applies
 * here word for word: this method is not the only way a row could ever be
 * inserted, and what the refusal adds is a sentence naming the rule instead of
 * SQLSTATE 23505 quoting an index name at an operator.
 *
 * ⚠️ **`released` AND `retired` ARE EXCLUDED, WHICH IS `forBusiness()`'s OWN
 * PREDICATE.** A released number has gone back to the carrier and a retired one
 * is parked with `business_id` already null, so counting either would refuse a
 * departing tenant's replacement — and `releaseFromTenant()` nulls the column on
 * the way out anyway, so the retired arm is belt-and-braces rather than the load
 * bearing half. A **quarantined** number is in scope: the tenant still holds it.
 *
 * ⛔ **AND IT IS A BILLING CONTAINMENT** (4820). The extra-number SKU is seeded
 * (`plan.base.additional_phone_number.monthly_cents`, 3305) and has no reader, so
 * a second number is not merely ambiguous — it is free, with no allowance to
 * check and no count to bill. 4689(c) calls that a matched pair of missing
 * halves; this is what stops one half arriving alone.
 *
 * ⚠️ **IT WILL REFUSE TO BUILD ON A SCHEMA THAT ALREADY HOLDS A DUPLICATE**, and
 * that is the right failure: a tenant holding two numbers today is a tenant being
 * billed for one, and which of the two to release is a question for a person.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX phone_numbers_one_live_per_business
                ON phone_numbers (business_id)
                WHERE business_id IS NOT NULL
                  AND state NOT IN ('released', 'retired')
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS phone_numbers_one_live_per_business');
    }
};
