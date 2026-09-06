<?php

declare(strict_types=1);

namespace App\Tests\Modules\X117;

use App\Modules\X117\Models\Order;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class X117RuntimeProofTest extends TestCase
{
    public function test_checkout_reaches_a_real_charge_id(): void
    {
        $path = storage_path('app/evidence/X-117/checkout.json');
        $this->assertFileExists($path);

        $data = json_decode(File::get($path), true);
        $this->assertSame('pending_payment', $data['order_status']);
        $this->assertSame(0, $data['payments_written']);
        $this->assertTrue($data['merchant_connected']);
        $this->assertFalse($data['running_unit_tests']);
    }
}
