<?php

declare(strict_types=1);

namespace Tests\Modules\X211;

use App\Modules\X211\Domain\ArEngine;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class X211RuntimeProofTest extends TestCase
{
    public function test_a_recovery_reaches_a_real_charge_id(): void
    {
        $path = storage_path('app/evidence/X-211/recovery.json');
        $this->assertFileExists($path);

        $data = json_decode(File::get($path), true);
        $this->assertStringStartsWith('ch_', $data['gateway_charge_id']);

        $this->assertArrayHasKey($data['reason'], ArEngine::REASONS);

        $this->assertTrue($data['refused_without_resolution']);
    }
}
