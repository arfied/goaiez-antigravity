<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Actuation\QuarantinedFix;
use App\Services\Actuation\SiteChangeQuarantines;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Let a fix that measured badly run on that site again.
 *
 * ⛔ **THIS SHIPS IN THE SAME SLICE AS THE AUTOMATIC QUARANTINE BECAUSE THE
 * QUARANTINE IS UNSHIPPABLE WITHOUT IT** — 3769's argument, and 3788's
 * discovery of what happens when the exit arrives one slice later: for a while
 * the only way out of an automatic containment was hand-written SQL against
 * production. Nothing here releases itself, deliberately (a quarantine on a
 * timer is not a quarantine), so this command is the whole of the way back.
 *
 * ⛔ **THE ACTOR AND THE REASON ARE REQUIRED AND HAVE NO DEFAULT** (3789).
 * `ops:cli` names no human, and this is a person deciding that an automation
 * which measurably harmed a customer's website should be allowed to run on it
 * again. `29` §2 rule 42 wants the person, not the terminal, and the entry lands
 * in the tenant's own append-only audit log.
 *
 * ⚠️ **RELEASING RE-ARMS NOTHING AND PROMISES NOTHING.** The fix may be applied
 * again, measured again over another fourteen to thirty days, and quarantined
 * again on its own next regression — that is the automatic path and it is
 * unchanged by this command. What does not exist is a shorter leash: the next
 * answer is a month away, and whoever releases is that leash until then.
 */
#[Signature('actuation:release-quarantine
    {business : The tenant id the site belongs to}
    {location : The location id whose website this is}
    {change_type : The kind of change resting on it, e.g. growth_page}
    {--reason= : Why it is being released. Required — it is filed on the row and in the audit log}
    {--actor= : Who is doing this. Required — it is filed as the actor}')]
#[Description('Let a quarantined site fix run on that site again, with a typed reason (BUILD-PLAN 2.11.3 H)')]
final class ReleaseSiteChangeQuarantine extends Command
{
    public function handle(SiteChangeQuarantines $quarantines): int
    {
        $reason = $this->stringOption('reason');
        $actor = $this->stringOption('actor');

        if ($reason === null || $actor === null) {
            $this->components->error('Both --reason and --actor are required. They are filed on the row.');

            return self::FAILURE;
        }

        $businessId = (int) $this->argument('business');
        $locationId = (int) $this->argument('location');
        $changeType = (string) $this->argument('change_type');

        /** @var array{bool, list<QuarantinedFix>} $result */
        $result = Tenancy::actingAs($businessId, function () use (
            $quarantines,
            $locationId,
            $changeType,
            $actor,
            $reason,
        ): array {
            $released = $quarantines->release($locationId, $changeType, $actor, $reason);

            // ⚠️ **THE LIVE LIST IS PRINTED ON A MISS RATHER THAN A BARE "NOT
            // FOUND".** `change_type` is a free string shared with
            // `site_changes`, so the realistic failure is a spelling an operator
            // could not have guessed — and 3789's *"an operator typing a dashed
            // number at 2 a.m. is not told no number matches"* is the same
            // lesson about the same kind of moment.
            return [$released, $released ? [] : $quarantines->live($locationId)];
        });

        Tenancy::forgetAll();

        [$released, $live] = $result;

        if ($released) {
            $this->components->info(
                'Released "'.$changeType.'" on location '.$locationId.'. It may be applied again, and the '
                .'next measurement is fourteen to thirty days after it lands.'
            );

            return self::SUCCESS;
        }

        $this->components->error('Nothing named "'.$changeType.'" is resting on location '.$locationId.'.');

        foreach ($live as $fix) {
            $this->line('  resting: '.$fix->changeType.' — '.$fix->reason);
        }

        return self::FAILURE;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
