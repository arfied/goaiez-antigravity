<?php

declare(strict_types=1);

use App\Livewire\Account\Credit;
use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('refuses stripe checkout if key is not live', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create();
    $business->owner_user_id = $user->id;
    $business->save();

    Config::set('credentials.stripe_secret', 'sk_test_123');

    Livewire::actingAs($user)
        ->test(Credit::class, ['business' => $business])
        ->set('pendingProduct', 'sms')
        ->set('shownPriceCents', 5000)
        ->set('pendingTier', 'automatic')
        ->set('shownGrantSeed', 1000)
        ->set('confirmed', true)
        ->call('buy')
        ->assertNoRedirect();
});

it('allows stripe checkout if key is live', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create();
    $business->owner_user_id = $user->id;
    $business->save();

    Config::set('credentials.stripe_secret', 'sk_live_123');
    Http::fake(['*' => Http::response(['url' => 'https://checkout.stripe.com/pay'], 200)]);

    Livewire::actingAs($user)
        ->test(Credit::class, ['business' => $business])
        ->set('pendingProduct', 'sms')
        ->set('shownPriceCents', 5000)
        ->set('pendingTier', 'automatic')
        ->set('shownGrantSeed', 1000)
        ->set('confirmed', true)
        ->call('buy')
        ->assertRedirect();
});
