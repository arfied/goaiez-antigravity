<?php

namespace Tests\Feature\Console;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X155\Models\FormDefinition;
use App\Support\Tenancy;
use Tests\TestCase;

class DemoFillCommandTest extends TestCase
{
    public function test_fill_then_purge_is_idempotent_and_marked(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        $this->artisan('demo:fill', ['email' => $owner->email])->assertExitCode(0);

        Tenancy::set($biz->id); // Restore tenant for test assertions

        $this->assertDatabaseHas('form_definitions', ['business_id' => $biz->id, 'form_name' => 'demo·Form 1']);
        $this->assertDatabaseHas('price_book_items', ['business_id' => $biz->id, 'service_name' => 'demo·Item 0']);
        $this->assertDatabaseHas('estimates', ['business_id' => $biz->id, 'estimate_number' => 'demo·EST0']);
        $this->assertDatabaseHas('membership_plans', ['business_id' => $biz->id, 'name' => 'demo·Plan A']);
        $this->assertDatabaseHas('job_costs', ['business_id' => $biz->id, 'service_type' => 'demo·Svc1']);
        $this->assertDatabaseHas('stock_items', ['business_id' => $biz->id, 'sku' => 'demo·Item0']);
        $this->assertDatabaseHas('timesheets', ['business_id' => $biz->id, 'status' => 'demo·open']);
        $this->assertDatabaseHas('commissions', ['business_id' => $biz->id, 'status' => 'demo·pending_cash_collection']);
        $this->assertDatabaseHas('field_suggestions', ['business_id' => $biz->id, 'query_text' => 'demo·Sugg 0']);
        $this->assertDatabaseHas('rates', ['business_id' => $biz->id, 'rate_code' => 'demo·RATE_0']);
        $this->assertDatabaseHas('call_tokens', ['business_id' => $biz->id, 'visitor_session_token' => 'demo·Tok0']);
        $this->assertDatabaseHas('number_pool', ['business_id' => $biz->id, 'phone_number' => 'demo·+15125551881']);

        $c1 = FormDefinition::count();
        $this->artisan('demo:fill', ['email' => $owner->email])->assertExitCode(0);

        Tenancy::set($biz->id); // Restore tenant
        $this->assertEquals($c1, FormDefinition::count());

        $unmarked = FormDefinition::create(['business_id' => $biz->id, 'form_name' => 'Unmarked Form', 'slug' => 'unmarked', 'steps' => [], 'schema' => []]);

        $this->artisan('demo:fill', ['email' => $owner->email, '--purge' => true])->assertExitCode(0);

        Tenancy::set($biz->id); // Restore tenant

        $this->assertDatabaseHas('form_definitions', ['id' => $unmarked->id]);
        $this->assertDatabaseMissing('form_definitions', ['form_name' => 'demo·Form 1']);
    }

    public function test_the_converted_screens_show_the_demo_rows(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        $this->artisan('demo:fill', ['email' => $owner->email])->assertExitCode(0);

        Tenancy::set($biz->id); // Restore tenant for test assertions
        $this->actingAs($owner);

        $this->get(route('x-155.forms'))->assertOk()->assertSee('demo·');
        $this->get(route('x-155.submissions-thread'))->assertOk()->assertSee('demo·');
        $this->get(route('x-163.pricebook'))->assertOk()->assertSee('demo·');
        $this->get(route('x-165.plans'))->assertOk()->assertSee('demo·');
        $this->get(route('x-166.by-service'))->assertOk()->assertSee('demo·');
        $this->get(route('x-167.stock-by-van'))->assertOk()->assertSee('demo·');
        $this->get(route('x-168.timesheets'))->assertOk()->assertSee('demo·');
        $this->get(route('x-170.commissions'))->assertOk()->assertSee('demo·');
        $this->get(route('x-188.pool-inventory'))->assertOk()->assertSee('demo·');
    }
}
