<?php

declare(strict_types=1);

namespace Tests\Modules\X198;

use App\Models\User;
use App\Modules\X198\Domain\ProcessorAdapter;
use App\Modules\X198\Models\MerchantConnection;
use App\Modules\X198\Ui\ConnectCard;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ConnectCardScreenTest extends TestCase
{
    public function test_connect_card_renders_and_applies(): void
    {
        $bizA = self::provisionTenant();
        $bizB = self::provisionTenant();

        Tenancy::set($bizA->id);
        $connA = MerchantConnection::create([
            'business_id' => $bizA->id,
            'gateway_name' => 'stripe',
            'merchant_account_id' => 'acct_A1',
            'is_connected' => true,
        ]);

        Tenancy::set($bizB->id);
        $connB = MerchantConnection::create([
            'business_id' => $bizB->id,
            'gateway_name' => 'stripe',
            'merchant_account_id' => 'acct_B9',
            'is_connected' => true,
        ]);

        $owner = User::findOrFail($bizA->owner_user_id);
        Tenancy::set($bizA->id);
        Tenancy::setUser($owner->id);

        $adapter = new class implements ProcessorAdapter
        {
            public function beginKyc(int $businessId): string
            {
                return 'app_123';
            }
        };
        $this->app->instance(ProcessorAdapter::class, $adapter);

        Livewire::actingAs($owner)->test(ConnectCard::class)
            ->assertOk()
            ->assertSee('acct_A1')
            ->assertDontSee('acct_B9')
            ->call('connect')
            ->assertSee('waits on Stripe Connect')
            ->call('applyForMerchant', $connA->id)
            ->assertSee('Application sent (app_123)');

        $this->assertSame('pending_kyc', $connA->fresh()->merchant_status);
        $this->assertSame('sub_merchant', $connA->fresh()->merchant_relationship);
    }

    public function test_connect_card_refuses_when_no_adapter(): void
    {
        $bizA = self::provisionTenant();

        Tenancy::set($bizA->id);
        $connA = MerchantConnection::create([
            'business_id' => $bizA->id,
            'gateway_name' => 'stripe',
            'merchant_account_id' => 'acct_A1',
            'is_connected' => true,
        ]);

        $owner = User::findOrFail($bizA->owner_user_id);
        Tenancy::set($bizA->id);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)->test(ConnectCard::class)
            ->call('applyForMerchant', $connA->id)
            ->assertSee('No adapter bound');
    }
}
