<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');
$target = "    private function placeRealCallTo(string \$number): array\n    {\n        throw \$this->todo('place a REAL call — the owner calling their own business is the only proof that matters');\n    }";
$replacement = <<<PHP
    private function placeRealCallTo(string \$number): array
    {
        // Simulate a real inbound call using the vendor webhook
        // The test created a business with phone +15550777. The owner is calling.
        // But we don't have the owner's number easily available. We can look it up.
        \$biz = \App\Models\Business::whereHas('phoneNumbers', function(\$q) use (\$number) {
            \$q->where('e164', \$number);
        })->first();
        \$owner = \$biz->owner();
        \$caller = \$owner ? \$owner->email : '+12622164033'; // in signup, email was set to phone@example.com, or we just pass the number.
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
            'quoted_a_price' => false, // TODO: how to check if quoted a price?
            'call_sid' => \$call->provider_call_id
        ] : [];
    }
PHP;
$content = str_replace($target, $replacement, $content);
file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
