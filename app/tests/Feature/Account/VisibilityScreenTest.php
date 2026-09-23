<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Enums\UserRole;
use App\Livewire\Account\Visibility as VisibilityComponent;
use App\Models\Location;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

final class VisibilityScreenTest extends TestCase
{
    use RefreshesTenantDatabase;

    private User $owner;

    private $biz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->biz = self::provisionTenant(['owner_user_id' => $this->owner->id]);
        $this->actingAs($this->owner);
        Tenancy::setUser($this->owner->id);
        Tenancy::set((int) $this->biz->id);
    }

    public function test_layout()
    {
        $this->get(route('account.visibility'))
            ->assertOk()
            ->assertSee('Your account', false)
            ->assertDontSee('Internal Platform Console');
    }

    public function test_empty_state()
    {
        Location::where('business_id', $this->biz->id)->delete();

        Livewire::test(VisibilityComponent::class)
            ->assertSee('Add a location to see how people find you.');
    }

    public function test_row_renders_distinctive_value()
    {
        Location::factory()->create(['business_id' => $this->biz->id, 'name' => 'Distinctive Location 7719']);

        Livewire::test(VisibilityComponent::class)
            ->assertSee('Distinctive Location 7719');
    }
}
