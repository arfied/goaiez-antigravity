<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use App\Jobs\AutopilotJob;
use Illuminate\Support\Facades\DB;
use ReflectionClass;

it('reads abandoned_repeat_hours from settings', function () {
    DB::table('platform_settings')->updateOrInsert(
        ['key' => 'autopilot.abandoned_repeat_hours'],
        ['value' => json_encode(42), 'updated_at' => now()]
    );

    $reflection = new ReflectionClass(AutopilotJob::class);
    $method = $reflection->getMethod('abandonedSummary');
    $method->setAccessible(true);

    $result = $method->invoke(null, 'test_auto', 123);

    expect($result)->toContain('42h');
});
