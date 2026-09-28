<?php

declare(strict_types=1);

namespace App\Modules\CMail;

use App\Modules\CMail\Domain\SystemTxtRecords;
use App\Modules\CMail\Domain\TxtRecords;
use App\Modules\CMail\Ui\ComplaintbounceBoard;
use App\Modules\CMail\Ui\DnsCard;
use App\Modules\CMail\Ui\SequenceView;
use App\Modules\CMail\Ui\WarmupCalendarsPer;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TxtRecords::class, SystemTxtRecords::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.generated.php');

        $this->loadMigrationsFrom(__DIR__.'/Database/migrations');
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'c-mail');

        if (class_exists(Livewire::class)) {
            Livewire::component('c-mail.dns-card', DnsCard::class);
            Livewire::component('c-mail.sequence-view', SequenceView::class);
            Livewire::component('c-mail.warmup-calendars-per', WarmupCalendarsPer::class);
            Livewire::component('c-mail.complaintbounce-board', ComplaintbounceBoard::class);
        }
    }
}
