<?php

declare(strict_types=1);

namespace Tests\Modules\X151;

use App\Modules\X151\Actions\FetchRefreshAction;
use App\Modules\X151\Models\Fetch;
use App\Modules\X151\Models\FetchTarget;
use App\Modules\X151\Ui\FetchBoard;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class X151ScreensTest extends TestCase
{

    protected int $businessId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->businessId = TestCase::provisionTenant(['name' => 'Test Tenant', 'currency' => 'USD'])->id;
        Tenancy::set($this->businessId);

        Fetch::where('business_id', $this->businessId)->delete();
        FetchTarget::where('business_id', $this->businessId)->delete();
    }

    public function test_fetch_board_mount_and_empty(): void
    {
        Livewire::test(FetchBoard::class, ['businessId' => $this->businessId])
            ->assertOk()
            ->assertSee('Nothing fetched yet');
    }

    public function test_fetch_board_rows_and_stale_tab(): void
    {
        $target = FetchTarget::create([
            'business_id' => $this->businessId,
            'domain' => 'example.com',
            'rps_ceiling' => 2,
            'concurrency_ceiling' => 2,
        ]);

        $fresh = Fetch::create([
            'business_id' => $this->businessId,
            'target_id' => $target->id,
            'url' => 'https://example.com/fresh',
            'status' => 'success',
            'is_stale' => false,
        ]);

        $old = Fetch::create([
            'business_id' => $this->businessId,
            'target_id' => $target->id,
            'url' => 'https://example.com/old',
            'status' => 'success',
            'is_stale' => false,
        ]);

        app(FetchRefreshAction::class)->handle($this->businessId, $old->id);

        Livewire::test(FetchBoard::class, ['businessId' => $this->businessId])
            ->call('setTab', 'stale')
            ->assertSee('/old')
            ->assertDontSee('/fresh')
            ->assertSee('Stale');
    }

    public function test_fetch_board_mark_stale_action(): void
    {
        $target = FetchTarget::create([
            'business_id' => $this->businessId,
            'domain' => 'example.com',
            'rps_ceiling' => 2,
            'concurrency_ceiling' => 2,
        ]);

        $fetch = Fetch::create([
            'business_id' => $this->businessId,
            'target_id' => $target->id,
            'url' => 'https://example.com/item',
            'status' => 'success',
            'is_stale' => false,
        ]);

        Livewire::test(FetchBoard::class, ['businessId' => $this->businessId])
            ->call('markStale', $fetch->id);

        $fetch->refresh();
        $this->assertTrue($fetch->is_stale);
        $this->assertEquals('stale', $fetch->status);
    }

    public function test_fetch_board_sample_state(): void
    {
        $count = Fetch::count();

        Livewire::test(FetchBoard::class, ['businessId' => $this->businessId])
            ->call('toggleSample')
            ->assertSee('CAPTCHA')
            ->assertSee('example-plumbing.test/pricing')
            ->call('markStale', 9302);

        $this->assertEquals($count, Fetch::count());
    }
}
