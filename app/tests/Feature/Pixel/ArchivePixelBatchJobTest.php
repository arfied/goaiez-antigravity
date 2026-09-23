<?php

declare(strict_types=1);

use App\Jobs\ArchivePixelBatchJob;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

beforeEach(function () {
    $this->business = pixelTenant();
});

it('the archive throwing -> the job swallows and records (read :315-340), no exception escapes failed()', function () {
    $payload = pixelBody(pixelKeyFor($this->business));
    $receivedAt = CarbonImmutable::now();

    $job = new ArchivePixelBatchJob(
        $this->business->id,
        'batch-123',
        $payload,
        'receipt-123',
        $receivedAt
    );

    // Fake the config so (string) throws a TypeError!
    config(['warehouse.l0_disk' => []]);

    Log::shouldReceive('error')->once();

    $job->failed(new RuntimeException('Some archive failure'));

    expect(true)->toBeTrue();
});
