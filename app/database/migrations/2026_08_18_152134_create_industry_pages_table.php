<?php

declare(strict_types=1);

use App\Enums\IndustryFamily;
use App\Support\IndustryPageManifest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The hundred industry pages — CC-3 §1, from PIII-64A–E.
 *
 * ## Platform-scoped, no row-level security — `legal_documents`' argument, minus the trigger
 *
 * These are **our** marketing pages about industries, not a tenant's data. There
 * is no `business_id` to scope by and there could not be one: a tenant-owned copy
 * of `/industries/plumbing` would mean every plumber on the platform holding a
 * private edition of a page the public reads. It joins the named-exception list
 * in `tests/Feature/Architecture/TenancyTest.php` with the argument written there
 * as well as here.
 *
 * ⚠️ **AND WITHOUT `legal_documents`' FREEZE TRIGGER, DELIBERATELY.** That table
 * freezes a published row because `consent_records.disclosure_version` points at
 * exact words somebody agreed to, and nothing anywhere records what the text used
 * to be. Nothing points at these rows; the whole design (CC-3's opening line) is
 * that **edits happen in the authored source rows and re-seed**, so a frozen row
 * would break the one workflow this table exists to serve.
 *
 * ## The three meta limits are columns, not only checks
 *
 * `IndustryPageManifest` refuses an over-long `h1`, `title` or `meta_desc` at
 * parse time and names the row. The widths here are the same three numbers, and
 * they exist because a check in one class is a check one writer can miss —
 * `IndustryPageFactory` is a second writer today and a screen could be a third.
 * A `<title>` that overflows the SERP is a silent defect: the page renders, the
 * suite passes, and Google prints an ellipsis.
 *
 * ## `position` is unique, and that is the hub's ordering
 *
 * PIII-64A–E number the hundred 01–100 and PIII-72 §A1 lists them in that order.
 * Two rows at position 41 is not a display bug, it is a corpus that has silently
 * lost a row — so it is refused here as well as in the manifest.
 *
 * ## `old_slugs` is jsonb with a GIN index
 *
 * The redirect lookup is *"is this unknown slug in ANY row's `old_slugs`?"*,
 * which is a containment query (`@>`) over every row. Unindexed it is a
 * sequential scan on every 404 under `/industries/`, which is a free
 * denial-of-service on a public path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('industry_pages', function (Blueprint $table): void {
            $table->id();

            $table->string('slug', 80)->unique();

            // A string cast to `IndustryFamily`, never a database enum — the
            // house rule, with the CHECK below doing the constraining a `enum`
            // column would have done, without the second source of truth.
            $table->string('family', 20);

            $table->string('h1', IndustryPageManifest::H1_MAX);
            $table->string('title', IndustryPageManifest::TITLE_MAX);
            $table->string('meta_desc', IndustryPageManifest::META_DESC_MAX);

            $table->text('hook');
            $table->text('beat');
            $table->jsonb('trio');
            $table->text('trust');

            // The word a visitor texts. Unique across the hundred because two
            // pages sharing one keyword makes the reply ambiguous at the demo
            // number, which is a live surface rather than a cosmetic clash.
            $table->string('demo_keyword', 40)->unique();

            $table->jsonb('faq_picks');

            $table->unsignedSmallInteger('position')->unique();

            // ⚠️ FALSE, AND THE FLIP IS THE OWNER'S WORD (CC-3 §4). One column
            // feeds both the page's `noindex` tag and `sitemap-industries.xml`,
            // so the indexability moment is atomic by construction.
            $table->boolean('index_mode')->default(false);

            // Appended by the model on any slug change; read back by the 301.
            $table->jsonb('old_slugs')->default(DB::raw("'[]'::jsonb"));

            $table->timestamps();
        });

        DB::statement(
            'ALTER TABLE industry_pages ADD CONSTRAINT industry_pages_family_check '
            ."CHECK (family IN ('".implode("', '", IndustryFamily::values())."'))"
        );

        // ⚠️ `jsonb_path_ops` RATHER THAN THE DEFAULT OPERATOR CLASS. The only
        // query this index serves is containment, and `jsonb_path_ops` is
        // smaller and faster for exactly that at the cost of the operators this
        // column never uses.
        DB::statement(
            'CREATE INDEX industry_pages_old_slugs_gin ON industry_pages '
            .'USING gin (old_slugs jsonb_path_ops)'
        );

        // Reading the hub is one query per family in `position` order.
        DB::statement('CREATE INDEX industry_pages_family_position ON industry_pages (family, position)');

        // No RLS: platform-scoped, argued in the class docblock above and named
        // in `TenancyTest`'s `$exempt` list.
    }

    public function down(): void
    {
        Schema::dropIfExists('industry_pages');
    }
};
