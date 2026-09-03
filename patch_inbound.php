<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');

$search = <<<PHP
    private function receiveInbound(array \$tenant, string \$from, string \$body): void
    {
        throw \$this->todo('deliver a real inbound message through the carrier webhook');
    }
PHP;
$replace = <<<PHP
    private function receiveInbound(array \$tenant, string \$from, string \$body): void
    {
        \$tenantPhone = \Illuminate\Support\Facades\DB::table('phone_numbers')
            ->where('business_id', \$tenant['id'])
            ->first()->e164 ?? '+19015922708';
        
        \$payload = [
            'results' => [
                [
                    'messageId' => (string) \Illuminate\Support\Str::uuid(),
                    'from' => \$from,
                    'to' => \$tenantPhone,
                    'text' => \$body,
                    'integrationType' => 'SMS'
                ]
            ]
        ];
        \$this->call('POST', '/webhooks/infobip/inbound', [], [], [], [], json_encode(\$payload));
    }
PHP;
$content = str_replace($search, $replace, $content);

file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
