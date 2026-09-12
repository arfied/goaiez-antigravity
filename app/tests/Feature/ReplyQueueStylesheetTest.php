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
    expect(substr_count($css, ':where(.dark,'))->toBe(0, 'it counts occurrences of `:where(.dark,` in the stylesheet this page links, the selector a `dark:` utility carries when its variant waits for a `.dark` class instead of `prefers-color-scheme`. Such a rule applies wherever that class is set, whatever the visitor\'s colour scheme, while the palette follows the scheme. UP from zero means the variant was bound to a class again; zero is the intended state. The comma is part of the needle because Tailwind wraps some utilities, such as `divide-*`, in `:where(` around their own escaped class name, so without it a `dark:divide-` utility is counted whatever its variant. Its limit: zero also reads when the build emits no `dark:` utility at all, or binds the variant to a class in another selector shape, so it cannot tell either of those from a variant bound to the scheme.');
    preg_match_all('/[{}]|dark\\\\:/', $css, $tokens, PREG_OFFSET_CAPTURE);
    $stack = [];
    $boundary = 0;
    $inside = 0;
    $outside = 0;
    $unbalanced = 0;
    foreach ($tokens[0] as [$token, $offset]) {
        $inScheme = $stack !== [] && $stack[count($stack) - 1];
        if ($token === '{') {
            $prelude = substr($css, $boundary, $offset - $boundary);
            $semicolon = strrpos($prelude, ';');
            if ($semicolon !== false) {
                $prelude = substr($prelude, $semicolon + 1);
            }
            $stack[] = $inScheme || str_starts_with(trim($prelude), '@media (prefers-color-scheme:dark)');
            $boundary = $offset + 1;
        } elseif ($token === '}') {
            if ($stack === []) {
                $unbalanced++;
            } else {
                array_pop($stack);
            }
            $boundary = $offset + 1;
        } elseif ($inScheme) {
            $inside++;
        } else {
            $outside++;
        }
    }
    $unbalanced += count($stack);
    expect($unbalanced)->toBe(0, 'it counts braces the walk above could not pair in the stylesheet this page links: a closing brace with no open block, plus blocks still open at the end. The walk reads every brace as structure, so a brace inside a quoted string or a comment desyncs it, and then the two counts after this line mean nothing. UP from zero means such a brace arrived or the stylesheet is truncated; report it, and never loosen the walk to pass it.');
    expect($outside)->toBe(0, 'it counts `dark\:` class names in the stylesheet this page links that sit in no block opened by `@media (prefers-color-scheme:dark)`, at any depth. Such a `dark:` utility applies whatever the visitor\'s colour scheme, while the palette follows the scheme. UP from zero means a `dark:` utility escaped the scheme query, in whatever selector shape; zero is the intended state. Its limits: it knows a `dark:` utility only by the escaped `dark\:` in its class name, so a variant emitted under another name is invisible to it; and it matches the opening `@media (prefers-color-scheme:dark)` as minified text, so a build that spells that query differently counts every `dark:` utility here.');
    expect($inside)->toBeGreaterThan(0, 'it counts `dark\:` class names inside a `@media (prefers-color-scheme:dark)` block of the stylesheet this page links, so the zero above cannot come from a build that emits no `dark:` utility at all. It asserts that the count is above zero, not its value. Zero means no `dark:` utility was emitted, or none carries the escaped `dark\:` class name the walk looks for; neither is a colour defect by itself, but the zero above then proves nothing.');
});
