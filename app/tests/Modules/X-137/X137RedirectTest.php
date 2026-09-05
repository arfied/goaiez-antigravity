<?php

declare(strict_types=1);

namespace Tests\Modules\X137;

use App\Modules\X137\Models\LinkClick;
use App\Modules\X137\Models\ShortLink;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class X137RedirectTest extends TestCase
{
    use RefreshesTenantDatabase;

    public function test_click_is_recorded_before_visitor_leaves(): void
    {
        $biz = self::provisionTenant(['name' => 'Biz A']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $link = ShortLink::create([
            'business_id' => $biz->id,
            'short_code' => 'xyz123',
            'destination_url' => 'https://example.com/target',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::statement("SELECT set_config('app.business_id', '', true)");

        $this->get("/l/{$biz->id}/xyz123")
            ->assertRedirect('https://example.com/target');

        DB::statement("SET app.business_id = '{$biz->id}'");
        $click = LinkClick::first();
        $this->assertNotNull($click);
        $this->assertEquals($link->id, $click->short_link_id);
    }

    public function test_tracking_failure_never_costs_click(): void
    {
        $biz = self::provisionTenant(['name' => 'Biz A']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $link = ShortLink::create([
            'business_id' => $biz->id,
            'short_code' => 'xyz123',
            'destination_url' => 'https://example.com/target',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Break the LinkClick insert by using an Eloquent event
        LinkClick::saving(function () {
            throw new \Exception('DB Error');
        });

        DB::statement("SELECT set_config('app.business_id', '', true)");

        $this->get("/l/{$biz->id}/xyz123")
            ->assertRedirect('https://example.com/target');

        DB::statement("SET app.business_id = '{$biz->id}'");
        $this->assertEquals(0, LinkClick::count());

        // Clear the event so it doesn't affect other tests
        LinkClick::flushEventListeners();
    }

    public function test_unknown_code_returns_404(): void
    {
        $bizA = self::provisionTenant(['name' => 'Biz A']);
        $bizB = self::provisionTenant(['name' => 'Biz B']);
        DB::statement("SET app.business_id = '{$bizA->id}'");

        $sl = ShortLink::create([
            'business_id' => $bizA->id,
            'short_code' => 'abc456',
            'destination_url' => 'https://example.com/target',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::statement("SELECT set_config('app.business_id', '', true)");

        // code that doesn't exist
        $this->get("/l/{$bizA->id}/nonexistent")
            ->assertStatus(404);

        // real code under the wrong business segment
        $this->get("/l/{$bizB->id}/abc456")
            ->assertStatus(404);
    }
}
