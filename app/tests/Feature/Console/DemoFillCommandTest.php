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

        Tenancy::forgetAll(); // explicitly clear tenant to test from a bare console context
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
        $this->assertDatabaseHas('dispatch_assignments', ['business_id' => $biz->id, 'job_id' => 100, 'is_sample' => true]);
        $this->assertDatabaseHas('review_requests', ['business_id' => $biz->id, 'rating' => 2, 'review_text' => 'demo·Tech arrived late and tracking was off']);
        $this->assertDatabaseHas('review_replies', ['business_id' => $biz->id, 'status' => 'draft']);
        $this->assertDatabaseHas('qa_tickets', ['business_id' => $biz->id, 'subject' => 'demo·Invoice higher than the estimate', 'status' => 'open']);
        $this->assertDatabaseHas('csat_answers', ['business_id' => $biz->id, 'score' => 4]);
        $this->assertDatabaseHas('alerts', ['business_id' => $biz->id, 'title' => 'demo·Angry customer on the line']);
        $this->assertDatabaseHas('reply_codes', ['business_id' => $biz->id, 'code' => 'A7K2Q']);
        $c1 = FormDefinition::count();
        Tenancy::forgetAll(); // explicitly clear tenant to test from a bare console context
        $this->artisan('demo:fill', ['email' => $owner->email])->assertExitCode(0);

        Tenancy::set($biz->id); // Restore tenant
        $this->assertEquals($c1, FormDefinition::count());

        $unmarked = FormDefinition::create(['business_id' => $biz->id, 'form_name' => 'Unmarked Form', 'slug' => 'unmarked', 'steps' => [], 'schema' => []]);

        Tenancy::forgetAll(); // explicitly clear tenant to test from a bare console context
        $this->artisan('demo:fill', ['email' => $owner->email, '--purge' => true])->assertExitCode(0);

        Tenancy::set($biz->id); // Restore tenant

        $this->assertDatabaseHas('form_definitions', ['id' => $unmarked->id]);
        $this->assertDatabaseMissing('form_definitions', ['form_name' => 'demo·Form 1']);
        $this->assertDatabaseMissing('dispatch_assignments', ['business_id' => $biz->id, 'job_id' => 100]);
        $this->assertDatabaseMissing('review_requests', ['business_id' => $biz->id, 'rating' => 2, 'review_text' => 'demo·Tech arrived late and tracking was off']);
        $this->assertDatabaseMissing('review_replies', ['business_id' => $biz->id, 'status' => 'draft']);
        $this->assertDatabaseMissing('qa_tickets', ['business_id' => $biz->id, 'subject' => 'demo·Invoice higher than the estimate', 'status' => 'open']);
        $this->assertDatabaseMissing('csat_answers', ['business_id' => $biz->id, 'score' => 4]);
        $this->assertDatabaseMissing('alerts', ['business_id' => $biz->id, 'title' => 'demo·Angry customer on the line']);
        $this->assertDatabaseMissing('reply_codes', ['business_id' => $biz->id, 'code' => 'A7K2Q']);
        $this->assertDatabaseMissing('people', ['business_id' => $biz->id, 'first_name' => 'demo·Dana']);
    }

    public function test_the_converted_screens_show_the_demo_rows(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::forgetAll(); // explicitly clear tenant to test from a bare console context
        $this->artisan('demo:fill', ['email' => $owner->email])->assertExitCode(0);

        Tenancy::set($biz->id); // Restore tenant for test assertions
        $this->actingAs($owner);

        $this->get(route('x-155.forms'))->assertOk()->assertSee('demo·');
        $this->get(route('x-155.submissions-thread'))->assertOk()->assertSee('demo·');
        $this->get(route('x-163.pricebook'))->assertOk()->assertSee('Item 0');
        $this->get(route('x-165.plans'))->assertOk()->assertSee('Plan A');
        $this->get(route('x-166.margin-by-job'))->assertOk()->assertSee('80.00');
        $this->get(route('x-167.stock-by-van'))->assertOk()->assertSee('demo·Van 1');
        $this->get(route('x-168.timesheets'))->assertOk()->assertSee(now()->startOfWeek()->format('Y-m-d'));
        $this->get(route('x-170.commissions'))->assertOk()->assertSee('pending_cash_collection');
        $this->get(route('x-162.dispatch-board'))->assertOk()->assertSee('Job #100');
        $this->get(route('x-181.qa-queue-sladueat'))->assertOk()->assertSee('demo·Invoice higher than the estimate');
        $this->get(route('x-153.alert-roster-screen'))->assertOk()->assertSee('demo·Angry customer on the line');
        $this->get(route('x-153.alert-reply-by'))->assertOk()->assertSee('A7K2Q');
        $this->get(route('c-reviews.loss-alerts'))->assertOk()->assertSee('SLA breached');
        // x-188 has no demo rows: number_pool carries neither a sample flag nor free text (PB-205b).
    }
}
