<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Actuation\ActuationTiers;
use App\Services\Actuation\ChangeEvidence;
use App\Services\Actuation\ChangeMetrics;
use App\Services\Actuation\OwnerSiteChange;
use App\Services\Actuation\SiteChanges;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Everything we measured about one change to a customer's website — including
 * the half an owner is deliberately not shown.
 *
 * ⛔ **THE SECOND HALF OF THE READER 5819 LEFT OWED, AND `ShowStorageFootprint`
 * IS THE PRECEDENT** (4763). The owner's card carries one sentence about the
 * **page's own** signal, because that is what `29` §2 rule 47 permits and what
 * 5808 allows to be claimed. What that sentence leaves out — the whole-property
 * search figures, the conversion counts, the exact windows and the per-signal
 * readings — is real evidence support needs on the phone when somebody asks
 * *"why did you take my page down"*, and **a document nobody can read is 272
 * whichever half of it is unread**.
 *
 * ⛔ **AND IT IS WHY THE OWNER-FACING SENTENCE CAN AFFORD TO BE NARROW.**
 * Without this, keeping the search figures off the card would mean keeping them
 * from everybody, for ever. With it, the answer to *"what are we keeping to
 * ourselves"* is *"nothing — the site-wide figure is one command away, and it is
 * off the card because it is not about that page"*.
 *
 * ⛔ **ONE ACCOUNT, NAMED, NEVER A LIST** — decision 569's wall, and
 * {@see ShowStorageFootprint}'s reasoning verbatim. `site_changes` is RLS
 * `ENABLE`+`FORCE`d, a platform operator has no tenant, and naming the account
 * is what sets `app.business_id` to it and nothing else.
 *
 * ⛔ **READ-ONLY, AND IT FILES NOTHING.** No impersonation session and no audit
 * row, on `ShowSearchConsoleStatus`' reasoning: it prints counts and dates, not
 * a customer's content. ⚠️ **The URL is the one thing here that came off their
 * site**, and it goes through {@see OwnerSiteChange::displayUrl()}, which drops
 * the query string on the rule `ChangeMeasurer`'s log line follows — a full URL
 * with a query string is where a booking reference ends up.
 *
 * ⚠️ **A NULL PRINTS AS "not seen" AND NEVER AS `0`** (229, 5804). The whole
 * reason these documents keep the two apart is that a page which did not exist
 * did not get zero visits, and an operator reading a zero would repeat it to a
 * customer.
 */
