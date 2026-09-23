<?php

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
use App\Support\Account\OwnerNav;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Livewire\Attributes\Layout;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        'x-110.tag-version-per',
    ];
}

function ownerRouteExclusions(): array
{
    return [
        'account.suspended' => 'This is an interruption screen shown when the account is suspended, not a navigable screen in the normal state.',
        'account.content-topics' => 'This is an internal sub-screen for content topics, not a standalone top-level screen.',
        'x-189.brand-card-editor' => 'This is a modal or sub-screen for configuring the brand card, not a top-level nav item.',

        'x-110.today' => 'This is embedded via @livewire in resources/views/livewire/account/home.blade.php:18.',
        'x-199.money-paid-today' => 'This is embedded via @livewire in resources/views/livewire/account/home.blade.php:19.',
        'x-199.unpaid' => 'This is embedded via @livewire in resources/views/livewire/account/home.blade.php:20.',
        'x-199.declines' => 'This is embedded via @livewire in resources/views/livewire/account/home.blade.php:21.',

        'x192.memberships' => 'A second registration of the same component (X192\Ui\MembershipsList) from the module\'s hand-written routes.php at /memberships under [web,auth] — with no tenant.role, unlike the canonical owner door x-192.memberships-list. Two routes, one screen; raised to Track 1.',
        'c-mail.complaintbounce-board' => 'Not in navigation yet.',
        'x-202.item' => 'detail page reached from the queue',
    ];
}

