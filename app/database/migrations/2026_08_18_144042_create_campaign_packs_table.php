<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The twelve authored campaign packs — T296 §B, CC-5 §1.
 *
 * ## Why this is a table and not a constant
 *
 * T296 §B1's mirror law: *"a gallery card is a VIEW of its SEQ pack — names,
 * previews, and messages render from the pack registry … A string born in the
 * gallery is a bug."* The rows are what a screen reads; `App\Support\
 * CampaignPackCatalog` is what writes them, through `php artisan packs:sync`.
 * It is the shape `plan_offers` and `legal_documents` already use — a reviewed
 * declaration in `app/Support`, one idempotent command, and rows underneath.
 *
 * ## ⛔ NO ROW-LEVEL SECURITY, AND THE ARGUMENT IS THE ONE `plan_offers` MAKES
 *
 * **These are our authored presets, not tenant data.** Every column is copy
 * GO AI EZ wrote for every tenant to read: a pack's name, its one-line promise,
 * and the messages it would send. There is no tenant whose data
 * this is, and a `business_id` here would mean each tenant holding a private
 * copy of the same twelve cards — which is `legal_documents`' argument one
 * table over. What is per-tenant is the **activation**, and an activation is
 * already a `campaigns` row: tenant-owned, RLS `ENABLE`+`FORCE`d, and scoped by
 * `BelongsToTenant`. Nothing about a pack becomes a tenant's until
 * `CampaignPacks::activate()` drafts one.
 *
 * ⚠️ **SO THE TABLE IS ON `TenancyTest`'s `$exempt` LIST AND THE MODEL IS ON
 * ITS ALLOWLIST**, both with this argument written out, because a table with no
 * model-borne tenancy trait is invisible to the RLS convention test that derives
 * its subject from `app/Models`.
 *
 * ## ⚠️ `messages` SHIPS EMPTY FOR ALL TWELVE, AND THAT IS A REFUSAL
 *
 * T296 §B3 authored twelve **names and one-liners** — it calls itself *"owner-
 * language renders of the SEQ packs of record"* — and the packs of record are
 * not in this repository. Inventing the bodies here would mean writing marketing
 * SMS copy that goes out over the GOAIEZ 10DLC brand from our own number pool,
 * which `ReactComposer`'s own docblock refuses in as many words. So the column
 * exists, the reader exists, and `CampaignPacks::activate()` refuses a pack with
 * nothing authored in it by name — see decision 5253.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_packs', function (Blueprint $table): void {
            $table->id();

            // What `packs:sync` matches an existing row by, and what a screen
            // routes on. Unique, so a second spelling seeds a second card
            // rather than updating the first — `plan_offers.key`'s reasoning.
            $table->string('key')->unique();

            $table->string('name');

            // T296 §B2's card grammar: the one-liner (what it does). Its own
            // column rather than part of the name, because a card renders the
            // two in different places and a screen that had to split a string
            // would be where the format drifts.
            //
            // ⚠️ **§B2's "who it's for" CHIP IS NOT HERE** — `CampaignPackCatalog`
            // carries the argument: §B3 authored no chips, and a column seeded
            // empty for ever is `CLAUDE.md`'s first recurring failure.
            $table->string('one_liner');

            // The pack's messages, in order, each `{day_offset, body}`. jsonb
            // rather than a child table: nothing queries inside a pack, the
            // whole list is read at once by the preview and by activation, and
            // a child table would need its own exemption argument for the same
            // rows.
            //
            // ⚠️ **A LIST, NOT AN OBJECT.** Order is the sequence, so the
            // encoding has to preserve it — and `CLAUDE.md`'s own rule about
            // asserting JSON-cast associative arrays with `toEqual` is a symptom
            // of the same fact: a database is free to reorder object keys.
            $table->jsonb('messages')->default('[]');

            // Gallery order. The owner's list is an order, and `id` would make
            // inserting a thirteenth in the middle impossible without a rewrite.
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
        Schema::dropIfExists('campaign_packs');
    }
};
