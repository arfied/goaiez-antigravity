<?php

declare(strict_types=1);

namespace Tests\Modules\X199;

use App\Modules\X121\Models\Person;
use App\Modules\X199\Domain\InvoiceReader;
use App\Modules\X199\Models\Invoice;
use App\Support\Tenancy;
use Tests\TestCase;

class InvoiceReaderTest extends TestCase
{
    public function test_open_for_business_orders_by_id_when_due_dates_tie(): void
    {
        $biz = self::provisionTenant();
        Tenancy::set($biz->id);
        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'John', 'last_name' => 'Doe']);

        foreach (['INV-OFB-A', 'INV-OFB-B', 'INV-OFB-C'] as $number) {
            Invoice::create([
                'business_id' => $biz->id,
                'customer_id' => $customer->id,
                'invoice_number' => $number,
                'total_cents' => 90000,
                'paid_cents' => 0,
                'status' => 'issued',
                'due_date' => now()->addDays(30),
            ]);
        }

        $order = app(InvoiceReader::class)->openForBusiness((int) $biz->id)
            ->pluck('invoice_number')
            ->all();

        $this->assertSame(['INV-OFB-A', 'INV-OFB-B', 'INV-OFB-C'], $order);
    }

    public function test_open_unpaid_for_business_orders_by_id_when_due_dates_tie(): void
    {
        $biz = self::provisionTenant();
        Tenancy::set($biz->id);
        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'John', 'last_name' => 'Doe']);

        foreach (['INV-OU-A', 'INV-OU-B', 'INV-OU-C'] as $number) {
            Invoice::create([
                'business_id' => $biz->id,
                'customer_id' => $customer->id,
                'invoice_number' => $number,
                'total_cents' => 90000,
                'paid_cents' => 0,
                'status' => 'issued',
                'due_date' => now()->addDays(30),
            ]);
        }

        $order = app(InvoiceReader::class)->openUnpaidForBusiness((int) $biz->id)
            ->pluck('invoice_number')
            ->all();

        $this->assertSame(['INV-OU-A', 'INV-OU-B', 'INV-OU-C'], $order);
    }

    public function test_open_overdue_for_business_orders_by_id_when_due_dates_tie(): void
    {
        $biz = self::provisionTenant();
        Tenancy::set($biz->id);
        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'John', 'last_name' => 'Doe']);

        foreach (['INV-OO-A', 'INV-OO-B', 'INV-OO-C'] as $number) {
            Invoice::create([
                'business_id' => $biz->id,
                'customer_id' => $customer->id,
                'invoice_number' => $number,
                'total_cents' => 90000,
                'paid_cents' => 0,
                'status' => 'issued',
                'due_date' => now()->subDays(10),
            ]);
        }

        $order = app(InvoiceReader::class)->openOverdueForBusiness((int) $biz->id)
            ->pluck('invoice_number')
            ->all();

        $this->assertSame(['INV-OO-A', 'INV-OO-B', 'INV-OO-C'], $order);
    }

    public function test_unpaid_overdue_for_business_orders_by_id_when_due_dates_tie(): void
    {
        $biz = self::provisionTenant();
        Tenancy::set($biz->id);
        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'John', 'last_name' => 'Doe']);

        foreach (['INV-UO-A', 'INV-UO-B', 'INV-UO-C'] as $number) {
            Invoice::create([
                'business_id' => $biz->id,
                'customer_id' => $customer->id,
                'invoice_number' => $number,
                'total_cents' => 90000,
                'paid_cents' => 0,
                'status' => 'issued',
                'due_date' => now()->subDays(10),
            ]);
        }

        $order = app(InvoiceReader::class)->unpaidOverdueForBusiness((int) $biz->id)
            ->pluck('invoice_number')
            ->all();

        $this->assertSame(['INV-UO-A', 'INV-UO-B', 'INV-UO-C'], $order);
    }
}
