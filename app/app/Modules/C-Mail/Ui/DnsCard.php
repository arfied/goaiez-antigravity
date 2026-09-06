<?php

declare(strict_types=1);

namespace App\Modules\CMail\Ui;

use App\Modules\CMail\Models\MailDomain;
use App\Support\Tenancy;
use Livewire\Component;

class DnsCard extends Component
{
    public function render()
    {
        $domain = MailDomain::where('business_id', Tenancy::idOrFail())->first();
        
        $records = [];
        if ($domain) {
            $records = [
                'SPF' => [
                    'name' => $domain->domain_name,
                    'value' => 'v=spf1 include:mail.tracksixty.com ~all',
                ],
                'DKIM' => [
                    'name' => 'selector._domainkey.' . $domain->domain_name,
                    'value' => 'v=DKIM1; k=rsa; p=MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQC3...',
                ],
                'DMARC' => [
                    'name' => '_dmarc.' . $domain->domain_name,
                    'value' => 'v=DMARC1; p=quarantine;',
                ],
            ];
        }

        return view('c-mail::dns-card', ['domain' => $domain, 'records' => $records]);
    }
}
