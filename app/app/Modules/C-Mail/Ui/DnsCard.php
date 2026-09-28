<?php

declare(strict_types=1);

namespace App\Modules\CMail\Ui;

use App\Modules\CMail\Actions\EmailDnsCheckAction;
use App\Modules\CMail\Models\MailDomain;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Email domain'])]
class DnsCard extends Component
{
    public string $domainName = '';

    public ?string $success = null;

    public ?string $error = null;

    public function submit(EmailDnsCheckAction $action): void
    {
        $this->reset(['success', 'error']);

        if (empty($this->domainName)) {
            $this->error = 'Domain name is required.';

            return;
        }

        $domain = $action->handle(Tenancy::idOrFail(), $this->domainName);

        $this->success = 'Checked DNS for '.$domain->domain_name.' — SPF '.$domain->spf_status.', DKIM '.$domain->dkim_status.', DMARC '.$domain->dmarc_status.'.';
        $this->reset(['domainName']);
    }

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

            if ($domain->dkim_status !== 'verified' && $domain->dkim_public_key) {
                $records['DKIM'] = [
                    'name' => $dkimSelector.'._domainkey.'.$domain->domain_name,
                    'value' => 'v=DKIM1; k=rsa; p='.$domain->dkim_public_key,
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
