<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

\Illuminate\Support\Facades\Schedule::call(function () {
    cache()->put('goaiez:scheduler:heartbeat', now(), 300);
    $minute = now()->format('YmdHi');
    $claimed = cache()->add("goaiez:scheduler:tick:{$minute}", true, 120);
    if (! $claimed) {
        cache()->increment('goaiez:scheduler:claims_this_minute');
    }
})->everyMinute()->name('scheduler-heartbeat');

\Illuminate\Support\Facades\Schedule::command('numbers:return-parked')
    ->daily()
    ->withoutOverlapping(180)
    ->runInBackground();
