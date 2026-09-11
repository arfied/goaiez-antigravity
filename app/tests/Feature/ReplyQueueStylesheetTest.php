<?php

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;

test('reply queue stylesheet defines canvas colors', function (): void {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    Business::provision([
        'owner_user_id' => $user->id,
        'name' => 'Test Business',
    ]);

    $response = $this->actingAs($user)->get(route('account.replies'));
    $response->assertOk();

    $content = $response->getContent();

    preg_match('/build\/(assets\/app-[^"]*\.css)/', $content, $matches);

    expect($matches)->not->toBeEmpty('the page links no built stylesheet');

    $css = file_get_contents(public_path('build/'.$matches[1]));

    expect($css)->toContain('.text-canvas');
    expect($css)->toContain('.bg-canvas');
});
