<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Industries\IndustryPages;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The `features.industry_pages` gate — CC-3 §3.
 *
 * ⚠️ **404 RATHER THAN 503 OR A HOLDING PAGE.** A flag that is off means these
 * addresses do not exist yet, and that is what a crawler and a visitor should
 * both be told. Anything else publishes the URL — a 503 invites a retry and a
 * holding page is a thin page a hundred times over, which is the one thing a
 * cluster of new pages must not be on the day it is first crawled.
 *
 * ⚠️ **A MIDDLEWARE RATHER THAN A LINE IN EACH ACTION** because the sitemaps are
 * on a second controller: a gate repeated in four methods is a gate three of them
 * keep. The route file applies it to the whole block.
 */
final readonly class RequireIndustryPages
{
    public function __construct(private IndustryPages $pages) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->pages->enabled(), 404);

        return $next($request);
    }
}
