<?php

declare(strict_types=1);

namespace Tests\Modules\X202\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X202\Models\ApprovalItem;
use App\Modules\X202\Ui\AuditExport;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class AuditExportScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-202.audit-export'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No decisions yet.');

        Tenancy::setUser($owner->id);
        ApprovalItem::create([
            'business_id' => $biz->id,
            'item_type' => 'distinctive_refund_4631',
            'subject' => 'Distinctive refund 4631',
            'payload' => ['amount_cents' => 4631],
            'status' => 'approved',
            'decided_at' => now(),
            'decision_comment' => 'distinctive decision 4631',
            'expires_at' => now()->addDay(),
        ]);
        Tenancy::forget();

        $this->get(route('x-202.audit-export'))
            ->assertOk()
            ->assertSee('distinctive decision 4631')
            ->assertDontSee('No decisions yet.');

        Livewire::test(AuditExport::class)->assertOk();
    }
}
