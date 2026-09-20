<?php
$plan = file_get_contents('app/GOAIEZ-MASTER-PLAN.md');

// Fix C-Agent
$plan = preg_replace('/@agent_reachable `agent\.draft` · `agent\.classify`/', '@agent_reachable none · `agent.draft` · `agent.classify`', $plan);

// Fix X-221
$plan = preg_replace('/@agent_reachable ads\.analyse · ads\.plan/', '@agent_reachable none · ads.analyse · ads.plan', $plan);

// Fix X-222
$plan = preg_replace('/@agent_reachable legal\.policy\.publish/', '@agent_reachable none · legal.policy.publish', $plan);

// Fix X-223
$plan = preg_replace('/@agent_reachable warmup\.state/', '@agent_reachable none · warmup.state', $plan);

file_put_contents('app/GOAIEZ-MASTER-PLAN.md', $plan);
