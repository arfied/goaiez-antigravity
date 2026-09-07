<?php

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
use App\Support\Account\OwnerNav;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Layout;

/**
 * ⚠️ THESE FOUR SCREENS ARE SAMPLES — THEY RENDER `<x-surface.sample-state>`
 * AND DO NOT BELONG IN THE NAV.
 * We hold them in a separate list rather than `$exclusions` because a written
 * state-fact rots: the day somebody finishes the screen, the exclusion
 * sentence becomes false and nothing anywhere objects.
 * By asserting that every route in this list still renders "not built yet",
 * the test intentionally goes RED the moment a screen improves. That failure
 * is the design, not a broken test — it forces the developer to remove the
 * route from here and add it to `OwnerNav` as a real screen.
 */
function sampleStateRoutes(): array
{
    return [
        'x-110.cooling',
        'x-110.visitors-live',
        'x-110.tag-version-per',
        'x-138.roi-dashboard',
    ];
}

/**
 * @see \App\Modules\X110\Ui\views\pixel-install.blade.php
 * @see \App\Modules\X110\Ui\views\widget-install.blade.php
 *
 * ⛔ OWED TO UI-47: The cross-link half.
 * "AN INVITATION OUT OF AN EMPTY STATE, WHICH IS THE ONE SHAPE
 * Architecture/OwnerNavTest ADMITS"
 * (Quoted from pixel-install.blade.php:128–131 and widget-install.blade.php:88–91)
 */
test('every owner screen route has nav or exclusion', function () {
    $exclusions = [
        'account.data-export.download' => 'This is a file download route, not an interactive screen.',
        'account.inbound-media.show' => 'This is a media endpoint returning images or audio, not a rendered HTML screen.',
        'account.suspended' => 'This is an interruption screen shown when the account is suspended, not a navigable screen in the normal state.',
        'account.voicemail.recording' => 'This is a media endpoint returning an audio file, not an HTML screen.',
        'account.content-topics' => 'This is an internal sub-screen for content topics, not a standalone top-level screen.',

        'x-110.today' => 'This is embedded via @livewire in resources/views/livewire/account/home.blade.php:18.',
        'x-199.money-paid-today' => 'This is embedded via @livewire in resources/views/livewire/account/home.blade.php:19.',
        'x-199.unpaid' => 'This is embedded via @livewire in resources/views/livewire/account/home.blade.php:20.',
        'x-199.declines' => 'This is embedded via @livewire in resources/views/livewire/account/home.blade.php:21.',

        'x192.memberships' => 'A second registration of the same component (X192\Ui\MembershipsList) from the module\'s hand-written routes.php at /memberships under [web,auth] — with no tenant.role, unlike the canonical owner door x-192.memberships-list. Two routes, one screen; raised to Track 1.',
    ];

    $sampleState = sampleStateRoutes();

    $navRoutes = collect(OwnerNav::all())->pluck('route')->all();
    $alsoCurrentFor = [];
    foreach (OwnerNav::all() as $item) {
        $alsoCurrentFor = array_merge($alsoCurrentFor, $item->alsoCurrentFor);
    }
    $navRoutes = array_merge($navRoutes, $alsoCurrentFor);

    $ownerRoutes = [];

    foreach (Route::getRoutes()->getRoutesByMethod()['GET'] ?? [] as $route) {
        $name = $route->getName();
        if (! $name) {
            continue;
        }

        $isAccount = str_starts_with($name, 'account.');
        $rendersLayout = false;

        $action = $route->getAction();
        if (isset($action['controller']) && is_string($action['controller'])) {
            $controller = explode('@', $action['controller'])[0];
            if (class_exists($controller)) {
                $reflection = new ReflectionClass($controller);
                $attributes = $reflection->getAttributes(Layout::class);
                foreach ($attributes as $attribute) {
                    if (($attribute->getArguments()[0] ?? '') === 'components.account.layout') {
                        // Decide owner-ness from the Route rather than the class,
                        // so admin routes sharing the same Livewire class are excluded.
                        // We check the route's middleware for the admin gate.
                        if (! in_array('can:'.AdminAccess::GATE, $route->gatherMiddleware(), true)) {
                            $rendersLayout = true;
                        }
                        break;
                    }
                }
            }
        }

        if ($isAccount || $rendersLayout) {
            $ownerRoutes[] = $name;
        }
    }

    $ownerRoutes = array_unique($ownerRoutes);

    foreach ($ownerRoutes as $route) {
        expect(in_array($route, $navRoutes, true) || array_key_exists($route, $exclusions) || in_array($route, $sampleState, true))
            ->toBeTrue("Owner screen route '{$route}' must either be in OwnerNav, EXCLUSIONS, or SAMPLE_STATE.");
    }
});

test('nav entries survive real get', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = Business::provision([
        'owner_user_id' => $user->id,
        'name' => 'Test Business',
    ]);

    foreach (OwnerNav::all() as $item) {
        $url = route($item->route);
        $response = $this->actingAs($user)->get($url);
        $response->assertOk();
    }
});

test('every sample-state exclusion is still a sample', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = Business::provision([
        'owner_user_id' => $user->id,
        'name' => 'Test Business',
    ]);

    $sampleState = sampleStateRoutes();

    foreach ($sampleState as $route) {
        $url = route($route);
        $response = $this->actingAs($user)->get($url);

        $message = "`{$route}` no longer renders `<x-surface.sample-state>`. It is a real screen now — move it into `OwnerNav` and drop it from `SAMPLE_STATE`.";

        expect(str_contains($response->getContent(), 'not built yet'))
            ->toBeTrue($message);
    }
});
