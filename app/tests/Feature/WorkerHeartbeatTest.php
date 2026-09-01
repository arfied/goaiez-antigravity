<?php

declare(strict_types=1);

use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

it('writes a heartbeat on queue.looping', function () {
    Cache::forget('goaiez:worker:heartbeat');

    Event::dispatch(new Looping('connection', 'queue'));

    $this->assertNotNull(Cache::get('goaiez:worker:heartbeat'));
});
