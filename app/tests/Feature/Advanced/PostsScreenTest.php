<?php

declare(strict_types=1);

namespace Tests\Feature\Advanced;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class PostsScreenTest extends TestCase
{
    use RefreshesTenantDatabase;

    protected function createTenant(bool $advanced = false): array
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant([
            'owner_user_id' => $user->id,
            'name' => 'Acme Dental '.rand(100, 999),
        ]);

        if ($advanced) {
            $business->update(['advanced_dashboard_enabled' => true]);
        }

        Tenancy::setUser((int) $user->id);
        Tenancy::set((int) $business->id);

        return [$user, $business];
    }

    public function test_get_and_shell_mark(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        $response = $this->actingAs($user)->get(route('advanced.posts'));
        $response->assertOk();
        $response->assertSee('Skip to content');
    }

    public function test_empty_state_renders_correctly(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        $response = $this->actingAs($user)->get(route('advanced.posts'));
        $response->assertSee('No pages published yet.');
    }

    public function test_one_published_page_of_this_tenant_renders_and_another_tenants_does_not(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        $location = $business->locations()->first();

        DB::table('growth_pages')->insert([
            'location_id' => $location->id,
            'business_id' => $business->id,
            'type' => 'post',
            'slug' => 'distinctive-page-7719',
            'title' => 'Distinctive Page 7719',
            'meta_description' => '',
            'content' => '',
            'target_keyword' => '',
            'status' => 'published',
            'hold_until' => null,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = TestCase::provisionTenant([
            'owner_user_id' => $userB->id,
            'name' => 'Acme Dental '.rand(100, 999),
        ]);

        Tenancy::setUser((int) $userB->id);
        Tenancy::set((int) $bizB->id);

        DB::table('growth_pages')->insert([
            'location_id' => $bizB->locations()->first()->id,
            'business_id' => $bizB->id,
            'type' => 'post',
            'slug' => 'distinctive-page-7720',
            'title' => 'Distinctive Page 7720',
            'meta_description' => '',
            'content' => '',
            'target_keyword' => '',
            'status' => 'published',
            'hold_until' => null,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Tenancy::setUser((int) $user->id);
        Tenancy::set((int) $business->id);

        $response = $this->actingAs($user)->get(route('advanced.posts'));
        $response->assertSee('Distinctive Page 7719');
        $response->assertDontSee('Distinctive Page 7720');
        $response->assertDontSee('No pages published yet.');
    }

    public function test_the_gbp_line_is_honest(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        $response = $this->actingAs($user)->get(route('advanced.posts'));
        $response->assertSee('waits on the GBP grant');
    }
}
