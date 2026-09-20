<?php
$line = "@agent_reachable `conversation.read` ⭐ *(`P-209`'s BACKFILL GATE, 2026-08-27 — **DERIVED, never guessed**: read-shaped and proposal actions only. **Anything that spends, sends, deletes or changes config is NOT reachable** — the agent proposes it through the approval desk.)*` · none";
preg_match_all(
    '/@agent_reachable\s+((?:`?[a-z*][a-z0-9_.*]*`?)(?:[^\n]*))/i',
    $line,
    $all,
    PREG_SET_ORDER
);
require 'app/vendor/autoload.php';
$app = require_once 'app/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$v = App\Doctor\DeclarationParser::clean($all[0][1]);
echo "CLEANED: '$v'\n";
