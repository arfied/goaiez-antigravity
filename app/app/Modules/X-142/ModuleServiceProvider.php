<?php

declare(strict_types=1);

namespace App\Modules\X142;

use App\Events\ReviewIngested;
use App\Modules\X01\Events\ContactCreated;
use App\Modules\X01\Events\ConversationUpdated;
use App\Modules\X10\Events\LeadAssigned;
use App\Modules\X142\Listeners\DispatchWebhooksForDomainEvents;
use App\Modules\X142\Ui\ConnectYourAi;
use App\Modules\X142\Ui\McpTokenRegistry;
use App\Modules\X142\Ui\WebhooksView;
use App\Modules\X164\Events\EstimateAccepted;
use App\Modules\X164\Events\EstimateSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(ContactCreated::class, [DispatchWebhooksForDomainEvents::class, 'onContactCreated']);
        Event::listen(ConversationUpdated::class, [DispatchWebhooksForDomainEvents::class, 'onMessageReceived']);
        Event::listen(LeadAssigned::class, [DispatchWebhooksForDomainEvents::class, 'onLeadAssigned']);
        Event::listen(EstimateSent::class, [DispatchWebhooksForDomainEvents::class, 'onEstimateSent']);
        Event::listen(EstimateAccepted::class, [DispatchWebhooksForDomainEvents::class, 'onEstimateAccepted']);
        Event::listen(ReviewIngested::class, [DispatchWebhooksForDomainEvents::class, 'onReviewIngested']);

        $this->loadRoutesFrom(__DIR__.'/routes.generated.php');

        $this->loadMigrationsFrom(__DIR__.'/Database/migrations');
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-142');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-142.connect-your-ai', ConnectYourAi::class);
            Livewire::component('x-142.webhooks', WebhooksView::class);
            Livewire::component('x-142.mcp-token-registry', McpTokenRegistry::class);
        }

    }
}
