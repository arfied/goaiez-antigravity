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
        $this->assertStringStartsWith('ch_', $data['gateway_charge_id']);

        // Assert the order the command wrote is paid
        $this->assertEquals('paid', $data['order_status']);
    }
}
