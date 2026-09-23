<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Livewire\Admin\AccountAudit;
use App\Models\AuditLogEntry;
use App\Models\Business;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class AccountAuditScreenTest extends TestCase
{
    use RefreshesTenantDatabase;

    protected User $admin;

    protected Business $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->business = TestCase::provisionTenant();
    }

    public function test_get_account_audit_renders_layout(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.audit-account'))
            ->assertOk()
            ->assertSee('Internal Platform Console');
    }

    public function test_owner_gets_measured_status(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($owner);
        Tenancy::setUser($owner->id);

        $this->get(route('admin.audit-account'))
            ->assertForbidden();
    }

    public function test_audit_row_renders(): void
    {
        AuditLogEntry::create([
            'business_id' => $this->business->id,
            'action' => 'business.viewed_by_staff',
            'actor' => 'user:1',
            'metadata' => [],
        ]);

        Livewire::actingAs($this->admin)
            ->test(AccountAudit::class)
            ->set('reference', (string) $this->business->id)
            ->call('resolve')
            ->assertSee('business.viewed_by_staff');
    }
}
