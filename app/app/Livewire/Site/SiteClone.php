<?php

namespace App\Livewire\Site;

use App\Enums\UserRole;
use App\Services\SiteClone\SiteCloneJobs;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Clone my website'])]
class SiteClone extends Component
{
    public string $url = '';

    public bool $attested = false;

    public ?string $notice = null;

    public int $businessId;

    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner, UserRole::Manager), 403);
        $this->businessId = Tenancy::idOrFail();
    }

    public function start(SiteCloneJobs $jobs): void
    {
        $this->notice = null;

        $result = $jobs->request($this->businessId, auth()->id(), $this->url, $this->attested);

        if ($result['status'] === 'refused') {
            $this->notice = $jobs->ownerSentence($result['reason']);
        } else {
            $this->url = '';
            $this->attested = false;
        }
    }

    public function cancel(SiteCloneJobs $jobs): void
    {
        $activeJob = $jobs->active($this->businessId);
        if ($activeJob) {
            $jobs->cancel($this->businessId, $activeJob->id);
        }
    }

    public function render(SiteCloneJobs $jobs): View
    {
        return view('livewire.site.clone', [
            'active' => $jobs->active($this->businessId),
            'history' => $jobs->history($this->businessId),
        ]);
    }
}
