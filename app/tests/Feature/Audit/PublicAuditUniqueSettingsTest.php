<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Jobs\PublicAuditJob;
use Illuminate\Support\Facades\DB;

it('reads unique_for_seconds from settings', function () {
    DB::table('platform_settings')->updateOrInsert(
        ['key' => 'audit.public.unique_for_seconds'],
        ['value' => json_encode(420), 'updated_at' => now()]
    );

    $job = new PublicAuditJob('tok');
    expect($job->uniqueFor())->toBe(420);
});
