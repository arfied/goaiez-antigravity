<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use App\Services\Fetch\PublicAddressGuard;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Owner ruling 2026-09-28 (main REVIEWS): outbound webhooks to a tenant's own
 * https URL, guarded by PublicAddressGuard, pinned, no redirects.
 *
 * This is the only file that reaches a tenant-supplied host.
 */
final class TenantWebhookClient
{
    /**
     * @return array{outcome: 'delivered'|'rejected'|'retry'|'refused', status: ?int, error: ?string}
     */
    public function post(string $url, string $secret, string $event, string $deliveryRef, string $body): array
    {
        $parts = parse_url($url);

        if (! isset($parts['scheme']) || $parts['scheme'] !== 'https') {
            return ['outcome' => 'refused', 'status' => null, 'error' => 'URL scheme must be https'];
        }

        $ips = app(PublicAddressGuard::class)->check($url);

        if ($ips === null || $ips === []) {
            return ['outcome' => 'refused', 'status' => null, 'error' => 'URL resolves to a private or invalid address'];
        }

        $host = $parts['host'];
        $port = $parts['port'] ?? 443;

        try {
            $response = Http::timeout(10)
                ->withOptions([
                    'allow_redirects' => false,
                    'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$ips[0]}"]],
                ])
                ->withHeaders([
                    'X-Goaiez-Event' => $event,
                    'X-Goaiez-Delivery' => $deliveryRef,
                    'X-Goaiez-Signature' => 'sha256='.hash_hmac('sha256', $body, $secret),
                    'Content-Type' => 'application/json',
                ])
                ->withBody($body, 'application/json')
                ->post($url);
        } catch (ConnectionException $e) {
            return ['outcome' => 'retry', 'status' => null, 'error' => $e->getMessage()];
        }

        $status = $response->status();

        if ($status >= 200 && $status < 300) {
            return ['outcome' => 'delivered', 'status' => $status, 'error' => null];
        }

        if ($status === 408 || $status === 429 || $status >= 500) {
            return ['outcome' => 'retry', 'status' => $status, 'error' => 'HTTP status '.$status];
        }

        return ['outcome' => 'rejected', 'status' => $status, 'error' => 'HTTP status '.$status];
    }
}
