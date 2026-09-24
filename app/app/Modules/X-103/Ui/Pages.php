<?php

namespace App\Modules\X103\Ui;

use App\Enums\UserRole;
use App\Modules\X103\Actions\CustomerQuestionsAction;
use App\Modules\X103\Actions\FaqDraftAction;
use App\Modules\X103\Actions\PageCreateAction;
use App\Modules\X103\Actions\PageDeleteAction;
use App\Modules\X103\Actions\PageDuplicateAction;
use App\Modules\X103\Actions\PageRenameAction;
use App\Modules\X103\Actions\PageRestoreVersionAction;
use App\Modules\X103\Actions\PageUnpublishAction;
use App\Modules\X103\Actions\SeoDraftAction;
use App\Modules\X103\Actions\SiteCopyPolishAction;
use App\Modules\X103\Actions\SiteEditProposeAction;
use App\Modules\X103\Actions\SitePageProposeAction;
use App\Modules\X103\Actions\SitePublishAction;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVersion;
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

    public array $renameSlug = [];

    public array $editRequest = [];

    public string $pageRequest = '';

    public array $renameTitle = [];

    public array $seoTitle = [];

    public array $seoDescription = [];

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

    public string $faqQuestion = '';

    public string $faqAnswer = '';

    public string $videoName = '';

    public string $videoUrl = '';

    public string $videoDate = '';

    public function addFaq(int $pageId): void
    {
        abort_unless(auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        if (trim($this->faqQuestion) === '' || trim($this->faqAnswer) === '') {
            $this->error = 'Fields cannot be empty.';

            return;
        }

        $page = Page::where('business_id', $this->businessId)->findOrFail($pageId);
        $blocks = $page->draft_blocks ?? [];
        $blocks[] = [
            'type' => 'faq',
            'question' => $this->faqQuestion,
            'answer' => $this->faqAnswer,
        ];
        $page->update(['draft_blocks' => $blocks]);

        $this->faqQuestion = '';
        $this->faqAnswer = '';
        $this->success = 'Block added.';
    }

    public function addVideo(int $pageId): void
    {
        abort_unless(auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        if (trim($this->videoName) === '' || trim($this->videoUrl) === '' || trim($this->videoDate) === '') {
            $this->error = 'Fields cannot be empty.';

            return;
        }

        if (! str_starts_with($this->videoUrl, 'https://')) {
            $this->error = 'Video URL must start with https://';

            return;
        }

        $page = Page::where('business_id', $this->businessId)->findOrFail($pageId);
        $blocks = $page->draft_blocks ?? [];
        $blocks[] = [
            'type' => 'video_embed',
            'name' => $this->videoName,
            'contentUrl' => $this->videoUrl,
            'uploadDate' => $this->videoDate,
        ];
        $page->update(['draft_blocks' => $blocks]);

        $this->videoName = '';
        $this->videoUrl = '';
        $this->videoDate = '';
        $this->success = 'Block added.';
    }

    public function removeBlock(int $pageId, int $index): void
    {
        abort_unless(auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        $page = Page::where('business_id', $this->businessId)->findOrFail($pageId);
        $blocks = $page->draft_blocks ?? [];
        if (isset($blocks[$index])) {
            array_splice($blocks, $index, 1);
            $page->update(['draft_blocks' => $blocks]);
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

            $result = $action->handle($this->businessId, $page->id, $page->draft_blocks ?? []);
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

    public function unpublish(int $pageId, PageUnpublishAction $action): void
    {
        abort_unless(auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        try {
            $action->handle($this->businessId, $pageId);
            $this->success = 'Page unpublished.';
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function rename(int $pageId, PageRenameAction $action): void
    {
        abort_unless(auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        $slug = $this->renameSlug[$pageId] ?? '';
        $title = $this->renameTitle[$pageId] ?? '';

        try {
            $page = $action->handle($this->businessId, $pageId, $slug, $title);
            if ($page->is_published) {
                $this->success = 'Renamed. Publish again to update the live page.';
            } else {
                $this->success = 'Renamed.';
            }
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public array $showHistory = [];

    public function toggleHistory(int $pageId): void
    {
        if (! empty($this->showHistory[$pageId])) {
            $this->showHistory[$pageId] = false;
        } else {
            $this->showHistory[$pageId] = true;
        }
    }

    public function restore(int $pageId, int $versionId, PageRestoreVersionAction $action): void
    {
        abort_unless(auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        $page = Page::where('business_id', $this->businessId)->findOrFail($pageId);
        $version = PageVersion::where('business_id', $this->businessId)->where('page_id', $pageId)->find($versionId);
        if (! $version) {
            abort(404);
        }

        if ($page->current_version_id === $versionId) {
            $this->error = 'Cannot restore the current version.';

            return;
        }

        try {
            $result = $action->handle($this->businessId, $pageId, $versionId);
            $this->success = "Restored version {$result['restored_commit_id']} as new commit {$result['commit_id']}.";
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function deletePage(int $pageId, PageDeleteAction $action): void
    {
        abort_unless(auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        $page = Page::where('business_id', $this->businessId)->find($pageId);
        if (! $page) {
            abort(404);
        }

        try {
            $action->handle($this->businessId, $page->id);
            $this->success = 'Page deleted.';
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function duplicatePage(int $pageId, PageDuplicateAction $action): void
    {
        abort_unless(auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        $page = Page::where('business_id', $this->businessId)->find($pageId);
        if (! $page) {
            abort(404);
        }

        try {
            $action->handle($this->businessId, $page->id);
            $this->success = 'Page duplicated.';
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function polish(int $pageId, SiteCopyPolishAction $action): void
    {
        abort_unless(auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        try {
            $res = $action->handle($this->businessId, $pageId);
            if ($res['status'] === 'refused') {
                $this->success = $res['reason'];
            } else {
                $this->success = "Polished {$res['blocks']} blocks with {$res['model']}";
            }
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function restoreOriginal(int $pageId): void
    {
        abort_unless(auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        $page = Page::where('business_id', $this->businessId)->findOrFail($pageId);
        $blocks = $page->draft_blocks ?? [];
        $restored = 0;

        foreach ($blocks as $i => $block) {
            if (isset($block['original_text'])) {
                $blocks[$i]['text'] = $block['original_text'];
                unset($blocks[$i]['original_text']);
                unset($blocks[$i]['source']);
                unset($blocks[$i]['model']);
                unset($blocks[$i]['peers']);
                $restored++;
            }
        }

        if ($restored > 0) {
            $page->update(['draft_blocks' => $blocks]);
        }
        $this->success = "Restored {$restored} blocks.";
    }

    public function askEdit(int $pageId, SiteEditProposeAction $action): void
    {
        abort_unless(auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        $page = Page::where('business_id', $this->businessId)->findOrFail($pageId);

        try {
            $res = $action->handle(
                businessId: $this->businessId,
                pageId: $pageId,
                request: trim((string) ($this->editRequest[$pageId] ?? '')),
                continue: isset($page->draft_meta['pending_edit'])
            );
            if ($res['status'] === 'refused') {
                $this->success = $res['reason'];
            } else {
                $this->success = "Proposed {$res['blocks']} blocks with {$res['model']} — review it below, then Apply or Discard.";
                unset($this->editRequest[$pageId]);
            }
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function applyEdit(int $pageId): void
    {
        abort_unless(auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        $page = Page::where('business_id', $this->businessId)->findOrFail($pageId);
        $pending = $page->draft_meta['pending_edit'] ?? null;
        if (! $pending) {
            $this->error = 'Nothing proposed.';

            return;
        }

        $page->draft_blocks = $pending['blocks'];
        $meta = $page->draft_meta;
        unset($meta['pending_edit']);
        $page->draft_meta = $meta;
        $page->save();

        $this->success = 'Applied to the draft. Publish when you are ready — History keeps the version before this one.';
    }

    public function discardEdit(int $pageId): void
    {
        abort_unless(auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        $page = Page::where('business_id', $this->businessId)->findOrFail($pageId);
        $meta = $page->draft_meta ?? [];
        if (isset($meta['pending_edit'])) {
            unset($meta['pending_edit']);
            $page->update(['draft_meta' => $meta]);
            $this->success = 'Discarded.';
        }
    }

    public function makePage(SitePageProposeAction $action): void
    {
        abort_unless(auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        try {
            $res = $action->handle($this->businessId, trim($this->pageRequest));
            if ($res['status'] === 'refused') {
                $this->success = $res['reason'];
            } else {
                $this->success = "Made a draft page \"{$res['title']}\" at /{$res['slug']} with {$res['blocks']} blocks using {$res['model']} — it is in the list above, unpublished. Publish it when you are happy, or delete it.";
                $this->pageRequest = '';
            }
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function draftFaq(int $pageId, FaqDraftAction $action): void
    {
        abort_unless(auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        try {
            $res = $action->handle($this->businessId, $pageId);
            if ($res['status'] === 'refused') {
                $this->success = $res['reason'] === 'no_facts_available' ? 'Nothing to write from yet — confirm a price or show a review on the website first.' : $res['reason'];
            } else {
                $this->success = "Drafted {$res['items']} questions with {$res['model']}";
            }
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function placeFaq(int $pageId): void
    {
        abort_unless(auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        $page = Page::where('business_id', $this->businessId)->findOrFail($pageId);
        $meta = $page->draft_meta ?? [];
        if (isset($meta['pending_faq'])) {
            $blocks = $page->draft_blocks ?? [];
            $blocks[] = [
                'type' => 'faq',
                'items' => $meta['pending_faq']['items'] ?? [],
                'source' => 'ai',
                'model' => $meta['pending_faq']['model'] ?? 'unknown',
            ];
            unset($meta['pending_faq']);
            $page->update([
                'draft_blocks' => $blocks,
                'draft_meta' => $meta,
            ]);
            $this->success = 'FAQ placed on page.';
        }
    }

    public function discardFaq(int $pageId): void
    {
        abort_unless(auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        $page = Page::where('business_id', $this->businessId)->findOrFail($pageId);
        $meta = $page->draft_meta ?? [];
        if (isset($meta['pending_faq'])) {
            unset($meta['pending_faq']);
            $page->update(['draft_meta' => $meta]);
            $this->success = 'Pending FAQ discarded.';
        }
    }

    public function draftSeo(int $pageId, SeoDraftAction $action): void
    {
        abort_unless(auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        try {
            $res = $action->handle($this->businessId, $pageId);
            if ($res['status'] === 'refused') {
                $this->success = $res['reason'];
            } else {
                $this->success = "Drafted with {$res['model']}";
                $this->seoTitle[$pageId] = $res['title'];
                $this->seoDescription[$pageId] = $res['description'];
            }
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function saveSeo(int $pageId): void
    {
        abort_unless(auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        $title = $this->seoTitle[$pageId] ?? '';
        $description = $this->seoDescription[$pageId] ?? '';

        if (mb_strlen($title) > 70) {
            $this->error = 'Title must be 70 characters or less.';

            return;
        }

        if (mb_strlen($description) > 160) {
            $this->error = 'Description must be 160 characters or less.';

            return;
        }

        $page = Page::where('business_id', $this->businessId)->findOrFail($pageId);
        $page->seo_title = $title ?: null;
        $page->seo_description = $description ?: null;
        $page->save();

        $this->success = 'SEO saved.';
    }

    public function render()
    {
        $pages = Page::where('business_id', $this->businessId)->orderByDesc('id')->get();

        foreach ($pages as $page) {
            if (! isset($this->seoTitle[$page->id])) {
                $this->seoTitle[$page->id] = $page->seo_title ?? '';
            }
            if (! isset($this->seoDescription[$page->id])) {
                $this->seoDescription[$page->id] = $page->seo_description ?? '';
            }
        }

        $deployments = [];
        foreach ($pages as $page) {
            $deployments[$page->id] = app(LatestDeploymentForPageAction::class)->handle($this->businessId, $page->id);
        }
        $deployments = collect($deployments)->filter();

        $versions = PageVersion::where('business_id', $this->businessId)
            ->whereIn('page_id', $pages->pluck('id')->toArray())
            ->orderByDesc('id')
            ->get()
            ->groupBy('page_id');

        $hasVersions = [];
        foreach ($pages as $page) {
            $hasVersions[$page->id] = isset($versions[$page->id]) && $versions[$page->id]->count() > 0;
        }

        $questions = app(CustomerQuestionsAction::class)->handle($this->businessId);

        return view('x-103::pages', [
            'pages' => $pages,
            'deployments' => $deployments,
            'versions' => $versions,
            'hasVersions' => $hasVersions,
            'questions' => $questions,
        ]);
    }
}