function ownerScreenRoutes(): array
{
    $ownerRoutes = [];

    foreach (Route::getRoutes()->getRoutesByMethod()['GET'] ?? [] as $route) {
        $name = $route->getName();
        if (! $name) {
            continue;
        }

        $isAccount = str_starts_with($name, 'account.');
        $rendersLayout = false;
        $isStream = false;

        $action = $route->getAction();
        if (isset($action['controller']) && is_string($action['controller'])) {
            $controller = explode('@', $action['controller'])[0];
            if (class_exists($controller)) {
                $reflection = new ReflectionClass($controller);

                // A route is not an owner screen when its action class declares __invoke and every type
                // in that return type is StreamedResponse or BinaryFileResponse. A written exclusion list
                // of media endpoints rots silently, so we derive the fact directly from the class.
                if ($reflection->hasMethod('__invoke')) {
                    $returnType = $reflection->getMethod('__invoke')->getReturnType();
                    if ($returnType) {
                        $types = [];
                        if ($returnType instanceof ReflectionUnionType) {
                            $types = $returnType->getTypes();
                        } elseif ($returnType instanceof ReflectionNamedType) {
                            $types = [$returnType];
                        }

                        if (! empty($types)) {
                            $allStreams = true;
                            foreach ($types as $type) {
                                $typeName = $type->getName();
                                if (! is_a($typeName, StreamedResponse::class, true) &&
                                    ! is_a($typeName, BinaryFileResponse::class, true)) {
                                    $allStreams = false;
                                    break;
                                }
                            }
                            if ($allStreams) {
                                $isStream = true;
                            }
                        }
                    }
                }

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

        if ($isStream) {
            continue;
        }

        if ($isAccount || $rendersLayout) {
            $ownerRoutes[] = $name;
        }
    }

    return array_unique($ownerRoutes);
}

/**
 * @see \App\Modules\X110\Ui\views\pixel-install.blade.php
 * @see \App\Modules\X110\Ui\views\widget-install.blade.php
 *
 * "AN INVITATION OUT OF AN EMPTY STATE, WHICH IS THE ONE SHAPE
 * Architecture/OwnerNavTest ADMITS"
 * (Quoted from pixel-install.blade.php:128–131 and widget-install.blade.php:88–91)
 */
test('every owner screen route has nav or exclusion', function () {
    $exclusions = ownerRouteExclusions();
    $sampleState = sampleStateRoutes();
    $ownerRoutes = ownerScreenRoutes();

    $navRoutes = collect(OwnerNav::all())->pluck('route')->all();
    $alsoCurrentFor = [];
    foreach (OwnerNav::all() as $item) {
        $alsoCurrentFor = array_merge($alsoCurrentFor, $item->alsoCurrentFor);
    }
    $navRoutes = array_merge($navRoutes, $alsoCurrentFor);

    foreach ($ownerRoutes as $route) {
        expect(in_array($route, $navRoutes, true) || array_key_exists($route, $exclusions) || in_array($route, $sampleState, true))
            ->toBeTrue("Owner screen route '{$route}' must either be in OwnerNav, EXCLUSIONS, or SAMPLE_STATE.");
    }
});

/**
 * We derive the allowable cross-links from OwnerNav::all() instead of listing them.
 * A written exclusion list of permissible cross-links rots silently: if a screen is
 * dropped from the nav, its hand-written cross-links remain, breaking the nav's
 * completeness as the sole map of the system.
 * By computing this at runtime, a red here means a screen is now reachable only
 * by knowing its URL. That is a bug in the app, not in the test.
 * Owner-ness of the source is a fact about where the blade lives:
 * app/Modules/[Module]/Ui/views/** and app/resources/views/livewire/account/**
 */
test('owner screens only cross-link to routes in the nav', function () {
    $sourceDirs = [
        'app/Modules/*/Ui/views',
        'resources/views/livewire/account',
        'resources/views/components/account',
        'resources/views/livewire/advanced',
    ];

    $files = [];
    foreach ($sourceDirs as $dirPattern) {
        foreach (glob(base_path($dirPattern)) as $dir) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                    $files[] = $file->getPathname();
                }
            }
        }
    }

    $navRoutes = collect(OwnerNav::all())->pluck('route')->all();
    foreach (OwnerNav::all() as $item) {
        $navRoutes = array_merge($navRoutes, $item->alsoCurrentFor);
    }
    $navRoutes = array_unique($navRoutes);

    $ownerRoutes = ownerScreenRoutes();

    $fails = [];

    foreach ($files as $file) {
        $content = file_get_contents($file);
        if (preg_match_all('/(?:href|:href)\s*=\s*(["\'])(.*?)\1/si', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $hrefContent = $match[2];
                if (preg_match_all('/route\s*\(\s*[\'"]([^\'"]+)[\'"]/', $hrefContent, $routeMatches)) {
                    foreach ($routeMatches[1] as $routeTarget) {
                        if (in_array($routeTarget, $ownerRoutes, true)) {
                            if (! in_array($routeTarget, $navRoutes, true)) {
                                $fails[] = basename($file)." links to {$routeTarget}";
                            }
                        }
                    }
                }
            }
        }
    }

    expect($fails)->toBeEmpty('An owner screen\'s href may target another owner screen only if that route is in OwnerNav::all(), or in some entry\'s alsoCurrentFor.');
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

        $count = substr_count($response->getContent(), '<h1');
        expect($count)->toBe(1, "Route {$item->route} has {$count} <h1 tags");
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

test('the reachability check states the size of its own blind spot', function () {
    $admitted = ownerScreenRoutes();

    $tenantRole = [];
    foreach (Route::getRoutes()->getRoutesByMethod()['GET'] ?? [] as $route) {
        $name = $route->getName();
        if (! $name) {
            continue;
        }
        if (in_array('tenant.role', $route->gatherMiddleware(), true)) {
            $tenantRole[] = $name;
        }
    }

    $invisible = array_values(array_diff(array_unique($tenantRole), $admitted));

    expect(count($invisible))->toBe(50, 'If it went UP, a new module route ships behind tenant.role without opting into the owner layout, so it is reachable by URL and invisible to every check in this file. That is a regression. If it went DOWN, somebody converted a screen properly. Lower the number and record it.');

    $withLayout = 0;
    $withoutLayout = 0;
    $unbuilt = 0;
    $built = 0;
    $unresolved = 0;

    $allRoutes = Route::getRoutes()->getRoutesByMethod()['GET'] ?? [];

    foreach ($invisible as $routeName) {
        $hasLayout = false;
        $controllerClass = null;

        foreach ($allRoutes as $routeObj) {
            if ($routeObj->getName() === $routeName) {
                $action = $routeObj->getAction();
                if (isset($action['controller']) && is_string($action['controller'])) {
                    $controllerClass = explode('@', $action['controller'])[0];
                    if (class_exists($controllerClass)) {
                        $reflection = new ReflectionClass($controllerClass);
                        $attributes = $reflection->getAttributes(Layout::class);
                        if (! empty($attributes)) {
                            $hasLayout = true;
                        }
                    }
                }
                break;
            }
        }

        if ($hasLayout) {
            $withLayout++;
        } else {
            $withoutLayout++;
            $viewPath = null;
            if ($controllerClass && class_exists($controllerClass)) {
                $reflection = new ReflectionClass($controllerClass);
                $content = file_get_contents($reflection->getFileName());
                if (preg_match("/view\(\s*['\"]([^'\"]+)['\"]/", $content, $matches)) {
                    $viewName = $matches[1];
                    if (View::exists($viewName)) {
                        $viewPath = View::make($viewName)->getPath();
                    }
                }
            }

            if ($viewPath && file_exists($viewPath)) {
                $viewContent = file_get_contents($viewPath);
                if (str_contains($viewContent, 'x-surface.sample-state')) {
                    $unbuilt++;
                } else {
                    $built++;
                }
            } else {
                $unresolved++;
            }
        }
    }

    expect($withLayout)->toBe(3, 'If it went UP, a new module route opted into the owner layout but forgot the auth gate, or (more likely) fell back from using AdminNav to hand-authoring a layout, so it is reachable by URL but carries no nav. That is a regression. If it went DOWN, somebody converted a screen properly. Lower the number and record it.');
    expect($withoutLayout)->toBe(47, 'If it went UP, a new module route shipped with no #[Layout] at all, falling through to the staff console. If it went DOWN, one was converted, or built out.');
    expect($withLayout + $withoutLayout)->toBe(count($invisible));

    expect($unbuilt)->toBe(17, 'If it went UP, a new unbuilt route shipped falling through to the staff console, or a screen went back to carrying the banner. If it went DOWN, an unbuilt route was built out, converted, or lost its route.');
    expect($built)->toBe(30, 'If it went UP, a new built route shipped falling through to the staff console, or an unbuilt route was built out. If it went DOWN, a built route was converted onto the owner layout, went back to carrying the banner, or lost its route.');
    expect($unresolved)->toBe(0, 'If it went UP, a new route falling through to the staff console could not resolve its view. If it went DOWN, an unresolved route was fixed or converted.');
});
