<?php

declare(strict_types=1);

namespace Tests\Modules\X199;

use App\Models\User;
use App\Modules\X198\Models\Payment;
use App\Modules\X199\Ui\Declines;
use Livewire\Livewire;
use App\Support\Tenancy;
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
        Livewire::actingAs($owner)->test(Declines::class)
            ->assertOk()
            ->assertSee('150.00')
            ->assertDontSee('250.00')
            ->assertDontSee('350.00')
            ->assertSeeHtml("didn't authorise");
    }
}
