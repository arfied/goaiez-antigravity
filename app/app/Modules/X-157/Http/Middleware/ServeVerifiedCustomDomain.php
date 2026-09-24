<?php

namespace App\Modules\X157\Http\Middleware;

use App\Modules\X103\Actions\PageReadAction;
use App\Modules\X157\Actions\ServeDeploymentAction;
use App\Modules\X157\Models\CustomDomainRequest;
use App\Modules\X157\Models\Deployment;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ServeVerifiedCustomDomain
{
    public function handle(Request $request, Closure $next)
    {
        $host = strtolower($request->getHost());
        $appHost = strtolower(parse_url(config('app.url'), PHP_URL_HOST));

        if ($host === $appHost) {
            return $next($request);
        }

        $row = CustomDomainRequest::withoutGlobalScopes()->where('domain', $host)->where('status', 'verified')->first();
        if (! $row) {
            Log::info('ROW IS NULL FOR HOST '.$host);

            return $next($request);
        }

        Tenancy::set($row->business_id);

        $action = app(ServeDeploymentAction::class);
        $path = trim($request->path(), '/');

        if ($path === 'sitemap.xml' || $path === 'robots.txt') {
            $latest = $this->latestDeployment($row->business_id, null);
            if (! $latest) {
                return $next($request);
            }

            return $path === 'sitemap.xml'
                ? $action->sitemap($row->business_id, $latest->deploy_hash, $host)
                : $action->robots($row->business_id, $latest->deploy_hash);
        }

        // "/" is the home page; "/<slug>" is that page. A slug nobody published
        // falls through to the app (a 404 there is honest; a stranger's page is not).
        $slug = $path === '' ? 'home' : $path;
        $page = app(PageReadAction::class)->publishedForSlugs($row->business_id, [$slug])->first();
        $deployment = $page ? $this->latestDeployment($row->business_id, (int) $page->id) : null;

        if ($deployment === null && $path === '') {
            // No page called "home" yet: the previous behaviour, so a one-page site
            // whose only page has another slug still answers at the root.
            $deployment = $this->latestDeployment($row->business_id, null);
        }

        if (! $deployment) {
            Log::info('DEPLOYMENT IS NULL FOR HOST '.$host.' BIZ '.$row->business_id.' PATH /'.$path);

            return $next($request);
        }

        $arm = $page ? app(\App\Modules\X103\Actions\PageVariantReadAction::class)->runningFor($row->business_id, (int) $page->id) : null;
        if ($arm !== null && $deployment->deploy_hash === $arm['control_hash']) {
            $gpc = (string) $request->headers->get('Sec-GPC', '') === '1';
            $cookieName = 'gz_arm_'.$arm['id'];
            $chosen = $gpc ? 'control' : (string) $request->cookie($cookieName, '');
            if (! in_array($chosen, ['control', 'variant'], true)) {
                $chosen = random_int(0, 1) === 1 ? 'variant' : 'control';
            }
            $hash = $chosen === 'variant' ? $arm['variant_hash'] : $arm['control_hash'];
            $response = $action->page($row->business_id, $hash);
            if (! $gpc && $request->cookie($cookieName) !== $chosen) {
                $response->withCookie(cookie($cookieName, $chosen, 60 * 24 * 90, '/', null, true, true, false, 'lax'));
            }
            return $response;
        }

        return $action->page($row->business_id, $deployment->deploy_hash);
    }

    /** The newest deployed, SSL-valid deployment — of one page, or of any page when $pageId is null. */
    private function latestDeployment(int $businessId, ?int $pageId): ?Deployment
    {
        return Deployment::where('business_id', $businessId)
            ->where('status', 'deployed')
            ->when($pageId !== null, fn ($q) => $q->where('page_id', $pageId))
            ->whereNull('page_variant_id')
            ->whereHas('edgeZone', function ($q) {
                $q->where('has_valid_ssl', true);
            })
            ->latest('id')
            ->first();
    }
}
