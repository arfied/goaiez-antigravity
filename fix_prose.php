<?php
$plan = file_get_contents('app/GOAIEZ-MASTER-PLAN.md');
$plan = str_replace('reply.published csat.requested', 'reply.published · csat.requested', $plan);
$plan = str_replace('message.received notification.classified', 'message.received · notification.classified', $plan);
file_put_contents('app/GOAIEZ-MASTER-PLAN.md', $plan);
