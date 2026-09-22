<?php

declare(strict_types=1);

namespace Tests\Modules\X162;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X162\Actions\RouteOptimiseAction;
use App\Modules\X162\Events\RouteChanged;
use App\Modules\X162\Models\DispatchAssignment;
use App\Modules\X162\Ui\Map;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class MapTest extends TestCase
{
    public function test_guest_is_forbidden(): void
    {
        Livewire::test(Map::class)->assertForbidden();
    }

    public function test_staff_is_forbidden(): void
    {
        $staff = User::factory()->role(UserRole::Staff)->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => User::factory()->create()->id]);
        Tenancy::setUser($staff->id);

        Livewire::actingAs($staff)
            ->test(Map::class)
            ->assertForbidden();
    }

    public function test_owner_with_nothing_sees_empty_sentence(): void
    {
        $owner = User::factory()->role(UserRole::Owner)->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)
            ->test(Map::class)
            ->assertOk()
            ->assertSee('No routes yet.')
            // The needle is escaped deliberately to match the HTML entity in the blade
            ->assertSee("No routes yet. A route appears when a technician's stops are ordered.");
    }

    public function test_seeded_routes_and_assignments_render_correctly(): void
    {
        Event::fake([RouteChanged::class]);

        $owner = User::factory()->role(UserRole::Owner)->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $firstId = DB::table('work_orders')->insertGetId([
            'business_id' => $biz->id,
            'title' => 'First stop',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $secondId = DB::table('work_orders')->insertGetId([
            'business_id' => $biz->id,
            'title' => 'Second stop',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(RouteOptimiseAction::class)->handle($biz->id, 7, [$secondId, $firstId], 3.2);

        DispatchAssignment::create([
            'business_id' => $biz->id,
            'job_id' => $secondId,
            'tech_id' => 7,
            'status' => 'en_route',
            'is_sample' => true,
        ]);

        Livewire::actingAs($owner)
            ->test(Map::class)
            ->assertSeeInOrder(['Second stop', 'First stop'])
            ->assertSee('3.2 km')
            ->assertSee('Technician 7')
            ->assertSee('En route')
            ->assertSee('<span>Sample</span>', false)
            ->assertSee('1 technician route');
    }

    public function test_unassigned_visible_when_assignment_absent(): void
    {
        Event::fake([RouteChanged::class]);

        $owner = User::factory()->role(UserRole::Owner)->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $firstId = DB::table('work_orders')->insertGetId([
            'business_id' => $biz->id,
            'title' => 'First stop',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $secondId = DB::table('work_orders')->insertGetId([
            'business_id' => $biz->id,
            'title' => 'Second stop',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(RouteOptimiseAction::class)->handle($biz->id, 7, [$secondId, $firstId], 3.2);

        Livewire::actingAs($owner)
            ->test(Map::class)
            ->assertSeeInOrder(['Second stop', 'First stop'])
            ->assertSee('Unassigned');
    }

    public function test_seeded_row_reaches_the_page(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $firstId = DB::table('work_orders')->insertGetId([
            'business_id' => $biz->id,
            'title' => 'Map Seam Stop',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(RouteOptimiseAction::class)->handle($biz->id, 7, [$firstId], 257.75);

        $this->actingAs($owner);
        $this->get(route('x-162.map'))
            ->assertOk()
            ->assertSee('257.8 km')
            ->assertDontSee('257.75');
    }
}
