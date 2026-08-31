<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| AI layer
|--------------------------------------------------------------------------
|
| Operational knobs only, on the same division of labour as config/places.php.
| Four things deliberately do NOT live here:
|
|   API keys         App\Support\PlatformCredentials — the seam doc 38's CFG1
|                    credential store replaces the inside of (D-149).
|
|   model prices     App\Enums\AiModel. A price is not a setting. Nobody should
|                    be able to make the bill look smaller by editing an
|                    environment variable, and the monthly ceiling is checked
|                    against those prices.
|
|   model per task   `platform_settings`, read by AiRouter (decision 280), so an
|                    operator can move a task onto a different model when a price
|                    moves or a model is retired — without a deploy. AiTask holds
|                    the default it falls back to.
|
|   the monthly cap  App\Support\DefaultsManifest. It lived here until CFG1
|                    landed (decision 501), and a cap in a config file cannot be
|                    what `38` Part 2 requires — an admin-editable value that
|                    moves without a deploy. `AI_MONTHLY_CAP_HUNDREDTHS` is gone
|                    with it; an operator sets the figure in Ops → Platform →
|                    Settings, and `defaults:sync` seeds it.
|
*/

return [

    /*
    | Seconds before an outbound model call is abandoned.
    |
    | Sixty, an order of magnitude above the Places timeout, and the difference
    | is the point: every AI call in this system runs inside a queued job, never
    | on a request path (`29` §2 forbids an LLM call on the synchronous context
    | path). Nobody is watching a spinner, so patience is cheap and a truncated
    | reply is not.
    */
    'timeout' => env('AI_TIMEOUT', 60),

];
