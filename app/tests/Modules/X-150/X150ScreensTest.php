<?php

declare(strict_types=1);

namespace Tests\Modules\X150;

use App\Modules\X150\Actions\ProviderFetchAction;
use App\Modules\X150\Models\ProviderAttempt;
use App\Modules\X150\Models\ProviderRoster;
use App\Modules\X150\Ui\ProviderCostPer;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class X150ScreensTest extends TestCase
{
    protected int $businessId;

    private ProviderFetchAction $fetchAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->businessId = TestCase::provisionTenant(['name' => 'Cost Tenant'])->id;
        Tenancy::set($this->businessId);

        ProviderAttempt::where('business_id', $this->businessId)->delete();
        ProviderRoster::where('business_id', $this->businessId)->delete();

        $this->fetchAction = new ProviderFetchAction;
    }

    public function test_provider_cost_per_mount_and_empty(): void
    {
        Livewire::test(ProviderCostPer::class, ['businessId' => $this->businessId])
            ->assertOk()
            ->assertSee('No providers on the roster yet');
    }

    public function test_provider_cost_per_valid_record(): void
    {
        $mockTier1Junk = ['name' => 'Bob Smith', 'phone' => 'N/A'];
        $mockTier2Valid = ['name' => 'Bob Smith', 'phone' => '+15551234567', 'carrier' => 'Verizon'];

        $this->fetchAction->fetch(
            businessId: $this->businessId,
            requestId: 'req_1',
            targetName: 'Bob Smith',
            mockTier1Data: $mockTier1Junk,
            mockTier2Data: $mockTier2Valid
        );

        $mockTier1Clean = ['name' => 'Alice Jones', 'phone' => '+15559876543'];
        $this->fetchAction->fetch(
            businessId: $this->businessId,
            requestId: 'req_2',
            targetName: 'Alice Jones',
            mockTier1Data: $mockTier1Clean
        );

        Livewire::test(ProviderCostPer::class, ['businessId' => $this->businessId])
            ->assertSee('$0.10')
            ->assertSee('$0.45')
            ->assertSee('In use');
    }

    public function test_provider_cost_per_mark_cold(): void
    {
        $mockTier1Clean = ['name' => 'Alice Jones', 'phone' => '+15559876543'];
        $this->fetchAction->fetch(
            businessId: $this->businessId,
            requestId: 'req_1',
            targetName: 'Alice Jones',
            mockTier1Data: $mockTier1Clean
        );

        $provider = ProviderRoster::where('business_id', $this->businessId)->first();

        Livewire::test(ProviderCostPer::class, ['businessId' => $this->businessId])
            ->call('markCold', $provider->id);

        $provider->refresh();
        $this->assertFalse($provider->is_active);

        Livewire::test(ProviderCostPer::class, ['businessId' => $this->businessId])
            ->assertSee('Cold');
    }

    public function test_provider_cost_per_error_state(): void
    {
        Livewire::test(ProviderCostPer::class, ['businessId' => $this->businessId])
            ->call('markCold', 999999)
            ->assertSee('Action failed');

        $this->assertSame(0, ProviderRoster::where('business_id', $this->businessId)->count());
    }

    public function test_provider_cost_per_sample_state(): void
    {
        $count = ProviderRoster::where('business_id', $this->businessId)->count();

        Livewire::test(ProviderCostPer::class, ['businessId' => $this->businessId])
            ->call('toggleSample')
            ->assertSee('DeepEnrich Premium')
            ->assertSee('$0.90')
            ->call('markCold', 9402);

        $this->assertEquals($count, ProviderRoster::where('business_id', $this->businessId)->count());
    }
}
