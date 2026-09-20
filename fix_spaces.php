<?php
$plan = file_get_contents('app/GOAIEZ-MASTER-PLAN.md');
$plan = preg_replace('/reply\.published\s+csat\.requested/', 'reply.published · csat.requested', $plan);
$plan = preg_replace('/message\.received\s+notification\.classified/', 'message.received · notification.classified', $plan);
file_put_contents('app/GOAIEZ-MASTER-PLAN.md', $plan);
