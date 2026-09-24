<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Enums\UserRole;
use App\Livewire\Account\Facts;
use App\Models\Business;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class FactsScreenTest extends TestCase
{
    use RefreshesTenantDatabase;

    private User $owner;

    private Business $biz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->biz = TestCase::provisionTenant(['owner_user_id' => $this->owner->id]);
        $this->actingAs($this->owner);
        Tenancy::setUser($this->owner->id);
        Tenancy::set((int) $this->biz->id);

        Mail::fake();
        Notification::fake();
    }

    public function test_get_shows_facts_screen(): void
    {
        $this->get(route('account.facts'))
            ->assertOk()
            ->assertSee('Your business facts')
            ->assertSee('Leave any box empty');
    }

    public function test_saves_facts_and_shows_them_on_get(): void
    {
        Livewire::actingAs($this->owner)
            ->test(Facts::class)
            ->set('facts.tagline', 'Distinctive tagline 4471')
            ->set('facts.years_in_business', '12')
            ->call('save');

        $this->assertDatabaseHas('business_facts', [
            'business_id' => $this->biz->id,
            'key' => 'tagline',
            'value' => 'Distinctive tagline 4471',
            'verified_by_owner' => true,
        ]);

        $this->get(route('account.facts'))
            ->assertSee('Distinctive tagline 4471');

        Livewire::actingAs($this->owner)
            ->test(Facts::class)
            ->set('facts.tagline', '')
            ->call('save');

        $this->assertDatabaseMissing('business_facts', [
            'business_id' => $this->biz->id,
            'key' => 'tagline',
        ]);
    }

    public function test_refuses_invalid_years(): void
    {
        Livewire::actingAs($this->owner)
            ->test(Facts::class)
            ->set('facts.years_in_business', 'twelve')
            ->call('save')
            ->assertHasErrors(['facts.years_in_business']);
    }

    public function test_refuses_staff_with_no_tenant_on_get(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        Tenancy::forgetAll();

        $this->actingAs($staff)
            ->get(route('account.facts'))
            ->assertForbidden();
    }
}
