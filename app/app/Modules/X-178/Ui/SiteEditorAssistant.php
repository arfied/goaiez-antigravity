<?php

declare(strict_types=1);

namespace App\Modules\X178\Ui;

use App\Enums\UserRole;
use App\Modules\X103\Actions\PageReadAction;
use App\Modules\X178\Actions\DesignUndoAction;
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

    public string $success = '';

    public string $error = '';

    public ?string $undoSuccess = null;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId ?: Tenancy::id() ?? 0;
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner, UserRole::Manager, UserRole::SuperAdmin), 403);
    }

    public function generate(FormGenerateAction $action): void
    {
        $this->reset(['success', 'error']);

        $pid = (int) $this->pageId;
        if ($pid === 0) {
            $this->error = 'A page must be selected.';

            return;
        }

        $result = $action->handle(Tenancy::idOrFail(), $pid);

        $this->success = "Generated form. It places a lead-capture block and invents no price. Block ref: {$result['block_ref']}. This feeds design changes; nothing downstream is wired to it yet.";
    }

    public function undoChange(int $changeId, DesignUndoAction $action): void
    {
        $this->reset('undoSuccess');
        $result = $action->handle(Tenancy::idOrFail(), $changeId);
        $change = DesignChange::where('business_id', Tenancy::idOrFail())->find($changeId);

        if ($change === null || $change->status !== 'undone') {
            $this->undoSuccess = 'That change is still applied.';

            return;
        }

        $this->undoSuccess = 'Change '.$change->block_ref.' is marked undone. '
            .'Nothing downstream reacts to an undo yet, and no earlier version is restored.';
    }

    public function render()
    {
        $changes = ($this->businessId > 0)
            ? DesignChange::where('business_id', $this->businessId)->latest()->get()
            : collect();

        $pages = ($this->businessId > 0)
            ? app(PageReadAction::class)->allFor($this->businessId)
            : collect();

        return view('x-178::site-editor-assistant', [
            'changes' => $changes,
            'pages' => $pages,
        ]);
    }
}
