<?php

declare(strict_types=1);

namespace App\Modules\X142\Ui;

use App\Modules\X142\Models\WebhookSubscription;
use Livewire\Component;

class WebhooksView extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $webhooks = ($this->businessId > 0)
            ? WebhookSubscription::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-142::webhooks', [
            'webhooks' => $webhooks,
        ]);
    }
}
