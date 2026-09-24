<?php

declare(strict_types=1);

namespace Tests\Feature\Places;

use App\Enums\CredentialEnvironment;
use App\Models\User;
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
}
