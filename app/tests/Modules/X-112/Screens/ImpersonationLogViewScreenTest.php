<?php

declare(strict_types=1);

namespace Tests\Modules\X112\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X112\Models\Agency;
use App\Modules\X112\Models\ImpersonationLog;
use App\Modules\X112\Ui\ImpersonationLogView;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ImpersonationLogViewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::setUser($owner->id);
        $agency = Agency::create([
            'business_id' => $biz->id,
            'agency_name' => 'Demo Agency',
            'whitelabel_domain' => 'demo.example',
            'agency_mode' => 'full_service',
        ]);
        ImpersonationLog::create([
            'business_id' => $biz->id,
            'agency_id' => $agency->id,
            'user_id' => $owner->id,
            'target_client_business_id' => $biz->id,
            'reason' => 'Debugging connection',
        ]);
        Tenancy::forget();

        $this->get(route('x-112.impersonation-log'))
            ->assertOk()
            ->assertSee('Debugging connection');

        Livewire::test(ImpersonationLogView::class, ['businessId' => $biz->id])
            ->assertOk()
            ->assertSee('Debugging connection');
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-112.impersonation-log.admin'))->assertOk();

        Livewire::test(ImpersonationLogView::class)->assertOk();
    }
}
