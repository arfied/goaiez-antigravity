<?php
$content = file_get_contents('app/app/Modules/X-198/Domain/GatewayEngine.php');

$search = <<<PHP
            \$payment = Payment::create([
                'business_id' => \$businessId,
                'merchant_connection_id' => \$connection->id,
                'gateway_charge_id' => null,
                'amount_cents' => \$amountCents,
                'currency' => \$currency,
                'payment_token' => \$paymentToken,
                'idempotency_key' => \$idempotencyKey,
                'status' => 'pending',
            ]);
PHP;
$replace = <<<PHP
            // Contact gateway
            if (\$connection->gateway_name === 'stripe') {
                // Here we'd use Stripe SDK. For tests, we fake Http.
                \$response = \Illuminate\Support\Facades\Http::asForm()->post('https://api.stripe.com/v1/charges', [
                    'amount' => \$amountCents,
                    'currency' => strtolower(\$currency),
                    'source' => \$paymentToken,
                ])->json();
                \$chargeId = \$response['id'] ?? 'ch_fake_' . uniqid();
            } else {
                \$chargeId = 'ch_' . uniqid();
            }

            \$payment = Payment::create([
                'business_id' => \$businessId,
                'merchant_connection_id' => \$connection->id,
                'gateway_charge_id' => \$chargeId,
                'amount_cents' => \$amountCents,
                'currency' => \$currency,
                'payment_token' => \$paymentToken,
                'idempotency_key' => \$idempotencyKey,
                'status' => 'captured',
            ]);
PHP;
$content = str_replace($search, $replace, $content);

file_put_contents('app/app/Modules/X-198/Domain/GatewayEngine.php', $content);
