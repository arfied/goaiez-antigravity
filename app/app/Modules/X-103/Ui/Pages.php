<?php

namespace App\Modules\X103\Ui;

use App\Enums\UserRole;
use App\Modules\X103\Actions\PageCreateAction;
use App\Modules\X103\Actions\SitePublishAction;
use App\Modules\X103\Models\Page;
use App\Modules\X157\Actions\LatestDeploymentForPageAction;
use App\Modules\X157\Actions\PlatformSiteAddressAction;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

#[Layout('components.account.layout', ['heading' => 'Pages'])]
class Pages extends Component
{
    #[Locked]
    public int $businessId;

    public string $newSlug = '';

    public string $newTitle = '';

    public ?string $error = null;

    public ?string $success = null;

    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner, UserRole::Manager), 403);
        $this->businessId = Tenancy::id();
    }

    public function addPage(PageCreateAction $action): void
    {
        $this->error = null;
        $this->success = null;

        if (trim($this->newSlug) === '' || trim($this->newTitle) === '') {
            $this->error = 'Slug and title are required.';

            return;
        }

        try {
            $action->handle($this->businessId, $this->newSlug, $this->newTitle);
            $this->success = 'Page added.';
            $this->newSlug = '';
            $this->newTitle = '';
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function publish(int $pageId, SitePublishAction $action): void
    {
        $this->error = null;
        $this->success = null;

        $page = Page::where('business_id', $this->businessId)->findOrFail($pageId);

        if ($page->is_published) {
            $this->error = 'That page is already published.';

            return;
        }

        try {
            app(PlatformSiteAddressAction::class)->handle($this->businessId);

            $result = $action->handle($this->businessId, $page->id, []);
            if ($result['status'] === 'published') {
                $deployment = app(LatestDeploymentForPageAction::class)->handle($this->businessId, $page->id);

                if ($deployment && $deployment->status === 'deployed') {
                    $this->success = $page->slug.' is live at '.url('/sites/'.$this->businessId.'/'.$deployment->deploy_hash);
                } elseif ($deployment && $deployment->status === 'rolled_back') {
                    $this->success = $page->slug.' was published but the deploy was rolled back: '.$deployment->rollback_reason;
                } else {
                    $this->success = 'published, not yet deployed';
                }
            }
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        $pages = Page::where('business_id', $this->businessId)->orderByDesc('id')->get();

        $deployments = [];
        foreach ($pages as $page) {
            $deployments[$page->id] = app(LatestDeploymentForPageAction::class)->handle($this->businessId, $page->id);
        }
        $deployments = collect($deployments)->filter();

        return view('x-103::pages', [
            'pages' => $pages,
            'deployments' => $deployments,
        ]);
    }
}
