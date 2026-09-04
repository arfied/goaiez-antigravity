<?php

declare(strict_types=1);

namespace Tests\Modules\X204;

use App\Models\Customer;
use App\Modules\X186\Models\CampaignRun;
use App\Modules\X204\Domain\ConsentService;
use App\Modules\X204\Events\SuppressionAdded;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CancelPendingStepsTest extends TestCase
{
    public function test_opt_out_cancels_pending_campaign_steps(): void
    {
        $num = '+1555000' . rand(1000, 9999);
        \Illuminate\Support\Facades\Artisan::call('sms:load-number-pool', ['numbers' => [$num]]);
        $tenant = self::provisionTenant(['name' => 'Opt Out Cancel Tenant', 'currency' => 'USD']);
        \Illuminate\Support\Facades\DB::statement("SET app.business_id = '{$tenant->id}'");

        $phone = '+15550009999';
        $customer = Customer::create([
            'business_id' => $tenant->id,
            'phone' => $phone,
            'first_name' => 'Test',
            'last_name' => 'Cancel',
        ]);

        $run = CampaignRun::create([
            'business_id' => $tenant->id,
            'person_id' => $customer->id,
            'campaign_id' => 'test-campaign',
            'current_step' => 1,
            'is_active' => true,
            'is_suppressed' => false,
        ]);

        $service = new ConsentService();
        $service->suppress($tenant->id, $phone, 'sms', 'opt_out');

        $run->refresh();
        $this->assertFalse($run->is_active, 'CampaignRun should be deactivated by the opt-out');
    }
}
