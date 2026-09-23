<?php

declare(strict_types=1);

namespace App\Modules\X103\Ui;

use App\Models\Location;
use App\Modules\X103\Actions\SiteBuildRunAction;
use App\Modules\X103\Actions\SitePublishAction;
use App\Modules\X103\Models\Page;
use App\Modules\X157\Actions\CustomDomainRequestAction;
use App\Modules\X157\Actions\CustomDomainStatusAction;
use App\Modules\X157\Actions\LatestDeploymentForPageAction;
use App\Modules\X157\Actions\PlatformSiteAddressAction;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('components.account.layout', ['heading' => 'Build my site'])]
class SiteBuild extends Component
{
    public int $businessId;

    public int $locationId;

    public array $ledger = [];

    public ?string $buildStatus = null;

    public ?string $buildReason = null;

    public string $domainName = '';

    public ?string $dnsStatus = null;

    public ?string $error = null;

    public function mount()
    {
        $this->businessId = Tenancy::idOrFail();
        $location = Location::where('business_id', $this->businessId)->first();
        $this->locationId = $location ? $location->id : 0;
    }

    public function runBuild(SiteBuildRunAction $action)
    {
        $this->error = null;
        try {
            $result = $action->handle($this->businessId, $this->locationId);
            $this->buildStatus = $result['status'];
            if ($this->buildStatus === 'refused') {
                $this->buildReason = $result['reason'] ?? '';
            }
            $this->ledger = $result;
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function publishAll(SitePublishAction $action, PlatformSiteAddressAction $addressAction, DefaultsRegistry $registry)
    {
        $this->error = null;
        try {
            $addressAction->handle($this->businessId);

            $maxPages = $registry->int('sites.build.max_pages_publish');
            $pages = Page::where('business_id', $this->businessId)->where('is_published', false)->take($maxPages)->get();
            foreach ($pages as $page) {
                $action->handle($this->businessId, $page->id, $page->draft_blocks ?? []);
            }
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function useDomain(CustomDomainRequestAction $action)
    {
        $this->error = null;
        if (empty($this->domainName)) {
            return;
        }

        try {
            $action->handle($this->businessId, $this->domainName);
            $this->dnsStatus = 'requested';
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        $pages = Page::where('business_id', $this->businessId)->get();

        $domainStatus = app(CustomDomainStatusAction::class)->handle($this->businessId);

        $deployments = [];
        foreach ($pages as $page) {
            $deployments[$page->id] = app(LatestDeploymentForPageAction::class)->handle($this->businessId, $page->id);
        }
        $deployments = collect($deployments)->filter();

        return view('x-103::site-build', [
            'pages' => $pages,
            'domainStatus' => $domainStatus,
            'deployments' => $deployments,
        ]);
    }
}
