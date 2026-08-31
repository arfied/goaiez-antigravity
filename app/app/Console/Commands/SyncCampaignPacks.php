<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Campaigns\CampaignPacks;
use App\Support\Campaigns\CampaignPackCatalog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Loads `App\Support\Campaigns\CampaignPackCatalog` into `campaign_packs`
 * (CC-5 §1).
 *
 * `offers:sync` and `legal:seed`'s sibling, with one deliberate difference the
 * service's own docblock argues in full: **the catalogue is authoritative
 * here**, because nothing else has ever written this table and refusing to
 * update would mean a corrected pack could never reach an installed system.
 *
 * ⚠️ **IT PRINTS THE UNAUTHORED PACKS ON EVERY RUN**, on `legal:seed`'s
 * reasoning: a deploy log reading "Wrote 12 packs" reads as a finished job, and
 * the opposite is true — twelve rows exist and not one of them has a message
 * written for it yet.
 *
 * ## ⛔ AND IT PRINTED A SENTENCE THAT WAS NEVER TRUE OF THIS TREE — CORRECTED
 * 2026-08-28 (11700)
 *
 * The census ended *"Their names and promises are **live in the gallery**; the
 * copy is the owner's to supply"*. ⛔ **There is no gallery.**
 * {@see CampaignPacks::gallery()} has no caller outside `tests/`, no view under
 * `resources/` renders a pack, and 11520–11524 carry the measurement with the
 * escape hatches that were checked. **The line was true of an intended screen and
 * has never been true of this tree**, and it was printed to the person running the
 * deploy — the reader most likely to conclude from it that the entry point exists.
 * Decision 5363 repeats the same sentence as a heading and cannot be corrected in
 * place.
 *
 * ⚠️ **NOTHING REPLACES IT, AND THAT IS THE REFUSAL RATHER THAN AN OMISSION**
 * (11526). The obvious replacement is a line saying nothing in `app/` reaches a
 * pack — **a comment in output form**, correct today and silently wrong the
 * morning somebody builds the entry point, and read then as a defect report about
 * a defect that no longer exists.
 *
 * ⚠️ **AND A LIVE-ROW FIGURE WAS BUILT, MEASURED AND WITHDRAWN IN THE SAME
 * SLICE** (11701). Counting `campaign_packs` rows here rather than catalogue
 * entries reddened the chokepoint lint in
 * `tests/Feature/Architecture/MessageCanonTest.php` — *only the campaign pack
 * service reads or writes campaign_packs* — correctly. ⚠️ **The path is written
 * out and the quotation marks are dropped**: there were two
 * `MessageCanonTest.php` files in `tests/`, `CitationTest`'s quoted-citation
 * collector keyed its index by BASENAME, and the citation form
 * `` `MessageCanonTest`'s "…" `` therefore resolved to whichever of the two the
 * filesystem walk returned last. Reported at 11708.
 * ⚠️ **THAT IS FIXED AND THIS WORKAROUND IS NO LONGER NEEDED — wave 46.** The
 * collector indexes every trailing run of path segments, so the citation may
 * carry its quotation marks and one segment of path — `Messaging/MessageCanonTest`
 * — and be checked rather than merely written. **The dropped quotation marks are
 * left here because a citation nobody is quoting is not wrong**, and rewriting
 * it is the next lane's to do deliberately rather than the integrator's to do in
 * passing. The two ways out were adding this file to
 * that lint's `$permitted` (weakening a chokepoint to get a nicer report) and
 * routing the count through {@see CampaignPacks::gallery()} (giving a method with
 * no `app/` caller its first one, so `ops:method-callers` would score the pack
 * chain one step more alive than it is). **Both were refused, and the catalogue is
 * the honest subject anyway**: this command's own `sync()` has just made the rows
 * match it, and 5253's refusal is a fact about the catalogue.
 */
#[Signature('packs:sync {--dry-run : Report what would be written without writing it}')]
#[Description('Seed the authored campaign packs, updating any whose words have changed')]
final class SyncCampaignPacks extends Command
{
    public function handle(CampaignPacks $packs): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $written = $packs->sync($dryRun);

        if ($written === []) {
            $this->info('Every campaign pack already matches the catalogue. Nothing written.');
        } else {
            foreach ($written as $key) {
                $this->line(($dryRun ? 'Would write' : 'Wrote').": {$key}");
            }
        }

        $this->reportUnauthored();

        return self::SUCCESS;
    }

    /**
     * Say out loud which packs cannot be turned on.
     */
    private function reportUnauthored(): void
    {
        $unauthored = array_values(array_filter(
            CampaignPackCatalog::packs(),
            static fn (array $pack): bool => $pack['messages'] === [],
        ));

        if ($unauthored === []) {
            return;
        }

        $this->newLine();
        $this->line('  <fg=yellow>'.count($unauthored).' of '.count(CampaignPackCatalog::packs())
            .' packs have no messages written for them and cannot be turned on.</>');
        $this->line("  <fg=yellow>The copy is the owner's to supply (decision 5253).</>");

        foreach ($unauthored as $pack) {
            $this->line("  <fg=gray>·</> {$pack['key']} — {$pack['name']}");
        }
    }
}
