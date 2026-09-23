<?php

declare(strict_types=1);

namespace Tests\Feature\Advanced;

use App\Enums\CreditKind;
use App\Enums\CreditPool;
use App\Enums\CreditProduct;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\CreditLedgerEntry;
use App\Models\User;
use App\Support\Tenancy;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class CreditsScreenTest extends TestCase
{
    use RefreshesTenantDatabase;

    protected function createTenant(bool $advanced = false): array
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $business = Business::provision([
            'owner_user_id' => $user->id,
            'name' => 'Acme Dental '.rand(100, 999),
        ]);

        if ($advanced) {
            $business->update(['advanced_dashboard_enabled' => true]);
        }

        Tenancy::setUser((int) $user->id);
        Tenancy::set((int) $business->id);

        return [$user, $business];
    }

    public function test_get_and_shell_mark(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        $response = $this->actingAs($user)->get(route('advanced.credits'));
        $response->assertOk();
        $response->assertSee('Skip to content');
    }

    public function test_the_tenants_balance_renders_from_the_component(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);

        CreditLedgerEntry::forceCreate([
            'business_id' => $business->id,
            'product' => CreditProduct::Ai,
            'pool' => CreditPool::TopUp,
            'kind' => CreditKind::Adjust,
            'delta' => 771900,
            'balance_after' => 771900,
            'reason' => 'Distinctive grant 7719',
            'created_by' => 'test',
        ]);

        $response = $this->actingAs($user)->get(route('advanced.credits'));
        $response->assertOk();

        $response->assertSee('Distinctive grant 7719');
        $response->assertSee('$77.19');
    }

    public function test_empty_ledger_and_no_arrangement_read_honestly(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        $response = $this->actingAs($user)->get(route('advanced.credits'));
        $response->assertOk();

        $response->assertSee('No credit activity yet.');
        $response->assertSee('No automatic refill set up.');
    }

    public function test_another_tenants_entries_are_not_listed(): void
    {
        [$userA, $bizA] = $this->createTenant(advanced: true);

        CreditLedgerEntry::forceCreate([
            'business_id' => $bizA->id,
            'product' => CreditProduct::Ai,
            'pool' => CreditPool::TopUp,
            'kind' => CreditKind::Adjust,
            'delta' => 500000,
            'balance_after' => 500000,
            'reason' => 'Biz A grant',
            'created_by' => 'test',
        ]);

        [$userB, $bizB] = $this->createTenant(advanced: true);

        $response = $this->actingAs($userB)->get(route('advanced.credits'));
        $response->assertOk();
        $response->assertDontSee('Biz A grant');
    }

    public function test_a_staff_user_gets_the_status_measured(): void
    {
        [$owner, $business] = $this->createTenant(advanced: true);
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $response = $this->actingAs($staff)->get(route('advanced.credits'));
        $response->assertStatus(302);
    }
}
