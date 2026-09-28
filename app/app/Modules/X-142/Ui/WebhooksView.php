<?php

declare(strict_types=1);

namespace App\Modules\X142\Ui;

use App\Enums\UserRole;
use App\Modules\X142\Actions\WebhookSubscribeAction;
use App\Modules\X142\Models\WebhookDelivery;
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

    public ?string $newSecret = null;

    public array $revealed = [];

    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner, UserRole::Manager, UserRole::SuperAdmin), 403);
        $this->businessId = Tenancy::id() ?? 0;
    }

    public function revealSecret(int $id): void
    {
        $subscription = WebhookSubscription::where('business_id', $this->businessId)->where('id', $id)->firstOrFail();
        $this->revealed[$id] = true;
    }

    public function toggleActive(int $id): void
    {
        $subscription = WebhookSubscription::where('business_id', $this->businessId)->where('id', $id)->firstOrFail();
        $subscription->update(['is_active' => ! $subscription->is_active]);
    }

    public function submit(WebhookSubscribeAction $action): void
    {
        $this->reset(['success', 'error', 'newSecret']);

        if (empty($this->url)) {
            $this->error = 'URL is required.';

            return;
        }

        try {
            $subscription = $action->subscribe($this->businessId, $this->url, $this->events);
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->newSecret = $subscription->secret;
        $this->success = 'Webhook added. We sign every delivery with the secret below in the X-Goaiez-Signature header (sha256 HMAC of the body). Copy it now.';
        $this->reset(['url', 'events']);
    }

    public function render()
    {
        $subscriptions = $this->businessId > 0 ? WebhookSubscription::where('business_id', $this->businessId)->orderBy('id', 'desc')->get() : collect();
        $deliveries = [];

        foreach ($subscriptions as $sub) {
            $deliveries[$sub->id] = WebhookDelivery::where('subscription_id', $sub->id)->latest()->limit(10)->get();
        }

        return view('x-142::webhooks', [
            'subscriptions' => $subscriptions,
            'deliveries' => $deliveries,
        ]);
    }
}
