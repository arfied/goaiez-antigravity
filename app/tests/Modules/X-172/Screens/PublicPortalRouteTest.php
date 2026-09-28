<?php

declare(strict_types=1);

namespace Tests\Modules\X172\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X172\Models\PortalLink;
use App\Modules\X172\Ui\CustomerfacingPortal;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

require_once __DIR__.'/Fixtures.php';

class PublicPortalRouteTest extends TestCase
{
    public function test_a_customer_opens_the_portal_from_the_link_without_logging_in(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $token = Fixtures::token($biz);
        Tenancy::forgetAll();

        $this->get('/portal/'.$token)->assertOk();

        Tenancy::set((int) $biz->id);
        $this->assertDatabaseHas('portal_views', [
            'portal_link_id' => PortalLink::where('token', $token)->value('id'),
        ]);
    }

    public function test_an_unknown_token_is_404_without_logging_in(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $token = Fixtures::token($biz);
        Tenancy::forgetAll();

        $this->get('/portal/distinctive-no-such-token-4621')->assertNotFound();
    }

    public function test_an_inactive_link_is_404(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $token = Fixtures::token($biz);

        Tenancy::set((int) $biz->id);
        PortalLink::where('token', $token)->update(['is_active' => false]);
        Tenancy::forgetAll();

        $this->get('/portal/'.$token)->assertNotFound();
    }

    public function test_a_portal_action_records_against_the_links_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $token = Fixtures::token($biz);
        Tenancy::forgetAll();

        Livewire::test(CustomerfacingPortal::class, ['token' => $token])
            ->call('requestPay')
            ->assertSet('errorMessage', null);

        Tenancy::set((int) $biz->id);
        $this->assertDatabaseHas('portal_views', ['action_taken' => 'pay_requested']);
    }
}
