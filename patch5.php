<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');

$search1 = "throw \$this->todo('place a REAL call — the owner calling their own business is the only proof that matters');";
$replace1 = <<<'PHP'
        $row = \Illuminate\Support\Facades\DB::table('phone_numbers')->where('e164', $number)->first();
        \App\Support\Tenancy::set($row->business_id);
        $biz = \App\Models\Business::find($row->business_id);
        $caller = '+12622164033';
        
        $this->postCarrierWebhook($biz->toArray(), 'call.answered', $caller);
        $this->drainQueue();
        \App\Support\Tenancy::set($biz->id); // RESTORE TENANCY
        
        $call = \Illuminate\Support\Facades\DB::table('calls')
            ->where('business_id', $biz->id)
            ->where('from_e164', $caller)
            ->latest('id')
            ->first();
            
        return $call ? [
            'answered' => $call->outcome === 'answered' || $call->outcome === 'in_progress',
            'quoted_a_price' => false,
            'call_sid' => $call->provider_call_id
        ] : [];
PHP;
$content = str_replace($search1, $replace1, $content);

file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
