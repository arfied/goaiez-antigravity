<?php
$plan = file_get_contents('app/GOAIEZ-MASTER-PLAN.md');
$plan = preg_replace('/@agent_reachable none/', '@agent_reachable none · none', $plan); // wait, if it was `none`, the original script replaced it. X-123 has `@agent_reachable none` originally!
// X-123
$plan = preg_replace('/@agent_reachable none/', '@agent_reachable none · none', $plan);
// X-204
$plan = preg_replace('/@agent_reachable none ⛔/', '@agent_reachable none · none ⛔', $plan);
file_put_contents('app/GOAIEZ-MASTER-PLAN.md', $plan);
