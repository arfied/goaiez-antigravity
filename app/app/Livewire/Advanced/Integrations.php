<?php

declare(strict_types=1);

namespace App\Livewire\Advanced;

use App\Models\Location;
use App\Modules\X142\Models\WebhookSubscription;
use App\Services\Gbp\GbpConnections;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.account')]
class Integrations extends Component
{
    public function render(GbpConnections $connections): View
    {
        $locations = Location::query()->orderBy('id')->get();
        $gbpConnections = $connections->forLocations();

        $webhookSubscriptions = WebhookSubscription::query()->latest()->get();

        $inboundEndpoints = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => '/'.ltrim($route->uri(), '/'))
            ->filter(fn (string $uri) => str_starts_with($uri, '/webhooks/') || str_starts_with($uri, '/api/v1/webhooks/'))
            ->values()
            ->all();

        return view('livewire.advanced.integrations', [
            'locations' => $locations,
            'connections' => $gbpConnections,
            'subscriptions' => $webhookSubscriptions,
            'inboundEndpoints' => $inboundEndpoints,
        ]);
    }
}
