<?php

declare(strict_types=1);

namespace Tests\Modules\X199;

use App\Models\User;
use App\Modules\X121\Models\Person;
use App\Modules\X198\Models\MerchantConnection;
use App\Modules\X199\Actions\TermsSetAction;
use App\Modules\X199\Domain\InvoiceEngine;
use App\Modules\X199\Models\CreditTerm;
use App\Modules\X199\Ui\Credits;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class CreditsScreenTest extends TestCase
{
    public function test_credits_shows_headroom_and_the_overflow_rule_and_refuses_a_bad_terms_type(): void
    {
        $biz = self::provisionTenant();
        $bizB = self::provisionTenant();

        Tenancy::set($bizB->id);
        $bea = Person::create(['business_id' => $bizB->id, 'first_name' => 'Bea', 'last_name' => 'Corp']);
        app(TermsSetAction::class)->handle($bizB->id, $bea->id, 'net_15', 20000);

        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $ada = Person::create(['business_id' => $biz->id, 'first_name' => 'Ada', 'last_name' => 'Lovelace']);
        $term = app(TermsSetAction::class)->handle($biz->id, $ada->id, 'net_30', 50000, 'pm_card_abc');

        $issued = app(InvoiceEngine::class)->issueInvoice(
            $biz->id,
            $ada->id,
            [['description' => 'Fence, 40 metres', 'quantity' => 1, 'unit_price_cents' => 60000]],
            'net_30'
        );
        $this->assertTrue($issued['is_over_limit']);
        $this->assertSame(10000, $issued['overflow_charge']->amount_cents);

        Tenancy::forgetUser();
        Livewire::test(Credits::class)->assertForbidden();
        Tenancy::setUser($owner->id);

        $screen = Livewire::actingAs($owner)->test(Credits::class)
            ->assertOk()
            ->assertSee('Ada Lovelace')
            ->assertSee('net 30')
            ->assertSee('500.00')
            ->assertSee('600.00')
            ->assertSee('over the limit')
            ->assertDontSee('covered by the card on file')
            ->assertSee('the card did not absorb it and the invoice still stands')
            ->assertDontSee('Bea Corp')
            ->assertSeeHtml('wire:submit="setTerms('.$term->id.')"')
            ->set('termsType.'.$term->id, 'net_60')
            ->set('limit.'.$term->id, 1000)
            ->call('setTerms', $term->id)
            ->assertSee('Terms on Ada Lovelace: net 60, limit 1,000.00.')
            ->assertSee('400.00 headroom');

        $term->refresh();
        $this->assertSame('net_60', $term->terms_type);
        $this->assertSame(100000, $term->credit_limit_cents);
        $this->assertSame('pm_card_abc', $term->card_on_file_token, 'a terms change keeps the card on file');

        $screen->set('termsType.'.$term->id, 'net_90')
            ->set('limit.'.$term->id, 1000)
            ->call('setTerms', $term->id)
            ->assertSee('net_90 is not a terms type');

        $term->refresh();
        $this->assertSame('net_60', $term->terms_type, 'a refused terms type leaves the row untouched');

        $screen->set('termsType.'.$term->id, 'net_60')
            ->set('limit.'.$term->id, -5)
            ->call('setTerms', $term->id)
            ->assertSee('A credit limit is never negative');

        $issued['overflow_charge']->update(['status' => 'charged']);
        app(InvoiceEngine::class)->recordPayment($biz->id, $issued['invoice']->id);

        app(InvoiceEngine::class)->issueInvoice(
            $biz->id,
            $ada->id,
            [['description' => 'Second Invoice', 'quantity' => 1, 'unit_price_cents' => 1000]],
            'net_30'
        );

        Livewire::actingAs($owner)->test(Credits::class)
            ->assertSee('Ada Lovelace')
            ->assertSee('reversed against the invoice')
            ->assertDontSee('covered by the card on file')
            ->call('setTerms', 999999)
            ->assertSee("isn't in this account");

        $this->assertSame(1, CreditTerm::where('business_id', $biz->id)->count());
    }

    public function test_credits_shows_charged_overflow_wording(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);

        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $ada = Person::create(['business_id' => $biz->id, 'first_name' => 'Ada', 'last_name' => 'Lovelace']);

        MerchantConnection::create([
            'business_id' => $biz->id,
            'gateway_name' => 'stripe',
            'merchant_account_id' => 'acct_test',
            'is_connected' => true,
        ]);

        Http::fake([
            'api.stripe.com/*' => Http::response(['id' => 'ch_mock_123', 'status' => 'succeeded'], 200),
        ]);

        app(TermsSetAction::class)->handle($biz->id, $ada->id, 'net_30', 50000, 'tok_visa');

        app(InvoiceEngine::class)->issueInvoice(
            $biz->id,
            $ada->id,
            [['description' => 'Fence, 40 metres', 'quantity' => 1, 'unit_price_cents' => 60000]],
            'net_30'
        );

        app(InvoiceEngine::class)->issueInvoice(
            $biz->id,
            $ada->id,
            [['description' => 'Second Invoice', 'quantity' => 1, 'unit_price_cents' => 1000]],
            'net_30'
        );

        Livewire::actingAs($owner)->test(Credits::class)
            ->assertOk()
            ->assertSee('Ada Lovelace')
            ->assertSee('covered by the card on file; service never stopped')
            ->assertDontSee('the card did not absorb it and the invoice still stands');
    }

    public function test_credits_says_a_pending_overflow_is_at_the_gateway_not_refused_by_the_card(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);

        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $ada = Person::create(['business_id' => $biz->id, 'first_name' => 'Ada', 'last_name' => 'Lovelace']);

        MerchantConnection::create([
            'business_id' => $biz->id,
            'gateway_name' => 'stripe',
            'merchant_account_id' => 'acct_test',
            'is_connected' => true,
        ]);

        // A real charge object exists at the provider and has not settled (R235), so the row is
        // 'refused' with a real gateway id on it.
        Http::fake([
            'api.stripe.com/*' => Http::response(['id' => 'ch_pending_credits_0000000', 'status' => 'pending'], 200),
        ]);

        app(TermsSetAction::class)->handle($biz->id, $ada->id, 'net_30', 50000, 'tok_visa');

        app(InvoiceEngine::class)->issueInvoice(
            $biz->id,
            $ada->id,
            [['description' => 'Fence, 40 metres', 'quantity' => 1, 'unit_price_cents' => 60000]],
            'net_30'
        );

        Livewire::actingAs($owner)->test(Credits::class)
            ->assertOk()
            ->assertSee('Ada Lovelace')
            ->assertSee('the gateway took it and has not settled it yet')
            ->assertDontSee('the card did not absorb it')
            ->assertDontSee('covered by the card on file');
    }
}
