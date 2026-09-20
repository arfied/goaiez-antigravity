<?php
$plan = file_get_contents('app/GOAIEZ-MASTER-PLAN.md');
$plan = preg_replace_callback('/@agent_reachable(.*?)$/m', function($matches) {
    if (strpos($matches[1], 'none') !== false) {
        return $matches[0];
    }
    return $matches[0] . ' · none';
}, $plan);
file_put_contents('app/GOAIEZ-MASTER-PLAN.md', $plan);
