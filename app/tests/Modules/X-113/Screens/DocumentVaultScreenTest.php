<?php

declare(strict_types=1);

namespace Tests\Modules\X113\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X113\Actions\DocumentUploadAction;
use App\Modules\X113\Actions\RoleCreateAction;
use App\Modules\X113\Actions\RolePermissionGrantAction;
use App\Modules\X113\Models\StaffDocument;
use App\Modules\X113\Models\StaffUser;
use App\Modules\X113\Ui\DocumentVault;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_can_upload_document_for_staff(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Storage::fake('local');

        $staff = StaffUser::create(['business_id' => $biz->id, 'name' => 'Alice', 'role_id' => null, 'email' => 'a@b.c']);
        $file = UploadedFile::fake()->createWithContent('contract.pdf', 'real bytes here');

        Livewire::test(DocumentVault::class)
            ->set('selectedStaffId', $staff->id)
            ->set('file', $file)
            ->call('uploadDocument')
            ->assertSet('success', 'Uploaded document contract.pdf. This feeds the vault list; nothing downstream is wired to it yet.');

        $this->assertDatabaseHas((new StaffDocument)->getTable(), [
            'business_id' => $biz->id,
            'staff_user_id' => $staff->id,
            'original_filename' => 'contract.pdf',
        ]);

        $doc = StaffDocument::first();
        $this->assertNotNull($doc);
        $this->assertStringNotContainsString('contract.pdf', $doc->storage_path);

        Storage::disk('local')->assertExists($doc->storage_path);

        $this->assertEquals(hash('sha256', 'real bytes here'), $doc->sha256);
        $this->assertEquals(strlen('real bytes here'), $doc->size_bytes);

        $this->get(route('x-113.document-vault'))
            ->assertOk()
            ->assertSee('contract.pdf');
    }

    public function test_refuses_upload_for_other_tenant_staff(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Storage::fake('local');

        $otherBiz = $this->provisionTenant();
        $otherStaff = StaffUser::create(['business_id' => $otherBiz->id, 'name' => 'Bob', 'role_id' => null, 'email' => 'b@b.c']);
        Tenancy::set($biz->id);
        $file = UploadedFile::fake()->createWithContent('contract.pdf', 'real bytes here');

        Livewire::test(DocumentVault::class)
            ->set('selectedStaffId', $otherStaff->id)
            ->set('file', $file)
            ->call('uploadDocument')
            ->assertSet('error', 'Staff user not found or does not belong to this tenant.');

        $this->assertDatabaseMissing((new StaffDocument)->getTable(), [
            'original_filename' => 'contract.pdf',
        ]);

        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_refuses_oversized_file(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Storage::fake('local');

        $staff = StaffUser::create(['business_id' => $biz->id, 'name' => 'Alice', 'role_id' => null, 'email' => 'a@b.c']);
        $file = UploadedFile::fake()->create('big.pdf', 3000);

        $response = Livewire::test(DocumentVault::class)
            ->set('selectedStaffId', $staff->id)
            ->set('file', $file)
            ->call('uploadDocument');

        $response->assertHasErrors(['file']);

        $errors = $response->errors();
        $this->assertEquals(DocumentVault::UPLOAD_REFUSED, $errors->first('file'));

        $this->assertDatabaseMissing((new StaffDocument)->getTable(), [
            'original_filename' => 'big.pdf',
        ]);
    }

    public function test_owner_can_download_document_and_receive_original_filename(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Storage::fake('local');

        $staff = StaffUser::create(['business_id' => $biz->id, 'name' => 'Alice', 'role_id' => null, 'email' => 'a@b.c']);
        $file = UploadedFile::fake()->createWithContent('contract.pdf', 'downloadable bytes');

        $doc = app(DocumentUploadAction::class)->handle($biz->id, $staff->id, $file, $owner->id);

        $response = Livewire::test(DocumentVault::class)
            ->call('download', $doc->id);

        $response->assertFileDownloaded('contract.pdf');
    }

    public function test_document_belonging_to_another_tenant_refused(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Storage::fake('local');

        $otherBiz = $this->provisionTenant();
        Tenancy::set($biz->id); // trap avoided

        $otherStaff = StaffUser::create(['business_id' => $otherBiz->id, 'name' => 'Bob', 'role_id' => null, 'email' => 'b@b.c']);
        $file = UploadedFile::fake()->createWithContent('secret.pdf', 'secret bytes');

        $doc = app(DocumentUploadAction::class)->handle($otherBiz->id, $otherStaff->id, $file, $owner->id);

        $this->expectException(ModelNotFoundException::class);
        Livewire::test(DocumentVault::class)
            ->call('download', $doc->id);
    }

    public function test_download_document_refuses_different_staff_member(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Storage::fake('local');

        $role = app(RoleCreateAction::class)->handle($biz->id, 'Manager');
        app(RolePermissionGrantAction::class)->handle($biz->id, $role->id, 'view_employee_documents');

        $staff1 = StaffUser::create(['business_id' => $biz->id, 'name' => 'Alice', 'role_id' => $role->id, 'email' => 'a@b.c']);
        $staff2 = StaffUser::create(['business_id' => $biz->id, 'name' => 'Bob', 'role_id' => $role->id, 'email' => 'b@b.c']);

        $file = UploadedFile::fake()->createWithContent('alice.pdf', 'alice bytes');
        $doc1 = app(DocumentUploadAction::class)->handle($biz->id, $staff1->id, $file, $owner->id);

        $vault = new DocumentVault;

        $this->expectException(ModelNotFoundException::class);
        $vault->downloadDocument($biz->id, $staff2->id, $doc1->id);
    }

    public function test_download_document_refuses_insufficient_permissions(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Storage::fake('local');

        $role = app(RoleCreateAction::class)->handle($biz->id, 'Manager');
        $staff = StaffUser::create(['business_id' => $biz->id, 'name' => 'Alice', 'role_id' => $role->id, 'email' => 'a@b.c']);

        $file = UploadedFile::fake()->createWithContent('alice.pdf', 'alice bytes');
        $doc = app(DocumentUploadAction::class)->handle($biz->id, $staff->id, $file, $owner->id);

        $vault = new DocumentVault;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('INSUFFICIENT_ROLE_PERMISSIONS');
        $vault->downloadDocument($biz->id, $staff->id, $doc->id);
    }
}
