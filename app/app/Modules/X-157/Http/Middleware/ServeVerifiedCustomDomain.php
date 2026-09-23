<?php

namespace App\Modules\X157\Http\Middleware;

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

        $deployment = Deployment::where('business_id', $row->business_id)
            ->where('status', 'deployed')
            ->whereHas('edgeZone', function ($q) {
                $q->where('has_valid_ssl', true);
            })
            ->latest('id')
            ->first();

        if (! $deployment) {
            Log::info('DEPLOYMENT IS NULL FOR HOST '.$host.' BIZ '.$row->business_id);

            return $next($request);
        }

        $action = app(ServeDeploymentAction::class);
        $path = $request->path();

        if ($path === '/' || $path === '') {
            return $action->page($row->business_id, $deployment->deploy_hash);
        }

        if ($path === 'sitemap.xml') {
            return $action->sitemap($row->business_id, $deployment->deploy_hash);
        }

        if ($path === 'robots.txt') {
            return $action->robots($row->business_id, $deployment->deploy_hash);
        }

        return $next($request);
    }
}
