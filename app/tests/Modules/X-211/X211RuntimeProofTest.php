<?php

declare(strict_types=1);

namespace Tests\Modules\X211;

use App\Modules\X211\Domain\ArEngine;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class X211RuntimeProofTest extends TestCase
{
    public function test_the_recovery_artifact_proves_the_plan_and_its_refusal(): void
    {
        $path = storage_path('app/evidence/X-211/recovery.json');
        $this->assertFileExists($path);

        $data = json_decode(File::get($path), true);
        $this->assertArrayNotHasKey('gateway_charge_id', $data);

        $this->assertIsInt($data['plan_id']);
        $this->assertIsInt($data['installment_amount_cents']);
        $this->assertMatchesRegularExpression('/^INV-[0-9]{6}$/', $data['invoice_number']);
        $this->assertArrayHasKey($data['reason'], ArEngine::REASONS);

        $this->assertTrue($data['refused_without_resolution']);
    }
}
