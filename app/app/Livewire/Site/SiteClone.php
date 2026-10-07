<?php

declare(strict_types=1);

namespace App\Livewire\Site;

use App\Enums\UserRole;
use App\Models\SiteCloneJob;
use App\Models\WebstudioSite;
use App\Services\SiteClone\SiteCloneJobs;
use App\Services\Webstudio\WebstudioSites;
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

    public function openEditor(int $jobId, SiteCloneJobs $jobs): mixed
    {
        $job = SiteCloneJob::where('business_id', $this->businessId)->find($jobId);
        $url = $job === null ? null : $jobs->editorUrl($job);
        if ($url === null) {
            return null;
        }

        return redirect()->away($url);
    }

    public function publish(int $siteId, WebstudioSites $sites): void
    {
        $this->notice = null;
        $res = $sites->requestPublish($this->businessId, $siteId);
        if ($res['status'] === 'refused') {
            $this->notice = $sites->ownerSentence($res['reason']);
        }
    }

    public function openSiteEditor(int $siteId, WebstudioSites $sites): mixed
    {
        $site = WebstudioSite::where('business_id', $this->businessId)->find($siteId);
        if ($site === null) {
            return null;
        }

        return redirect()->away($sites->editorUrl($site));
    }

    public function render(SiteCloneJobs $jobs, WebstudioSites $sites): View
    {
        $sites->recoverStale($this->businessId);

        return view('livewire.site.clone', [
            'active' => $jobs->active($this->businessId),
            'history' => $jobs->history($this->businessId),
            'jobs' => $jobs,
            'sites' => $sites->all($this->businessId),
            'siteSvc' => $sites,
        ]);
    }
}
