<?php

declare(strict_types=1);

namespace App\Modules\CMail\Ui;

use App\Modules\CMail\Models\MailDomain;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Livewire\Component;

class DnsCard extends Component
{
    public function render(DefaultsRegistry $defaults)
    {
        $domain = MailDomain::where('business_id', Tenancy::idOrFail())->first();
        $sendingDomain = $defaults->value('mail.sending_domain');

        $records = [];
        if ($domain && is_string($sendingDomain)) {
            $spfInclude = $domain->spf_include ?: $sendingDomain;
            $dkimSelector = $domain->dkim_selector ?: 'google';

            if ($domain->spf_status !== 'verified') {
                $records['SPF'] = [
                    'name' => $domain->domain_name,
                    'value' => 'v=spf1 include:'.$spfInclude.' ~all',
                ];
            }

            if ($domain->dkim_status !== 'verified') {
                $records['DKIM'] = [
                    'name' => $dkimSelector.'._domainkey.'.$domain->domain_name,
                    'value' => $domain->dkim_public_key ? 'v=DKIM1; k=rsa; p='.$domain->dkim_public_key : 'UNRESOLVED (missing key from provider)',
                ];
            }

            if ($domain->dmarc_status !== 'verified') {
                $records['DMARC'] = [
                    'name' => '_dmarc.'.$domain->domain_name,
                    'value' => 'v=DMARC1; p=quarantine;',
                ];
            }
        }

        return view('c-mail::dns-card', ['domain' => $domain, 'records' => $records]);
    }
}
