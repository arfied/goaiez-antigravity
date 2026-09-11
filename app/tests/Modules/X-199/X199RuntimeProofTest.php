<?php

declare(strict_types=1);

namespace Tests\Modules\X199;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class X199RuntimeProofTest extends TestCase
{
    public function test_the_invoice_artifact_proves_its_number_sequence_and_refuses_a_duplicate(): void
    {
        $path = storage_path('app/evidence/X-199/invoice.json');
        $this->assertFileExists($path);

        $data = json_decode(File::get($path), true);

        $this->assertArrayNotHasKey('gateway_charge_id', $data);
        $this->assertMatchesRegularExpression('/^INV-[0-9]{6}$/', $data['invoice_number']);
        $this->assertSame('paid', $data['invoice_status']);
        $this->assertNotNull($data['paid_at']);
        $this->assertSame('invoices_business_id_invoice_number_unique', $data['duplicate_refused_by']);
        $this->assertSame('database', $data['queue_driver']);
        $this->assertFalse($data['running_unit_tests']);
    }
}
