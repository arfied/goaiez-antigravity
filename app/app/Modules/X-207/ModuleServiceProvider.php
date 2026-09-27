<?php

declare(strict_types=1);

namespace App\Modules\X207;

use App\Modules\CSms\Events\MessageReceived;
use App\Modules\X01\Events\ConversationUpdated;
use App\Modules\X10\Events\LeadAssigned;
use App\Modules\X207\Listeners\PushOnInboundText;
use App\Modules\X207\Listeners\PushOnInboxMessage;
use App\Modules\X207\Listeners\PushOnLeadAssigned;
use App\Modules\X207\Ui\OneConfirmonceToggle;
use App\Modules\X207\Ui\PerplatformDeliveryHealth;
use App\Modules\X207\Ui\PromptcopyEditor;
use App\Modules\X207\Ui\RetirementReasons;
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
        Event::listen(LeadAssigned::class, PushOnLeadAssigned::class);
        Event::listen(MessageReceived::class, PushOnInboundText::class);
        Event::listen(ConversationUpdated::class, PushOnInboxMessage::class);

        $this->loadRoutesFrom(__DIR__.'/routes.generated.php');

        $this->loadMigrationsFrom(__DIR__.'/Database/migrations');
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-207');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-207.one-confirmonce-toggle', OneConfirmonceToggle::class);
            Livewire::component('x-207.promptcopy-editor', PromptcopyEditor::class);
            Livewire::component('x-207.retirement-reasons', RetirementReasons::class);
            Livewire::component('x-207.perplatform-delivery-health', PerplatformDeliveryHealth::class);
        }
    }
}
