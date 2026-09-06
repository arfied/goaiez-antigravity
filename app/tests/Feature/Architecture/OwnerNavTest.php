<?php

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
use App\Support\Account\OwnerNav;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Layout;

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
        'x-199.unpaid' => 'This is embedded via @livewire in resources/views/livewire/account/home.blade.php:20.',
    ];

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
                        if (! in_array('can:access-admin', $route->gatherMiddleware())) {
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
        expect(in_array($route, $navRoutes, true) || array_key_exists($route, $exclusions))
            ->toBeTrue("Owner screen route '{$route}' must either be in OwnerNav or EXCLUSIONS.");
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
