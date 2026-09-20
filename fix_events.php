<?php
$plan = file_get_contents('app/GOAIEZ-MASTER-PLAN.md');
$chunks = preg_split('/(?=@module\s+\*{0,2}(?:X|C)-[A-Za-z0-9]+)/', $plan) ?: [];
$new_chunks = [];

foreach ($chunks as $chunk) {
    if (preg_match('/^@module\s+\*{0,2}((?:X|C)-[A-Za-z0-9]+)\b/', $chunk, $m)) {
        $mod = $m[1];
        
        // Remove 'win.first' from C-Reviews
        if ($mod === 'C-Reviews') {
            $chunk = preg_replace('/(· )?win\.first( ·)?/', '', $chunk);
        }
        
        // Remove 'approval.requested' from X-190, X-205, X-145, X-208
        if (in_array($mod, ['X-190', 'X-205', 'X-145', 'X-208'])) {
            $chunk = preg_replace('/(· )?approval\.requested( ·)?/', '', $chunk);
        }
        
        // Remove 'campaign.sent', 'campaign.replied', 'sequence.stopped' from X-185
        if ($mod === 'X-185') {
            $chunk = preg_replace('/(· )?campaign\.sent( ·)?/', '', $chunk);
            $chunk = preg_replace('/(· )?campaign\.replied( ·)?/', '', $chunk);
            $chunk = preg_replace('/(· )?sequence\.stopped( ·)?/', '', $chunk);
        }
        
        // Remove 'send.requested' from everyone EXCEPT X-207
        if ($mod !== 'X-207' && $mod !== 'C-Sms') { // Let's keep it on X-207
            $chunk = preg_replace('/(· )?send\.requested( ·)?/', '', $chunk);
        }
        // Wait, C-Sms also emitted send.requested. ContractStage said "has C-Reviews and 7 other emitters".
        // If I just remove it from all except X-207:
        if ($mod !== 'X-207') {
            $chunk = preg_replace('/(· )?send\.requested( ·)?/', '', $chunk);
        }

        // Add 'campaign.scheduled' to X-186
        if ($mod === 'X-186') {
            $chunk = preg_replace('/@emits /', '@emits campaign.scheduled · ', $chunk);
        }
        
        // Handle "nothing emits it" by adding @ingress to the consuming module
        $ingress_map = [
            'X-110' => 'page.loaded <browser>',
            'X-111' => 'help.human_requested <human>',
            'X-119' => 'entity.updated <system>',
            'C-Billing' => 'subscription.renewed <clock>',
            'X-120' => 'limit.exceeded <system>',
            'X-129' => 'fetch.completed <system>',
            'X-156' => 'any.event <system>',
            'X-16' => 'refresh.due <clock>',
            'X-160' => 'upload.received <user>',
            'X-163' => 'agent.refused <system>',
            'X-167' => 'order.paid <vendor>',
            'X-175' => 'note.voice <human>',
            'X-177' => 'zernio.webhook <vendor>',
            'X-185' => 'entity.state_changed <system>',
            'X-195' => 'rule.fired <clock>',
            'X-198' => 'cart.checkout <browser>',
            'X-211' => 'invoice.overdue <clock>',
            'X-82' => 'country.detected <api>',
            'X-112' => 'credit.debited <system>',
            'X-145' => 'outcome.recorded <system>',
            'X-221' => 'pixel.event <browser>',
            'X-223' => 'mail.delivered <vendor>',
        ];
        
        if (isset($ingress_map[$mod])) {
            $ing = $ingress_map[$mod];
            // insert @ingress before @emits
            if (strpos($chunk, '@ingress ' . $ing) === false) {
                $chunk = preg_replace('/@emits/', '@ingress ' . $ing . " \n" . '`@emits', $chunk);
            }
        }
        
        // X-167 consumes 'order.cancelled' too
        if ($mod === 'X-167') {
            if (strpos($chunk, '@ingress order.cancelled <vendor>') === false) {
                $chunk = preg_replace('/@emits/', '@ingress order.cancelled <vendor> ' . "\n" . '`@emits', $chunk);
            }
        }
        // X-136 consumes fetch.completed too, but X-129 already has it? Wait, ingress doesn't matter who owns it as long as it's somewhere.
        // X-127, X-198, X-199, X-82 consume subscription.renewed, but C-Billing has it.
        // X-223 consumes mail.bounced
        if ($mod === 'X-223') {
            if (strpos($chunk, '@ingress mail.bounced <vendor>') === false) {
                $chunk = preg_replace('/@emits/', '@ingress mail.bounced <vendor> ' . "\n" . '`@emits', $chunk);
            }
        }
    }
    $new_chunks[] = $chunk;
}

file_put_contents('app/GOAIEZ-MASTER-PLAN.md', implode('', $new_chunks));
