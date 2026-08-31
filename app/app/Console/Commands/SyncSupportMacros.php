<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Support\SupportMacros;
use App\Support\LegalCanon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Loads `App\Support\Support\SupportMacroCatalog` into `support_macros`
 * (CC-5 §4).
 *
 * `packs:sync`'s twin, with the same authoritative-catalogue rule and the same
 * argument for it.
 *
 * ⚠️ **IT SAYS WHEN A MACRO IS UNAVAILABLE RATHER THAN LETTING IT VANISH.** S-3
 * is bound to `legal.guarantee_sentence` and is not offered while that key is
 * empty, so a deploy log that only said "wrote 11 macros" would leave an agent
 * looking for a button that is deliberately absent.
 */
#[Signature('macros:sync {--dry-run : Report what would be written without writing it}')]
#[Description('Seed the support macro library, updating any macro whose words have changed')]
final class SyncSupportMacros extends Command
{
    public function handle(SupportMacros $macros, LegalCanon $canon): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $written = $macros->sync($dryRun);

        if ($written === []) {
            $this->info('Every support macro already matches the catalogue. Nothing written.');
        } else {
            foreach ($written as $key) {
                $this->line(($dryRun ? 'Would write' : 'Wrote').": {$key}");
            }
        }

        if (! $canon->has(LegalCanon::GUARANTEE_SENTENCE_KEY)) {
            $this->newLine();
            $this->line('  <fg=yellow>`'.LegalCanon::GUARANTEE_SENTENCE_KEY.'` has no value, so macro '
                .'s-3 (the guarantee make-good) is not offered in the console.</>');
            $this->line('  <fg=yellow>The promise is counsel\'s wording and is bound rather than '
                .'pasted — set the row and the button appears (decision 5251).</>');
        }

        return self::SUCCESS;
    }
}
