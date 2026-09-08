<?php

declare(strict_types=1);

namespace Tests\Modules\X199;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X199\Models\CreditTerm;
use App\Modules\X199\Ui\Credits;
use App\Support\Tenancy;
use Database\Factories\PersonFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class CreditsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_credits_data_and_tenant_isolation(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['name' => 'Credits Tenant 1', 'owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);
        $customer = PersonFactory::new()->create(['business_id' => $biz->id]);

        CreditTerm::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'terms_type' => 'net_30',
            'credit_limit_cents' => 500000,
            'current_outstanding_cents' => 150000,
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        $otherBiz = TestCase::provisionTenant(['name' => 'Credits Tenant 2']);
        Tenancy::actingAs($otherBiz->id, function () use ($otherBiz) {
            $otherCustomer = PersonFactory::new()->create(['business_id' => $otherBiz->id]);
            CreditTerm::create([
                'business_id' => $otherBiz->id,
                'customer_id' => $otherCustomer->id,
                'terms_type' => 'due_on_receipt',
                'credit_limit_cents' => 99900,
                'current_outstanding_cents' => 0,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        });

        Tenancy::set((int) $biz->id);

        Livewire::test(Credits::class)
            ->assertSee('5,000.00')
            ->assertSee('1,500.00')
            ->assertSee('net 30')
            ->assertDontSee('selected>due on receipt</option>', false)
            ->assertDontSee('999.00');

        CreditTerm::where('business_id', $biz->id)->delete();
        Livewire::test(Credits::class)
            ->assertSee('Nobody is on terms yet.')
            ->assertSee('A commercial customer gets terms')
            ->assertSee('appear here the moment they do');
    }

    public function test_route_renders_credits(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::set((int) $biz->id);
        $customer = PersonFactory::new()->create(['business_id' => $biz->id]);

        CreditTerm::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'terms_type' => 'net_30',
            'credit_limit_cents' => 500000,
            'current_outstanding_cents' => 150000,
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        $this->get(route('x-199.credits'))
            ->assertOk()
            ->assertSee('5,000.00')
            ->assertSee('1,500.00')
            ->assertSee('net 30');
    }
}
