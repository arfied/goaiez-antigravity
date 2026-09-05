<?php

declare(strict_types=1);

namespace Tests\Modules\X110;

use App\Models\Location;
use App\Models\User;
use App\Modules\X110\Models\PixelEvent;
use App\Modules\X110\Models\Session;
use App\Modules\X110\Models\Visit;
use App\Modules\X110\Ui\InstallVerify;
use App\Services\Tenant\LocationWebsite;
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

        $loc = Location::where('business_id', $biz->id)->first();
        if (! $loc) {
            $loc = Location::create(['business_id' => $biz->id, 'name' => 'HQ']);
        }
        app(LocationWebsite::class)->confirm($loc, 'https://example.com', 'user:1', true);

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
            ->assertSee('1 event in the last 60 seconds')
            ->assertDontSee('page_view')
            ->call('toggleEvents')
            ->assertSee('page_view');
    }

    public function test_account_tracking_renders_install_verify(): void
    {
        $this->seed(UiReviewSeeder::class);
        $owner = User::where('email', 'owner2@business.com')->firstOrFail();

        $this->actingAs($owner)->get('/account/tracking');

        $loc = Location::where('business_id', Tenancy::id())->first();
        if ($loc) {
            app(LocationWebsite::class)->confirm($loc, 'https://example.com', 'user:'.$owner->id, true);
        }

        $this->actingAs($owner)->get('/account/tracking')
            ->assertOk()
            ->assertSee('Tag Installation', false);
    }

    public function test_install_verify_handles_empty_state(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Install Verify Empty State']);
        Tenancy::set((int) $biz->id);

        Livewire::test(InstallVerify::class, ['businessId' => $biz->id])
            ->assertSee('No website location set')
            ->assertSee('Set your website URL in your location settings so we know where to listen.');
    }
}
