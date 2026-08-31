<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Global kill switch
    |--------------------------------------------------------------------------
    |
    | Stops every automation everywhere, before any side effect. Config rather
    | than a database flag on purpose: a kill switch that needs a working
    | database to be honoured is not much of a kill switch, and the moment you
    | most want one is usually the moment something is badly wrong.
    |
    */

    'kill_switch' => env('AUTOPILOT_KILL_SWITCH', false),

    /*
    |--------------------------------------------------------------------------
    | Disabled automations
    |--------------------------------------------------------------------------
    |
    | Automation keys to stop individually, for when one integration is
    | misbehaving and the rest should keep running. Comma-separated in the
    | environment.
    |
    */

    'disabled_automations' => array_filter(
        explode(',', (string) env('AUTOPILOT_DISABLED', '')),
    ),

];
