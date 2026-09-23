<?php

declare(strict_types=1);

namespace App\Modules\X103\Ui;

use App\Models\Business;
use App\Models\Location;
use App\Modules\X103\Actions\SiteBuildRunAction;
use App\Modules\X103\Actions\SitePublishAction;
use App\Modules\X103\Models\Page;
use App\Modules\X157\Actions\EdgeProvisionAction;
use App\Modules\X157\Actions\LatestDeploymentForPageAction;
use App\Modules\X157\Actions\PlatformSiteAddressAction;
use App\Modules\X157\Models\EdgeZone;
use App\Services\Config\DefaultsRegistry;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Throwable;

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
        $user = Auth::user();
        abort_unless($user, 403);
        $business = Business::where('owner_user_id', $user->id)->first();
        abort_unless($business, 403);
        $this->businessId = $business->id;
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

    public function useDomain(EdgeProvisionAction $action)
    {
        $this->error = null;
        if (empty($this->domainName)) {
            return;
        }

        try {
            $action->handle($this->businessId, $this->domainName, true);
            $this->dnsStatus = 'pending';
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        $pages = Page::where('business_id', $this->businessId)->get();
        $zone = EdgeZone::where('business_id', $this->businessId)->first();

        $deployments = [];
        foreach ($pages as $page) {
            $deployments[$page->id] = app(LatestDeploymentForPageAction::class)->handle($this->businessId, $page->id);
        }
        $deployments = collect($deployments)->filter();

        return view('x-103::site-build', [
            'pages' => $pages,
            'zone' => $zone,
            'deployments' => $deployments,
        ]);
    }
}
