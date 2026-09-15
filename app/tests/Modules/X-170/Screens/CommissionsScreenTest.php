<?php

declare(strict_types=1);

namespace Tests\Modules\X170\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X170\Models\Commission;
use App\Modules\X170\Ui\Commissions;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class CommissionsScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-170.commissions'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No commission records.');

        Tenancy::setUser($owner->id);
        Commission::create([
            'business_id' => $biz->id,
            'invoice_id' => 4475,
            'staff_id' => 10,
            'amount_cents' => 123456,
            'status' => 'released',
            'payment_id' => 'pay_distinctive_4475'
        ]);
        Tenancy::forget();

        $this->get(route('x-170.commissions'))
            ->assertOk()
            ->assertSee('Staff 10')
            ->assertSee('1,234.56')
            ->assertSee('[released]')
            ->assertDontSee('No commission records.');

        Livewire::test(Commissions::class)->assertOk();
    }
}
