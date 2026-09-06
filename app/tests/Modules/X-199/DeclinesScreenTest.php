<?php

declare(strict_types=1);

namespace Tests\Modules\X199;

use App\Models\User;
use App\Modules\X198\Models\Payment;
use App\Modules\X199\Ui\Declines;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class DeclinesScreenTest extends TestCase
{
    public function test_declines_screen(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);

        $otherBiz = self::provisionTenant();

        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $failedAmount = 15000;
        $capturedAmount = 25000;
        $otherFailedAmount = 35000;

        $token = 'tok_123';

        Payment::create([
            'business_id' => $biz->id,
            'amount_cents' => $failedAmount,
            'currency' => 'USD',
            'payment_token' => $token,
            'idempotency_key' => 'idemp1',
            'status' => 'failed',
            'created_at' => now()->subDay(),
        ]);

        Payment::create([
            'business_id' => $biz->id,
            'amount_cents' => $capturedAmount,
            'currency' => 'USD',
            'payment_token' => 'tok_abc',
            'idempotency_key' => 'idemp2',
            'status' => 'captured',
            'created_at' => now()->subHour(),
        ]);

        Tenancy::set($otherBiz->id);
        Payment::create([
            'business_id' => $otherBiz->id,
            'amount_cents' => $otherFailedAmount,
            'currency' => 'USD',
            'payment_token' => 'tok_other',
            'idempotency_key' => 'idemp3',
            'status' => 'failed',
            'created_at' => now(),
        ]);

        Tenancy::set($biz->id);
        Tenancy::forgetUser();
        Livewire::test(Declines::class)
            ->assertForbidden();

        Tenancy::setUser($owner->id);
        $failed = Payment::where('business_id', $biz->id)->where('status', 'failed')->first();

        Livewire::actingAs($owner)->test(Declines::class)
            ->assertOk()
            ->assertSee('150.00')
            ->assertDontSee('250.00')
            ->assertDontSee('350.00')
            ->assertSeeHtml("didn't authorise")
            ->call('sendPayLink', $failed->id)->assertSee('pay.goaiez.com/link/')
            ->call('sendPayLink', 999999)->assertSee("isn't in this account")->assertSee('Not recovered')->call('settleUpLater', $failed->id)->assertDontSee('150.00')->assertSee('No declines this week.');
    }

    public function test_a_decline_recovered_on_the_same_token_is_counted_as_recovered(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);

        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        app(\App\Modules\X198\Actions\MerchantConnectAction::class)->handle($biz->id, 'stripe', 'acct_test');

        $this->app->instance(\App\Modules\X198\Domain\StripeGatewayClient::class, new class {
            public function charge(int $amountCents, string $source, string $currency = 'USD'): string {
                throw new \RuntimeException('Stripe charge failed: card_declined');
            }
        });

        $captureAction = app(\App\Modules\X198\Actions\PaymentCaptureAction::class);
        $token = 'tok_recovery';

        $time1 = \Carbon\Carbon::parse('2026-09-06 10:00:00');
        \Carbon\Carbon::setTestNow($time1);
        try {
            $captureAction->handle($biz->id, 1000, $token, 'idem_1');
        } catch (\RuntimeException $e) {}

        $time2 = \Carbon\Carbon::parse('2026-09-06 10:05:00');
        \Carbon\Carbon::setTestNow($time2);
        
        $this->app->instance(\App\Modules\X198\Domain\StripeGatewayClient::class, new class {
            public function charge(int $amountCents, string $source, string $currency = 'USD'): string {
                return 'ch_success_recovery';
            }
        });
        $captureAction->handle($biz->id, 1000, $token, 'idem_2');

        \Carbon\Carbon::setTestNow();

        Livewire::actingAs($owner)->test(Declines::class)
            ->assertOk()
            ->assertSee('10.00')
            ->assertSee('Recovered ' . $time2->format('M j, g:i A'));
    }
}
