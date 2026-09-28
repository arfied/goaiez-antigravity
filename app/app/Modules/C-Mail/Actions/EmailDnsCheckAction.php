<?php

declare(strict_types=1);

namespace App\Modules\CMail\Actions;

use App\Modules\CMail\Domain\NoTxtRecords;
use App\Modules\CMail\Domain\TxtRecords;
use App\Modules\CMail\Models\MailDomain;

/**
 * Checks DNS presence and shape, not correctness.
 */
final class EmailDnsCheckAction
{
    public function __construct(private ?TxtRecords $lookup = null) {}

    public function handle(int $businessId, string $domainName, string $dkimSelector = 'google'): MailDomain
    {
        $lookup = $this->lookup ?? new NoTxtRecords;

        // SPF
        $spfRecords = $lookup->txt($domainName);
        $spfStatus = 'pending';
        if (is_array($spfRecords)) {
            $spfCount = 0;
            foreach ($spfRecords as $record) {
                if (str_starts_with($record, 'v=spf1')) {
                    $spfCount++;
                }
            }
            if ($spfCount === 0) {
                $spfStatus = 'missing';
            } elseif ($spfCount === 1) {
                $spfStatus = 'verified';
            } else {
                $spfStatus = 'misconfigured';
            }
        }

        // DKIM
        $dkimRecords = $lookup->txt("{$dkimSelector}._domainkey.{$domainName}");
        $dkimStatus = 'pending';
        if (is_array($dkimRecords)) {
            $dkimCount = 0;
            foreach ($dkimRecords as $record) {
                if (str_starts_with($record, 'v=DKIM1')) {
                    $dkimCount++;
                }
            }
            if ($dkimCount > 0) {
                $dkimStatus = 'verified';
            } else {
                $dkimStatus = 'missing';
            }
        }

        // DMARC
        $dmarcRecords = $lookup->txt("_dmarc.{$domainName}");
        $dmarcStatus = 'pending';
        if (is_array($dmarcRecords)) {
            $dmarcCount = 0;
            foreach ($dmarcRecords as $record) {
                if (str_starts_with($record, 'v=DMARC1')) {
                    $dmarcCount++;
                }
            }
            if ($dmarcCount > 0) {
                $dmarcStatus = 'verified';
            } else {
                $dmarcStatus = 'missing';
            }
        }

        return MailDomain::updateOrCreate(
            ['business_id' => $businessId, 'domain_name' => $domainName],
            [
                'dkim_status' => $dkimStatus,
                'spf_status' => $spfStatus,
                'dmarc_status' => $dmarcStatus,
            ]
        );
    }
}
