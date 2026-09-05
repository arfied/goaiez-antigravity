<?php

declare(strict_types=1);

namespace Tests\Modules\X211;

use App\Models\User;
use App\Modules\X121\Models\Person;
use App\Modules\X199\Models\Invoice;
use App\Modules\X199\Models\InvoiceLine;
use App\Modules\X211\Events\ArPackaged;
use App\Modules\X211\Models\ArCollectionsPackage;
use App\Modules\X211\Models\ArDunningAction;
use App\Modules\X211\Models\ReceivableState;
use App\Modules\X211\Ui\CollectionsPackagePreview;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class CollectionsPackagePreviewScreenTest extends TestCase
{
    public function test_collections_package_needs_a_resolution_attempt_and_a_human(): void
    {
        $biz = self::provisionTenant();
        $bizB = self::provisionTenant();

        Tenancy::set($bizB->id);
        Invoice::create([
            'business_id' => $bizB->id,
            'customer_id' => Person::create(['business_id' => $bizB->id, 'first_name' => 'B', 'last_name' => 'B'])->id,
            'invoice_number' => 'INV-B1',
            'total_cents' => 10000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(90),
        ]);

        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'John', 'last_name' => 'Doe']);

        $inv1 = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-A1',
            'total_cents' => 40000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(75),
        ]);
        InvoiceLine::create(['business_id' => $biz->id, 'invoice_id' => $inv1->id, 'description' => 'Roof repair', 'quantity' => 1, 'unit_price_cents' => 40000, 'subtotal_cents' => 40000]);

        $inv2 = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-A2',
            'total_cents' => 20000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(70),
        ]);
        ArDunningAction::create(['business_id' => $biz->id, 'invoice_id' => $inv2->id, 'action' => 'reason_recorded', 'reason' => 'Customer promised to pay']);

        Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-A3',
            'total_cents' => 30000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->addDays(5),
        ]);

        Event::fake([ArPackaged::class]);

        $screen = Livewire::actingAs($owner)->test(CollectionsPackagePreview::class)
            ->assertOk()
            ->assertSee('INV-A1')
            ->assertSee('INV-A2')
            ->assertSee('75 days overdue')
            ->assertSee('no resolution attempt yet')
            ->assertSee('1 resolution attempts on record')
            ->assertDontSee('INV-A3')
            ->assertDontSee('INV-B1')
            ->assertDontSee('waits on a collections partner')
            ->assertSeeHtml('wire:submit="package('.$inv1->id.')"')
            ->call('package', $inv1->id)
            ->assertSee('Record a resolution attempt first');

        $this->assertSame(0, ArCollectionsPackage::where('business_id', $biz->id)->count());
        Event::assertNotDispatched(ArPackaged::class);

        $screen->call('package', $inv2->id)
            ->assertSee('INV-A2 packaged for collections')
            ->assertSee('Built, not sent')
            ->assertSee('waits on a collections partner')
            ->assertSee('1 actions');

        $package = ArCollectionsPackage::where('business_id', $biz->id)->where('invoice_id', $inv2->id)->firstOrFail();
        $this->assertSame($owner->id, $package->packaged_by_user_id);
        $this->assertSame('INV-A2', $package->contents['invoice_number']);
        $this->assertSame(20000, $package->contents['balance_cents']);
        $this->assertCount(1, $package->contents['actions']);
        $this->assertNull($package->transmitted_at);
        $this->assertSame('packaged_collections', ReceivableState::where('business_id', $biz->id)->where('invoice_id', $inv2->id)->value('status'));
        Event::assertDispatchedTimes(ArPackaged::class, 1);

        $package->forceFill(['partner' => "O'Brien & Sons", 'transmitted_at' => now()])->save();

        $this->assertSame(0, ArCollectionsPackage::where('business_id', $bizB->id)->count());

        Livewire::actingAs($owner)->test(CollectionsPackagePreview::class)
            ->assertSee('Packaged')
            ->assertSee('INV-A1')
            ->assertSee("sent to O'Brien & Sons")
            ->call('package', 999999)
            ->assertSee("isn't in this account");
    }
}
