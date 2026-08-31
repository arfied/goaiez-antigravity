<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The support macro library — T308 §A3, CC-5 §4.
 *
 * S-0 (the escalation line) through S-10, one voice, rendered as insert-buttons
 * in the Ops support composer. T308 §A2's law is why they are rows: *"every
 * macro = a VIEW row — edits in the library, renders in the composer (the house
 * pattern, again)"*. `App\Support\Support\SupportMacroCatalog` is the library;
 * `php artisan macros:sync` is what loads it.
 *
 * ## ⛔ NO ROW-LEVEL SECURITY — `campaign_packs`' ARGUMENT, ONE TABLE OVER
 *
 * **Our authored presets, not tenant data**, and this one is further outside the
 * boundary than the packs are: a macro is what *GO AI EZ staff* say to a tenant.
 * No tenant reads this table, none writes it, and a `business_id` would mean
 * each account holding a private copy of our own support voice. The tenant-owned
 * artefact is the reply itself, which is a `support_messages` row — tenant-owned,
 * RLS `ENABLE`+`FORCE`d, written inside `Tenancy::actingAs()`.
 *
 * ⚠️ **AND THE READER GENUINELY HAS NO TENANT.** `Livewire\Support\Tickets` is
 * an Ops screen; `28` §9.1's internal roles belong to no business, and under
 * `FORCE` RLS a query with no `app.business_id` returns nothing at all —
 * `withoutGlobalScope()` included, because the policy is the database's. A
 * tenant-scoped macro library would be a library the support console could never
 * read, which is `support_queue_entries`' argument beside it.
 *
 * ## ⚠️ WHAT IS DELIBERATELY NOT ON THIS TABLE
 *
 * No customer, no tenant, no phone number, no ticket. A row is a key, a title
 * and a body of ours with `{slot}` placeholders a human fills in — so the
 * strongest thing a reader who should not have it learns is how we talk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_macros', function (Blueprint $table): void {
            $table->id();

            // `s-1` … `s-10`, and `s-0` for the escalation line. What
            // `macros:sync` matches an existing row by.
            $table->string('key')->unique();

            // What the button says. T308 §A3's own headings.
            $table->string('title');

            // The paste. T308 §A2: *"slots and [DATA] only, never typed
            // numbers"* — every figure in a macro is a slot an agent fills, and
            // `SupportMacros::assertEverySlotResolved()` is what stops one
            // reaching a tenant unfilled.
            $table->text('body');

            $table->integer('position');

            $table->timestamps();

            $table->index('position');
        });

        // ⛔ NO `ENABLE`/`FORCE ROW LEVEL SECURITY` AND NO POLICY — see the
        // class docblock. Named on `TenancyTest`'s `$exempt` list with the same
        // argument, where being wrong fails the build.
    }

    public function down(): void
    {
        Schema::dropIfExists('support_macros');
    }
};
