<?php

namespace App\Modules\X103\Ui;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Location;
use App\Modules\X103\Actions\CustomerQuestionsAction;
use App\Modules\X103\Actions\FaqDiscardAction;
use App\Modules\X103\Actions\FaqDraftAction;
use App\Modules\X103\Actions\FaqPlaceAction;
use App\Modules\X103\Actions\PageCreateAction;
use App\Modules\X103\Actions\PageDeleteAction;
use App\Modules\X103\Actions\PageDuplicateAction;
use App\Modules\X103\Actions\PageReadAction;
use App\Modules\X103\Actions\PageRenameAction;
use App\Modules\X103\Actions\PageRestoreVersionAction;
use App\Modules\X103\Actions\PageUnpublishAction;
use App\Modules\X103\Actions\QuestionAnswerDraftAction;
use App\Modules\X103\Actions\SeoDraftAction;
use App\Modules\X103\Actions\SiteBuildRunAction;
use App\Modules\X103\Actions\SiteCopyPolishAction;
use App\Modules\X103\Actions\SiteEditApplyAction;
use App\Modules\X103\Actions\SiteEditAskAction;
use App\Modules\X103\Actions\SiteEditDiscardAction;
use App\Modules\X103\Actions\SitePageProposeAction;
use App\Modules\X103\Actions\SitePreviewAction;
use App\Modules\X103\Actions\SitePublishAction;
use App\Modules\X103\Domain\PagePreview;
use App\Modules\X103\Domain\SectionOrder;
use App\Modules\X103\Domain\SiteEngine;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVersion;
use App\Modules\X157\Actions\LatestDeploymentForPageAction;
use App\Modules\X157\Actions\PlatformSiteAddressAction;
use App\Services\Config\DefaultsRegistry;
use App\Services\Industry\IndustryResolver;
use App\Services\Industry\IndustryStartingPoints;
use App\Services\Facts\BusinessFacts;
use App\Services\Facts\BusinessFactKey;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

#[Layout('components.account.layout', ['heading' => 'Pages'])]
class Pages extends Component
{
    #[Locked]
    public int $businessId;

    #[Url(as: 'edit')]
    public ?int $editingPageId = null;

    public bool $previewProposed = true;

    public string $deviceWidth = 'desktop';

    public string $newSlug = '';

    public string $newTitle = '';

    public ?string $error = null;

    public ?string $success = null;

    public array $renameSlug = [];

    public array $editRequest = [];

    public string $pageRequest = '';

    public ?string $buildStatus = null;

    public ?string $buildReason = null;

    public array $buildResult = [];

    public ?int $locationId = null;

    public array $renameTitle = [];

    public array $seoTitle = [];

