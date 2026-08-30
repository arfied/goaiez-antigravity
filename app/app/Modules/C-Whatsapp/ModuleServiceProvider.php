<?php

declare(strict_types=1);

namespace App\Modules\CWhatsapp;

use App\Modules\CWhatsapp\Ui\TemplateApprovalQueue;
use App\Modules\CWhatsapp\Ui\TemplateStatusCard;
use App\Modules\CWhatsapp\Ui\Thread;
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
        $this->loadMigrationsFrom(__DIR__.'/Database/migrations');
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'c-whatsapp');

        if (class_exists(Livewire::class)) {
            Livewire::component('c-whatsapp.thread', Thread::class);
            Livewire::component('c-whatsapp.template-status-card', TemplateStatusCard::class);
            Livewire::component('c-whatsapp.template-approval-queue', TemplateApprovalQueue::class);
        }
    }
}
