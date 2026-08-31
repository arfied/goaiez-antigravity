<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\IndustryPage;
use App\Support\IndustryPageManifest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Loads PIII-64A–E's hundred authored rows into `industry_pages` — CC-3 §2.
 *
 * `legal:seed` and `defaults:sync`'s sibling, and a command rather than a
 * migration for the three reasons written on those: a migration runs once, a
 * rollback would delete rows it did not write, and the seed has to be re-runnable
 * against a live database, because **the authored files ARE the data** and
 * editing a row means re-running this.
 *
 * ## Upsert by slug, and what that deliberately does and does not touch
 *
 * ⚠️ **IT OVERWRITES AUTHORED CONTENT AND THIS IS THE OPPOSITE OF `legal:seed`.**
 * That command refuses to touch a document that has any version at all, because
 * `legal_documents` holds words counsel reviewed and a consent record points at.
 * Here the source file is the reviewed artefact and the row is its render, so a
 * re-seed after an edit *must* move the row or the whole VIEW LAW is decorative.
 *
 * ⛔ **IT NEVER TOUCHES `index_mode` OR `old_slugs` ON A ROW THAT EXISTS.** Those
 * two are not authored — `index_mode` is the owner's flip (CC-3 §4) and
 * `old_slugs` is written by the model on a rename (§5). A seed that reset either
 * would un-index the hundred on the next deploy, or orphan every URL a rename had
 * preserved, in a command nobody would think to look at.
 *
 * ⚠️ **A SLUG CHANGED IN THE SOURCE FILE IS A NEW ROW, NOT A RENAME**, because
 * the upsert matches on the slug. §5's redirect covers a rename made *through the
 * model*; renaming in the corpus and re-seeding leaves the old row in place and
 * adds a second one. The seed reports rows it did not write for exactly this
 * reason — see the summary.
 */
#[Signature('industries:seed {--dry-run : Report what would be written without writing it}')]
#[Description("Seed PIII-64A–E's hundred industry pages from the authored source files")]
final class SeedIndustryPages extends Command
{
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // ⚠️ EVERY ROW IS PARSED AND VALIDATED BEFORE ANY OF THEM IS WRITTEN.
        // `SeedLegalDrafts`' ordering and its reason: a corpus that fails at row
        // eighty-seven would otherwise leave eighty-six pages seeded and the hub
        // missing a family's tail, which is a half-published marketing site that
        // looks complete.
        $rows = IndustryPageManifest::rows();

        $existing = IndustryPage::query()->pluck('slug')->all();

        $created = 0;
        $updated = 0;

        if (! $dryRun) {
            DB::transaction(function () use ($rows, &$created, &$updated): void {
                foreach ($rows as $row) {
                    $page = IndustryPage::query()->firstOrNew(['slug' => $row['slug']]);

                    $page->exists ? $updated++ : $created++;

                    $page->fill([
                        'family' => $row['family'],
                        'h1' => $row['h1'],
                        'title' => $row['title'],
                        'meta_desc' => $row['meta_desc'],
                        'hook' => $row['hook'],
                        'beat' => $row['beat'],
                        'trio' => $row['trio'],
                        'trust' => $row['trust'],
                        'demo_keyword' => $row['demo_keyword'],
                        'faq_picks' => $row['faq_picks'],
                        'position' => $row['position'],
                    ]);

                    // A new row takes the column default (`false`) and an
                    // existing one keeps whatever the owner set — see the class
                    // docblock. `index_mode` and `old_slugs` are absent from the
                    // fill above deliberately, not by oversight.
                    $page->save();
                }
            });
        }

        $orphans = array_values(array_diff($existing, array_column($rows, 'slug')));

        $this->report(count($rows), $created, $updated, $orphans, $dryRun);

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $orphans
     */
    private function report(int $parsed, int $created, int $updated, array $orphans, bool $dryRun): void
    {
        $this->info(($dryRun ? 'Would seed ' : 'Seeded ').$parsed.' industry pages'
            .($dryRun ? '.' : " — {$created} new, {$updated} updated."));

        if ($orphans === []) {
            return;
        }

        // ⚠️ REPORTED AND NEVER DELETED. A row the corpus no longer names is
        // either a slug that was renamed in the source file — in which case the
        // old row is what the 301 needs — or a page somebody removed on purpose.
        // Deleting on a guess makes the first case a dead URL, which is the one
        // outcome §5 exists to prevent.
        $this->warn('These rows are in the database and not in the corpus, and were left alone: '
            .implode(', ', $orphans));
    }
}
