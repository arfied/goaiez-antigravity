<?php

declare(strict_types=1);

namespace Tests\Modules\X199;

use App\Models\User;
use App\Modules\X198\Actions\MerchantConnectAction;
use App\Modules\X198\Actions\PaymentCaptureAction;
use App\Modules\X198\Domain\StripeGatewayClient;
use App\Modules\X198\Models\Payment;
use App\Modules\X199\Models\DeclineDeferral;
use App\Modules\X199\Ui\Declines;
use App\Support\Tenancy;
use Carbon\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class DeclinesScreenTest extends TestCase
{
    public function test_declines_screen(): void
    {
        $base = now()->startOfWeek()->addDays(3)->setTime(12, 0);
        Carbon::setTestNow($base);

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

        Carbon::setTestNow();
    }

    public function test_a_decline_recovered_on_the_same_token_is_counted_as_recovered(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);

        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        app(MerchantConnectAction::class)->handle($biz->id, 'stripe', 'acct_test');

        $this->app->instance(StripeGatewayClient::class, new class
        {
            public function charge(int $amountCents, string $source, string $currency = 'USD'): string
            {
                throw new \RuntimeException('Stripe charge failed: card_declined');
            }
        });

        $captureAction = app(PaymentCaptureAction::class);
        $token = 'tok_recovery';

        $time1 = now()->startOfWeek()->addDays(3)->setTime(10, 0);
        $time2 = $time1->copy()->addMinutes(5);

        Carbon::setTestNow($time1);
        try {
            $captureAction->handle($biz->id, 1000, $token, 'idem_1');
        } catch (\RuntimeException $e) {
        }

        Carbon::setTestNow($time2);

        $this->app->instance(StripeGatewayClient::class, new class
        {
            public function charge(int $amountCents, string $source, string $currency = 'USD'): string
            {
                return 'ch_success_recovery';
            }
        });
        $captureAction->handle($biz->id, 1000, $token, 'idem_2');

        Livewire::actingAs($owner)->test(Declines::class)
            ->assertOk()
            ->assertSee('10.00')
            ->assertSee('Recovered '.$time2->format('M j, g:i A'));

        Carbon::setTestNow();
    }

    public function test_decline_deferral_persists_across_remounts(): void
    {
        $base = now()->startOfWeek()->addDays(3)->setTime(10, 0);
        Carbon::setTestNow($base);

        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $payment = Payment::create([
            'business_id' => $biz->id,
            'amount_cents' => 15000,
            'currency' => 'USD',
            'payment_token' => 'tok_1',
            'idempotency_key' => 'idemp1',
            'status' => 'failed',
            'created_at' => $base,
        ]);

        Livewire::actingAs($owner)->test(Declines::class)
            ->assertSee('150.00')
            ->call('settleUpLater', $payment->id)
            ->assertDontSee('150.00');

        Livewire::actingAs($owner)->test(Declines::class)
            ->assertDontSee('150.00');

        Carbon::setTestNow();
    }

    public function test_deferred_decline_shown_under_toggle_show_all(): void
    {
        $base = now()->startOfWeek()->addDays(3)->setTime(10, 0);
        Carbon::setTestNow($base);

        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $payment = Payment::create([
            'business_id' => $biz->id,
            'amount_cents' => 20000,
            'currency' => 'USD',
            'payment_token' => 'tok_2',
            'idempotency_key' => 'idemp2',
            'status' => 'failed',
            'created_at' => $base,
        ]);

        DeclineDeferral::create([
            'business_id' => $biz->id,
            'payment_id' => $payment->id,
        ]);

        Livewire::actingAs($owner)->test(Declines::class)
            ->assertDontSee('200.00')
            ->call('toggleShowAll')
            ->assertSee('200.00')
            ->assertSee('Deferred');

        Carbon::setTestNow();
    }

    public function test_deferring_another_accounts_payment_is_refused(): void
    {
        $base = now()->startOfWeek()->addDays(3)->setTime(10, 0);
        Carbon::setTestNow($base);

        $biz1 = self::provisionTenant();
        $owner1 = User::findOrFail($biz1->owner_user_id);

        $biz2 = self::provisionTenant();

        Tenancy::set($biz2->id);
        $payment = Payment::create([
            'business_id' => $biz2->id,
            'amount_cents' => 30000,
            'currency' => 'USD',
            'payment_token' => 'tok_3',
            'idempotency_key' => 'idemp3',
            'status' => 'failed',
            'created_at' => $base,
        ]);

        Tenancy::set($biz1->id);
        Tenancy::setUser($owner1->id);

        Livewire::actingAs($owner1)->test(Declines::class)
            ->call('settleUpLater', $payment->id)
            ->assertSee("isn't in this account");

        $this->assertEquals(0, DeclineDeferral::count());

        Carbon::setTestNow();
    }
}
