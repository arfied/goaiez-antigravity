<?php

declare(strict_types=1);

namespace Tests\Modules\X110;

use App\Models\User;
use App\Modules\X110\Models\PixelEvent;
use App\Modules\X110\Models\Session;
use App\Modules\X110\Models\Visit;
use App\Modules\X110\Ui\Today;
use App\Support\Tenancy;
use Database\Seeders\UiReviewSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class TodayTest extends TestCase
{
    use DatabaseTransactions;

    public function test_today_component(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Today Tenant']);
        Tenancy::set((int) $biz->id);

        Livewire::test(Today::class, ['businessId' => $biz->id])
            ->assertSee('Pixel not verified');

        $visit = Visit::create([
            'business_id' => $biz->id,
            'visitor_id' => '456',
            'landing_page' => 'https://example.com',
        ]);

        $sess = Session::create([
            'business_id' => $biz->id,
            'visit_id' => $visit->id,
            'session_token' => 'tok1',
            'started_at' => now(),
        ]);

        PixelEvent::create([
            'business_id' => $biz->id,
            'event_name' => 'page_view',
            'session_id' => $sess->id,
            'payload' => [],
            'created_at' => now(),
        ]);

        Livewire::test(Today::class, ['businessId' => $biz->id])
            ->assertSee("Today's Visitors", false)
            ->assertSee('1');
    }

    public function test_home_renders_today(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Home Tenant']);
        Tenancy::set((int) $biz->id);
        $this->seed(UiReviewSeeder::class);
        $owner = User::where('email', 'owner2@business.com')->first();

        $this->actingAs($owner)->get('/home')->assertOk();
    }
}
