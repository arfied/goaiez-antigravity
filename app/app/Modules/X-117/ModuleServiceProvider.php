<?php

declare(strict_types=1);

namespace App\Modules\X117;

use App\Modules\X117\Ui\CartBlock;
use App\Modules\X117\Ui\CheckoutBlock;
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
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-117');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\EvidenceCheckoutCommand::class,
                Console\RuntimeProofCommand::class,
            ]);
        }

        if (class_exists(Livewire::class)) {
            Livewire::component('x-117.cart-block', CartBlock::class);
            Livewire::component('x-117.checkout-block', CheckoutBlock::class);
        }
    }
}
