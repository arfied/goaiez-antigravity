<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Staff screens can actually show a message — decision 5460
|--------------------------------------------------------------------------
|
| ⛔ THE MECHANISM EXISTED, WAS CALLED CORRECTLY EVERYWHERE, AND WAS WIRED TO
| NOTHING. `masmerise/livewire-toaster` needs `<x-toaster-hub />` in the layout.
| The account shell has had one since it was built; `layouts/app.blade.php` —
| every staff screen — never did. So `Toaster::success()` and `Toaster::error()`
| ran all over `app/Livewire/Admin` and delivered nowhere.
|
| ⛔ WHAT IT COST, CONCRETELY. `LegalDocuments::act()` catches the service's
| refusals on purpose and shows the message, because its docblock is right that
| a generic failure would turn "published text is frozen" into a mystery. The
| owner met that exact case as a Publish button that did nothing: no error, no
| log line, and a service refusing with a paragraph naming precisely what to fix.
|
| ⚠️ WHY NOTHING CAUGHT IT, WHICH IS THE PART WORTH KEEPING. Every admin test
| drives its component through `Livewire::actingAs()->test()`, which runs no
| middleware and renders no layout (809). A component test can prove a toast was
| dispatched and can never prove one was displayed — the two halves live in
| different files and only one of them had a test. So this renders the real page
| over HTTP, which is the only way the layout is exercised at all.
|
| ⚠️ TWO-FACTOR IS WHY THIS IS FIDDLIER THAN IT LOOKS. `RequiresTwoFactor`
| redirects a staff account without a confirmed second factor, and a probe that
| forgets it asserts against a redirect stub rather than the page — which is how
| the first diagnosis of this bug concluded, wrongly, that staff screens were not
| using this layout at all.
*/

test('a staff screen renders the toast hub, so a refusal can reach the person', function (): void {
    $admin = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);

    $html = $this->actingAs($admin)->get('/admin/legal/terms')->assertOk()->getContent();

    // The page itself, first — an assertion about a layout is worthless if the
    // request never got past the middleware, and that is exactly the mistake
    // this test's own first draft made.
    expect($html)->toContain('Terms of Service')
        ->and($html)->toContain('max-w-7xl');

    expect($html)->toContain('toaster');
});

test('the signed-out marketing shell does not carry the staff hub', function (): void {
    // ⚠️ THE OTHER DIRECTION, SO "toaster" IS NOT MATCHING SOMETHING AMBIENT.
    // A test that only asserts presence would pass identically if the string
    // appeared in a shared script bundle on every page in the application.
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->not->toContain('toaster');
});
