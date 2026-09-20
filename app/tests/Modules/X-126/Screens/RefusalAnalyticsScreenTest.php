<?php

declare(strict_types=1);

namespace Tests\Modules\X126\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X126\Models\CapabilityDecision;
use App\Modules\X126\Ui\RefusalAnalytics;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class RefusalAnalyticsScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::setUser($owner->id);
        CapabilityDecision::create([
            'business_id' => $biz->id,
            'capability_name' => 'RefundInvoice',
            'decision' => 'refused',
            'refusal_code' => 'NO_FACT',
        ]);
        Tenancy::forget();

        $this->get(route('x-126.refusal-analytics'))
            ->assertOk()
            ->assertSee('NO_FACT');

        Livewire::test(RefusalAnalytics::class, ['businessId' => $biz->id])
            ->assertOk()
            ->assertSee('NO_FACT');
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-126.refusal-analytics.admin'))->assertOk();

        Livewire::test(RefusalAnalytics::class)->assertOk();
    }
}
