<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Enums\UserRole;
use App\Livewire\Account\Knowledge as KnowledgeComponent;
use App\Models\KnowledgeSource;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

final class KnowledgeScreenTest extends TestCase
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
        $this->get(route('account.knowledge'))
            ->assertOk()
            ->assertSee('Your account', false)
            ->assertDontSee('Internal Platform Console');
    }

    public function test_empty_state()
    {
        Livewire::test(KnowledgeComponent::class)
            ->assertSee('Nothing yet. Until you add something');
    }

    public function test_tenant_isolation()
    {
        $bizB = self::provisionTenant();

        Tenancy::setUser($this->owner->id);
        Tenancy::set((int) $this->biz->id);

        KnowledgeSource::factory()->create([
            'business_id' => $this->biz->id,
            'title' => 'Distinctive File 7719.txt',
        ]);

        Tenancy::set((int) $bizB->id);
        KnowledgeSource::factory()->create([
            'business_id' => $bizB->id,
            'title' => 'Distinctive File 7720.txt',
        ]);

        Tenancy::set((int) $this->biz->id);

        Livewire::test(KnowledgeComponent::class)
            ->assertSee('Distinctive File 7719.txt')
            ->assertDontSee('Distinctive File 7720.txt');
    }
}
