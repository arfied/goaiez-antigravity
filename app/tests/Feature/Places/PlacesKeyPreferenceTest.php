<?php

declare(strict_types=1);

namespace Tests\Feature\Places;

use App\Enums\CredentialEnvironment;
use App\Enums\PlacesSku;
use App\Exceptions\PlacesBudgetExhausted;
use App\Models\PlacesApiCall;
use App\Models\User;
use App\Modules\X206\Actions\CredentialForgetAction;
use App\Modules\X206\Actions\CredentialStoreAction;
use App\Services\Config\CredentialStore;
use App\Services\Config\DefaultsRegistry;
use App\Services\Places\GooglePlacesClient;
use App\Services\Places\PlacesSpend;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class PlacesKeyPreferenceTest extends TestCase
{
    use RefreshesTenantDatabase;

    public function test_key_preference(): void
    {
        $owner = User::factory()->create();
        $biz = self::provisionTenant(['owner_user_id' => $owner->id]);

        app(CredentialStore::class)->set(
            'google_places_key',
            'platform-key-7731',
            'user:1',
            CredentialEnvironment::Live,
        );

        Tenancy::set((int) $biz->id);

        Http::fake([
            '*' => Http::response(['suggestions' => []], 200),
        ]);

        app(DefaultsRegistry::class)->set(PlacesSpend::AUTOCOMPLETE_BUDGET_KEY, 100, 'user:1');

        $client = app(GooglePlacesClient::class);
        $client->autocomplete('coffee');

        Http::assertSent(fn ($r) => $r->header('X-Goog-Api-Key')[0] === 'platform-key-7731');

        app(CredentialStoreAction::class)->handle((int) $biz->id, GooglePlacesClient::TENANT_SERVICE, 'distinctive-tenant-key-4471');

        $client->autocomplete('tea');

        Http::assertSent(fn ($r) => $r->header('X-Goog-Api-Key')[0] === 'distinctive-tenant-key-4471');

        Tenancy::forgetAll();

        $client->autocomplete('water');

        Http::assertSent(fn ($r) => $r->header('X-Goog-Api-Key')[0] === 'platform-key-7731');
    }

    public function test_a_search_on_the_tenants_own_key_is_recorded_as_own_key_and_never_refused_by_the_platform_ceiling(): void
    {
        $owner = User::factory()->create();
        $biz = self::provisionTenant(['owner_user_id' => $owner->id]);

        app(CredentialStore::class)->set(
            'google_places_key',
            'platform-key-7731',
            'user:1',
            CredentialEnvironment::Live,
        );

        Tenancy::set((int) $biz->id);

        Http::fake([
            '*' => Http::response(['places' => []], 200),
        ]);

        app(DefaultsRegistry::class)->set(PlacesSpend::TENANT_CEILING_KEY, 0, 'user:1');

        app(CredentialStoreAction::class)->handle((int) $biz->id, GooglePlacesClient::TENANT_SERVICE, 'distinctive-tenant-key-4471');

        $client = app(GooglePlacesClient::class);
        $client->textSearch('coffee');

        Http::assertSent(fn ($r) => $r->header('X-Goog-Api-Key')[0] === 'distinctive-tenant-key-4471');
        $this->assertDatabaseHas('places_api_calls', ['business_id' => $biz->id, 'purpose' => 'tenant_own_key']);

        $this->assertSame(1, PlacesApiCall::where('purpose', 'tenant_own_key')->count());

        app(CredentialForgetAction::class)->handle((int) $biz->id, GooglePlacesClient::TENANT_SERVICE);

        try {
            $client->textSearch('tea');
        } catch (PlacesBudgetExhausted $e) {
            // expected because ceiling is 0 and no longer using own key
        }

        $this->assertSame(1, PlacesApiCall::where('purpose', 'tenant_own_key')->count());
    }

    public function test_own_key_calls_are_excluded_from_the_platform_spend_views(): void
    {
        $owner = User::factory()->create();
        $biz = self::provisionTenant(['owner_user_id' => $owner->id]);

        app(CredentialStore::class)->set(
            'google_places_key',
            'platform-key-7731',
            'user:1',
            CredentialEnvironment::Live,
        );

        Tenancy::set((int) $biz->id);

        Http::fake([
            '*' => Http::response(['places' => []], 200),
        ]);

        app(PlacesSpend::class)->record(PlacesSku::TextSearchPro, PlacesSpend::OWN_KEY_PURPOSE, businessId: (int) $biz->id);
        app(PlacesSpend::class)->record(PlacesSku::TextSearchPro, PlacesSpend::AUDIT_PURPOSE);

        $this->assertEquals(PlacesSku::TextSearchPro->centsPerThousand() / 1000, app(PlacesSpend::class)->spentTodayCents());

        $this->assertSame(1, app(PlacesSpend::class)->todayBySku()[PlacesSku::TextSearchPro->value]['calls']);
    }
}
