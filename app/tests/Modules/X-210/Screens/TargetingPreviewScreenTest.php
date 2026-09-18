<?php

declare(strict_types=1);

namespace Tests\Modules\X210\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X210\Models\Promotion;
use App\Modules\X210\Models\PromotionScope;
use App\Modules\X210\Ui\TargetingPreview;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class TargetingPreviewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-210.targeting-preview'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No targeting scopes yet.');

        Tenancy::set((int) $biz->id);
        $promo = Promotion::create([
            'business_id' => $biz->id,
            'code' => 'DISTINCT4471',
            'discount_type' => 'percentage',
            'discount_value' => 10,
        ]);
        PromotionScope::create([
            'business_id' => $biz->id,
            'promotion_id' => $promo->id,
            'scope_type' => 'territory',
            'scope_value' => 'zip-75004-distinctive',
        ]);
        Tenancy::forget();

        $this->get(route('x-210.targeting-preview'))
            ->assertOk()
            ->assertSee('zip-75004-distinctive')
            ->assertSee('territory')
            ->assertDontSee('No targeting scopes yet.');

        Livewire::actingAs($owner)->test(TargetingPreview::class, ['businessId' => $biz->id])->assertOk();
    }
}
