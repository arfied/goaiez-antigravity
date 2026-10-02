<?php

declare(strict_types=1);

namespace App\Livewire\Site;

use App\Enums\UserRole;
use App\Modules\X103\Actions\PageLayoutProposeAction;
use App\Modules\X103\Actions\PageReadAction;
use App\Modules\X103\Actions\PageUndoAction;
use App\Modules\X103\Actions\SiteBlockFieldSetAction;
use App\Modules\X103\Actions\SiteEditApplyAction;
use App\Modules\X103\Actions\SiteEditAskAction;
use App\Modules\X103\Actions\SiteEditDiscardAction;
use App\Modules\X103\Actions\SitePublishAction;
use App\Modules\X103\Domain\PageLayouts;
use App\Modules\X103\Domain\PagePreview;
use App\Modules\X103\Domain\SiteEngine;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVersion;
use App\Modules\X157\Actions\LatestDeploymentForPageAction;
use App\Modules\X157\Actions\PlatformSiteAddressAction;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

#[Layout('components.account.layout', ['heading' => 'Site studio', 'maxWidth' => 'max-w-full'])]
class Studio extends Component
{
    #[Locked]
    public int $businessId;

    public ?int $pageId = null;

    public ?int $selectedBlockIndex = null;

    public ?string $error = null;

    public ?string $success = null;

    public string $request = '';

    public string $blockHeadline = '';

    public function setBlockField(SiteBlockFieldSetAction $action): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        if ($this->pageId === null || $this->selectedBlockIndex === null) {
            $this->error = 'No page or block selected.';

            return;
        }

