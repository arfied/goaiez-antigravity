<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
use App\Support\Account\OwnerNav;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class AllScreensTest extends TestCase
{
    use RefreshesTenantDatabase;

    public function test_all_screens_renders_every_catalog_entry(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $business = Business::provision([
            'owner_user_id' => $user->id,
            'name' => 'Acme Dental '.rand(100, 999),
        ]);

        $response = $this->actingAs($user)->get('/account/all-screens');

        $response->assertOk();
        $response->assertSee('All screens');

        $catalog = OwnerNav::catalog();

        $this->assertNotEmpty($catalog);

        $content = $response->getContent();

        $foundCount = 0;
        foreach ($catalog as $item) {
            $routeUrl = route($item->route);
            if (str_contains($content, 'href="'.$routeUrl.'"')) {
                $foundCount++;
            }
        }

        $catalogCount = count($catalog);
        $this->assertEquals($catalogCount, $foundCount);
        $this->assertGreaterThan(0, $catalogCount);
    }
}
