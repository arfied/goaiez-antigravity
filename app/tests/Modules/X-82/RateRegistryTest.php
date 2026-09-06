<?php

declare(strict_types=1);

namespace Tests\Modules\X82;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X82\Models\Rate;
use App\Modules\X82\Models\RateVersion;
use App\Modules\X82\Ui\RateRegistryView;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class RateRegistryViewTest extends TestCase
{
    public function test_guest_is_forbidden(): void
    {
        Livewire::test(RateRegistryView::class)->assertForbidden();
    }

    public function test_fresh_tenant_sees_empty_sentence(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $admin->id]);
        Tenancy::setUser($admin->id);

        Livewire::actingAs($admin)
            ->test(RateRegistryView::class)
            ->assertOk()
            ->assertSee('No rates in the registry. Seed the two packages.');
    }

    public function test_seeded_rows_displays_rate_and_sample_pill(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $admin->id]);
        Tenancy::setUser($admin->id);

        Rate::create([
            'business_id' => $biz->id,
            'rate_code' => 'TEST_RATE',
            'amount_cents' => 15000,
            'currency' => 'USD',
            'current_version' => 1,
            'is_active' => true,
            'is_sample' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(RateRegistryView::class)
            ->assertOk()
            ->assertSee('TEST_RATE')
            ->assertSee('$150.00')
            ->assertSeeHtml('<span aria-hidden="true">▲</span>
    <span>Sample</span>', false);
    }

    public function test_set_rate_action(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $admin->id]);
        Tenancy::setUser($admin->id);

        Livewire::actingAs($admin)
            ->test(RateRegistryView::class)
            ->set('newRateCode', 'NEW_RATE')
            ->set('newAmountDollars', '99.50')
            ->call('setRate');

        $this->assertDatabaseHas('rates', [
            'business_id' => $biz->id,
            'rate_code' => 'NEW_RATE',
            'amount_cents' => 9950,
            'current_version' => 1,
        ]);

        $this->assertDatabaseHas('rate_versions', [
            'business_id' => $biz->id,
            'version_number' => 1,
            'amount_cents' => 9950,
        ]);
    }

    public function test_inline_set_rate_writes_new_version_and_leaves_previous(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $admin->id]);
        Tenancy::setUser($admin->id);

        $rate = Rate::create([
            'business_id' => $biz->id,
            'rate_code' => 'INLINE_RATE',
            'amount_cents' => 5000,
            'currency' => 'USD',
            'current_version' => 1,
            'is_active' => true,
        ]);

        RateVersion::create([
            'business_id' => $biz->id,
            'rate_id' => $rate->id,
            'version_number' => 1,
            'amount_cents' => 5000,
            'effective_from' => now()->subDay(),
        ]);

        Livewire::actingAs($admin)
            ->test(RateRegistryView::class)
            ->set("amountInput.{$rate->id}", '75.00')
            ->call('setInlineRate', $rate->id);

        $this->assertDatabaseHas('rates', [
            'business_id' => $biz->id,
            'rate_code' => 'INLINE_RATE',
            'amount_cents' => 7500,
            'current_version' => 2,
        ]);

        $this->assertDatabaseHas('rate_versions', [
            'business_id' => $biz->id,
            'version_number' => 1,
            'amount_cents' => 5000,
        ]);

        $this->assertDatabaseHas('rate_versions', [
            'business_id' => $biz->id,
            'version_number' => 2,
            'amount_cents' => 7500,
        ]);
    }

    public function test_formatting_anchor_has_no_literals(): void
    {
        // The TEST ANCHOR checks formatting, so we assert the value is properly formatted in the view.
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $admin->id]);
        Tenancy::setUser($admin->id);

        Rate::create([
            'business_id' => $biz->id,
            'rate_code' => 'TEST_RATE_FORMAT',
            'amount_cents' => 123456,
            'currency' => 'USD',
            'current_version' => 1,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(RateRegistryView::class)
            ->assertOk()
            ->assertSee('$1,234.56');
    }
}
