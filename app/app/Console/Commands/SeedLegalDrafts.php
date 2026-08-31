<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\LegalDocumentType;
use App\Services\Legal\LegalDocuments;
use App\Support\LegalDraftManifest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Loads the v0.9 legal drafts into `legal_documents` (doc `38` Part 9, D-157).
 *
 * ⚠️ THE SET IS `LegalDraftManifest::documents()`'s TO STATE AND IS NOT WRITTEN
 * DOWN HERE. It was "`39`'s thirteen" until CC-4 added R53's internal refund
 * policy from a second drafting pack, and a count in a docblock is 2505's shape
 * — see 5149, which forbade exactly this in a neighbouring file.
 *
 * `defaults:sync`'s sibling, and a command rather than a migration for the three
 * reasons written on that file: a migration runs once, a rollback would delete
 * rows it did not write, and the seed has to be re-runnable against a live
 * database.
 *
 * ## What this closes
 *
 * `legal_documents`, its versioning service, this schema's first trigger, the
 * public page and the drafting screen have all existed since decisions 417–420.
 * **What did not exist was a single row.** The only writer reachable in
 * production was a Livewire textarea, so counsel's review — `29` §12.1's
 * prelaunch gate 1 — began with somebody hand-typing every legal instrument,
 * and the likeliest outcome of that is a document nobody gets around to.
 *
 * ## What it will not do
 *
 * ⚠️ **IT NEVER TOUCHES A DOCUMENT THAT ALREADY HAS A VERSION.** Not the
 * published one, not an open draft, not one an admin is halfway through editing.
 * `defaults:sync` writes only missing *keys* for the same reason, and the stakes
 * here are higher by an order of magnitude: this table holds the terms every
 * tenant is bound by and the words `consent_records.disclosure_version` points
 * at, and the failure mode of a seed that overwrote would be **silent** — the
 * screen would look right and only the text would have moved back.
 *
 * The check is deliberately *any* version rather than *this* version. Seeding
 * `0.9` beside a published `1.0` would put a stale draft under counsel's nose on
 * the admin index, which is precisely the confusion this document set cannot
 * afford. Decision 721.
 *
 * ## Everything it writes is unpublished and unreviewed, on purpose
 *
 * `39`'s checklist step 3 is *counsel review → named reviewer → admin publish*,
 * and `LegalDocuments::publish()` refuses an unreviewed version. So this command
 * cannot, by construction, put text in front of the public: `/legal/{doc}`
 * serves the newest **published** version and there is none. Seeding changes
 * nothing a customer sees, and that is the correct outcome — what it changes is
 * that counsel now has drafts to read instead of empty boxes.
 */
#[Signature('legal:seed {--dry-run : Report what would be written without writing it}')]
#[Description('Seed the v0.9 legal drafts for any document that has no version yet')]
final class SeedLegalDrafts extends Command
{
    public function handle(LegalDocuments $documents): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $seeded = [];
        $skipped = [];

        // ⚠️ EVERY BODY IS READ BEFORE ANY OF THEM IS WRITTEN, AND THE LOOP
        // ORDER IS WHY. A missing draft file is a packaging fault rather than a
        // database one; read inside the writing loop, a file missing at position
        // twelve would seed eleven documents and then throw, leaving an install
        // half-legal — and the eleven would then be *skipped* on every rerun, so
        // the partial state is the one that persists. `body()` throws, so this
        // loop is the whole of the check.
        // `array_map` rather than a keyed lookup built in one loop and read in
        // the next: it evaluates every body before returning, which is the
        // property wanted, and it hands the second loop its body directly
        // instead of an index that has to be proved present.
        $drafts = array_map(
            fn (LegalDocumentType $type): array => [
                'type' => $type,
                'body' => LegalDraftManifest::body($type),
            ],
            LegalDraftManifest::documents(),
        );

        foreach ($drafts as ['type' => $type, 'body' => $body]) {
            if ($documents->history($type)->isNotEmpty()) {
                $skipped[] = $type;

                continue;
            }

            $seeded[] = $type;

            if ($dryRun) {
                continue;
            }

            // Two writes, one row: `startDraft()` opens it and `saveDraft()`
            // fills it, which is exactly what the admin screen does and needs no
            // fourteenth method on the chokepoint service. The transaction is
            // what stops a failure between them leaving an empty draft that this
            // command would then skip forever on the next run.
            DB::transaction(function () use ($documents, $type, $body): void {
                $draft = $documents->startDraft($type, LegalDraftManifest::VERSION);

                $documents->saveDraft($draft, $body, isPlaceholder: true);
            });
        }

        $this->report($seeded, $skipped, $dryRun);

        return self::SUCCESS;
    }

    /**
     * @param  list<LegalDocumentType>  $seeded
     * @param  list<LegalDocumentType>  $skipped
     */
    private function report(array $seeded, array $skipped, bool $dryRun): void
    {
        if ($seeded === []) {
            $this->info('Every legal document already has a version. Nothing seeded.');
        } else {
            $verb = $dryRun ? 'Would seed' : 'Seeded';

            $this->info($verb.' '.count($seeded).' draft'.(count($seeded) === 1 ? '' : 's')
                .' at version '.LegalDraftManifest::VERSION.':');

            foreach ($seeded as $type) {
                $this->line("  <fg=green>+</> {$type->value} — {$type->title()}");
            }
        }

        foreach ($skipped as $type) {
            $this->line("  <fg=gray>·</> {$type->value} already has a version — left alone");
        }

        // Printed on every run, the way `defaults:sync` prints its withheld
        // figures, and for the same reason: this is the part of the outcome most
        // likely to be misread. A deploy log saying "Seeded 13 drafts" reads like
        // the legal surface is done, and it is the opposite — it is the moment
        // the review starts.
        if ($seeded !== []) {
            $this->newLine();
            $this->line('  <fg=yellow>Every seeded draft is an unreviewed placeholder and none is published.</>');
            $this->line('  <fg=yellow>Counsel review per document, then a named reviewer, then publish — `39` step 3.</>');
        }
    }
}
