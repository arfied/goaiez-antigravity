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

    public function test_seeded_rate_reaches_the_page(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz1 = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Rate::create([
            'business_id' => $biz1->id,
            'rate_code' => 'TEST_RATE_TENANT',
            'amount_cents' => 68743,
            'currency' => 'USD',
            'current_version' => 1,
            'is_active' => true,
        ]);

        $this->get(route('x-82.rate-registry'))
            ->assertOk()
            ->assertSee('$687.43');

        $admin = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($admin);
        $biz2 = $this->provisionTenant(['owner_user_id' => $admin->id]);

        Rate::create([
            'business_id' => $biz2->id,
            'rate_code' => 'TEST_RATE_ADMIN',
            'amount_cents' => 68743,
            'currency' => 'USD',
            'current_version' => 1,
            'is_active' => true,
        ]);

        $this->get(route('x-82.rate-registry.admin'))
            ->assertOk()
            ->assertSee('$687.43');
    }

    public function test_staff_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Staff;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);

        Livewire::actingAs($user)->test(RateRegistryView::class)->assertForbidden();

    }

    public function test_a_typed_amount_with_cents_is_stored_to_the_cent_on_a_new_rate(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $admin->id]);
        Tenancy::setUser($admin->id);

        Livewire::actingAs($admin)
            ->test(RateRegistryView::class)
            ->set('newRateCode', 'CENTS_RATE')
            ->set('newAmountDollars', '19.99')
            ->call('setRate');

        $stored = Rate::where('business_id', $biz->id)->where('rate_code', 'CENTS_RATE')->value('amount_cents');

        $this->assertSame(1999, (int) $stored, 'T1 A1 a typed 19.99 was not stored as 1999 cents');
    }

    public function test_a_typed_amount_with_cents_is_stored_to_the_cent_on_an_inline_rate(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $admin->id]);
        Tenancy::setUser($admin->id);

        $rate = Rate::create([
            'business_id' => $biz->id,
            'rate_code' => 'INLINE_CENTS',
            'amount_cents' => 5000,
            'currency' => 'USD',
            'current_version' => 1,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(RateRegistryView::class)
            ->set("amountInput.{$rate->id}", '19.99')
            ->call('setInlineRate', $rate->id);

        $this->assertSame(1999, (int) $rate->fresh()->amount_cents, 'T2 A1 a typed 19.99 was not stored as 1999 cents');
    }
}
