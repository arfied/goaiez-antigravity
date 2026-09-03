<?php

declare(strict_types=1);

namespace Tests\Modules\X204;

use App\Modules\X121\Models\Person;
use App\Modules\X204\Actions\ConsentDecideAction;
use App\Modules\X204\Actions\ConsentSuppressAction;
use App\Modules\X204\Domain\ConsentService;
use App\Modules\X204\Models\SendPermit;
use App\Modules\X204\Models\Suppression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        // N-001
        $this->assertTrue(defined(ConsentService::class.'::P060_CODES'), 'System lacks P-060 code set definition');
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
        // N-002
        $modulesPath = base_path('app/Modules');
        $this->assertDirectoryExists($modulesPath);

        // (a) every file under app/Modules that calls ->send( or canSend also references ConsentService::decide or ConsentDecideAction
        $cmdA = "grep -rlE '\->send\(|canSend' {$modulesPath}";
        exec($cmdA, $filesA);
        $violatorsA = [];
        foreach ($filesA as $file) {
            $content = file_get_contents($file);
            if (strpos($content, 'ConsentService::decide') === false && strpos($content, 'ConsentDecideAction') === false) {
                $violatorsA[] = $file;
            }
        }
        if (!empty($violatorsA)) {
            echo "N-002 Violators (a):\n" . implode("\n", $violatorsA) . "\n";
        }

        // (b) no file outside X-204 compares consent_state or opted_in in code (exclude strings/comments/capabilities.php/migrations)
        $cmdB = "grep -rnE 'consent_state|opted_in' {$modulesPath} | grep -v 'X-204' | grep -v 'X204' | grep -v 'capabilities.php' | grep -v 'Migrations'";
        exec($cmdB, $outputB);
        $violatorsB = [];
        foreach ($outputB as $line) {
            // match comparisons
            if (preg_match('/(?:===?|!==?|[<>]=?)\s*[\'"]?(?:consent_state|opted_in)[\'"]?|[\'"]?(?:consent_state|opted_in)[\'"]?\s*(?:===?|!==?|[<>]=?)/', $line) ||
                preg_match('/->(?:consent_state|opted_in)\s*(?:===?|!==?|[<>]=?)/', $line)) {
                $violatorsB[] = $line;
            }
        }
        if (!empty($violatorsB)) {
            echo "N-002 Violators (b):\n" . implode("\n", $violatorsB) . "\n";
        }

        $allViolators = array_merge($violatorsA, $violatorsB);
        $this->assertEmpty($allViolators, "Found N-002 violators.");
    }

    public function test_n_003_stop_mid_sequence_halts_pending_steps(): void
    {
        // N-003
        $biz = TestCase::provisionTenant(['name' => 'N-003 Biz']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $person = Person::create([
            'business_id' => $biz->id,
            'first_name' => 'Stop User',
            'phone' => '+15125550005',
        ]);

        DB::table('campaign_steps')->insert([
            ['business_id' => $biz->id, 'campaign_id' => 'camp1', 'step_number' => 1, 'channel' => 'sms', 'template_name' => 't1', 'person_id' => $person->id, 'recipient' => $person->phone, 'cancelled_at' => null, 'sent_at' => null],
            ['business_id' => $biz->id, 'campaign_id' => 'camp1', 'step_number' => 2, 'channel' => 'sms', 'template_name' => 't2', 'person_id' => $person->id, 'recipient' => $person->phone, 'cancelled_at' => null, 'sent_at' => null],
        ]);

        $this->suppressor->handle($biz->id, $person->phone, 'sms', 'customer_stop');

        $pendingCount = DB::table('campaign_steps')
            ->where('person_id', $person->id)
            ->whereNull('cancelled_at')
            ->whereNull('sent_at')
            ->count();

        $this->assertEquals(0, $pendingCount, 'Pending steps must be halted when STOP is delivered');
    }

    public function test_n_004_imported_person_is_unpermitted(): void
    {
        // N-004
        $biz = TestCase::provisionTenant(['name' => 'N-004 Biz']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->assertTrue(class_exists(Person::class), 'Person model not found');

        $person = Person::create([
            'business_id' => $biz->id,
            'first_name' => 'Imported User',
            'phone' => '+15125550004',
        ]);

        $this->assertEquals('UNPERMITTED', $person->consent_state ?? null, 'Imported person must be UNPERMITTED');
    }

    public function test_n_005_cadence_ceiling_counts_every_class(): void
    {
        // N-005
        $biz = TestCase::provisionTenant(['name' => 'N-005 Biz']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $phone = '+15125550006';

        $this->decider->handle($biz->id, $phone, 'sms', 'opted_in');
        $this->decider->handle($biz->id, $phone, 'sms', 'transactional');

        $this->assertTrue(method_exists($this->consent, 'getSendCountInWindow'), 'ConsentService lacks getSendCountInWindow behaviour');
        $count = $this->consent->getSendCountInWindow($biz->id, $phone, 'sms', 24);

        $this->assertEquals(2, $count, 'Cadence ceiling must count every class');
    }

    public function test_n_006_suppression_is_per_destination_never_per_customer(): void
    {
        // N-006
        $biz = TestCase::provisionTenant(['name' => 'N-006 Biz']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $phone1 = '+15125550061';
        $phone2 = '+15125550062';

        $this->suppressor->handle($biz->id, $phone1, 'sms', 'customer_stop');

        $tableColumns = Schema::getColumnListing((new Suppression)->getTable());

        $this->assertContains('recipient_phone', $tableColumns, 'Suppression must be by destination');
        $this->assertNotContains('customer_id', $tableColumns, 'Suppression must NEVER be per customer');

        $res = $this->decider->handle($biz->id, $phone2, 'sms', 'opted_in');
        $this->assertTrue($res['granted'], 'Phone2 must remain unsuppressed');
    }
}
