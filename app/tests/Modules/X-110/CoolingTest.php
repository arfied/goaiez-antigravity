<?php

declare(strict_types=1);

namespace Tests\Modules\X110;

use App\Modules\X110\Models\PixelEvent;
use App\Modules\X110\Models\Session;
use App\Modules\X110\Models\Visit;
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

        $visit1 = Visit::create([
            'business_id' => $biz->id,
            'visitor_id' => 'v-cool',
            'landing_page' => '/',
            'created_at' => now()->subMinutes(5),
        ]);
        Session::create([
            'business_id' => $biz->id,
            'visit_id' => $visit1->id,
            'session_token' => 'tok1',
            'started_at' => now()->subMinutes(5),
            'created_at' => now()->subMinutes(5),
        ]);

        $visit2 = Visit::create([
            'business_id' => $biz->id,
            'visitor_id' => 'v-hot',
            'landing_page' => '/',
            'created_at' => now()->subDays(10),
        ]);
        $session2 = Session::create([
            'business_id' => $biz->id,
            'visit_id' => $visit2->id,
            'session_token' => 'tok2',
            'started_at' => now()->subDays(10),
            'created_at' => now()->subDays(10),
        ]);
        PixelEvent::create([
            'business_id' => $biz->id,
            'session_id' => $session2->id,
            'event_name' => 'form.abandoned',
            'payload' => [
                'form_id' => 'lead',
                'abandoned_field' => 'phone',
            ],
            'created_at' => now()->subDays(10),
        ]);

        $component = Livewire::test(Cooling::class, ['businessId' => $biz->id]);
        $component->assertSeeInOrder(['v-hot', 'v-cool'])
            ->assertSee('opener-v-hot', false);
            
        $html = $component->html();
        $posHot = strpos($html, '>v-hot<');
        $posCool = strpos($html, '>v-cool<');
        $this->assertNotFalse($posHot, 'v-hot not found');
        $this->assertNotFalse($posCool, 'v-cool not found');
        $this->assertLessThan($posCool, $posHot, 'v-hot should appear before v-cool');
    }

    public function test_cooling_empty_and_derivation(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Cooling Tenant']);
        Tenancy::set((int) $biz->id);

        Livewire::test(Cooling::class, ['businessId' => $biz->id])
            ->assertSee('Nobody cooling down right now');

        $visit = Visit::create([
            'business_id' => $biz->id,
            'visitor_id' => 'v-derived',
            'landing_page' => '/',
            'created_at' => now()->subDays(1),
        ]);
        $session = Session::create([
            'business_id' => $biz->id,
            'visit_id' => $visit->id,
            'session_token' => 'tok1',
            'started_at' => now()->subDays(1),
            'created_at' => now()->subDays(1),
        ]);
        PixelEvent::create([
            'business_id' => $biz->id,
            'session_id' => $session->id,
            'event_name' => 'form.abandoned',
            'payload' => [
                'form_id' => 'lead',
                'abandoned_field' => 'phone',
            ],
            'created_at' => now()->subDays(1),
        ]);

        Livewire::test(Cooling::class, ['businessId' => $biz->id])
            ->assertSee("quit the lead at 'phone'")
            ->assertSee("Quiet ");
    }
}
