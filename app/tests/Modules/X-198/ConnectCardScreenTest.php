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
            ->assertSee('waits on the processor contract');
    }

    public function test_connect_card_refuses_a_connection_that_has_already_applied(): void
    {
        $bizA = self::provisionTenant();

        Tenancy::set($bizA->id);
        $connA = MerchantConnection::create([
            'business_id' => $bizA->id,
            'gateway_name' => 'stripe',
            'merchant_account_id' => 'acct_A1',
            'is_connected' => true,
            'merchant_status' => 'pending_kyc',
        ]);

        $owner = User::findOrFail($bizA->owner_user_id);
        Tenancy::set($bizA->id);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)->test(ConnectCard::class)
            ->call('applyForMerchant', $connA->id)
            ->assertSee('already past that');
    }

    public function test_connect_card_heads_an_application_refusal_as_an_application()
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $connA = MerchantConnection::create(['business_id' => $biz->id, 'gateway_name' => 'stripe', 'merchant_account_id' => 'acct_A1', 'merchant_status' => 'external_gateway', 'is_connected' => true]);

        Livewire::actingAs($owner)->test(ConnectCard::class)
            ->call('applyForMerchant', $connA->id)
            ->assertSee('Could not send the application')
            ->assertDontSee('Could not connect');
    }

    public function test_the_connect_door_names_what_it_waits_on_and_never_a_delivery_date()
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)->test(ConnectCard::class)
            ->assertSee('No gateway has been connected on this account')
            ->call('connect')
            ->assertSee('no client id is configured in this checkout yet')
            ->assertDontSee('week 2');

        config(['services.stripe.client_id' => 'ca_test_money92']);

        Livewire::actingAs($owner)->test(ConnectCard::class)
            ->call('connect')
            ->assertSee('The Stripe Connect redirect is not built in this checkout yet')
            ->assertDontSee('week 2');
    }

    public function test_the_merchant_pill_says_not_applied_and_never_names_an_external_gateway()
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        MerchantConnection::create([
            'business_id' => $biz->id,
            'gateway_name' => 'stripe',
            'merchant_account_id' => 'acct_A1',
            'merchant_status' => 'external_gateway',
            'is_connected' => true,
        ]);

        Livewire::actingAs($owner)->test(ConnectCard::class)
            ->assertOk()
            ->assertSee('not applied')
            ->assertDontSee('external_gateway');
    }

    public function test_the_connect_card_heads_recorded_gateways_and_never_offers_to_manage_them(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        MerchantConnection::create([
            'business_id' => $biz->id,
            'gateway_name' => 'stripe',
            'merchant_account_id' => 'acct_A1',
            'is_connected' => true,
        ]);

        Livewire::actingAs($owner)->test(ConnectCard::class)
            ->assertSee('Recorded gateways')
            ->assertSee('wait on contracts that are not in this checkout yet')
            ->assertDontSee('Connect Gateway')
            ->assertDontSee('Manage your connections');
    }

    public function test_the_recorded_gateways_list_holds_its_order_when_a_row_is_rewritten(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $first = MerchantConnection::create([
            'business_id' => $biz->id,
            'gateway_name' => 'stripe',
            'merchant_account_id' => 'acct_one',
            'is_connected' => true,
        ]);

        MerchantConnection::create([
            'business_id' => $biz->id,
            'gateway_name' => 'square',
            'merchant_account_id' => 'acct_two',
            'is_connected' => true,
        ]);

        MerchantConnection::create([
            'business_id' => $biz->id,
            'gateway_name' => 'clover',
            'merchant_account_id' => 'acct_three',
            'is_connected' => true,
        ]);

        $first->update(['gateway_name' => 'plaid']);

        Livewire::actingAs($owner)->test(ConnectCard::class)
            ->assertSeeInOrder(['acct_one', 'acct_two', 'acct_three']);
    }
}
