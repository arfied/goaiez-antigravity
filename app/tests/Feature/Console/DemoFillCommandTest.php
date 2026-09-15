<?php

namespace Tests\Feature\Console;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X155\Models\FormDefinition;
use App\Support\Tenancy;
use Tests\TestCase;

class DemoFillCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Tenancy::forgetAll();
        parent::tearDown();
    }

    public function test_fill_then_purge_is_idempotent_and_marked(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        $this->artisan('demo:fill', ['email' => $owner->email])->assertExitCode(0);

        Tenancy::set($biz->id); // Restore tenant for test assertions

        $this->assertDatabaseHas('form_definitions', ['business_id' => $biz->id, 'form_name' => 'demo·Form 1']);
        $this->assertDatabaseHas('price_book_items', ['business_id' => $biz->id, 'service_name' => 'Item 0', 'is_sample' => true]);
        $this->assertDatabaseHas('estimates', ['business_id' => $biz->id, 'estimate_number' => 'demo·EST0']);
        $this->assertDatabaseHas('membership_plans', ['business_id' => $biz->id, 'name' => 'Plan A', 'is_sample' => true]);
        $this->assertDatabaseHas('job_costs', ['business_id' => $biz->id, 'service_type' => 'Svc1', 'is_sample' => true]);
        $this->assertDatabaseHas('stock_items', ['business_id' => $biz->id, 'sku' => 'Item0', 'is_sample' => true]);
        $this->assertDatabaseHas('timesheets', ['business_id' => $biz->id, 'status' => 'open', 'is_sample' => true]);
        $this->assertDatabaseHas('commissions', ['business_id' => $biz->id, 'status' => 'pending_cash_collection', 'clawback_reason' => 'demo·filler']);
        $this->assertDatabaseHas('field_suggestions', ['business_id' => $biz->id, 'query_text' => 'Sugg 0', 'is_sample' => true]);
        $this->assertDatabaseHas('rates', ['business_id' => $biz->id, 'rate_code' => 'RATE_0', 'is_sample' => true]);
        $this->assertDatabaseHas('call_tokens', ['business_id' => $biz->id, 'whisper_text' => 'demo·Call from website direct']);
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
        $this->get(route('x-163.pricebook'))->assertOk()->assertSee('Sample');
        $this->get(route('x-165.plans'))->assertOk()->assertSee('Sample');
                $this->get(route('x-167.stock-by-van'))->assertOk()->assertSee('Sample');
        $this->get(route('x-168.timesheets'))->assertOk()->assertSee('Sample');
                }
}
