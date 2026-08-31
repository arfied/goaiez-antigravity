<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Google Places
|--------------------------------------------------------------------------
|
| Operational knobs only. Two things deliberately do NOT live here:
|
|   the API key      App\Support\PlatformCredentials, which is the seam doc 38's
|                    CFG1 credential store replaces the inside of (D-149,
|                    BUILD-PLAN §4.4). Never config() at a call site.
|
|   SKU prices       App\Enums\PlacesSku. A price is not a setting — nobody
|                    should be able to make the audit look cheaper by editing an
|                    environment variable, and the daily spend ceiling is derived
|                    from those prices.
|
| The daily budget itself lives in `platform_settings` (decision 193), not here,
| so it moves without a deploy.
|
*/

return [

    /*
    | Seconds before an outbound Places call is abandoned.
    |
    | Eight, because the whole audit has a 20s perceived budget (`29` §6.2) and
    | it makes three sequential calls. A slow provider must degrade one check,
    | not consume the entire page's patience.
    */
    'timeout' => env('PLACES_TIMEOUT', 8),

];
