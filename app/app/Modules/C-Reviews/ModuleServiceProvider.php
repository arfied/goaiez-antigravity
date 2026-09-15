<?php

declare(strict_types=1);

namespace App\Modules\CReviews;

use App\Modules\CReviews\Listeners\AskForCsatOnTicketResolved;
use App\Modules\CReviews\Listeners\AskForReviewOnJobCompleted;
use App\Modules\CReviews\Listeners\RecordCsatOnReply;
use App\Modules\CReviews\Ui\LossAlerts;
use App\Modules\CReviews\Ui\QaReport;
use App\Modules\CReviews\Ui\ReviewsQaRequests;
use App\Modules\CReviews\Ui\Tickets;
use App\Modules\CSms\Events\MessageReceived;
use App\Modules\X171\Events\JobCompleted;
use App\Modules\X181\Events\TicketResolved;
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
        $this->loadRoutesFrom(__DIR__.'/routes.generated.php');

        Event::listen(
            MessageReceived::class,
            RecordCsatOnReply::class
        );

        Event::listen(
            JobCompleted::class,
            AskForReviewOnJobCompleted::class
        );

        Event::listen(
            TicketResolved::class,
            AskForCsatOnTicketResolved::class
        );

        $this->loadMigrationsFrom(__DIR__.'/Database/migrations');
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'c-reviews');

        if (class_exists(Livewire::class)) {
            Livewire::component('c-reviews.reviews-qa-requests', ReviewsQaRequests::class);
            Livewire::component('c-reviews.qa-report', QaReport::class);
            Livewire::component('c-reviews.tickets', Tickets::class);
            Livewire::component('c-reviews.loss-alerts', LossAlerts::class);
        }
    }
}
