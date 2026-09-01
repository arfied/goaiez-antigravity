<?php

declare(strict_types=1);

use App\Jobs\DeliverPlatformMail;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

it('refuses to merge unverified user and sends notification', function () {
    Queue::fake();

    $user = User::factory()->create(['email_verified_at' => null]);

    $socialiteUser = new SocialiteUser;
    $socialiteUser->map([
        'id' => '12345',
        'nickname' => 'test',
        'name' => 'Test User',
        'email' => $user->email,
        'avatar' => 'http://example.com/avatar.jpg',
    ]);

    Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
    Socialite::shouldReceive('user')->andReturn($socialiteUser);

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect('/login');
    $response->assertSessionHasErrors('email');

    $this->assertGuest();

    Queue::assertPushed(DeliverPlatformMail::class);
});
