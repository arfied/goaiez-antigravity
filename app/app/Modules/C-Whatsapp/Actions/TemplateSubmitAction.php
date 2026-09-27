<?php

declare(strict_types=1);

namespace App\Modules\CWhatsapp\Actions;

use App\Exceptions\GbpRequestFailed;
use App\Modules\CWhatsapp\Domain\TemplateStatuses;
use App\Modules\CWhatsapp\Models\WhatsappTemplate;
use App\Services\Zernio\ZernioWhatsappClient;

final class TemplateSubmitAction
{
    public function handle(
        int $businessId,
        string $name,
        string $category,
        string $bodyText,
        string $language = 'en_US'
    ): WhatsappTemplate {
        if (! preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException('Template names use lowercase letters, numbers and underscores, starting with a letter.');
        }

        if (! in_array(strtoupper($category), ['UTILITY', 'MARKETING', 'AUTHENTICATION'], true)) {
            throw new \InvalidArgumentException('Invalid category.');
        }

        $template = WhatsappTemplate::updateOrCreate(
            ['business_id' => $businessId, 'name' => $name],
            [
                'category' => $category,
                'body_text' => $bodyText,
                'language' => $language,
                'status' => 'draft',
            ]
        );

        $connection = app(WhatsappConnectionLookupAction::class)->forBusiness($businessId);

        if (! $connection || $connection->status !== 'connected') {
            return $template;
        }

        $client = app(ZernioWhatsappClient::class);

        try {
            $ref = $client->submitTemplate(
                $connection->account_ref,
                $name,
                $category,
                $language,
                $bodyText
            );

            $template->update([
                'status' => TemplateStatuses::fromZernio($ref['status']) ?? 'pending_approval',
                'provider_template_ref' => $ref['ref'],
                'submitted_at' => now(),
                'status_reason' => null,
            ]);
        } catch (GbpRequestFailed $e) {
            $template->update([
                'status' => 'submit_failed',
                'status_reason' => $e->reason,
            ]);
        }

        return $template;
    }
}
