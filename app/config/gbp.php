<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Google Business Profile reads
|--------------------------------------------------------------------------
|
| Operational knobs only, matching config/places.php. Three things deliberately
| do NOT live here:
|
|   the API key      App\Support\PlatformCredentials — the seam doc 38's CFG1
|                    credential store replaces the inside of (D-149). Never
|                    config() at a call site.
|
|   the on/off flag  the Defaults Registry, key `gbp.zernio_enabled`. A vendor
|                    integration that can only be switched off by a deploy is
|                    one that stays on through an incident.
|
|   the base URL     App\Services\Gbp\ZernioGbpClient, as a literal const. It is
|                    a fact about somebody else's API rather than a setting, and
|                    `40` Part 8's outbound-host lint reads literals — an
|                    env-driven host is one an operator can point anywhere and
|                    one the subprocessor inventory can no longer verify.
|
*/

return [

    /*
    | Seconds before an outbound Zernio call is abandoned.
    |
    | Ten rather than the Places file's eight: nothing here sits on a visitor-
    | facing path with a perceived-time budget. Every caller is a queued job, so
    | the cost of waiting is a worker slot rather than somebody's patience — and
    | a review sync abandoned early just runs again with the same cursor.
    */
    'timeout' => env('GBP_TIMEOUT', 10),

];
