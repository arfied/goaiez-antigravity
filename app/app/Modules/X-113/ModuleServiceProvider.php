<?php

declare(strict_types=1);

namespace App\Modules\X113;

use App\Modules\X113\Ui\DocumentVault;
use App\Modules\X113\Ui\PermissionMatrix;
use App\Modules\X113\Ui\Roles;
use App\Modules\X113\Ui\Staff;
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
        $this->loadRoutesFrom(__DIR__.'/routes.generated.php');






        $this->loadMigrationsFrom(__DIR__.'/Database/migrations');
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-113');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-113.staff', Staff::class);
            Livewire::component('x-113.roles', Roles::class);
            Livewire::component('x-113.permission-matrix', PermissionMatrix::class);
            Livewire::component('x-113.document-vault', DocumentVault::class);
        }
    }
}
