<?php

declare(strict_types=1);

namespace App\Modules\X198\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class RuntimeProofCommand extends Command
{
    protected $signature = 'x198:runtime-proof';

    protected $description = 'Generate runtime proof for X-198';

    public function handle(): int
    {
        if (app()->runningUnitTests()) {
            $this->error('The artifact may only be produced by a real CLI run.');
            return self::FAILURE;
        }

        $chargePath = storage_path('app/evidence/j9/charge.json');
        if (!File::exists($chargePath)) {
            $this->error('Missing evidence/j9/charge.json');
            return self::FAILURE;
        }

        $chargeData = json_decode(File::get($chargePath), true);
        if (!isset($chargeData['gateway_charge_id']) || !str_starts_with($chargeData['gateway_charge_id'], 'ch_')) {
            $this->error('charge.json artifact_id must start with ch_');
            return self::FAILURE;
        }

        $invoicePaidPath = storage_path('app/evidence/journeys/invoice-to-paid.json');
        if (!File::exists($invoicePaidPath)) {
            $this->error('Missing evidence/journeys/invoice-to-paid.json');
            return self::FAILURE;
        }

        $invoicePaidData = json_decode(File::get($invoicePaidPath), true);
        if (($invoicePaidData['passed'] ?? false) !== true) {
            $this->error('invoice-to-paid.json passed is not true');
            return self::FAILURE;
        }

        if (($invoicePaidData['queue_driver'] ?? null) === 'sync') {
            $this->error('invoice-to-paid.json driver cannot be sync');
            return self::FAILURE;
        }

        if (($invoicePaidData['artifact_id'] ?? null) !== $chargeData['gateway_charge_id']) {
            $this->error('invoice-to-paid.json artifact_id differs from charge.json gateway_charge_id');
            return self::FAILURE;
        }

        $junitPath = storage_path('app/evidence/X-198/junit.xml');
        if (!File::exists($junitPath)) {
            $this->error('Missing evidence/X-198/junit.xml');
            return self::FAILURE;
        }

        $junitContent = File::get($junitPath);
        $junitXml = simplexml_load_string($junitContent);
        if ($junitXml === false) {
            $this->error('Invalid junit.xml');
            return self::FAILURE;
        }

        // Check if the XML names an_invoice_reaches_a_real_charge_id
        $hasName = str_contains($junitContent, 'an_invoice_reaches_a_real_charge_id') || str_contains($junitContent, 'An invoice reaches a real charge id');
        $hasErrors = str_contains($junitContent, 'failures="0"') && str_contains($junitContent, 'errors="0"');
        if (!$hasName || !$hasErrors) {
            $this->error('junit.xml does not name an_invoice_reaches_a_real_charge_id with failures=0 errors=0');
            return self::FAILURE;
        }

        $capturedAt = null;
        $testsuites = $junitXml->xpath('//testsuite');
        foreach ($testsuites as $ts) {
            if (isset($ts['timestamp'])) {
                $capturedAt = (string)$ts['timestamp'];
                break;
            }
        }
        
        if (!$capturedAt) {
            // Fallback since pest may not output timestamp attribute
            $capturedAt = date('c', filemtime($junitPath));
        }

        $proof = [
            'artifact_id' => $chargeData['gateway_charge_id'],
            'driver' => $invoicePaidData['queue_driver'],
            'captured_at' => $capturedAt,
            'junit' => 'evidence/X-198/junit.xml'
        ];

        File::put(storage_path('app/evidence/X-198/runtime-proof.json'), json_encode($proof, JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
