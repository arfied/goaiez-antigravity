<?php

declare(strict_types=1);

namespace Tests\Modules\X157;

use App\Models\Business;
use App\Modules\X108\Models\Appointment;
use App\Modules\X157\Actions\EdgeDeployAction;
use App\Modules\X157\Actions\EdgeProvisionAction;
use App\Modules\X163\Models\PriceBookItem;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class EdgeDeployBoundsTest extends TestCase
{
    use RefreshesTenantDatabase;

    private EdgeProvisionAction $provisionAction;

    private EdgeDeployAction $deployAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provisionAction = new EdgeProvisionAction;
        $this->deployAction = app(EdgeDeployAction::class);
    }

    public function test_appointments_are_bounded_and_ordered_by_start_time_asc()
    {
        $biz = Business::factory()->create();
        $zone = $this->provisionAction->handle($biz->id, 'bounds-apt.com', true);

        for ($i = 25; $i >= 1; $i--) {
            Appointment::create([
                'business_id' => $biz->id,
                'service_name' => "Appointment {$i}",
                'start_time' => now()->addDays($i),
                'end_time' => now()->addDays($i)->addHours(1),
            ]);
        }

        $result = $this->deployAction->handle($biz->id, $zone->id, 100, 1500);
        $this->assertEquals('deployed', $result['status']);

        $html = Storage::disk('local')->get("sites/{$result['deploy_hash']}.html");

        $count = substr_count($html, 'class="event-item"');
        $this->assertEquals(20, $count);

        for ($i = 1; $i <= 20; $i++) {
            $this->assertStringContainsString("Appointment {$i}", $html);
        }

        for ($i = 21; $i <= 25; $i++) {
            $this->assertStringNotContainsString("Appointment {$i}", $html);
        }
    }

    public function test_price_book_items_are_ordered_by_id_asc()
    {
        $biz = Business::factory()->create();
        $zone = $this->provisionAction->handle($biz->id, 'bounds-price.com', true);

        for ($i = 1; $i <= 25; $i++) {
            PriceBookItem::create([
                'business_id' => $biz->id,
                'service_name' => "Service {$i}",
                'price_cents' => 1000,
                'is_confirmed' => true,
                'is_sample' => false,
            ]);
        }

        // Update the first 10 items to move them to the end of the heap, ensuring insertion order != natural DB order.
        $items = PriceBookItem::where('business_id', $biz->id)->orderBy('id', 'asc')->limit(10)->get();
        foreach ($items as $item) {
            $item->update(['price_cents' => 1001]);
        }

        $result = $this->deployAction->handle($biz->id, $zone->id, 100, 1500);
        $this->assertEquals('deployed', $result['status']);

        $html = Storage::disk('local')->get("sites/{$result['deploy_hash']}.html");

        $count = substr_count($html, 'class="offer-item"');
        $this->assertEquals(20, $count);

        for ($i = 1; $i <= 20; $i++) {
            $this->assertStringContainsString("Service {$i}", $html);
        }

        for ($i = 21; $i <= 25; $i++) {
            $this->assertStringNotContainsString("Service {$i}", $html);
        }
    }
}