#[Signature('actuation:evidence {business : The business id} {change : The site_changes row id}')]
#[Description('Print everything measured about one change to a customer website')]
final class ShowChangeEvidence extends Command
{
    public function handle(SiteChanges $log, ActuationTiers $tiers): int
    {
        $businessId = (int) $this->argument('business');
        $changeId = (int) $this->argument('change');

        $card = Tenancy::actingAs($businessId, function () use ($log, $tiers, $changeId): ?OwnerSiteChange {
            foreach ($log->history($tiers) as $candidate) {
                if ($candidate->id === $changeId) {
                    return $candidate;
                }
            }

            return null;
        });

        if (! $card instanceof OwnerSiteChange) {
            // ⚠️ **ONE SENTENCE FOR "NOT THIS TENANT'S" AND FOR "NEVER REACHED
            // THE PAGE"**, deliberately. `history()` lists applied change sets
            // for the named account only, and an operator who could tell those
            // two apart from the message could enumerate another tenant's rows.
            $this->error('No applied change #'.$changeId.' on account #'.$businessId.'.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('Account   #'.$businessId);
        $this->line('Change    #'.$card->id.'   '.$card->tier->value
            .'   '.($card->createdThePage ? 'a page we added' : 'a page we edited'));
        $this->line('Page      '.$card->displayUrl());
        $this->line('Location  '.$card->locationName);
        $this->line('Applied   '.$card->appliedAt->toFormattedDayDateString());
        $this->line('State     '.$card->state->label().'  ('.$card->state->value.')');

        $this->evidence($card);

        if ($card->undoneAt !== null) {
            $this->newLine();
            $this->line('Undone    '.$card->undoneAt->toFormattedDayDateString());
            $this->line('Reason    '.($card->undoneReason ?? '—'));
        }

        if ($card->withheld !== []) {
            $this->newLine();
            $this->line('Fields we could not write:');

            foreach ($card->withheld as $sentence) {
                $this->line('  · '.$sentence);
            }
        }

        $this->newLine();

        return self::SUCCESS;
    }

    private function evidence(OwnerSiteChange $card): void
    {
        $this->newLine();

        if (! $card->evidence instanceof ChangeEvidence) {
            // ⚠️ **TWO REASONS AND THIS COMMAND CANNOT TELL THEM APART**, so it
            // says both. *Not measured yet* is the ordinary one; *a document of a
            // version this build does not read* is the other, and it matters
            // because it would otherwise look like the first for ever.
            $this->warn('  No readable measurement on this row.');
            $this->line('  Either nothing has measured it yet, or the stored documents are of a');
            $this->line('  version this build does not read (ChangeMetrics::VERSION is '.ChangeMetrics::VERSION.').');

            return;
        }

        $evidence = $card->evidence;

        $this->line('  '.str_pad('', 22).str_pad('before', 12, ' ', STR_PAD_LEFT).str_pad('after', 12, ' ', STR_PAD_LEFT));
        $this->line('  '.str_pad('days in window', 22)
            .str_pad((string) $evidence->baselineDays, 12, ' ', STR_PAD_LEFT)
            .str_pad((string) $evidence->measuredDays, 12, ' ', STR_PAD_LEFT));

        foreach ([
            'page views' => [$evidence->pageviewsBefore, $evidence->pageviewsAfter],
            'page conversions' => [$evidence->conversionsBefore, $evidence->conversionsAfter],
            'site search clicks' => [$evidence->searchClicksBefore, $evidence->searchClicksAfter],
            'site impressions' => [$evidence->searchImpressionsBefore, $evidence->searchImpressionsAfter],
        ] as $label => $window) {
            $this->line('  '.str_pad($label, 22)
                .str_pad($this->count($window[0]), 12, ' ', STR_PAD_LEFT)
                .str_pad($this->count($window[1]), 12, ' ', STR_PAD_LEFT));
        }

        $this->newLine();
        $this->line('  page signal    '.str_pad($evidence->pageVerdict->value, 20).$this->move($evidence->pageBasisPoints));
        $this->line('  search signal  '.str_pad($evidence->searchVerdict->value, 20).$this->move($evidence->searchBasisPoints));
        $this->line('  overall        '.$evidence->verdict->value);

        // ⛔ **THE ASYMMETRY IS PRINTED RATHER THAN ASSUMED KNOWN** (5808). An
        // operator reading a healthy site-wide rise beside a flat page and
        // concluding the change worked is `28` §4.3's fabricated win being made
        // by a person instead of by code, which is not better.
        $this->newLine();
        $this->line('  The search figures are for the WHOLE PROPERTY, never this page — Search');
        $this->line('  Console is queried by date for a site. That signal may reach "regressed"');
        $this->line('  and may never reach "improved", so a rise there is not this page\'s win.');

        if ($card->createdThePage) {
            $this->line('  This page did not exist in the before window, so its page figures there');
            $this->line('  are withheld rather than zero.');
        }
    }

    /**
     * ⚠️ **"not seen" RATHER THAN `0`, WHICH IS THE WHOLE POINT OF THE
     * DOCUMENT** ({@see ChangeMetrics}, 229). Null means we could not see; zero
     * means nobody came. Every tenant today is the first of those.
     */
    private function count(?int $value): string
    {
        return $value === null ? 'not seen' : number_format($value);
    }

    private function move(?int $basisPoints): string
    {
        if ($basisPoints === null) {
            return '—';
        }

        return ($basisPoints >= 0 ? '+' : '−').intdiv(abs($basisPoints), 100).'%';
    }
}
