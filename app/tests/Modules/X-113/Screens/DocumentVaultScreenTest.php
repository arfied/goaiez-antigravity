<?php

declare(strict_types=1);

namespace Tests\Modules\X113\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X113\Actions\RoleCreateAction;
use App\Modules\X113\Actions\RolePermissionGrantAction;
use App\Modules\X113\Models\StaffUser;
use App\Modules\X113\Ui\DocumentVault;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentVaultScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-113.document-vault'))->assertOk();

        Livewire::test(DocumentVault::class)->assertOk();
    }

    public function test_renders_empty_state_with_no_staff(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-113.document-vault'))
            ->assertOk()
            ->assertSee('No staff yet.');
    }

    public function test_renders_staff_without_permission(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $role = app(RoleCreateAction::class)->handle($biz->id, 'Manager');
        StaffUser::create(['business_id' => $biz->id, 'name' => 'Alice', 'role_id' => $role->id, 'email' => 'a@b.c']);

        $this->get(route('x-113.document-vault'))
            ->assertOk()
            ->assertSee('Alice')
            ->assertSee('Manager')
            ->assertSee('Not permitted');
    }

    public function test_renders_staff_with_permission(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $role = app(RoleCreateAction::class)->handle($biz->id, 'Manager');
        StaffUser::create(['business_id' => $biz->id, 'name' => 'Alice', 'role_id' => $role->id, 'email' => 'a@b.c']);

        app(RolePermissionGrantAction::class)
            ->handle($biz->id, $role->id, 'view_employee_documents');

        $this->get(route('x-113.document-vault'))
            ->assertOk()
            ->assertSee('Alice')
            ->assertSee('Manager')
            ->assertSee('Can view documents');
    }

    public function test_renders_staff_with_no_role(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        StaffUser::create(['business_id' => $biz->id, 'name' => 'Bob', 'role_id' => null, 'email' => 'b@b.c']);

        $this->get(route('x-113.document-vault'))
            ->assertOk()
            ->assertSee('Bob')
            ->assertSee('no role')
            ->assertSee('Not permitted');
    }
}
