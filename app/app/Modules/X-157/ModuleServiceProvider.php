<?php

declare(strict_types=1);

namespace App\Modules\X157;

use App\Models\Business;
use App\Modules\X103\Events\PageUnpublished;
use App\Modules\X103\Events\SitePublished;
use App\Modules\X137\Actions\CallAttributeAction;
use App\Modules\X155\Actions\FormCaptureAction;
use App\Modules\X157\Actions\EdgeDeployAction;
use App\Modules\X157\Actions\SitemapRenderAction;
use App\Modules\X157\Models\Deployment;
use App\Modules\X157\Models\EdgeZone;
use App\Modules\X157\Ui\EdgeStatusPer;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.generated.php');

        $this->loadMigrationsFrom(__DIR__.'/Database/migrations');
        $this->loadViewsFrom(__DIR__.'/Ui/views', 'x-157');

        if (class_exists(Livewire::class)) {
            Livewire::component('x-157.edge-status-per', EdgeStatusPer::class);
        }

        Route::get('/sites/{business}/{deploy_hash}', function (string $business, string $deployHash) {
            $business = (int) $business;
            Tenancy::set($business);

            $deployment = Deployment::where('business_id', $business)->where('deploy_hash', $deployHash)->firstOrFail();
            abort_if($deployment->status !== 'deployed', 404);

            $zone = $deployment->edgeZone;
            abort_if($zone === null || ! $zone->has_valid_ssl, 404);

            $html = Storage::disk('local')->get("sites/{$deployHash}.html");
            abort_if($html === null, 404);

            return response($html, 200)->header('Content-Type', 'text/html');
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

        Route::get('/sites/{business}/{deploy_hash}/sitemap.xml', function (string $business, string $deployHash) {
            $businessId = (int) $business;
            Tenancy::set($businessId);

            $deployment = Deployment::where('business_id', $businessId)->where('deploy_hash', $deployHash)->firstOrFail();
            abort_if($deployment->status !== 'deployed', 404);

            $zone = $deployment->edgeZone;
            abort_if($zone === null || ! $zone->has_valid_ssl, 404);

            $xml = app(SitemapRenderAction::class)->handle($businessId);

            return response($xml, 200)->header('Content-Type', 'application/xml');
        })->whereNumber('business');

        Route::get('/sites/{business}/{deploy_hash}/robots.txt', function (string $business, string $deployHash) {
            $businessId = (int) $business;
            Tenancy::set($businessId);

            $deployment = Deployment::where('business_id', $businessId)->where('deploy_hash', $deployHash)->firstOrFail();
            abort_if($deployment->status !== 'deployed', 404);

            $zone = $deployment->edgeZone;
            abort_if($zone === null || ! $zone->has_valid_ssl, 404);

            $sitemapUrl = route('x-157.site', ['business' => $businessId, 'deploy_hash' => $deployHash]).'/sitemap.xml';

            $txt = "User-agent: *\n";
            $txt .= "Allow: /sites/{$businessId}/\n";
            $txt .= "Disallow: /sites/{$businessId}/{$deployHash}/dni\n";
            $txt .= "Disallow: /sites/{$businessId}/{$deployHash}/forms/\n";
            $txt .= "Sitemap: {$sitemapUrl}\n";

            return response($txt, 200)->header('Content-Type', 'text/plain');
        })->whereNumber('business');

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
            } catch (\Throwable) {
                return;
            }
        });
    }
}
