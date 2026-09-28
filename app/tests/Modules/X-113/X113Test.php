<?php

declare(strict_types=1);

namespace Tests\Modules\X113;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X113\Actions\DocumentUploadAction;
use App\Modules\X113\Actions\RoleAssignAction;
use App\Modules\X113\Actions\SecureFieldRevealAction;
use App\Modules\X113\Actions\StaffAuthenticateCheckAction;
use App\Modules\X113\Actions\StaffDeactivateAction;
use App\Modules\X113\Actions\StaffInviteAction;
use App\Modules\X113\Actions\StaffRosterAction;
use App\Modules\X113\Domain\StaffEngine;
use App\Modules\X113\Events\RoleAssigned;
use App\Modules\X113\Events\StaffDeactivated;
use App\Modules\X113\Models\Role;
use App\Modules\X113\Models\RolePermission;
use App\Modules\X113\Models\StaffUser;
use App\Modules\X113\Ui\DocumentVault;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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

    /**
     * [G4-15]
     * The permission row is the gate.
     */
    public function test_g4_15_permission_row_is_the_gate(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Staff Security Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $role = Role::create([
            'business_id' => $biz->id,
            'name' => 'Junior Technician',
            'description' => 'Field tech without gate access',
        ]);

        $staff = $this->inviteAction->handle(
            businessId: $biz->id,
            email: 'tech_no_access@example.com',
            name: 'No Access Bob',
            roleId: $role->id
        );

        // In-scope reveal attempt -> REFUSED because no permission row
        $refusedRes = $this->revealAction->revealField(
            businessId: $biz->id,
            staffUserId: $staff->id,
            targetJobId: 101,
            assignedJobId: 101,
            requiredPermission: 'view_gate_access_code',
            secretFieldValue: 'GATE-CODE-4491'
        );
        $this->assertEquals('refused', $refusedRes['status']);
        $this->assertEquals('INSUFFICIENT_ROLE_PERMISSIONS', $refusedRes['refusal_code']);

        // Grant the permission to that role
        RolePermission::create([
            'business_id' => $biz->id,
            'role_id' => $role->id,
            'permission' => 'view_gate_access_code',
        ]);

        // Same call again -> REVEALED
        $revealedRes = $this->revealAction->revealField(
            businessId: $biz->id,
            staffUserId: $staff->id,
            targetJobId: 101,
            assignedJobId: 101,
            requiredPermission: 'view_gate_access_code',
            secretFieldValue: 'GATE-CODE-4491'
        );
        $this->assertEquals('revealed', $revealedRes['status']);
        $this->assertEquals('GATE-CODE-4491', $revealedRes['value']);
        $this->assertTrue($revealedRes['audit_logged']);
    }

    /**
     * [G4-35]
     * The role is what carries the grant.
     */
    public function test_g4_35_role_carries_the_grant(): void
    {
        Event::fake([RoleAssigned::class]);

        $biz = TestCase::provisionTenant(['name' => 'Staff Security Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $roleGranted = Role::create([
            'business_id' => $biz->id,
            'name' => 'Granted Role',
            'description' => 'Role with gate access',
        ]);
        RolePermission::create([
            'business_id' => $biz->id,
            'role_id' => $roleGranted->id,
            'permission' => 'view_gate_access_code',
        ]);

        $roleUngranted = Role::create([
            'business_id' => $biz->id,
            'name' => 'Ungranted Role',
            'description' => 'Role without gate access',
        ]);

        $staff = $this->inviteAction->handle(
            businessId: $biz->id,
            email: 'tech_mover@example.com',
            name: 'Mover Tech',
            roleId: $roleGranted->id
        );

        // Put the staff user on the granted role, reveal in scope, assert revealed
        $revealedRes = $this->revealAction->revealField(
            businessId: $biz->id,
            staffUserId: $staff->id,
            targetJobId: 101,
            assignedJobId: 101,
            requiredPermission: 'view_gate_access_code',
            secretFieldValue: 'GATE-CODE-4491'
        );
        $this->assertEquals('revealed', $revealedRes['status']);

        // Move them with RoleAssignAction to the ungranted role
        $this->roleAction->handle(
            businessId: $biz->id,
            staffUserId: $staff->id,
            roleId: $roleUngranted->id
        );

        // Call revealField again with identical arguments -> REFUSED
        $refusedRes = $this->revealAction->revealField(
            businessId: $biz->id,
            staffUserId: $staff->id,
            targetJobId: 101,
            assignedJobId: 101,
            requiredPermission: 'view_gate_access_code',
            secretFieldValue: 'GATE-CODE-4491'
        );
        $this->assertEquals('refused', $refusedRes['status']);
        $this->assertEquals('INSUFFICIENT_ROLE_PERMISSIONS', $refusedRes['refusal_code']);
    }

    /**
     * [G10-15]
     * Employee documents under RBAC.
     */
    public function test_g10_15_employee_documents_under_rbac(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Doc Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $role = Role::create([
            'business_id' => $biz->id,
            'name' => 'Doc Reader',
            'description' => 'Can read docs',
        ]);

        $staff = $this->inviteAction->handle(
            businessId: $biz->id,
            email: 'docreader@example.com',
            name: 'Doc Reader',
            roleId: $role->id
        );

        $vault = new DocumentVault;

        // 1. Refusal when permission is missing
        try {
            $vault->downloadDocument($biz->id, $staff->id, 999);
            $this->fail('Expected exception for missing permission');
        } catch (\Exception $e) {
            $this->assertEquals('INSUFFICIENT_ROLE_PERMISSIONS', $e->getMessage());
        }

        // 2. Grant permission
        RolePermission::create([
            'business_id' => $biz->id,
            'role_id' => $role->id,
            'permission' => 'view_employee_documents',
        ]);

        // 3. Allowed path works
        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent('real_doc.pdf', 'my_real_bytes');
        $uploadedDoc = app(DocumentUploadAction::class)->handle($biz->id, $staff->id, $file, null);

        $this->assertEquals('my_real_bytes', $vault->downloadDocument($biz->id, $staff->id, $uploadedDoc->id));
    }

    /**
     * [G15-05]
     * T677 — coaching framing; the quarterly nag is a reminder, not a ranking
     */
    public function test_g15_05_quarterly_nag_is_coaching_reminder_not_ranking(): void
    {
        $this->assertTrue(Schema::hasColumn('staff_users', 'coaching_notes'), 'staff_users must have coaching_notes for positive feedback');
        $this->assertFalse(Schema::hasColumn('staff_users', 'quarterly_rank'), 'no quarterly ranking');

        $reflection = new \ReflectionClass(StaffEngine::class);
        foreach ($reflection->getMethods() as $method) {
            $name = strtolower($method->getName());
            $this->assertFalse(
                str_contains($name, 'rank'),
                "StaffEngine must not have ranking method $name"
            );
        }
    }

    /**
     * [G20-02]
     * nagging managers about performance reviews — not customer reviews
     */
    public function test_g20_02_performance_reviews_are_not_customer_reviews(): void
    {
        $this->assertFalse(Schema::hasColumn('staff_users', 'customer_review'));
        $this->assertTrue(Schema::hasColumn('staff_users', 'coaching_notes'));

        $reflection = new \ReflectionClass(StaffEngine::class);
        foreach ($reflection->getMethods() as $method) {
            $name = strtolower($method->getName());
            $this->assertFalse(
                str_contains($name, 'customer'),
                "StaffEngine must not have customer method $name"
            );
        }
    }

    /**
     * [G21-08]
     * /pto — the time-off workflow, not a platform decision
     */
    public function test_roster_lists_active_staff_only(): void
    {
        $ownerA = User::factory()->create();
        $bizA = TestCase::provisionTenant(['name' => 'Biz A', 'currency' => 'USD', 'owner_user_id' => $ownerA->id]);
        $ownerB = User::factory()->create();
        $bizB = TestCase::provisionTenant(['name' => 'Biz B', 'currency' => 'USD', 'owner_user_id' => $ownerB->id]);

        Tenancy::setUser($ownerA->id);
        Tenancy::set((int) $bizA->id);
        $roleA = Role::create(['business_id' => $bizA->id, 'name' => 'Role A']);
        $activeA = $this->inviteAction->handle($bizA->id, 'a@ex.com', 'Staff A', $roleA->id);
        $deactiveA = $this->inviteAction->handle($bizA->id, 'da@ex.com', 'Deactive A', $roleA->id);
        $this->deactivateAction->handle($bizA->id, $deactiveA->id);

        Tenancy::setUser($ownerB->id);
        Tenancy::set((int) $bizB->id);
        $roleB = Role::create(['business_id' => $bizB->id, 'name' => 'Role B']);
        $activeB = $this->inviteAction->handle($bizB->id, 'b@ex.com', 'Staff B', $roleB->id);

        $action = new StaffRosterAction;

        Tenancy::setUser($ownerA->id);
        Tenancy::set((int) $bizA->id);
        $rosterA = $action->handle($bizA->id);
        $this->assertCount(1, $rosterA);
        $this->assertEquals('Staff A', $rosterA[0]['name']);

        Tenancy::setUser($ownerB->id);
        Tenancy::set((int) $bizB->id);
        $rosterB = $action->handle($bizB->id);
        $this->assertCount(1, $rosterB);
        $this->assertEquals('Staff B', $rosterB[0]['name']);
    }

    public function test_g21_08_pto_is_workflow_not_platform_decision(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        // Positive assertion beside it showing the allowed path works
        $this->get(route('x-113.staff'))->assertOk();

        // Assert on the real surface that the thing is genuinely not there
        $this->get('/app/x-113/pto')->assertNotFound();
    }

    public function test_roster_excludes_demo_people(): void
    {
        $ownerA = User::factory()->create();
        $bizA = TestCase::provisionTenant(['name' => 'Biz A', 'currency' => 'USD', 'owner_user_id' => $ownerA->id]);

        Tenancy::setUser($ownerA->id);
        Tenancy::set((int) $bizA->id);
        $roleA = Role::create(['business_id' => $bizA->id, 'name' => 'Role A']);
        $activeA = $this->inviteAction->handle($bizA->id, 'a@ex.com', 'Staff A', $roleA->id);

        $this->inviteAction->handle($bizA->id, 'ghost@ex.com', 'demo·Ghost 4911', $roleA->id);

        $action = new StaffRosterAction;
        $roster = $action->handle($bizA->id);

        $this->assertCount(1, $roster);
        $this->assertEquals('Staff A', $roster[0]['name']);
    }
}
