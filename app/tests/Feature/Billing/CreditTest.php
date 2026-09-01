<?php

declare(strict_types=1);

use App\Livewire\Account\Credit;
use App\Models\Business;
use App\Models\User;
use App\Support\PlatformCredentials;
use Livewire\Livewire;

it('refuses stripe checkout if key is not live', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create();
    $business->owner_user_id = $user->id;
    $business->save();
    
    \Illuminate\Support\Facades\Config::set('credentials.stripe_secret', 'sk_test_123');

    Livewire::actingAs($user)
        ->test(Credit::class, ['business' => $business])
        ->set('pendingProduct', 'sms')
        ->set('shownPriceCents', 5000)
        ->set('pendingTier', 'tier_1')
        ->set('shownGrantSeed', 5000)
        ->set('confirmed', true)
        ->call('buy');
    
    // Assert there was no redirect
    $this->assertNull(Livewire::actingAs($user)->test(Credit::class, ['business' => $business])->get('redirect'));
});
