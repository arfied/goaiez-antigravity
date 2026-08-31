<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| FetchGateway
|--------------------------------------------------------------------------
|
| Operational knobs for the F0 gateway (`40` Part 6). Policy does NOT live here
| — method ceilings, robots, kill switches and rate budgets are `fetch_sources`
| rows, because `40` §6.2 requires that raising a ceiling need a counsel-note on
| the row rather than an edit somebody can slip into a pull request.
|
*/

return [

    /*
    | Seconds before an outbound page fetch is abandoned.
    |
    | Ten. The audit's whole perceived budget is 20s (`29` §6.2) and the NAP
    | quick-scan is one of four checks, so a slow site degrades its own check
    | rather than the page.
    */
    'timeout' => env('FETCH_TIMEOUT', 10),

    /*
    | The address `/bot` offers a webmaster who would rather write to somebody
    | than edit their robots.txt.
    |
    | ⚠️ NULL BY DEFAULT, AND THE PAGE RENDERS NO CONTACT SECTION WHEN IT IS.
    | `/bot` exists because `RobotsPolicy::USER_AGENT` advertised a URL that did
    | not resolve; printing a mailbox nobody has configured would rebuild that
    | defect one paragraph further down, and a bounced mail from a webmaster
    | asking why we fetched their site is worse than never offering the address.
    |
    | Blocking in robots.txt is the real mechanism and needs no reply from us,
    | so the page is complete without this. Set it when a monitored mailbox
    | exists — and note decision 414: a change here needs `composer deploy`
    | re-run, or the cached config keeps serving null.
    */
    'contact_email' => env('FETCH_CONTACT_EMAIL'),

];
