<?php

declare(strict_types=1);

namespace App\Livewire\Site;

use App\Enums\UserRole;
use App\Modules\X103\Actions\PageLayoutProposeAction;
use App\Modules\X103\Actions\PageReadAction;
use App\Modules\X103\Actions\PageUndoAction;
use App\Modules\X103\Actions\SiteBlockAddAction;
use App\Modules\X103\Actions\SiteBlockArrangeAction;
use App\Modules\X103\Actions\SiteBlockFieldSetAction;
use App\Modules\X103\Actions\SiteDesignRequestAction;
use App\Modules\X103\Actions\SiteEditApplyAction;
use App\Modules\X103\Actions\SiteEditAskAction;
use App\Modules\X103\Actions\SiteEditDiscardAction;
use App\Modules\X103\Actions\SitePublishAction;
use App\Modules\X103\Domain\InlineFields;
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

    public string $sectionRequest = '';

    public string $newAboutText = '';

    public string $newFaqQuestion = '';

    public string $newFaqAnswer = '';

    public string $blockHeadline = '';

    public bool $showDesign = false;

    private const SECTION_ASKS = [
        'shorter' => 'Make the text in this section shorter. Keep the meaning and every fact.',
        'friendlier' => 'Make the text in this section warmer and friendlier. Keep every fact.',
        'professional' => 'Make the text in this section sound more professional. Keep every fact.',
        'spelling' => 'Fix spelling and grammar in this section. Change nothing else.',
    ];

    /** The one-click design request: the same Ask, with a request that asks for design and not only words. */
    public const DESIGN_ASK = 'Make this page look modern and professional, not only its words: give the top banner a stand-out layout and a button, make a picture for it if it has none, add a row of key numbers and a call-to-action band, and choose fresh colours and fonts. Use the facts I have given wherever they apply.';

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

    public function editInline(int $index, string $field, string $value, SiteBlockFieldSetAction $action): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        if ($this->pageId === null) {
            $this->error = 'No page selected.';

            return;
        }

        $page = Page::where('business_id', $this->businessId)->findOrFail($this->pageId);
        $pending = $page->draft_meta['pending_edit']['blocks'] ?? null;
        $blocks = is_array($pending) ? $pending : ($page->draft_blocks ?? []);

        if (! InlineFields::allows($blocks[$index] ?? null, $field)) {
            $this->error = 'That cannot be edited on the page.';

            return;
        }

        $res = $action->handle($this->businessId, $this->pageId, $index, $field, trim($value));
        if ($res['status'] === 'refused') {
            $this->error = trim($value) === ''
                ? 'That text cannot be empty — type something, or press Esc to cancel.'
                : 'That change could not be saved — reload the page and try again.';

            return;
        }

        $this->success = is_array($pending)
            ? 'Saved to the proposal you are previewing.'
            : 'Saved to your draft. Undo last change takes it back.';
    }

    public function arrangeSection(string $move, SiteBlockArrangeAction $action): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        if ($this->pageId === null || $this->selectedBlockIndex === null) {
            $this->error = 'Select a section on the canvas first.';

            return;
        }

        $res = $action->handle($this->businessId, $this->pageId, $this->selectedBlockIndex, $move);
        if ($res['status'] === 'refused') {
            $this->error = [
                'edge' => $move === 'up' ? 'That section is already at the top.' : 'That section is already at the bottom.',
                'not_a_section' => 'That cannot be moved or removed.',
                'unknown_move' => 'There is no such change.',
            ][(string) $res['reason']] ?? (string) $res['reason'];

            return;
        }

        $this->selectedBlockIndex = $res['index'];
        if ($res['target'] === 'pending') {
            $this->success = 'Changed in the proposal you are previewing.';
        } else {
            $this->success = $move === 'remove'
                ? 'Section removed. Undo last change brings it back.'
                : 'Section moved. Undo last change puts it back.';
        }
    }

    public function addSection(string $type, SiteBlockAddAction $action): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        if ($this->pageId === null) {
            $this->error = 'No page selected.';

            return;
        }

        if ($type === 'about') {
            $fields = ['text' => $this->newAboutText];
        } elseif ($type === 'faq') {
            $fields = ['question' => $this->newFaqQuestion, 'answer' => $this->newFaqAnswer];
        } else {
            $this->error = 'There is no such section.';

            return;
        }

        foreach ($fields as $value) {
            if (trim($value) === '') {
                $this->error = 'Fill in the text first — a new section starts with your words, not placeholder text.';

                return;
            }
        }

        $res = $action->handle($this->businessId, $this->pageId, $this->selectedBlockIndex, $type, $fields);
        if ($res['status'] === 'refused') {
            $this->error = 'That section could not be added — reload the page and try again.';

            return;
        }

        $this->selectedBlockIndex = $res['index'];
        $this->newAboutText = '';
        $this->newFaqQuestion = '';
        $this->newFaqAnswer = '';
        $this->success = $res['target'] === 'pending'
            ? 'Added to the proposal you are previewing.'
            : 'Section added. Undo last change removes it.';
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

    public function askDesign(SiteEditAskAction $action): void
    {
        $this->request = self::DESIGN_ASK;
        $this->ask($action);
    }

    public function designWithAi(SiteDesignRequestAction $action): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        if ($this->pageId === null) {
            $this->error = 'No page selected.';

            return;
        }

        $res = $action->handle($this->businessId, $this->pageId);
        if ($res['status'] === 'queued') {
            $this->showDesign = true;
            $this->success = 'The AI is designing this page. It takes a minute or two; the preview switches to it when it is ready.';
        } else {
            $this->error = 'The AI is already designing this page.';
        }
    }

    public function askSection(string $preset, SiteEditAskAction $action): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        if ($this->pageId === null || $this->selectedBlockIndex === null) {
            $this->error = 'Select a section on the canvas first.';

            return;
        }

        $instruction = $preset === 'custom' ? trim($this->sectionRequest) : (self::SECTION_ASKS[$preset] ?? '');
        if ($instruction === '') {
            $this->error = $preset === 'custom' ? 'Type what you want changed in this section first.' : 'There is no such quick action.';

            return;
        }

        try {
            $res = $action->handle(
                businessId: $this->businessId,
                pageId: $this->pageId,
                request: $instruction,
                onlyBlock: $this->selectedBlockIndex
            );
            if ($res['status'] === 'proposed') {
                $this->success = "Proposed {$res['edits']} edits to this section with {$res['model']} — review it on the page, then Apply or Discard.";
                $this->sectionRequest = '';
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
        $this->showDesign = false;
    }

    public function render()
    {
        $pages = Page::where('business_id', $this->businessId)->orderByDesc('id')->get();

        $previewHtml = '';
        $selectedPage = null;

        if ($this->pageId !== null) {
            $selectedPage = Page::where('business_id', $this->businessId)->findOrFail($this->pageId);
            $hasProposal = isset($selectedPage->draft_meta['pending_edit']);
            $designReady = ($selectedPage->draft_meta['design']['status'] ?? null) === 'ready';
            $previewHtml = $this->showDesign && $designReady
                ? app(PagePreview::class)->designHtml($selectedPage)
                : app(PagePreview::class)->html($selectedPage, $hasProposal, $this->selectedBlockIndex, true);

            $script = <<<'HTML'
<script>
    // The iframe is opaque-origin due to sandbox="allow-scripts" without that origin flag.
    // It has no origin to name, so targetOrigin '*' is required. The editor checks event.source.
    document.addEventListener('click', function(event) {
        const field = event.target.closest('[data-field]');
        const selected = field ? field.closest('[data-se' + 'lected-block]') : null;
        if (field && selected) {
            event.preventDefault();
            if (field.isContentEditable) {
                return;
            }
            const before = field.innerText;
            let done = false;
            const finish = function(save) {
                if (done) {
                    return;
                }
                done = true;
                field.contentEditable = 'false';
                if (!save) {
                    field.innerText = before;
                    return;
                }
                const value = field.innerText.trim();
                if (value === before.trim()) {
                    return;
                }
                parent.postMessage({
                    source: 'studio-canvas',
                    kind: 'edit',
                    index: parseInt(selected.getAttribute('data-block-index'), 10),
                    field: field.getAttribute('data-field'),
                    value: value
                }, '*');
            };
            field.contentEditable = 'true';
            field.focus();
            field.addEventListener('blur', function() { finish(true); }, { once: true });
            field.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); finish(true); field.blur(); }
                if (e.key === 'Escape') { e.preventDefault(); finish(false); field.blur(); }
            });
            return;
        }
        event.preventDefault();
        const block = event.target.closest('[data-block-index]');
        if (block) {
            parent.postMessage({
                source: 'studio-canvas',
                kind: 'select',
                index: parseInt(block.getAttribute('data-block-index'), 10),
                type: block.getAttribute('data-block-type')
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
