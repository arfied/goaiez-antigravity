<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Enums\UserRole;
use App\Livewire\Account\PlacesKey;
use App\Models\Business;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class PlacesKeyScreenTest extends TestCase
{
    use RefreshesTenantDatabase;

    private User $owner;

    private Business $biz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->biz = self::provisionTenant(['owner_user_id' => $this->owner->id]);
        $this->actingAs($this->owner);
        Tenancy::setUser($this->owner->id);
        Tenancy::set((int) $this->biz->id);

        Mail::fake();
        Notification::fake();
    }

    public function test_get_shows_platform_initially(): void
    {
        $this->get(route('account.places-key'))
            ->assertOk()
            ->assertSee('Your Google Maps key')
            ->assertSee('platform');
    }

    public function test_save_valid_key_stores_it(): void
    {
        Http::fake([
            '*' => Http::response(['suggestions' => []], 200),
        ]);

        Livewire::actingAs($this->owner)
            ->test(PlacesKey::class)
            ->set('candidate', 'distinctive-tenant-key-4471-xxxxxxxx')
            ->call('save');

        $this->assertDatabaseHas('credentials', [
            'business_id' => $this->biz->id,
            'service_name' => 'google_places',
        ]);

        Http::assertSent(fn ($r) => $r->header('X-Goog-Api-Key')[0] === 'distinctive-tenant-key-4471-xxxxxxxx');

        $this->get(route('account.places-key'))->assertSee('Yours');
    }

    public function test_save_invalid_key_refuses(): void
    {
        Http::fake([
            'https://*' => Http::response([], 403),
        ]);

        Livewire::actingAs($this->owner)
            ->test(PlacesKey::class)
            ->set('candidate', 'distinctive-tenant-key-4471-xxxxxxxx')
            ->call('save');

        $this->assertDatabaseMissing('credentials', [
            'business_id' => $this->biz->id,
            'service_name' => 'google_places',
        ]);

        $this->get(route('account.places-key'))->assertSee('platform');
    }

    public function test_forget_removes_key(): void
    {
        Http::fake([
            '*' => Http::response(['suggestions' => []], 200),
        ]);

        $component = Livewire::actingAs($this->owner)
            ->test(PlacesKey::class)
            ->set('candidate', 'distinctive-tenant-key-4471-xxxxxxxx')
            ->call('save');

        $this->assertDatabaseHas('credentials', [
            'business_id' => $this->biz->id,
            'service_name' => 'google_places',
        ]);

        $component->call('forget');

        $this->assertDatabaseMissing('credentials', [
            'business_id' => $this->biz->id,
            'service_name' => 'google_places',
        ]);
    }

    public function test_refuses_staff_with_no_tenant_on_get(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $this->actingAs($staff);
        Tenancy::setUser($staff->id);
        Tenancy::forget();

        $this->get(route('account.places-key'))->assertForbidden();
    }
}
