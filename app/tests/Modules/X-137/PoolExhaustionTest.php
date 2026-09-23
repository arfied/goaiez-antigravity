<?php

declare(strict_types=1);

namespace Tests\Modules\X137;

use App\Modules\X137\Actions\CallAttributeAction;
use App\Modules\X137\Models\CallToken;
use App\Services\Config\DefaultsRegistry;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PoolExhaustionTest extends TestCase
{
    private CallAttributeAction $attributeAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->attributeAction = new CallAttributeAction(app(DefaultsRegistry::class));
    }

    public function test_pool_exhaustion_renders_fallback_and_is_unattributed(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Exhaustion Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        DB::table('dni_pool_numbers')->insert([
            ['business_id' => $biz->id, 'phone_number' => '+15550001001'],
            ['business_id' => $biz->id, 'phone_number' => '+15550001002'],
        ]);

        DB::table('dni_pool_settings')->insert([
            'business_id' => $biz->id,
            'fallback_number' => '+15559999999',
        ]);

        $t1 = $this->attributeAction->allocateFromPool($biz->id, 'v1', 'src');
        $t2 = $this->attributeAction->allocateFromPool($biz->id, 'v2', 'src');
        $t3 = $this->attributeAction->allocateFromPool($biz->id, 'v3', 'src');

        $this->assertEquals('active', $t1->status);
        $this->assertEquals('active', $t2->status);
        $this->assertEquals('unattributed', $t3->status);
        $this->assertEquals('+15559999999', $t3->allocated_number);
    }

    public function test_pool_exhaustion_never_reuses_token(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Exhaustion Tenant 2', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        DB::table('dni_pool_numbers')->insert([
            ['business_id' => $biz->id, 'phone_number' => '+15550001003'],
        ]);

        DB::table('dni_pool_settings')->insert([
            'business_id' => $biz->id,
            'fallback_number' => '+15559999999',
        ]);

        $t1 = $this->attributeAction->allocateFromPool($biz->id, 'v1', 'src');
        $t2 = $this->attributeAction->allocateFromPool($biz->id, 'v2', 'src');

        $this->assertNotEquals($t1->allocated_number, $t2->allocated_number);
    }

    public function test_number_returns_to_availability_when_expired_or_joined(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Exhaustion Tenant 3', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        DB::table('dni_pool_numbers')->insert([
            ['business_id' => $biz->id, 'phone_number' => '+15550001004'],
        ]);

        $t1 = $this->attributeAction->allocateFromPool($biz->id, 'v1', 'src');
        $this->assertEquals('+15550001004', $t1->allocated_number);

        // Mark as joined
        $t1->update(['status' => 'joined', 'joined_call_id' => 999]);

        $t2 = $this->attributeAction->allocateFromPool($biz->id, 'v2', 'src');
        $this->assertEquals('+15550001004', $t2->allocated_number);
        $this->assertEquals('active', $t2->status);
    }

    public function test_offline_campaign_number_cannot_be_assigned_to_second_live_campaign(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Offline Campaign Refusal', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->attributeAction->allocateStaticToken($biz->id, '+15554440000', 'print_ad');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('NUMBER_ALREADY_ASSIGNED_TO_DIFFERENT_CAMPAIGN');
        $this->attributeAction->allocateStaticToken($biz->id, '+15554440000', 'radio_spot');
    }

    public function test_cannot_insert_duplicate_pool_number(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Dup Number Refusal', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        DB::table('dni_pool_numbers')->insert([
            'business_id' => $biz->id,
            'phone_number' => '+15559990001',
        ]);

        $this->expectException(QueryException::class);
        DB::table('dni_pool_numbers')->insert([
            'business_id' => $biz->id,
            'phone_number' => '+15559990001',
        ]);
    }

    public function test_cannot_insert_duplicate_pool_settings(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Dup Settings Refusal', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        DB::table('dni_pool_settings')->insert([
            'business_id' => $biz->id,
            'fallback_number' => '+15558880000',
        ]);

        $this->expectException(QueryException::class);
        DB::table('dni_pool_settings')->insert([
            'business_id' => $biz->id,
            'fallback_number' => '+15558880001',
        ]);
    }

    public function test_a_blank_fallback_number_is_refused_as_not_configured_for_dni(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Blank Fallback Refusal', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        DB::table('dni_pool_numbers')->insert([
            ['business_id' => $biz->id, 'phone_number' => '+15550001005'],
        ]);

        DB::table('dni_pool_settings')->insert([
            'business_id' => $biz->id,
            'fallback_number' => '   ',
        ]);

        $this->attributeAction->allocateFromPool($biz->id, 'v1', 'src');

        $tokensBefore = CallToken::where('business_id', $biz->id)->count();

        try {
            $this->attributeAction->allocateFromPool($biz->id, 'v2', 'src');
            $this->fail('Expected exception was not thrown');
        } catch (\DomainException $e) {
            $this->assertEquals('BUSINESS_NOT_CONFIGURED_FOR_DNI', $e->getMessage());
            $this->assertEquals($tokensBefore, CallToken::where('business_id', $biz->id)->count());
        }
    }

    public function test_a_blank_pool_number_is_never_allocated(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Blank Pool Member', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        DB::table('dni_pool_numbers')->insert([
            ['business_id' => $biz->id, 'phone_number' => '   '],
            ['business_id' => $biz->id, 'phone_number' => '+15550001006'],
        ]);

        $t1 = $this->attributeAction->allocateFromPool($biz->id, 'v1', 'src');

        $this->assertEquals('+15550001006', $t1->allocated_number);
        $this->assertEquals('active', $t1->status);
    }

    public function test_a_pool_of_only_blank_numbers_falls_through_to_the_static_fallback(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Only Blank Pool', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        DB::table('dni_pool_numbers')->insert([
            ['business_id' => $biz->id, 'phone_number' => '   '],
        ]);

        DB::table('dni_pool_settings')->insert([
            'business_id' => $biz->id,
            'fallback_number' => '+15559999999',
        ]);

        $t1 = $this->attributeAction->allocateFromPool($biz->id, 'v1', 'src');

        $this->assertEquals('+15559999999', $t1->allocated_number);
        $this->assertEquals('unattributed', $t1->status);
    }

    public function test_a_blank_visitor_session_token_is_refused_before_a_pool_number_is_touched(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Blank Token Refusal', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        DB::table('dni_pool_numbers')->insert([
            ['business_id' => $biz->id, 'phone_number' => '+15550001007'],
            ['business_id' => $biz->id, 'phone_number' => '+15550001008'],
        ]);

        $tokensBefore = CallToken::where('business_id', $biz->id)->count();

        try {
            $this->attributeAction->allocateFromPool($biz->id, '   ', 'src');
            $this->fail('Expected exception was not thrown');
        } catch (\DomainException $e) {
            $this->assertEquals('VISITOR_SESSION_TOKEN_REQUIRED', $e->getMessage());
            $this->assertEquals($tokensBefore, CallToken::where('business_id', $biz->id)->count());
        }
    }
}
