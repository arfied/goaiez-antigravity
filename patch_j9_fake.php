<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');

$search = <<<PHP
    private function payInvoice(array \$invoice): array
    {
        // 1. Create a MerchantConnection so GatewayEngine::capture works
PHP;
$replace = <<<PHP
    private function payInvoice(array \$invoice): array
    {
        \Illuminate\Support\Facades\Http::fake([
            'api.stripe.com/v1/charges' => \Illuminate\Support\Facades\Http::response([
                'id' => 'ch_' . uniqid(),
                'status' => 'succeeded',
            ], 200),
        ]);

        // 1. Create a MerchantConnection so GatewayEngine::capture works
PHP;
$content = str_replace($search, $replace, $content);

file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
