<?php

declare(strict_types=1);

namespace App\Modules\X142\Ui;

use App\Enums\UserRole;
use App\Modules\X142\Actions\WebhookSubscribeAction;
use App\Modules\X142\Models\WebhookSubscription;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Webhooks'])]
class WebhooksView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $url = '';

    public string $events = '';

    public ?string $success = null;

    public ?string $error = null;

    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner, UserRole::Manager, UserRole::SuperAdmin), 403);
        $this->businessId = Tenancy::id() ?? 0;
    }

    public function submit(WebhookSubscribeAction $action): void
    {
        $this->reset(['success', 'error']);

        if (empty($this->url)) {
            $this->error = 'URL is required.';

            return;
        }

        try {
            $action->subscribe(Tenancy::idOrFail(), $this->url, $this->events);
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->success = 'Webhook subscribed. This feeds the webhook list; nothing downstream is wired to it yet.';
        $this->reset(['url', 'events']);
    }

    public function render()
    {
        $subscriptions = $this->businessId > 0 ? WebhookSubscription::where('business_id', $this->businessId)->orderBy('id', 'desc')->get() : collect();

        return view('x-142::webhooks', [
            'subscriptions' => $subscriptions,
        ]);
    }
}
