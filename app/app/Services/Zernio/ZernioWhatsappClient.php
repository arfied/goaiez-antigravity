<?php

declare(strict_types=1);

namespace App\Services\Zernio;

use App\Exceptions\GbpRequestFailed;
use App\Services\Config\DefaultsRegistry;
use App\Support\PlatformCredentials;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class ZernioWhatsappClient
{
    private const string BASE = 'https://zernio.com/api/v1';

    private const string PLATFORM = 'whatsapp';

    public function __construct(
        private readonly DefaultsRegistry $registry,
    ) {}

    private function request(): PendingRequest
    {
        return Http::withToken(PlatformCredentials::get('zernio_api_key'))
            ->timeout((int) config('gbp.timeout', 10))
            ->acceptJson();
    }

    private function assertUsable(): void
    {
        if ($this->registry->value('whatsapp.zernio_enabled') !== true) {
            throw GbpRequestFailed::disabled();
        }

        if (! PlatformCredentials::has('zernio_api_key')) {
            throw GbpRequestFailed::unconfigured();
        }
    }

    public function connectUrl(string $profileRef, string $redirectUrl): string
    {
        $this->assertUsable();

        try {
            $response = $this->request()->get(self::BASE.'/connect/whatsapp', [
                'profileId' => $profileRef,
                'redirect_url' => $redirectUrl,
                'onboarding' => (string) $this->registry->value('whatsapp.zernio_onboarding'),
            ]);
        } catch (ConnectionException) {
            throw GbpRequestFailed::unreachable('connection_failed');
        }

        if ($response->failed()) {
            throw GbpRequestFailed::from($response, accountScoped: true);
        }

        $authUrl = $response->json('authUrl');

        if (! is_string($authUrl) || $authUrl === '') {
            throw GbpRequestFailed::unreadable('auth_url_missing');
        }

        return $authUrl;
    }

    public function profileOwnsAccount(string $profileRef, string $accountRef): bool
    {
        $this->assertUsable();

        try {
            $response = $this->request()->get(self::BASE.'/accounts', [
                'profileId' => $profileRef,
                'platform' => self::PLATFORM,
                'includeOverLimit' => 'true',
            ]);
        } catch (ConnectionException) {
            throw GbpRequestFailed::unreachable('connection_failed');
        }

        if ($response->failed()) {
            throw GbpRequestFailed::from($response, accountScoped: true);
        }

        $accounts = $response->json('accounts');

        if (! is_array($accounts)) {
            throw GbpRequestFailed::unreadable('accounts_missing');
        }

        foreach ($accounts as $account) {
            if (is_array($account) && ($account['_id'] ?? null) === $accountRef) {
                return true;
            }
        }

        return false;
    }

    public function disconnectAccount(string $accountRef): void
    {
        $this->assertUsable();

        try {
            $response = $this->request()->delete(self::BASE.'/accounts/'.rawurlencode($accountRef));
        } catch (ConnectionException) {
            throw GbpRequestFailed::unreachable('connection_failed');
        }

        if ($response->failed() && $response->status() !== 404) {
            throw GbpRequestFailed::from($response, accountScoped: true);
        }
    }

    public function submitTemplate(string $accountRef, string $name, string $category, string $language, string $bodyText): array
    {
        $this->assertUsable();

        $component = [
            'type' => 'body',
            'text' => $bodyText,
        ];

        // Only build example.body_text when text contains {{n}} placeholders
        $exampleCount = preg_match_all('/\{\{(\d+)\}\}/', $bodyText);
        if ($exampleCount > 0) {
            $component['example'] = [
                'body_text' => [array_fill(0, $exampleCount, 'example')],
            ];
        }

        try {
            $response = $this->request()->post(self::BASE.'/whatsapp/templates', [
                'accountId' => $accountRef,
                'name' => $name,
                'category' => strtoupper($category),
                'language' => $language,
                'components' => [$component],
            ]);
        } catch (ConnectionException) {
            throw GbpRequestFailed::unreachable('connection_failed');
        }

        if ($response->failed()) {
            throw GbpRequestFailed::from($response, accountScoped: true);
        }

        $template = $response->json('template');

        return [
            'ref' => is_array($template) && array_key_exists('id', $template) ? (string) $template['id'] : null,
            'status' => is_array($template) && array_key_exists('status', $template) ? (string) $template['status'] : null,
        ];
    }

    public function templates(string $accountRef): array
    {
        $this->assertUsable();

        try {
            $response = $this->request()->get(self::BASE.'/whatsapp/templates', [
                'accountId' => $accountRef,
            ]);
        } catch (ConnectionException) {
            throw GbpRequestFailed::unreachable('connection_failed');
        }

        if ($response->failed()) {
            throw GbpRequestFailed::from($response, accountScoped: true);
        }

        $templates = $response->json('templates');

        return is_array($templates) ? $templates : [];
    }

    public function replyInConversation(string $conversationId, string $accountRef, string $message, string $idempotencyKey): ZernioSendReceipt
    {
        $this->assertUsable();

        try {
            $response = $this->request()
                ->withHeaders(['Idempotency-Key' => $idempotencyKey])
                ->post(self::BASE.'/inbox/conversations/'.rawurlencode($conversationId).'/messages', [
                    'accountId' => $accountRef,
                    'message' => $message,
                ]);
        } catch (ConnectionException) {
            return new ZernioSendReceipt('unknown');
        }

        if ($response->successful()) {
            $messageId = $response->json('data.messageId');
            if (is_string($messageId)) {
                return new ZernioSendReceipt('sent', messageRef: $messageId, conversationId: $response->json('data.conversationId'));
            }

            return new ZernioSendReceipt('unknown');
        }

        if ($response->serverError()) {
            return new ZernioSendReceipt('unknown');
        }

        $body = $response->body();
        if ($response->status() === 403 || str_contains($body, '131047')) {
            return new ZernioSendReceipt('refused', code: 'window_closed');
        }
        if (str_contains($body, '131056')) {
            return new ZernioSendReceipt('failed', code: 'pacing');
        }

        return new ZernioSendReceipt('failed', code: (string) ($response->json('code') ?? $response->json('error')));
    }

    public function openWithTemplate(string $accountRef, string $participantDigits, string $templateName, string $language, array $params = []): ZernioSendReceipt
    {
        $this->assertUsable();

        try {
            $response = $this->request()->post(self::BASE.'/inbox/conversations', [
                'accountId' => $accountRef,
                'participantId' => $participantDigits,
                'templateName' => $templateName,
                'templateLanguage' => $language,
                'templateParams' => $params,
            ]);
        } catch (ConnectionException) {
            return new ZernioSendReceipt('unknown');
        }

        if ($response->successful()) {
            $messageId = $response->json('data.messageId');
            if (is_string($messageId)) {
                return new ZernioSendReceipt('sent', messageRef: $messageId, conversationId: $response->json('data.conversationId'));
            }

            return new ZernioSendReceipt('unknown');
        }

        if ($response->serverError()) {
            return new ZernioSendReceipt('unknown');
        }

        $body = $response->body();
        if (str_contains($body, 'TEMPLATE_REQUIRED')) {
            return new ZernioSendReceipt('failed', code: 'TEMPLATE_REQUIRED');
        }
        if (str_contains($body, 'INVALID_TEMPLATE_PARAMS')) {
            return new ZernioSendReceipt('failed', code: 'INVALID_TEMPLATE_PARAMS');
        }

        return new ZernioSendReceipt('failed', code: (string) ($response->json('code') ?? $response->json('error')));
    }
}
