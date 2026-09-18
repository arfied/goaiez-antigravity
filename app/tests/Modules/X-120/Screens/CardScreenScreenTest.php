<?php

declare(strict_types=1);

namespace Tests\Modules\X120\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X120\Models\CardToken;
use App\Modules\X120\Ui\CardScreen;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class CardScreenScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-120.card-screen'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No cards on file');

        Tenancy::setUser($owner->id);
        CardToken::create([
            'business_id' => $biz->id,
            'gateway_payment_method_id' => 'Distinctive pm 4623',
            'gateway_customer_id' => 'Distinctive cus 4623',
            'brand' => 'visa',
            'last_four' => '4623',
            'exp_month' => 12,
            'exp_year' => now()->year + 3,
            'is_default' => true,
        ]);
        Tenancy::forget();

        $this->get(route('x-120.card-screen'))
            ->assertOk()
            ->assertSee('Card ending in 4623')
            ->assertSee('Default')
            ->assertDontSee('No cards on file');

        Livewire::test(CardScreen::class)->assertOk();
    }
}
