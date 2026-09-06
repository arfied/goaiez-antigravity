<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Business;
use App\Support\Account\OwnerNav;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Livewire\Attributes\Layout;
use ReflectionClass;

class OwnerNavTest extends TestCase
{
    /**
     * @see \App\Modules\X110\Ui\views\pixel-install.blade.php
     * @see \App\Modules\X110\Ui\views\widget-install.blade.php
     * 
     * ⛔ OWED TO UI-47: The cross-link half.
     * "AN INVITATION OUT OF AN EMPTY STATE, WHICH IS THE ONE SHAPE
     * Architecture/OwnerNavTest ADMITS"
     * (Quoted from pixel-install.blade.php:128–131 and widget-install.blade.php:88–91)
     */

    private const EXCLUSIONS = [
        'account.data-export.download' => 'This is a file download route, not an interactive screen.',
        'account.inbound-media.show' => 'This is a media endpoint returning images or audio, not a rendered HTML screen.',
        'account.suspended' => 'This is an interruption screen shown when the account is suspended, not a navigable screen in the normal state.',
        'account.voicemail.recording' => 'This is a media endpoint returning an audio file, not an HTML screen.',
        'account.content-topics' => 'This is an internal sub-screen for content topics, not a standalone top-level screen.',
        
        'x-110.visitors-live' => 'This is a module-specific detail screen, reached via the module UI rather than top-level navigation.',
        'x-110.today' => 'This is a module-specific detail screen, reached via the module UI rather than top-level navigation.',
        'x-110.cooling' => 'This is a module-specific detail screen, reached via the module UI rather than top-level navigation.',
        'x-110.abandoned-forms' => 'This is a module-specific detail screen, reached via the module UI rather than top-level navigation.',
        'x-110.install-verify' => 'This is a module-specific detail screen, reached via the module UI rather than top-level navigation.',
        'x-110.tag-version-per' => 'This is a module-specific detail screen, reached via the module UI rather than top-level navigation.',
        
        'x-110.visitors-live.admin' => 'This is an admin view for the module screen, not an owner screen.',
        'x-110.today.admin' => 'This is an admin view for the module screen, not an owner screen.',
        'x-110.cooling.admin' => 'This is an admin view for the module screen, not an owner screen.',
        'x-110.abandoned-forms.admin' => 'This is an admin view for the module screen, not an owner screen.',
        'x-110.install-verify.admin' => 'This is an admin view for the module screen, not an owner screen.',
        'x-110.tag-version-per.admin' => 'This is an admin view for the module screen, not an owner screen.',
        
        'x-199.money-paid-today' => 'This is a module-specific detail screen, reached via the module UI rather than top-level navigation.',
        'x-199.unpaid' => 'This is a module-specific detail screen, reached via the module UI rather than top-level navigation.',
        'x-199.declines' => 'This is a module-specific detail screen, reached via the module UI rather than top-level navigation.',
        'x-199.invoices' => 'This is a module-specific detail screen, reached via the module UI rather than top-level navigation.',
        'x-199.credits' => 'This is a module-specific detail screen, reached via the module UI rather than top-level navigation.',
        
        'x-138.attribution-row' => 'This is a module-specific detail screen, reached via the module UI rather than top-level navigation.',
        'x-138.roi-dashboard' => 'This is a module-specific detail screen, reached via the module UI rather than top-level navigation.',
        
        'x-192.memberships-list' => 'This is a module-specific detail screen, reached via the module UI rather than top-level navigation.',
        'x192.memberships' => 'This is a module-specific detail screen, reached via the module UI rather than top-level navigation.',
    ];

    public function test_every_owner_screen_route_has_nav_or_exclusion(): void
    {
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
                            $rendersLayout = true;
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
            $this->assertTrue(
                in_array($route, $navRoutes, true) || array_key_exists($route, self::EXCLUSIONS),
                "Owner screen route '{$route}' must either be in OwnerNav or EXCLUSIONS."
            );
        }
    }

    public function test_nav_entries_survive_real_get(): void
    {
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
    }
}
