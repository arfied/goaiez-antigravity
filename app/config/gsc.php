<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Google Search Console reads
|--------------------------------------------------------------------------
|
| Operational knobs only, matching config/places.php and config/gbp.php. Four
| things deliberately do NOT live here:
|
|   the credential   there isn't one of ours. Every call carries the *tenant's*
|                    own OAuth grant out of App\Services\Oauth\TokenService, so
|                    there is no platform key to hold — which is also why this
|                    file has no App\Support\PlatformCredentials entry beside it.
|                    The client id and secret behind the connect flow are
|                    Socialite's, in config/services.php.
|
|   the scopes       config/oauth.php, under the `gsc` provider, with the live
|                    documentation date beside them. That file is where every
|                    provider's grant shape lives and splitting it would put two
|                    halves of one OAuth contract in two places.
|
|   the base URL     App\Services\Gsc\GoogleSearchConsoleClient, as a literal
|                    const. It is a fact about somebody else's API rather than a
|                    setting, and `40` Part 8's outbound-host lint reads literals
|                    — an env-driven host is one an operator can point anywhere
|                    and one docs/SUBPROCESSOR-INVENTORY.md can no longer verify.
|                    ⚠️ It is NOT www.googleapis.com; the client says why.
|
|   a rate governor  Google's per-project ceiling is 30,000,000 QPD and this
|                    slice makes one call per location per day
|                    (https://developers.google.com/webmaster-tools/limits, read
|                    2026-08-05). The limit that could actually bite is the
|                    per-user 1,200 QPM, and it is scoped to the *tenant's* Google
|                    account rather than ours — so a governor here would be a
|                    knob nobody can tune correctly against a ceiling nobody can
|                    observe. Named rather than guessed at, which is decision
|                    499's posture on register freshness.
|
*/

return [

    /*
    | Seconds before an outbound Search Console call is abandoned.
    |
    | Fifteen rather than the Zernio file's ten and the Places file's eight.
    | Nothing here sits on a visitor-facing path — every caller is a queued job
    | or an Ops command, so the cost of waiting is a worker slot rather than
    | somebody's patience — and a Search Analytics query over a month of daily
    | rows is a genuine aggregation on Google's side rather than a key lookup.
    | An abandoned sync just runs again tomorrow against the same window.
    */
    'timeout' => (int) env('GSC_TIMEOUT', 15),

    /*
    | How many days back a sync asks for, and how far back it starts.
    |
    | ⚠️ `lookback_days` IS NOT A REPORTING WINDOW. It is how much history one
    | sync re-fetches on every run, which has to be longer than Google's
    | finalisation lag or a day that was still moving when we first read it never
    | gets corrected. Google documents the lag as roughly two days with the tail
    | trailing further and states no fixed number, so this is deliberately
    | generous rather than tight — re-reading a settled day costs one row in an
    | upsert and nothing else.
    |
    | `skip_recent_days` is the opposite end: the most recent day or two is
    | always incomplete, and while `metadata.firstIncompleteDate` means we can
    | *label* it rather than hide it, asking for it at all buys a row that will
    | be replaced tomorrow. Zero would still be correct; two is what Google's own
    | reporting does.
    |
    | ⚠️ Neither is a threshold in doc `38` Part 2's sense — they are shapes of a
    | vendor request, like a field mask, not a number an operator tunes to change
    | what the product does. A Defaults Registry key here would invite exactly
    | the "raise a third party's ceiling" edit ZernioGbpClient::MAX_LIMIT refuses.
    */
    'lookback_days' => (int) env('GSC_LOOKBACK_DAYS', 30),

    'skip_recent_days' => (int) env('GSC_SKIP_RECENT_DAYS', 2),

];
