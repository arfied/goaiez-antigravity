<?php

declare(strict_types=1);

namespace Tests\Modules\X113;

use App\Modules\X113\Actions\RoleAssignAction;
use App\Modules\X113\Actions\SecureFieldRevealAction;
use App\Modules\X113\Actions\StaffAuthenticateCheckAction;
use App\Modules\X113\Actions\StaffDeactivateAction;
use App\Modules\X113\Actions\StaffInviteAction;
use App\Modules\X113\Domain\StaffEngine;
use App\Modules\X113\Events\RoleAssigned;
use App\Modules\X113\Events\StaffDeactivated;
use App\Modules\X113\Models\Role;
use App\Modules\X113\Models\RolePermission;
use App\Modules\X113\Models\StaffUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class X113Test extends TestCase
{
    private StaffInviteAction $inviteAction;

    private StaffDeactivateAction $deactivateAction;

    private RoleAssignAction $roleAction;

    private StaffAuthenticateCheckAction $authCheckAction;

    private SecureFieldRevealAction $revealAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inviteAction = new StaffInviteAction;
        $this->deactivateAction = new StaffDeactivateAction;
        $this->roleAction = new RoleAssignAction;
        $this->authCheckAction = new StaffAuthenticateCheckAction;
        $this->revealAction = new SecureFieldRevealAction;
    }

    /**
     * TEST ANCHOR
     * a staff user deactivated from the owner's seat is REFUSED ON THEIR VERY NEXT REQUEST, not at session expiry ·
     * grep -r 'rank' app/Modules/X-113/ finds no ranking field (§150.4) ·
     * a secure field reveal is role- AND job-scoped, and logged (P-198).
     * [G11-04]
     * [G4-15]
     * [G4-35]
     */
    public function test_anchor_immediate_deactivation_no_ranking_fields_and_scoped_secure_reveal(): void
    {
        Event::fake([StaffDeactivated::class, RoleAssigned::class]);

        $biz = TestCase::provisionTenant(['name' => 'Staff Security Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Staff creation & role assignment
        $role = Role::create([
            'business_id' => $biz->id,
            'name' => 'Senior Technician',
            'description' => 'Field tech with customer door code access',
        ]);

        RolePermission::create([
            'business_id' => $biz->id,
            'role_id' => $role->id,
            'permission' => 'view_gate_access_code',
        ]);

        $staff = $this->inviteAction->handle(
            businessId: $biz->id,
            email: 'tech1@example.com',
            name: 'Bob Builder',
            roleId: $role->id
        );

        $this->assertTrue($staff->is_active);

        // 2. Immediate deactivation: REFUSED ON THEIR VERY NEXT REQUEST, not at session expiry (TEST ANCHOR)
        $this->deactivateAction->handle($biz->id, $staff->id);
        Event::assertDispatched(StaffDeactivated::class);

        $nextRequestAuth = $this->authCheckAction->authorizeRequest($biz->id, $staff->id);
        $this->assertFalse($nextRequestAuth['authorized']);
        $this->assertEquals('refused', $nextRequestAuth['status']);
        $this->assertEquals('STAFF_USER_DEACTIVATED', $nextRequestAuth['refusal_code']);

        // 3. Check that staff_users has 0 ranking fields in database schema (§150.4, TEST ANCHOR)
        $columns = Schema::getColumnListing('staff_users');
        $rankingCols = array_filter($columns, fn ($col) => str_contains(strtolower($col), 'rank'));
        $this->assertEmpty($rankingCols, 'staff_users must have 0 ranking columns (§150.4)');

        // 4. Secure field reveal: role- AND job-scoped, and logged (TEST ANCHOR, P-198)
        $activeStaff = $this->inviteAction->handle(
            businessId: $biz->id,
            email: 'tech2@example.com',
            name: 'Alice Dispatch',
            roleId: $role->id
        );

        // Out-of-job-scope attempt -> REFUSED
        $outOfScopeRes = $this->revealAction->revealField(
            businessId: $biz->id,
            staffUserId: $activeStaff->id,
            targetJobId: 105, // Trying to view job #105
            assignedJobId: 101, // But assigned only to job #101
            requiredPermission: 'view_gate_access_code',
            secretFieldValue: 'GATE-CODE-4491'
        );
        $this->assertEquals('refused', $outOfScopeRes['status']);
        $this->assertEquals('OUT_OF_JOB_SCOPE', $outOfScopeRes['refusal_code']);

        // In-scope reveal -> REVEALED + AUDIT LOGGED
        $inScopeRes = $this->revealAction->revealField(
            businessId: $biz->id,
            staffUserId: $activeStaff->id,
            targetJobId: 101,
            assignedJobId: 101,
            requiredPermission: 'view_gate_access_code',
            secretFieldValue: 'GATE-CODE-4491'
        );
        $this->assertEquals('revealed', $inScopeRes['status']);
        $this->assertEquals('GATE-CODE-4491', $inScopeRes['value']);
        $this->assertTrue($inScopeRes['audit_logged']);
    }

    /**
     * [G7-29] [G15-02]
     * The scorecard is POSITIVE ONLY (T677).
     * No ranking of people.
     */
    public function test_g7_29_g15_02_positive_only_scorecard_and_no_ranking_surface(): void
    {
        $this->assertTrue(Schema::hasColumn('staff_users', 'coaching_notes'), 'staff_users must have coaching_notes for positive feedback');

        foreach (['score', 'rating', 'ranking', 'grade', 'points', 'percentile', 'stack_rank', 'performance_score'] as $col) {
            $this->assertFalse(Schema::hasColumn('staff_users', $col), "staff_users must not have $col");
        }

        $classes = [
            StaffEngine::class,
            StaffInviteAction::class,
            StaffDeactivateAction::class,
            RoleAssignAction::class,
            StaffAuthenticateCheckAction::class,
            SecureFieldRevealAction::class,
        ];

        foreach ($classes as $class) {
            $reflection = new \ReflectionClass($class);
            foreach ($reflection->getMethods() as $method) {
                $name = strtolower($method->getName());
                $this->assertFalse(
                    str_starts_with($name, 'score') || str_starts_with($name, 'rate') || str_starts_with($name, 'rank'),
                    "$class must not have scoring method $name"
                );
            }
        }
    }

    /**
     * [G9-36]
     * Hiring is not the platform.
     */
    public function test_g9_36_hiring_is_not_the_platform(): void
    {
        foreach (['interview', 'candidate', 'applicant', 'scorecard'] as $col) {
            $this->assertFalse(Schema::hasColumn('staff_users', $col), "staff_users must not have hiring column $col");
        }

        $models = [
            Role::class,
            RolePermission::class,
            StaffUser::class,
        ];
        foreach ($models as $modelClass) {
            $model = new $modelClass;
            $table = $model->getTable();
            $this->assertFalse(
                str_contains($table, 'interview') || str_contains($table, 'candidate') || str_contains($table, 'applicant') || str_contains($table, 'scorecard'),
                "Model $modelClass must not own a hiring table"
            );
        }

        $reflection = new \ReflectionClass(StaffEngine::class);
        foreach ($reflection->getMethods() as $method) {
            $name = strtolower($method->getName());
            $this->assertFalse(
                str_contains($name, 'hire') || str_contains($name, 'interview') || str_contains($name, 'candidate'),
                "StaffEngine must not have hiring method $name"
            );
        }
    }
}
