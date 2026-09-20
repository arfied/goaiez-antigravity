<?php

declare(strict_types=1);

namespace App\Modules\X183\Ui;

use App\Modules\X183\Models\ContentDraft;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Your drafts'])]
class DraftReview extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $title = '';
    public string $bodyText = '';
    public bool $isCaseStudy = false;
    public ?string $success = null;
    public ?string $error = null;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function submit(\App\Modules\X183\Actions\ContentWriteAction $action): void
    {
        $this->reset(['success', 'error']);

        if (empty($this->title)) {
            $this->error = 'Title is required.';
            return;
        }

        if (empty($this->bodyText)) {
            $this->error = 'Body text is required.';
            return;
        }

        $draft = $action->writeDraft(Tenancy::idOrFail(), $this->title, $this->bodyText, $this->isCaseStudy, false);
        $this->success = "Recorded draft '{$draft->title}'. This feeds the draft list; nothing downstream is wired to it yet.";
        $this->reset(['title', 'bodyText', 'isCaseStudy']);
    }

    public function render()
    {
        $drafts = ($this->businessId > 0)
            ? ContentDraft::where('business_id', $this->businessId)->with('gateResults')->get()
            : collect();

        return view('x-183::draft-review', [
            'drafts' => $drafts,
        ]);
    }
}
