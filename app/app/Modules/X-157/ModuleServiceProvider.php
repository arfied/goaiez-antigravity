<?php

declare(strict_types=1);

namespace App\Modules\X157;

use App\Models\Business;
use App\Modules\X103\Events\PageUnpublished;
use App\Modules\X103\Events\SitePublished;
use App\Modules\X108\Actions\WaitlistJoinAction;
use App\Modules\X121\Actions\PersonUpsertAction;
use App\Modules\X137\Actions\CallAttributeAction;
use App\Modules\X155\Actions\FormCaptureAction;
use App\Modules\X157\Actions\EdgeDeployAction;
use App\Modules\X157\Actions\ServeDeploymentAction;
use App\Modules\X157\Domain\DnsResolver;
use App\Modules\X157\Domain\SystemDnsResolver;
use App\Modules\X157\Http\Middleware\ServeVerifiedCustomDomain;
use App\Modules\X157\Models\Deployment;
use App\Modules\X157\Models\EdgeZone;
use App\Modules\X157\Ui\EdgeStatusPer;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

final class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DnsResolver::class, SystemDnsResolver::class);
    }

    public function boot(): void
    {
        $this->app['router']->pushMiddlewareToGroup('web', ServeVerifiedCustomDomain::class);
        $this->loadRoutesFrom(__DIR__.'/routes.generated.php');

        $this->loadMigrationsFrom(__DIR__.'/Database/migrations');
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-157');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-157.edge-status-per', EdgeStatusPer::class);
        }

        Route::get('/sites/{business}/{deploy_hash}', function (string $business, string $deployHash) {
            return app(ServeDeploymentAction::class)->page((int) $business, $deployHash);
        })->name('x-157.site')->whereNumber('business');

        Route::get('/sites/{business}/{deploy_hash}/media/{file?}', function (string $business, string $deployHash, string $file = '') {
            if ($file === '') {
                abort(404);
            }
            $businessId = (int) $business;
            Tenancy::set($businessId);

            $deployment = Deployment::where('business_id', $businessId)->where('deploy_hash', $deployHash)->firstOrFail();
            abort_if($deployment->status !== 'deployed', 404);

            $zone = $deployment->edgeZone;
            abort_if($zone === null || ! $zone->has_valid_ssl, 404);

            $content = Storage::disk('local')->get("site-inventory/{$businessId}/{$file}");
            abort_if($content === null, 404);

            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content);

            return response($content, 200)->header('Content-Type', $mime ?: 'application/octet-stream');
        })->name('x-157.site.media')->whereNumber('business')->where('file', '[A-Za-z0-9._-]+');

        Route::get('/sites/{business}/{deploy_hash}/dni', function (string $business, string $deployHash, Request $request) {
            $businessId = (int) $business;
            Tenancy::set($businessId);

            $deployment = Deployment::where('business_id', $businessId)->where('deploy_hash', $deployHash)->firstOrFail();
            abort_if($deployment->status !== 'deployed', 404);

            $zone = $deployment->edgeZone;
            abort_if($zone === null || ! $zone->has_valid_ssl, 404);

            try {
                // A token that is not a string is not a token, so it is normalised to blank and refused by the message the action already declares.
                $rawToken = $request->input('visitor_session_token', '');
                $token = app(CallAttributeAction::class)->allocateFromPool(
                    businessId: $businessId,
                    visitorSessionToken: is_string($rawToken) ? $rawToken : ''
                );
            } catch (\DomainException $e) {
                return response()->json(['error' => $e->getMessage()], 409);
            }

            return response()->json([
                'number' => $token->allocated_number,
                'status' => $token->status,
            ]);
        })->whereNumber('business');

        Route::post('/sites/{business}/{deploy_hash}/forms/{form}', function (string $business, string $deployHash, string $form, Request $request) {
            $businessId = (int) $business;
            Tenancy::set($businessId);

            $deployment = Deployment::where('business_id', $businessId)->where('deploy_hash', $deployHash)->firstOrFail();
            abort_if($deployment->status !== 'deployed', 404);
            $zone = $deployment->edgeZone;
            abort_if($zone === null || ! $zone->has_valid_ssl, 404);

            $result = app(FormCaptureAction::class)->handle(
                businessId: $businessId,
                formDefinitionId: (int) $form,
                payload: $request->all(),
                ipAddress: $request->ip(),
            );

            return response()->json($result, $result['status'] === 'captured' ? 201 : 422);
        })->whereNumber('business')->whereNumber('form');

        Route::post('/sites/{business}/{deploy_hash}/book', function (string $business, string $deployHash, Request $request) {
            $businessId = (int) $business;
            Tenancy::set($businessId);

            $deployment = Deployment::where('business_id', $businessId)->where('deploy_hash', $deployHash)->firstOrFail();
            abort_if($deployment->status !== 'deployed', 404);
            $zone = $deployment->edgeZone;
            abort_if($zone === null || ! $zone->has_valid_ssl, 404);

            $age = $request->input('age');
            if (is_numeric($age) && (int) $age < 18) {
                return response()->json(['status' => 'rejected', 'reason' => 'under_18'], 422);
            }
            try {
                $data = $request->validate([
                    'name' => ['required', 'string', 'max:120'],
                    'phone' => ['required', 'string', 'max:40'],
                    'service' => ['required', 'string', 'max:120'],
                    'preferred_date' => ['required', 'date', 'after_or_equal:today'],
                    'email' => ['nullable', 'email', 'max:190'],
                ]);
            } catch (ValidationException $e) {
                return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
            }

            $upsert = app(PersonUpsertAction::class)->upsertByPhone($businessId, $data['phone'], ['first_name' => $data['name'], 'email' => $data['email'] ?? null], false);
            $waitlist = app(WaitlistJoinAction::class)->handle($businessId, $data['name'], $data['phone'], $data['service'], (string) $data['preferred_date'], false, $deployHash);

            return response()->json(['status' => 'requested', 'waitlist_id' => (int) $waitlist->id, 'person_id' => (int) $upsert['id']], 201);
        })->whereNumber('business');

        Route::get('/sites/{business}/{deploy_hash}/sitemap.xml', function (string $business, string $deployHash) {
            return app(ServeDeploymentAction::class)->sitemap((int) $business, $deployHash);
        })->whereNumber('business');

        Route::get('/sites/{business}/{deploy_hash}/robots.txt', function (string $business, string $deployHash) {
            return app(ServeDeploymentAction::class)->robots((int) $business, $deployHash);
        })->whereNumber('business');

        Route::get('/sites/{business}/{deploy_hash}/llms.txt', fn (string $business, string $deployHash) => app(ServeDeploymentAction::class)->llms((int) $business, $deployHash))->whereNumber('business');

        Event::listen(PageUnpublished::class, function (PageUnpublished $e): void {
            Deployment::where('business_id', $e->businessId)
                ->where('page_id', $e->pageId)
                ->where('status', 'deployed')
                ->update(['status' => 'unpublished']);
        });

        Event::listen(SitePublished::class, function (SitePublished $event): void {
            $zone = EdgeZone::where('business_id', $event->businessId)
                ->where('has_valid_ssl', true)
                ->latest('id')
                ->first();

            if ($zone === null) {
                return;
            }

            // A deploy failure is contained here: the page stays published (R245,
            // 2026-09-05). Publishing is X-103's door and the edge is a separate
            // concern — the tenant must not lose the content because the edge did.
            try {
                app(EdgeDeployAction::class)->handle(
                    businessId: $event->businessId,
                    edgeZoneId: $zone->id,
                    pageId: $event->pageId,
                    commitId: $event->commitId,
                    businessName: Business::where('id', $event->businessId)->value('name'),
                );
            } catch (\Throwable $e) {
                // Contained (the page stays published) but never silent: the owner's
                // Pages screen reads "published, not yet deployed" and somebody has
                // to be able to find out why (wave 810).
                report($e);

                return;
            }
        });
    }
}
