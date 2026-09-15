<?php

declare(strict_types=1);

namespace Tests\Modules\X190\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X190\Models\PartnerPool;
use App\Modules\X190\Ui\PoolDepthPer;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class PoolDepthPerScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-190.pool-depth-per'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No partners in your pool yet.');

        Tenancy::set((int) $biz->id);
        PartnerPool::create([
            'business_id' => $biz->id,
            'company_name' => 'Pool Partner 4471',
            'category' => 'roofing',
            'territory_zip' => '75003',
        ]);
        Tenancy::forget();

        $this->get(route('x-190.pool-depth-per'))
            ->assertOk()
            ->assertSee('1 partner in your pool.')
            ->assertDontSee('No partners in your pool yet.');

        Livewire::actingAs($owner)->test(PoolDepthPer::class, ['businessId' => $biz->id])->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-190.pool-depth-per.admin'))->assertOk();

        Livewire::test(PoolDepthPer::class)->assertOk();
    }
}
