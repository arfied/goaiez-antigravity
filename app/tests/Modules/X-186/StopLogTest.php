<?php

declare(strict_types=1);

namespace Tests\Modules\X186;

use App\Modules\X121\Models\Person;
use App\Modules\X186\Models\CampaignRun;
use App\Modules\X186\Ui\StopLog;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class StopLogTest extends TestCase
{
    use DatabaseTransactions;

    public function test_renders_stop_log(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Stop Tenant']);
        $foreignBiz = TestCase::provisionTenant(['name' => 'Foreign Stop Tenant']);

        Tenancy::set((int) $foreignBiz->id);
        $foreignPerson = Person::create(['business_id' => $foreignBiz->id, 'first_name' => 'Foreign', 'last_name' => 'Person']);
        CampaignRun::create([
            'business_id' => $foreignBiz->id,
            'campaign_id' => 'FOREIGN-RUN',
            'person_id' => $foreignPerson->id,
            'stopped_reason' => 'replied',
            'is_active' => false,
        ]);

        Tenancy::set((int) $biz->id);
        $person = Person::create(['business_id' => $biz->id, 'first_name' => 'John', 'last_name' => 'Doe']);

        $stoppedRun = CampaignRun::create([
            'business_id' => $biz->id,
            'campaign_id' => 'STOPPED-CAMPAIGN',
            'person_id' => $person->id,
            'stopped_reason' => 'replied',
            'is_active' => false,
        ]);

        $suppressedRun = CampaignRun::create([
            'business_id' => $biz->id,
            'campaign_id' => 'SUPPRESSED-CAMPAIGN',
            'person_id' => $person->id,
            'is_suppressed' => true,
            'suppression_reason' => 'Open RECOVER',
            'stopped_reason' => null,
            'is_active' => true,
        ]);

        $runningRun = CampaignRun::create([
            'business_id' => $biz->id,
            'campaign_id' => 'STILL-RUNNING-CAMPAIGN',
            'person_id' => $person->id,
            'is_active' => true,
        ]);

        Livewire::test(StopLog::class, ['businessId' => $biz->id])
            ->assertSee('John Doe')
            ->assertSee('STOPPED-CAMPAIGN')
            ->assertSee('SUPPRESSED-CAMPAIGN')
            ->assertSee('Open RECOVER')
            ->assertDontSee('FOREIGN-RUN')
            ->assertDontSee('STILL-RUNNING-CAMPAIGN')
            ->call('stopRemaining', $person->id)
            ->assertSee('Stopped 1 remaining sequence');

        $runningRun->refresh();
        $this->assertFalse($runningRun->is_active);
        $this->assertNotNull($runningRun->stopped_reason);
    }
}
