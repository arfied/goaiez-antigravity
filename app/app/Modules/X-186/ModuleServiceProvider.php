<?php

declare(strict_types=1);

namespace App\Modules\X186;

use App\Modules\X186\Models\CampaignStep;
use App\Modules\X186\Ui\AudiencePreviewCount;
use App\Modules\X186\Ui\LiveRun;
use App\Modules\X186\Ui\SequenceBuilder;
use App\Modules\X186\Ui\StopLog;
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
        $this->loadMigrationsFrom(__DIR__.'/Database/migrations');
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-186');

        Event::listen('suppression.added', function (array $payload) {
            CampaignStep::where('business_id', $payload['business_id'])
                ->where('recipient', $payload['recipient_phone'])
                ->where('channel', $payload['channel'])
                ->whereNull('sent_at')
                ->whereNull('cancelled_at')
                ->update(['cancelled_at' => now()]);
        });

        if (class_exists(Livewire::class)) {
            Livewire::component('x-186.sequence-builder', SequenceBuilder::class);
            Livewire::component('x-186.audience-preview-count', AudiencePreviewCount::class);
            Livewire::component('x-186.live-run', LiveRun::class);
            Livewire::component('x-186.stop-log', StopLog::class);
        }
    }
}
