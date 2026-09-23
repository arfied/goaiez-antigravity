<?php

declare(strict_types=1);

namespace Tests\Modules\X113\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X113\Actions\RoleCreateAction;
use App\Modules\X113\Models\RolePermission;
use App\Modules\X113\Ui\PermissionMatrix;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class PermissionMatrixScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-113.permission-matrix'))->assertOk();

        Livewire::test(PermissionMatrix::class)->assertOk();
    }

    public function test_grant_permission_success_and_failures(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        // a GET before any role exists showing the empty state
        $this->get(route('x-113.permission-matrix'))
            ->assertOk()
            ->assertSee('Roles are created on the Roles screen.');

        // a role created (use RoleCreateAction) appearing on the matrix with no permissions
        $roleAction = app(RoleCreateAction::class);
        $role = $roleAction->handle($biz->id, 'Manager');

        $this->get(route('x-113.permission-matrix'))
            ->assertOk()
            ->assertSee('Manager')
            ->assertSee('No permissions granted.');

        // refusal case: empty input → $error set, assertDatabaseMissing
        Livewire::test(PermissionMatrix::class)
            ->set('roleId', 0)
            ->call('grantPermission')
            ->assertSet('error', 'Please select a role.');

        Livewire::test(PermissionMatrix::class)
            ->set('roleId', $role->id)
            ->set('permission', '')
            ->call('grantPermission')
            ->assertSet('error', 'Please select a permission.');

        $this->assertDatabaseMissing((new RolePermission)->getTable(), [
            'business_id' => $biz->id,
            'role_id' => $role->id,
        ]);

        // the control granting the permission
        Livewire::test(PermissionMatrix::class)
            ->set('roleId', $role->id)
            ->set('permission', 'view_employee_documents')
            ->call('grantPermission')
            ->assertSet('success', "Granted permission 'view_employee_documents' to role 'Manager'. The only thing reading this grant today is the document vault's download check.");

        // assertDatabaseHas on (new RolePermission)->getTable()
        $this->assertDatabaseHas((new RolePermission)->getTable(), [
            'business_id' => $biz->id,
            'role_id' => $role->id,
            'permission' => 'view_employee_documents',
        ]);

        // a GET afterwards showing the permission against that role
        $this->get(route('x-113.permission-matrix'))
            ->assertOk()
            ->assertSee('view_employee_documents');

        // the duplicate grant refused with only one row existing
        Livewire::test(PermissionMatrix::class)
            ->set('roleId', $role->id)
            ->set('permission', 'view_employee_documents')
            ->call('grantPermission')
            ->assertSet('error', "Role already has the 'view_employee_documents' permission.");

        $this->assertDatabaseCount((new RolePermission)->getTable(), 1);

        // an unknown permission string refused with no row
        Livewire::test(PermissionMatrix::class)
            ->set('roleId', $role->id)
            ->set('permission', 'delete_everything')
            ->call('grantPermission')
            ->assertSet('error', "Unknown permission: 'delete_everything'");

        $this->assertDatabaseMissing((new RolePermission)->getTable(), [
            'permission' => 'delete_everything',
        ]);
    }

    public function test_grant_refuses_other_tenant_role(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $otherBiz = $this->provisionTenant();
        $roleAction = app(RoleCreateAction::class);
        $otherRole = $roleAction->handle($otherBiz->id, 'Other Manager');
        Tenancy::set($biz->id);

        Livewire::test(PermissionMatrix::class)
            ->set('roleId', $otherRole->id)
            ->set('permission', 'view_employee_documents')
            ->call('grantPermission')
            ->assertSet('error', 'Invalid role.');

        $this->assertDatabaseMissing((new RolePermission)->getTable(), [
            'role_id' => $otherRole->id,
            'permission' => 'view_employee_documents',
        ]);
    }
}
