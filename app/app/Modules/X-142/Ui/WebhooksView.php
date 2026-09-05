<?php

declare(strict_types=1);

namespace App\Modules\X142\Ui;

use App\Modules\X142\Models\WebhookSubscription;
use Livewire\Component;

class WebhooksView extends Component
{
    public function render()
    {
        $subscriptions = WebhookSubscription::orderBy('id', 'desc')->get();

        return view('x-142::webhooks', [
            'subscriptions' => $subscriptions,
        ]);
    }
}
