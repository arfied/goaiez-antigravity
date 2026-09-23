<?php

declare(strict_types=1);

namespace App\Modules\CMail\Ui;

use App\Modules\CMail\Actions\EmailWarmupAction;
use App\Modules\CMail\Models\MailDomain;
use App\Modules\CMail\Models\WarmupCalendar;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Warm-up sequence'])]
class SequenceView extends Component
{
    public ?int $mailDomainId = null;

    public function startWarmup(EmailWarmupAction $action): void
    {
        $this->validate(['mailDomainId' => ['required', 'integer']]);
        $domain = MailDomain::query()->where('business_id', (int) Tenancy::idOrFail())->findOrFail($this->mailDomainId);
        $action->handle((int) Tenancy::idOrFail(), (int) $domain->id);
        session()->flash('status', 'Warm-up started for '.$domain->domain_name.'. Today\'s allowance is on the calendar below.');
    }

    public function render()
    {
        $businessId = (int) Tenancy::idOrFail();
        $domains = MailDomain::query()->where('business_id', $businessId)->orderBy('domain_name')->get();
        $calendars = WarmupCalendar::query()->where('business_id', $businessId)->get()->keyBy('mail_domain_id');

        return view('c-mail::sequence-view', ['domains' => $domains, 'calendars' => $calendars]);
    }
}
