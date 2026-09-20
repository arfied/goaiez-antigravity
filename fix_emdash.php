<?php
$plan = file_get_contents('app/GOAIEZ-MASTER-PLAN.md');

$plan = str_replace('`. **Must not touch', '` — **Must not touch', $plan);
$plan = str_replace(' (X-110)', '', $plan);
$plan = str_replace(' (X-136)', '', $plan);
$plan = str_replace(' (X-204)', '', $plan);
$plan = str_replace(' (C-Mail)', '', $plan);

file_put_contents('app/GOAIEZ-MASTER-PLAN.md', $plan);
