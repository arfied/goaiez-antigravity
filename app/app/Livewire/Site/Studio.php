<?php

declare(strict_types=1);

namespace App\Livewire\Site;

use App\Enums\UserRole;
use App\Modules\X103\Actions\PageReadAction;
use App\Modules\X103\Domain\PagePreview;
use App\Modules\X103\Models\Page;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Site studio'])]
class Studio extends Component
{
    #[Locked]
    public int $businessId;

    public ?int $pageId = null;

    public ?int $selectedBlockIndex = null;

    public function mount(PageReadAction $pages): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner, UserRole::Manager), 403);
        $this->businessId = Tenancy::id();

        $home = $pages->homeFor($this->businessId) ?? Page::where('business_id', $this->businessId)->orderBy('id')->first();
        $this->pageId = $home?->id;
    }

    public function selectBlock(int $index): void
    {
        $this->selectedBlockIndex = $index;
    }

    public function render()
    {
        $pages = Page::where('business_id', $this->businessId)->orderByDesc('id')->get();

        $previewHtml = '';
        $selectedPage = null;

        if ($this->pageId !== null) {
            $selectedPage = Page::where('business_id', $this->businessId)->findOrFail($this->pageId);
            $previewHtml = app(PagePreview::class)->html($selectedPage, false);

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
        if ($this->selectedBlockIndex !== null && $selectedPage && is_array($selectedPage->draft_blocks)) {
            if (isset($selectedPage->draft_blocks[$this->selectedBlockIndex])) {
                $selectedBlockType = $selectedPage->draft_blocks[$this->selectedBlockIndex]['type'] ?? 'unknown';
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
