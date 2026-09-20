<?php
$plan = file_get_contents('app/GOAIEZ-MASTER-PLAN.md');

// Fix X-222 owns_table
$plan = str_replace('@owns_table legal_requests · policy_pages · retention_rules. Must not touch X-204 decisions, any sender, X-121 history rows.', '@owns_table legal_requests · policy_pages · retention_rules` **Must not touch X-204 decisions, any sender, X-121 history rows.**', $plan);

// Fix X-223 owns_table
$plan = str_replace('@owns_table warmup_domains · warmup_seeds · warmup_days. Must not touch X-186 campaigns, X-204, any tenant contact row.', '@owns_table warmup_domains · warmup_seeds · warmup_days` **Must not touch X-186 campaigns, X-204, any tenant contact row.**', $plan);

// Fix X-221 owns_table
$plan = str_replace('@owns_table ad_report_imports · ad_findings · ad_plans. Must not touch X-186 sends, X-110 pixel writes, any vendor write endpoint.', '@owns_table ad_report_imports · ad_findings · ad_plans` **Must not touch X-186 sends, X-110 pixel writes, any vendor write endpoint.**', $plan);

// Fix agent_reachable (insert `· none` BEFORE the next `@` tag)
$plan = preg_replace('/(@agent_reachable[^\@\`\n]+)/', '$1 · none ', $plan);

// Remove duplicate `· none` if it exists
$plan = str_replace(' · none  · none', ' · none', $plan);

// Fix the · none at the end of the lines
$plan = str_replace('` · none', '`', $plan);

file_put_contents('app/GOAIEZ-MASTER-PLAN.md', $plan);
