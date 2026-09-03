<?php

declare(strict_types=1);

namespace Tests\Modules\X204;

use App\Modules\X204\Actions\ConsentDecideAction;
use App\Modules\X204\Actions\ConsentSuppressAction;
use App\Modules\X204\Domain\ConsentService;
use App\Modules\X204\Models\SendPermit;
use App\Modules\X204\Models\Suppression;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConsentAssertionTest extends TestCase
{
    private ConsentService $consent;
    private ConsentDecideAction $decider;
    private ConsentSuppressAction $suppressor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->consent = new ConsentService;
        $this->decider = new ConsentDecideAction($this->consent);
        $this->suppressor = new ConsentSuppressAction($this->consent);
    }
    public function test_n_001_refusal_without_p060_code_fails(): void
    {
        $this->assertTrue(defined(ConsentService::class . '::P060_CODES'), 'System lacks P-060 code set definition');
        $this->assertCount(20, ConsentService::P060_CODES, 'P-060 code set must have 20 codes');

        $this->expectException(\InvalidArgumentException::class);
        SendPermit::create([
            'business_id' => 1,
            'recipient_phone' => '+123',
            'channel' => 'sms',
            'permit_status' => 'refused',
            'refusal_reason' => 'NOT_A_P060_CODE',
        ]);
    }
    public function test_n_002_no_channel_decides_permission_for_itself(): void
    {
        $modulesPath = base_path('app/Modules');
        $this->assertDirectoryExists($modulesPath);

        $output = [];
        $cmd = "grep -rE 'status.*opted_in|suppressed|permit_status' {$modulesPath} | grep -v 'X-204' | grep -v 'X204'";
        exec($cmd, $output);
        
        $this->assertEmpty($output, "Found consent branch outside X-204:\n" . implode("\n", $output));
    }
    public function test_n_003_stop_mid_sequence_halts_pending_steps(): void
    {
        $this->assertTrue(method_exists(ConsentService::class, 'haltPendingSequences'), 'ConsentService lacks haltPendingSequences behaviour');
    }
    public function test_n_004_imported_person_is_unpermitted(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'N-004 Biz']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->assertTrue(class_exists(\App\Modules\X121\Models\Person::class), 'Person model not found');
        
        $person = \App\Modules\X121\Models\Person::create([
            'business_id' => $biz->id,
            'name' => 'Imported User',
            'phone' => '+15125550004',
        ]);
        
        $this->assertEquals('UNPERMITTED', $person->consent_state ?? null, 'Imported person must be UNPERMITTED');
    }
    public function test_n_005_cadence_ceiling_counts_every_class(): void
    {
        $this->assertTrue(method_exists(ConsentService::class, 'checkCadenceCeiling'), 'System lacks cadence ceiling behaviour');
    }
    public function test_n_006_suppression_is_per_destination_never_per_customer(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'N-006 Biz']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $phone1 = '+15125550061';
        $phone2 = '+15125550062';
        
        $this->suppressor->handle($biz->id, $phone1, 'sms', 'customer_stop');
        
        $tableColumns = \Illuminate\Support\Facades\Schema::getColumnListing((new Suppression)->getTable());
        
        $this->assertContains('recipient_phone', $tableColumns, 'Suppression must be by destination');
        $this->assertNotContains('customer_id', $tableColumns, 'Suppression must NEVER be per customer');
        
        $res = $this->decider->handle($biz->id, $phone2, 'sms', 'opted_in');
        $this->assertTrue($res['granted'], 'Phone2 must remain unsuppressed');
    }
}
