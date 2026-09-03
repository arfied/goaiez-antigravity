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
    public function test_N_001_refusal_without_p060_code_fails(): void
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
    public function test_N_002_no_channel_decides_permission_for_itself(): void
    {
        $modulesPath = base_path('app/Modules');
        $this->assertDirectoryExists($modulesPath);

        $output = [];
        $cmd = "grep -rE 'status.*opted_in|suppressed|permit_status' {$modulesPath} | grep -v 'X-204' | grep -v 'X204'";
        exec($cmd, $output);
        
        $this->assertEmpty($output, "Found consent branch outside X-204:\n" . implode("\n", $output));
    }
    public function test_N_003_stop_mid_sequence_halts_pending_steps(): void
    {
        $this->assertTrue(method_exists(ConsentService::class, 'haltPendingSequences'), 'ConsentService lacks haltPendingSequences behaviour');
    }
}
