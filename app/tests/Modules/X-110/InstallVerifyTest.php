<?php

declare(strict_types=1);

namespace Tests\Modules\X110;

use App\Models\User;
use App\Modules\X110\Models\PixelEvent;
use App\Modules\X110\Models\Session;
use App\Modules\X110\Models\Visit;
use App\Modules\X110\Ui\InstallVerify;
use App\Support\Tenancy;
use Database\Seeders\UiReviewSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class InstallVerifyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_install_verify_component(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Install Verify Tenant']);
        Tenancy::set((int) $biz->id);

        Livewire::test(InstallVerify::class, ['businessId' => $biz->id])
            ->assertSee('Tag Installation', false)
            ->assertSee('No events seen in the last 60 seconds');

        $visit = Visit::create(['business_id' => $biz->id, 'visitor_id' => 'x']);
        $session = Session::create(['business_id' => $biz->id, 'visit_id' => $visit->id, 'session_token' => 'x', 'started_at' => now()]);

        PixelEvent::create([
            'business_id' => $biz->id,
            'event_name' => 'page_view',
            'session_id' => $session->id,
            'payload' => [],
            'created_at' => now(),
        ]);

        Livewire::test(InstallVerify::class, ['businessId' => $biz->id])
            ->assertSee('1 events in the last 60 seconds');
    }

    public function test_account_tracking_renders_install_verify(): void
    {
        $this->seed(UiReviewSeeder::class);
        $owner = User::where('email', 'owner2@business.com')->first();
        $this->actingAs($owner)->get('/account/tracking')
            ->assertOk()
            ->assertSee('Tag Installation', false);
    }
}