    public array $seoDescription = [];

    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner, UserRole::Manager), 403);
        $this->businessId = Tenancy::id();
        $location = Location::where('business_id', $this->businessId)->first();
        $this->locationId = $location ? $location->id : 0;
    }

    public function openEditor(int $pageId): void
    {
        Page::where('business_id', $this->businessId)->findOrFail($pageId);
        $this->editingPageId = $pageId;
    }

    public function closeEditor(): void
    {
        $this->editingPageId = null;
    }

    public function showProposed(bool $on): void
    {
        $this->previewProposed = $on;
    }

    public function setDeviceWidth(string $width): void
    {
        $this->deviceWidth = $width;
    }

    public function addPage(PageCreateAction $action): void
    {
        abort_unless(auth()->user()->hasRole(UserRole::Owner), 403);
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

    public array $answerPage = [];

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

    public function addHero(int $pageId, BusinessFacts $facts): void
    {
        abort_unless(auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        $page = Page::where('business_id', $this->businessId)->findOrFail($pageId);

        if (isset($page->draft_meta['pending_edit'])) {
            $this->error = 'Apply or discard the AI proposal first.';

            return;
        }

        $blocks = $page->draft_blocks ?? [];
        foreach ($blocks as $block) {
            if (($block['type'] ?? '') === 'hero') {
                $this->error = 'This page already has a hero.';

                return;
            }
        }

        $stated = $facts->all($this->businessId);
        $hero = [
            'type' => 'hero',
            'headline' => (string) Business::whereKey($this->businessId)->value('name'),
            'subline' => (string) ($stated[BusinessFactKey::TAGLINE] ?? ''),
            'source' => 'owner: add hero',
        ];
        array_unshift($blocks, $hero);
        $page->update(['draft_blocks' => $blocks]);

        $this->success = 'Hero added.';
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
        abort_unless(auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        $page = Page::where('business_id', $this->businessId)->findOrFail($pageId);

        if ($page->is_published && ! $this->draftDiffersFromPublished($page)) {
            $this->error = 'That page is already published, and the draft has no changes.';

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
            if (isset($block['original_subline'])) {
                $blocks[$i]['subline'] = $block['original_subline'];
                unset($blocks[$i]['original_subline']);
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

    public function askEdit(int $pageId, SiteEditAskAction $action): void
    {
        abort_unless(auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        try {
            $res = $action->handle(
                businessId: $this->businessId,
                pageId: $pageId,
                request: trim((string) ($this->editRequest[$pageId] ?? ''))
            );
            if ($res['status'] === 'refused') {
                $this->error = $res['message'] ?? $res['reason'];
            } else {
                $numEdits = $res['edits'];
                $msg = "Proposed {$numEdits} edits with {$res['model']} — review it below, then Apply or Discard.";
                if (($res['images'] ?? 0) > 0) {
                    $msg .= " Made {$res['images']} picture(s) for it.";
                }
                $this->success = $msg;
                unset($this->editRequest[$pageId]);
            }
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function applyEdit(int $pageId, SiteEditApplyAction $action): void
    {
        abort_unless(auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        $res = $action->handle($this->businessId, $pageId);

        if ($res['status'] === 'refused') {
            $this->error = $res['reason'];

            return;
        }

        if ($res['applied_style']) {
            $this->success = 'Applied. The new colours and fonts show on every page the next time you publish it.';
        } else {
            $this->success = 'Applied to the draft. Publish when you are ready — History keeps the version before this one.';
        }
    }

    public function undoEdit(int $pageId): void
    {
        abort_unless(auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        $page = Page::where('business_id', $this->businessId)->findOrFail($pageId);
        $meta = $page->draft_meta ?? [];
        $undo = $meta['undo'] ?? [];

        if (empty($undo)) {
            $this->error = 'Nothing to undo.';

            return;
        }

        $entry = array_pop($undo);

        if (is_array($entry) && isset($entry['blocks']) && array_key_exists('site_tokens', $entry)) {
            $page->draft_blocks = $entry['blocks'];
            Business::whereKey($this->businessId)->update(['site_tokens' => $entry['site_tokens']]);
        } else {
            $page->draft_blocks = $entry;
        }

        $meta['undo'] = $undo;
        $page->draft_meta = $meta;
        $page->save();

        $this->success = 'Undone. Your draft is back to how it was before the last change.';
    }

    public function discardEdit(int $pageId, SiteEditDiscardAction $action): void
    {
        abort_unless(auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        $action->handle($this->businessId, $pageId);
        $this->success = 'Discarded.';
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
                $this->editingPageId = (int) $res['page_id'];
                $this->success = "Made a draft page \"{$res['title']}\" at /{$res['slug']} with {$res['blocks']} blocks using {$res['model']}. It is open in the editor — change it by asking, then publish when you are happy.";
                $this->pageRequest = '';
            }
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function draftAnswer(string $key, QuestionAnswerDraftAction $action): void
    {
        abort_unless(auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        $questions = app(CustomerQuestionsAction::class)->handle($this->businessId);
        $questionRow = null;
        foreach ($questions as $q) {
            if ($q['key'] === $key) {
                $questionRow = $q;
                break;
            }
        }

        if (! $questionRow) {
            return;
        }

        $pageId = (int) ($this->answerPage[$key] ?? 0);
        if ($pageId === 0) {
            $this->error = 'Pick the page the answer should go on.';

            return;
        }

        try {
            $res = $action->handle($this->businessId, $pageId, $questionRow['source'], $questionRow['id'], $questionRow['question']);
            if ($res['status'] === 'refused') {
                if ($res['reason'] === 'pending_faq_exists') {
                    $this->success = 'That page already has proposed questions waiting — place or discard them first.';
                } elseif ($res['reason'] === 'moderation_unavailable') {
                    $this->success = ($res['detail'] ?? '') === 'phi_withheld'
                        ? 'Questions cannot be checked for this account, so nothing is drafted from them.'
                        : 'The question could not be checked right now, so nothing was drafted. Try again later.';
                } elseif ($res['reason'] === 'moderation_refused') {
                    $this->success = 'The question could not be checked right now, so nothing was drafted. Try again later.';
                } elseif ($res['reason'] === 'flagged') {
                    $this->success = 'This question cannot be published as written ('.implode(', ', $res['flags']).').';
                } elseif ($res['reason'] === 'no_facts_available') {
                    $this->success = 'Nothing to write from yet — confirm a price or show a review on the website first.';
                } else {
                    $this->success = $res['reason'];
                }
            } else {
                $this->success = "Drafted an answer with {$res['model']} — see the page's proposed questions.";
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

    public function placeFaq(int $pageId, FaqPlaceAction $action): void
    {
        abort_unless(auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        if ($action->handle($this->businessId, $pageId)) {
            $this->success = 'FAQ placed on page.';
        }
    }

    public function discardFaq(int $pageId, FaqDiscardAction $action): void
    {
        abort_unless(auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        if ($action->handle($this->businessId, $pageId)) {
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

    /** True when the draft differs from what is currently published, ignoring the blocks the engine appends on publish (wave 833). */
    private function draftDiffersFromPublished(Page $page): bool
    {
        $version = $page->current_version_id ? PageVersion::where('business_id', $this->businessId)->find($page->current_version_id) : null;
        if ($version === null) {
            return true;
        }
        $strip = fn (array $blocks): array => array_values(array_filter($blocks, fn ($b) => ! in_array($b['type'] ?? '', SiteEngine::REQUIRED_BLOCK_TYPES, true)));

        return json_encode($strip($page->draft_blocks ?? [])) !== json_encode($strip(is_array($version->content_blocks) ? $version->content_blocks : []));
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
        $hasChanges = [];
        foreach ($pages as $page) {
            $hasVersions[$page->id] = isset($versions[$page->id]) && $versions[$page->id]->count() > 0;
            $hasChanges[$page->id] = $page->is_published && $this->draftDiffersFromPublished($page);
        }

        $questions = app(CustomerQuestionsAction::class)->handle($this->businessId);

        $editing = null;
        $previewHtml = null;

        if ($this->editingPageId !== null) {
            $editing = Page::where('business_id', $this->businessId)->findOrFail($this->editingPageId);
            $previewHtml = app(PagePreview::class)->html($editing, $this->previewProposed);
        }

        return view('x-103::pages', [
            'pages' => $pages,
            'deployments' => $deployments,
            'versions' => $versions,
            'hasVersions' => $hasVersions,
            'hasChanges' => $hasChanges,
            'questions' => $questions,
            'editing' => $editing,
            'previewHtml' => $previewHtml,
            'previews' => app(SitePreviewAction::class)->handle($this->businessId),
            'chosenVariant' => Business::query()->whereKey($this->businessId)->value('site_variant') ?? 'a',
            'buildStatus' => $this->buildStatus,
            'buildReason' => $this->buildReason,
            'buildResult' => $this->buildResult,
        ]);
    }

    public function chooseLook(string $variant, IndustryStartingPoints $sp, IndustryResolver $ir, PageReadAction $pages): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        if (! in_array($variant, IndustryStartingPoints::VARIANTS, true)) {
            $this->error = 'Invalid look.';

            return;
        }

        Business::query()->whereKey($this->businessId)->update(['site_variant' => $variant]);

        $home = $pages->homeFor($this->businessId);
        if ($home && is_array($home->draft_blocks)) {
            $family = $ir->for($this->businessId)['family'];
            $variantTokens = $sp->variant($sp->for($family), $variant);
            $home->draft_blocks = SectionOrder::apply($home->draft_blocks, $variantTokens['section_order']);
            $home->save();
        }

        $this->success = 'Look '.strtoupper($variant).' picked — your pages follow it from the next draft and the next publish.';
    }

    public function runBuild(SiteBuildRunAction $action)
    {
        abort_unless(auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        try {
            $result = $action->handle($this->businessId, $this->locationId);
            $this->buildStatus = $result['status'] ?? null;
            if ($this->buildStatus === 'refused') {
                $this->buildReason = $result['reason'] ?? '';
            }
            $this->buildResult = $result;
            // Refresh previews etc by just letting render() happen
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function publishAll(SitePublishAction $action, PlatformSiteAddressAction $addressAction, DefaultsRegistry $registry)
    {
        abort_unless(auth()->user()->hasRole(UserRole::Owner), 403);
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
}
