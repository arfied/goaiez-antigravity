<?php

declare(strict_types=1);

namespace App\Modules\X178\Ui;

use App\Modules\X178\Actions\FormGenerateAction;
use App\Modules\X178\Models\DesignChange;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Site editor'])]
class SiteEditorAssistant extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $pageId = '';
    public string $niche = 'unmapped';

    public string $success = '';
    public string $error = '';

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId ?: Tenancy::id() ?? 0;
        abort_if($this->businessId === 0, 404);
    }

    public function generate(FormGenerateAction $action): void
    {
        $this->reset(['success', 'error']);

        $pid = (int) $this->pageId;
        if ($pid === 0) {
            $this->error = 'Page ID must be provided and cannot be 0.';
            return;
        }

        $result = $action->handle(Tenancy::idOrFail(), $pid, $this->niche);
        
        $this->success = "Generated form. It places a lead-capture block and invents no price. Block ref: {$result['block_ref']}. This feeds the margin lists; nothing downstream is wired to it yet.";
    }

    public function render()
    {
        $changes = ($this->businessId > 0)
            ? DesignChange::where('business_id', $this->businessId)->latest()->get()
            : collect();

        return view('x-178::site-editor-assistant', [
            'changes' => $changes,
        ]);
    }
}
