<?php

declare(strict_types=1);

namespace Tests\Modules\X110;

use App\Modules\X110\Models\PixelEvent;
use App\Modules\X110\Models\Session;
use App\Modules\X110\Models\Visit;
use App\Modules\X110\Ui\VisitorsLive;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class VisitorsLiveTest extends TestCase
{
    use DatabaseTransactions;

    public function test_visitors_live_empty(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Visitors Live Empty']);
        Tenancy::set((int) $biz->id);

        Livewire::test(VisitorsLive::class, ['businessId' => $biz->id])
            ->assertSee('Nobody on the site right now')
            ->assertSee('Pixel not verified');

        $biz2 = TestCase::provisionTenant(['name' => 'Visitors Live Empty Verified']);
        Tenancy::set((int) $biz2->id);

        $visit = Visit::create(['business_id' => $biz2->id, 'visitor_id' => 'x']);
        $session = Session::create(['business_id' => $biz2->id, 'visit_id' => $visit->id, 'session_token' => 'x', 'started_at' => now()->subHours(2)]);

        PixelEvent::create([
            'business_id' => $biz2->id,
            'event_name' => 'page_view',
            'session_id' => $session->id,
            'payload' => [],
            'created_at' => now()->subHours(2),
        ]);

        Livewire::test(VisitorsLive::class, ['businessId' => $biz2->id])
            ->assertSee('Nobody on the site right now')
            ->assertSee('The tag is installed and listening.');
    }

    public function test_visitors_live_with_data(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Visitors Live Data']);
        Tenancy::set((int) $biz->id);

        $visit = Visit::create([
            'business_id' => $biz->id,
            'visitor_id' => 'v-123',
            'landing_page' => 'https://example.com/pricing',
            'utm_source' => 'google',
        ]);

        Session::create([
            'business_id' => $biz->id,
            'visit_id' => $visit->id,
            'session_token' => 'tok',
            'started_at' => now()->subMinutes(5),
        ]);

        Livewire::test(VisitorsLive::class, ['businessId' => $biz->id])
            ->assertSee('v-123')
            ->assertSee('https://example.com/pricing')
            ->assertSee('google');
    }
}
