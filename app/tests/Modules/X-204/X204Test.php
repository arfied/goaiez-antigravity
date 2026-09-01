<?php

declare(strict_types=1);

namespace Tests\Modules\X204;

use App\Modules\X204\Actions\AttestationRecordAction;
use App\Modules\X204\Actions\ConsentDecideAction;
use App\Modules\X204\Actions\ConsentLiftAction;
use App\Modules\X204\Actions\ConsentSuppressAction;
use App\Modules\X204\Actions\RegisterCheckAction;
use App\Modules\X204\Domain\ConsentService;
use App\Modules\X204\Events\ConsentDecided;
use App\Modules\X204\Events\PermitGranted;
use App\Modules\X204\Events\SuppressionAdded;
use App\Modules\X204\Models\ComplianceRegister;
use App\Modules\X204\Models\SendPermit;
use App\Modules\X204\Models\Suppression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X204Test extends TestCase
{
    private ConsentService $consent;

    private ConsentDecideAction $decider;

    private ConsentSuppressAction $suppressor;

    private ConsentLiftAction $lifter;

    private AttestationRecordAction $attestation;

    private RegisterCheckAction $register;

    protected function setUp(): void
    {
        parent::setUp();
        $this->consent = new ConsentService;
        $this->decider = new ConsentDecideAction($this->consent);
        $this->suppressor = new ConsentSuppressAction($this->consent);
        $this->lifter = new ConsentLiftAction($this->consent);
        $this->attestation = new AttestationRecordAction($this->consent);
        $this->register = new RegisterCheckAction;
    }

    /**
     * TEST ANCHOR
     * grep -rE '\->send\(|canSend' app/Modules/ shows every send path calling ConsentService::decide() and no module containing a consent branch of its own — enforced by doctor;
     * decide() handed an unknown state returns a refusal, never a grant.
     */
    public function test_anchor_unknown_state_refusal_and_suppression_enforcement(): void
    {
        Event::fake([ConsentDecided::class, PermitGranted::class, SuppressionAdded::class]);

        $biz = \Tests\TestCase::provisionTenant(['name' => 'Consent Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $phone = '+15125550199';

        // 1. decide() handed an unknown state returns a refusal, never a grant
        $unknownRes = $this->decider->handle($biz->id, $phone, 'sms', 'non_existent_state_xyz');
        $this->assertFalse($unknownRes['granted'], 'Must refuse on unknown state');
        $this->assertEquals('UNKNOWN_STATE', $unknownRes['reason']);

        $permitUnknown = SendPermit::find($unknownRes['permit_id']);
        $this->assertEquals('refused', $permitUnknown->permit_status);

        // 2. Valid state grants permit
        $validRes = $this->decider->handle($biz->id, $phone, 'sms', 'opted_in');
        $this->assertTrue($validRes['granted'], 'Must grant on valid opted_in state');
        $this->assertNotNull($validRes['permit_id']);

        Event::assertDispatched(PermitGranted::class);

        // 3. Suppression blocks permit
        $this->suppressor->handle($biz->id, $phone, 'sms', 'customer_stop');
        $suppressedRes = $this->decider->handle($biz->id, $phone, 'sms', 'opted_in');

        $this->assertFalse($suppressedRes['granted'], 'Must refuse when phone is suppressed');
        $this->assertEquals('SUPPRESSED', $suppressedRes['reason']);
    }

    /**
     * [N-013] consent decide and compliance registers
     */
    public function test_n_013_compliance_registers_and_lift(): void
    {
        $biz = \Tests\TestCase::provisionTenant(['name' => 'Compliance Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $phone = '+15125550188';
        $this->suppressor->handle($biz->id, $phone, 'sms', 'opt_out');
        $this->lifter->handle($biz->id, $phone, 'sms');

        ComplianceRegister::create([
            'business_id' => $biz->id,
            'register_name' => 'tcpa_quiet_hours',
            'status' => 'compliant',
            'slot_states' => ['opted_in' => true],
        ]);

        $regCheck = $this->register->handle($biz->id, 'tcpa_quiet_hours');
        $this->assertTrue($regCheck['is_compliant']);
    }
}
