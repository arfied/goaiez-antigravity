<?php

declare(strict_types=1);

namespace Tests\Modules\X211\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X199\Models\Invoice;
use App\Modules\X211\Models\ArDunningAction;
use App\Modules\X211\Ui\CollectionsPackagePreview;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class CollectionsPackagePreviewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-211.collections-package-preview'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('Nothing to package.');

        Tenancy::setUser($owner->id);
        $invoice = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => null,
            'invoice_number' => 'Distinctive INV-4613',
            'total_cents' => 42000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => today()->subDays(30),
        ]);
        ArDunningAction::create([
            'business_id' => $biz->id,
            'invoice_id' => $invoice->id,
            'action' => 'reason_recorded',
            'reason' => 'Customer promised to pay',
        ]);
        Tenancy::forget();

        $this->get(route('x-211.collections-package-preview'))
            ->assertOk()
            ->assertSee('Distinctive INV-4613')
            ->assertSee('30 days overdue')
            ->assertSee('1 resolution attempts on record')
            ->assertSee('420.00 owed')
            ->assertDontSee('Nothing to package.');

        Livewire::test(CollectionsPackagePreview::class)->assertOk();
    }
}
