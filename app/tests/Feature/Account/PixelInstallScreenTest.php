<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Enums\UserRole;
use App\Livewire\Account\PixelInstall as PixelInstallComponent;
use App\Models\User;
use App\Services\Pixel\PixelKeys;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

final class PixelInstallScreenTest extends TestCase
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
        $this->get(route('account.pixel-install'))
            ->assertOk()
            ->assertSee('Your account', false)
            ->assertDontSee('Internal Platform Console');
    }

    public function test_renders_snippet_identifier()
    {
        $key = app(PixelKeys::class)->ensureFor($this->biz);

        Livewire::test(PixelInstallComponent::class)
            ->assertSee($key);
    }

    public function test_staff_user()
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $this->actingAs($staff);
        Tenancy::setUser($staff->id);

        $this->get(route('account.pixel-install'))
            ->assertForbidden();
    }
}
