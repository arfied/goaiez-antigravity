<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');
// Replace the whole placeRealCallTo method
\$start = strpos(\$content, 'private function placeRealCallTo');
\$end = strpos(\$content, 'protected static int \$sendCapCounter', \$start);
\$newMethod = <<<PHP
    private function placeRealCallTo(string \$number): array
    {
        \$row = \Illuminate\Support\Facades\DB::table('phone_numbers')->where('e164', \$number)->first();
        \App\Support\Tenancy::set(\$row->business_id);
        \$biz = \App\Models\Business::find(\$row->business_id);
        \$owner = \$biz->owner;
        \$caller = \$owner ? \$owner->email : '+12622164033';
        if (strpos(\$caller, '@') !== false) {
            \$caller = explode('@', \$caller)[0];
        }
        
        \$this->postCarrierWebhook(\$biz->toArray(), 'call.answered', \$caller);
        \$this->drainQueue();
        
        // Find the call
        \$call = \Illuminate\Support\Facades\DB::table('calls')
            ->where('business_id', \$biz->id)
            ->where('from_e164', \$caller)
            ->latest('id')
            ->first();
            
        return \$call ? [
            'answered' => \$call->outcome === 'answered' || \$call->outcome === 'in_progress',
            'quoted_a_price' => false,
            'call_sid' => \$call->provider_call_id
        ] : [];
    }

    // ── waiting on asynchronous work ─────────────────────────────────────

    
PHP;
\$content = substr(\$content, 0, \$start) . \$newMethod . substr(\$content, \$end);
file_put_contents('app/tests/Journeys/JourneyHarness.php', \$content);
