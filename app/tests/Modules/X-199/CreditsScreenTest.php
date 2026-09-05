<?php

declare(strict_types=1);

namespace Tests\Modules\X199;

use App\Models\User;
use App\Modules\X121\Models\Person;
use App\Modules\X199\Actions\TermsSetAction;
use App\Modules\X199\Domain\InvoiceEngine;
use App\Modules\X199\Models\CreditTerm;
use App\Modules\X199\Ui\Credits;
use App\Support\Tenancy;
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

        Livewire::actingAs($owner)->test(Credits::class)
            ->assertSee('reversed against the invoice')
            ->assertDontSee('covered by the card on file')
            ->call('setTerms', 999999)
            ->assertSee("isn't in this account");

        $this->assertSame(1, CreditTerm::where('business_id', $biz->id)->count());
    }
}
