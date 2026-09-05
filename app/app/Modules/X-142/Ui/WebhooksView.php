<?php

declare(strict_types=1);

namespace App\Modules\X142\Ui;

use App\Enums\UserRole;
use App\Modules\X142\Models\WebhookSubscription;
use Livewire\Component;

class WebhooksView extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner, UserRole::Manager, UserRole::SuperAdmin), 403);
    }

    public function render()
    {
        $subscriptions = WebhookSubscription::orderBy('id', 'desc')->get();

        return view('x-142::webhooks', [
            'subscriptions' => $subscriptions,
        ]);
    }
}