        $res = $action->handle($this->businessId, $this->pageId, $this->selectedBlockIndex, 'headline', $this->blockHeadline);
        if ($res['status'] === 'refused') {
            $this->error = $res['reason'];
        } else {
            $this->success = 'Headline saved.';
        }
    }

    private function draftDiffersFromPublished(Page $page): bool
    {
        $version = $page->current_version_id ? PageVersion::where('business_id', $this->businessId)->find($page->current_version_id) : null;
        if ($version === null) {
            return true;
        }
        $strip = fn (array $blocks): array => array_values(array_filter($blocks, fn ($b) => ! in_array($b['type'] ?? '', SiteEngine::REQUIRED_BLOCK_TYPES, true)));

        return json_encode($strip($page->draft_blocks ?? [])) !== json_encode($strip(is_array($version->content_blocks) ? $version->content_blocks : []));
    }

    public function publish(int $pageId, SitePublishAction $action): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner), 403);
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

    public function ask(SiteEditAskAction $action): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        if ($this->pageId === null) {
            $this->error = 'No page selected.';

            return;
        }

        try {
            $res = $action->handle(
                businessId: $this->businessId,
                pageId: $this->pageId,
                request: trim($this->request)
            );
            if ($res['status'] === 'proposed') {
                $numEdits = $res['edits'];
                $msg = "Proposed {$numEdits} edits with {$res['model']} — review it below, then Apply or Discard.";
                if (($res['images'] ?? 0) > 0) {
                    $msg .= " Made {$res['images']} picture(s) for it.";
                }
                $this->success = $msg;
                $this->request = '';
            } else {
                $this->error = $res['message'] ?? $res['reason'];
            }
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function applyProposal(SiteEditApplyAction $action): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        if ($this->pageId === null) {
            $this->error = 'No page selected.';

            return;
        }

        $res = $action->handle($this->businessId, $this->pageId);

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

    public function discardProposal(SiteEditDiscardAction $action): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        if ($this->pageId === null) {
            $this->error = 'No page selected.';

            return;
        }

        $action->handle($this->businessId, $this->pageId);
        $this->success = 'Discarded.';
    }

    public function proposeLayout(string $layout, PageLayoutProposeAction $action): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        if ($this->pageId === null) {
            $this->error = 'No page selected.';

            return;
        }

        $res = $action->handle($this->businessId, $this->pageId, $layout);

        if ($res['status'] === 'proposed') {
            $this->selectedBlockIndex = null;
            $this->success = 'Previewing the '.PageLayouts::LAYOUTS[$layout]['label'].' layout. Apply keeps it; Discard puts the page back.';

            return;
        }

        if ($res['status'] === 'unchanged') {
            $this->success = 'Your page is already in that order.';

            return;
        }

        $reason = (string) $res['reason'];
        $this->error = [
            'pending_edit' => 'Apply or discard the proposal you are previewing first.',
            'empty_page' => 'This page has no sections to arrange yet.',
            'unknown_layout' => 'There is no layout by that name.',
        ][$reason] ?? $reason;
    }

    public function undo(PageUndoAction $action): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        if ($this->pageId === null) {
            $this->error = 'No page selected.';

            return;
        }

        $res = $action->handle($this->businessId, $this->pageId);
        if ($res['status'] === 'refused') {
            $this->error = $res['reason'];

            return;
        }

        $this->selectedBlockIndex = null;
        $this->success = 'Undone. Your draft is back to how it was before the last change.';
    }

    public function mount(PageReadAction $pages): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner, UserRole::Manager), 403);
        $this->businessId = Tenancy::idOrFail();

        $home = $pages->homeFor($this->businessId) ?? Page::where('business_id', $this->businessId)->orderBy('id')->first();
        $this->pageId = $home?->id;
    }

    public function selectBlock(int $index): void
    {
        if ($this->pageId === null) {
            return;
        }

        $page = Page::where('business_id', $this->businessId)->find($this->pageId);
        if (! $page) {
            return;
        }

        $pending = $page->draft_meta['pending_edit']['blocks'] ?? null;
        $blocks = is_array($pending) ? $pending : ($page->draft_blocks ?? []);

        if (! is_array($blocks) || $index < 0 || $index >= count($blocks)) {
            return;
        }

        $this->selectedBlockIndex = $index;
        $this->blockHeadline = $blocks[$index]['headline'] ?? '';
    }

    public function updatedPageId(): void
    {
        $this->selectedBlockIndex = null;
    }

    public function render()
    {
        $pages = Page::where('business_id', $this->businessId)->orderByDesc('id')->get();

        $previewHtml = '';
        $selectedPage = null;

        if ($this->pageId !== null) {
            $selectedPage = Page::where('business_id', $this->businessId)->findOrFail($this->pageId);
            $hasProposal = isset($selectedPage->draft_meta['pending_edit']);
            $previewHtml = app(PagePreview::class)->html($selectedPage, $hasProposal, $this->selectedBlockIndex);

            $script = <<<'HTML'
<script>
    document.addEventListener('click', function(event) {
        event.preventDefault();
        const block = event.target.closest('[data-block-index]');
        if (block) {
            const index = parseInt(block.getAttribute('data-block-index'), 10);
            const type = block.getAttribute('data-block-type');
            // The iframe is opaque-origin due to sandbox="allow-scripts" without that origin flag.
            // It has no origin to name, so targetOrigin '*' is required.
            // The parent window will check event.data.source === 'studio-canvas' to ensure safety.
            parent.postMessage({
                source: 'studio-canvas',
                index: index,
                type: type
            }, '*');
        }
    });
</script>
HTML;

            // Insert script before </body> to ensure it runs correctly and is well-formed HTML.
            if (str_contains($previewHtml, '</body>')) {
                $previewHtml = str_replace('</body>', $script."\n".'</body>', $previewHtml);
            } else {
                $previewHtml .= "\n".$script;
            }
        }

        $selectedBlockType = null;
        if ($this->selectedBlockIndex !== null && $selectedPage) {
            $pending = $selectedPage->draft_meta['pending_edit']['blocks'] ?? null;
            $blocks = is_array($pending) ? $pending : ($selectedPage->draft_blocks ?? []);

            if (is_array($blocks) && isset($blocks[$this->selectedBlockIndex])) {
                $selectedBlockType = $blocks[$this->selectedBlockIndex]['type'] ?? 'unknown';
            }
        }

        return view('livewire.site.studio', [
            'pages' => $pages,
            'selectedPage' => $selectedPage,
            'previewHtml' => $previewHtml,
            'selectedBlockType' => $selectedBlockType,
        ]);
    }
}
