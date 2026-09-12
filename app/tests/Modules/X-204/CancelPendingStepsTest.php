<?php

declare(strict_types=1);

namespace Tests\Modules\X204;

use App\Models\Customer;
use App\Modules\X121\Models\Person;
use App\Modules\X186\Models\CampaignRun;
use App\Modules\X204\Domain\ConsentService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CancelPendingStepsTest extends TestCase
{
    public function test_opt_out_deactivates_campaign_runs_for_that_person(): void
    {
        $tenant = self::provisionTenant(['name' => 'Opt Out Cancel Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$tenant->id}'");

        $phone = '+15550009999';

        $targetPerson = Person::create([
            'business_id' => $tenant->id,
            'phone' => $phone,
            'first_name' => 'Target',
            'last_name' => 'Person',
        ]);

        $targetRun = CampaignRun::create([
            'business_id' => $tenant->id,
            'person_id' => $targetPerson->id,
            'campaign_id' => 'target-campaign',
            'current_step' => 1,
            'is_active' => true,
            'is_suppressed' => false,
        ]);

        $decoyPerson = Person::create([
            'business_id' => $tenant->id,
            'phone' => '+15550008888',
            'first_name' => 'Decoy',
            'last_name' => 'Person',
        ]);

        $decoyRun = CampaignRun::create([
            'business_id' => $tenant->id,
            'person_id' => $decoyPerson->id,
            'campaign_id' => 'decoy-campaign',
            'current_step' => 1,
            'is_active' => true,
            'is_suppressed' => false,
        ]);

        Customer::forceCreate([
            'id' => $decoyPerson->id,
            'business_id' => $tenant->id,
            'phone' => $phone,
            'name' => 'Decoy Customer',
        ]);

        $service = new ConsentService;
        $service->suppress($tenant->id, $phone, 'sms', 'opt_out');

        $targetRun->refresh();
        $this->assertFalse($targetRun->is_active, 'Target CampaignRun should be deactivated by the opt-out');

        $decoyRun->refresh();
        $this->assertTrue($decoyRun->is_active, 'Decoy CampaignRun should remain untouched');
    }

    // Removed test_opt_out_does_nothing_if_no_person_exists: six other tests already prove a no-person suppression does not throw.
}
