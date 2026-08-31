<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which page the adapter actually wrote to — decision 5966's seam, built at 6141
 * to close 6040.
 *
 * ⛔ **WITHOUT IT, "THE URL WE RECORDED IS THE PAGE THE ADAPTER WROTE" IS
 * UNANSWERABLE IN PRINCIPLE.** `site_changes.url` is what we *asked for*: it was
 * built by concatenating `locations.website_url` with a slug, snapshotted, and
 * announced to six search engines. What a CMS then did with it is its own
 * business — WordPress resolves a URL through its last path segment and an owner
 * may edit that slug, change the permalink structure, add or drop `www.`,
 * reparent the page or move domain at any moment in the thirty days between our
 * write and our revert. **Every one of those makes the recorded URL resolve to
 * nothing while our page is still live**, and 6040 records what the platform did
 * about it: reported the page removed, told the owner *"Undone — the page is off
 * your website"*, closed the row and switched off the nightly retry built for
 * exactly that case.
 *
 * ⚠️ **THE VALUE IS THE ADAPTER'S AND NOTHING ABOVE IT PARSES IT** (5966's own
 * rule). `WordPressAdapter` writes `pages/51`; a future adapter writes whatever
 * finds the same page again on its own CMS. `SiteChanges` stores it, hands it
 * back inside the `ChangeSet` at revert time, and never looks inside — parsing
 * it, or parsing it back out of `AdapterOutcome::$detail` where it used to live
 * as prose, would be 5811's failure with a write attached.
 *
 * ⚠️ **NULLABLE, AND NULL MEANS "WE DID NOT RECORD ONE" RATHER THAN "THERE IS
 * NONE"** — every row written before this migration, and every row written by an
 * adapter that has no such name for a page. **A null is fail-closed** where it
 * matters: `WordPressAdapter::unpublishPage()` falls back to the URL, and if the
 * URL resolves to nothing it answers `Unverified` rather than reporting the page
 * gone. The pre-existing behaviour for a pre-existing row is the honest one, not
 * a new risk (5533's shape).
 *
 * ⛔ **NO INDEX AND NO UNIQUENESS.** Nothing looks a change set up by this value
 * — it is only ever read off a row already in hand — and two change sets against
 * one page is ordinary: we may edit the same page twice.
 *
 * ⚠️ **ROW-LEVEL SECURITY IS THE TABLE'S AND IS UNTOUCHED.** `site_changes`
 * carries `ENABLE` + `FORCE` and its policy from the creating migration; adding
 * a column does not need a second one, and the `TenancyTest` lints derive their
 * subject from the table rather than from a column list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_changes', function (Blueprint $table): void {
            $table->string('written_page_ref')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_changes', function (Blueprint $table): void {
            $table->dropColumn('written_page_ref');
        });
    }
};
