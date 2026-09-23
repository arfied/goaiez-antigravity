<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Enums\BrandRegistrationStatus;
use App\Enums\UserRole;
use App\Livewire\Account\Texting as TextingComponent;
use App\Models\BrandRegistration;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

final class TextingScreenTest extends TestCase
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
        $this->get(route('account.texting'))
            ->assertOk()
            ->assertSee('Your account', false)
            ->assertDontSee('Internal Platform Console');
    }

    public function test_empty_state()
    {
        Livewire::test(TextingComponent::class)
            ->assertSee('Nothing has been registered for you yet');
    }

    public function test_row_renders_distinctive_value()
    {
        BrandRegistration::forceCreate([
            'business_id' => $this->biz->id,
            'status' => BrandRegistrationStatus::Rejected,
            'rejection_reason' => 'Distinctive Person 7719',
            'submitted_at' => now(),
            'rejected_at' => now(),
        ]);

        Livewire::test(TextingComponent::class)
            ->assertSee('Distinctive Person 7719');
    }
}
