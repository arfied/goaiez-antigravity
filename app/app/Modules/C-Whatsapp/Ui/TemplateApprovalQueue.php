<?php

declare(strict_types=1);

namespace App\Modules\CWhatsapp\Ui;

use App\Modules\CWhatsapp\Actions\TemplateSubmitAction;
use App\Modules\CWhatsapp\Models\WhatsappTemplate;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Template approval queue'])]
class TemplateApprovalQueue extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $name = '';

    public string $category = '';

    public string $bodyText = '';

    public ?string $error = null;

    public ?string $success = null;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function submit(TemplateSubmitAction $action)
    {
        $this->error = null;
        $this->success = null;

        if ($this->name === '') {
            $this->error = 'Name is required.';

            return;
        }

        if ($this->category === '') {
            $this->error = 'Category is required.';

            return;
        }

        if ($this->bodyText === '') {
            $this->error = 'Body text is required.';

            return;
        }

        try {
            $template = $action->handle(
                businessId: Tenancy::idOrFail(),
                name: $this->name,
                category: $this->category,
                bodyText: $this->bodyText
            );

            if ($template->status === 'pending_approval') {
                $this->success = "Submitted {$this->name} to Meta through Zernio. Meta usually reviews within a day.";
            } elseif ($template->status === 'draft') {
                $this->success = "Saved {$this->name} as a draft — connect WhatsApp to submit it to Meta.";
            } elseif ($template->status === 'submit_failed') {
                $this->success = "Meta did not accept {$this->name}: {$template->status_reason}";
            }

            $this->name = '';
            $this->category = '';
            $this->bodyText = '';
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        $pending = ($this->businessId > 0)
            ? WhatsappTemplate::where('business_id', $this->businessId)->whereIn('status', ['draft', 'submit_failed', 'pending_approval', 'in_appeal'])->orderByDesc('id')->get()
            : collect();

        return view('c-whatsapp::template-approval-queue', [
            'pending' => $pending,
        ]);
    }
}
