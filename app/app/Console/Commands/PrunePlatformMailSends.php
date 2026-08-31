<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Mail\MailQuota;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Delete platform mail-meter rows past {@see self::RETENTION_DAYS} —
 * decisions 8000-8019.
 *
 * ## ⛔ WHY THIS TABLE AND NOT ONE OF THE OTHER HUNDRED AND FORTY-SIX
 *
 * `db:footprint` was built in this slice because *"which tables have no
 * horizon"* had been answered from memory for three waves and the largest table
 * in the schema was absent from every list anybody wrote. **The first thing the
 * derived report printed was this table, at the top.** It is the only one of
 * the hundred and forty-six that needs no ruling from anybody before it can be
 * bounded, and the three reasons are all facts about its own migration:
 *
 *   1. **It holds no personal data of any kind.** A row is a mailer name, one
 *      of *our own* sending accounts, and a timestamp — the creating migration
 *      is explicit: *"NO ADDRESS, NO SUBJECT, NO NOTIFICATION CLASS … this is a
 *      meter and it must not become a send log."* A retention period is the
 *      owner's ruling where it is a period over somebody else's personal data
 *      (4941-4942); this is the platform's own machinery, `platform_health_
 *      windows`' shape.
 *   2. **Nothing has ever been promised about it.** No screen renders it, no
 *      export includes it, and no published term names it.
 *   3. **Its only reader counts twenty-four hours.** {@see MailQuota::used()},
 *      through a chokepoint lint that makes `MailQuota` the only class allowed
 *      to touch the model at all — so *"is there another reader whose history
 *      this would truncate?"* is answered by a build-failing test rather than by
 *      a grep.
 *
 * ## ⚠️ AND IT IS THE ONE PRUNER IN THE FAMILY THAT IS NOT AN OWNER WALK
 *
 * `PruneIngestRejects` and `PruneSendingHealth` walk every user and every
 * business because their tables are row-level secured on `app.business_id` and
 * a console DELETE with no tenant matches nothing (7626). This table carries no
 * tenant column and its policy is `USING (true)`, so the range DELETE genuinely
 * works — and {@see MailQuota::prune()} says so at the statement, with a test
 * that would redden if the policy were ever narrowed.
 *
 * ⚠️ **A HORIZON IS NOT A METER'S CORRECTNESS.** This changes nothing about
 * what any ceiling, reserve or alert can see: `used()` counts a rolling day and
 * the horizon is thirty. The only thing it changes is that the table stops
 * being permanent.
 */
#[Signature('mail:prune-send-meter')]
#[Description('Delete platform mail-meter rows past their retention window')]
final class PrunePlatformMailSends extends Command
{
    /**
     * How long a meter row is kept.
     *
     * ⚠️ **NOT A REGISTRY KEY, ON {@see WatchPlatformHealth::KEEP_DAYS}'
     * ARGUMENT** — *"a configurable retention here would be a setting whose only
     * possible effect is to make the table bigger."* Nothing reads a row older
     * than a day, so an Ops box offering to keep them longer offers a bill and
     * no capability.
     *
     * ⛔ **AND THIRTY RATHER THAN ONE, WHICH IS THE HALF WORTH ARGUING.** The
     * reader's window is twenty-four hours, so a one-day horizon would cost the
     * ceiling nothing — and it would delete the only per-send series anybody
     * could use to answer *"why did we hit the ceiling on Tuesday?"* on
     * Wednesday morning. `operator_alerts` keeps the bell for a year but its
     * context holds a total and not the series. Thirty is
     * {@see WatchPlatformHealth::KEEP_DAYS}, the platform's other own-meter, and
     * 4942's rule that the conservative direction on a deletion schedule is to
     * delete less.
     */
    public const int RETENTION_DAYS = 30;

    public function handle(MailQuota $quota): int
    {
        $deleted = $quota->prune(self::RETENTION_DAYS);

        $this->info($deleted === 0
            ? 'No mail-meter rows older than '.self::RETENTION_DAYS.' days to prune.'
            : "Pruned {$deleted} mail-meter ".str('row')->plural($deleted)
                .' older than '.self::RETENTION_DAYS.' days.');

        return self::SUCCESS;
    }
}
