<?php
require 'app/vendor/autoload.php';
$app = require_once 'app/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$plan = file_get_contents('app/GOAIEZ-MASTER-PLAN.md');
$chunks = preg_split('/(?=@module\s+\*{0,2}(?:X|C)-[A-Za-z0-9]+)/', $plan) ?: [];
foreach ($chunks as $chunk) {
    if (preg_match('/^@module\s+\*{0,2}X-116\b/', $chunk)) {
        preg_match_all(
            '/@agent_reachable\s+((?:`?[a-z*][a-z0-9_.*]*`?)(?:[^\n]*))/i',
            $chunk,
            $all,
            PREG_SET_ORDER
        );
        $best = '';
        foreach ($all as $hit) {
            $value = App\Doctor\DeclarationParser::clean($hit[1]);
            if ($value !== '' && strlen($value) > strlen($best)) {
                $best = $value;
            }
        }
        echo "BEST: '$best'\n";
    }
}
