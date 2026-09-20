<?php
$plan = file_get_contents('app/GOAIEZ-MASTER-PLAN.md');

// Fix X-221 owns_table
$plan = str_replace('@owns_table ad_report_imports · ad_findings · ad_plans. Must not touch X-186 sends, X-110 pixel writes, any vendor write endpoint.', '@owns_table ad_report_imports · ad_findings · ad_plans` **Must not touch X-186 sends, X-110 pixel writes, any vendor write endpoint.**', $plan);
$plan = str_replace('@consumes pixel.event (X-110) · signal.detected (X-136)', '@consumes pixel.event · signal.detected', $plan);

// Fix X-222 owns_table
$plan = str_replace('@owns_table legal_requests · policy_pages · retention_rules. Must not touch X-204 decisions, any sender, X-121 history rows.', '@owns_table legal_requests · policy_pages · retention_rules` **Must not touch X-204 decisions, any sender, X-121 history rows.**', $plan);
$plan = str_replace('@consumes consent.decided (X-204)', '@consumes consent.decided', $plan);

// Fix X-223 owns_table
$plan = str_replace('@owns_table warmup_domains · warmup_seeds · warmup_days. Must not touch X-186 campaigns, X-204, any tenant contact row.', '@owns_table warmup_domains · warmup_seeds · warmup_days` **Must not touch X-186 campaigns, X-204, any tenant contact row.**', $plan);
$plan = str_replace('@consumes mail.delivered · mail.bounced (C-Mail)', '@consumes mail.delivered · mail.bounced', $plan);

// X-221 @intent GROW (needs capability.decided)
$plan = str_replace('@consumes pixel.event · signal.detected', '@consumes pixel.event · signal.detected · capability.decided', $plan);

// X-223 @intent GROW (needs capability.decided)
$plan = str_replace('@consumes mail.delivered · mail.bounced', '@consumes mail.delivered · mail.bounced · capability.decided', $plan);

file_put_contents('app/GOAIEZ-MASTER-PLAN.md', $plan);
