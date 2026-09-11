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
    expect(preg_match('/@media \(prefers-color-scheme:dark\)\{[^{}]*\{[^}]*--color-paper:#16191c/', $css))->toBe(1, 'the dark surface value is not declared inside a prefers-color-scheme:dark block of the stylesheet this page links, so either the dark palette applies in every colour scheme or it is gone. It reads the minified stylesheet and only the first rule inside a `prefers-color-scheme:dark` block, so a build that merges another rule into that block ahead of the palette can red it while the palette is correct; report such a red, and never loosen the pattern to pass it.');
    expect(substr_count($css, '--color-paper:#16191c'))->toBe(1, 'the dark value is also declared somewhere else, outside the guard, or the dark palette is gone');
    expect(substr_count($css, '--color-paper:#fafaf9'))->toBe(1, 'the stylesheet carries no light surface, so every visitor gets the dark palette, or the light value is declared twice');
});
