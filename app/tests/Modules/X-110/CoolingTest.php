<?php

declare(strict_types=1);

namespace Tests\Modules\X110;

use App\Modules\X110\Models\Session;
use App\Modules\X110\Models\Visit;
use App\Modules\X110\Models\PixelEvent;
use App\Modules\X110\Ui\Cooling;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class CoolingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_cooling_ordering(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Cooling Tenant']);
        Tenancy::set((int) $biz->id);

        // Visitor 1: 1 visit, no events, very quiet (quietest)
        $visit1 = Visit::create([
            'business_id' => $biz->id,
            'visitor_id' => 'v-quiet',
            'landing_page' => '/',
            'created_at' => now()->subDays(10),
        ]);
        Session::create([
            'business_id' => $biz->id,
            'visit_id' => $visit1->id,
            'session_token' => 'tok1',
            'started_at' => now()->subDays(10),
            'created_at' => now()->subDays(10),
        ]);

        // Visitor 2: 1 visit, but has form abandoned (hotter). Not as quiet as visitor 1.
        $visit2 = Visit::create([
            'business_id' => $biz->id,
            'visitor_id' => 'v-hot',
            'landing_page' => '/',
            'created_at' => now()->subDays(2),
        ]);
        $session2 = Session::create([
            'business_id' => $biz->id,
            'visit_id' => $visit2->id,
            'session_token' => 'tok2',
            'started_at' => now()->subDays(2),
            'created_at' => now()->subDays(2),
        ]);
        PixelEvent::create([
            'business_id' => $biz->id,
            'session_id' => $session2->id,
            'event_name' => 'form.abandoned',
            'payload' => [
                'form_id' => 'lead',
                'abandoned_field' => 'phone',
            ],
            'created_at' => now()->subDays(2),
        ]);

        // We want to test that it sorts by heat descending, then quiet-time descending.
        // Heat: v-hot (2), v-quiet (1)
        // Quiet-time: v-quiet (10 days), v-hot (2 days)
        // Order should be v-hot first, then v-quiet.

        Livewire::test(Cooling::class, ['businessId' => $biz->id])
            ->assertSeeInOrder(['v-hot', 'v-quiet']);
    }
}
