<?php

declare(strict_types=1);

namespace App\Jobs\Whatsapp;

use App\Enums\AutopilotActionType;
use App\Exceptions\GbpRequestFailed;
use App\Jobs\AutopilotJob;
use App\Services\Conversations\ConversationThreads;
use App\Services\Zernio\ZernioWhatsappMedia;
use Illuminate\Support\Facades\Storage;

final class StoreWhatsappMediaJob extends AutopilotJob
{
    public function __construct(int $businessId, public readonly int $messageId)
    {
        parent::__construct($businessId);
    }

    public function automationKey(): string
    {
        return 'whatsapp.media_store';
    }

    protected function activityAction(): ?AutopilotActionType
    {
        return null;
    }

    protected function execute(): array
    {
        $message = app(ConversationThreads::class)->inboundWithPendingMedia($this->messageId);
        if ($message === null) {
            return ['outcome' => 'unavailable', 'reason' => 'message_missing'];
        }

        $n = 0;
        $attachments = $message->attachments ?? [];
        foreach ($attachments as $index => $att) {
            if (($att['status'] ?? null) === 'pending') {
                try {
                    $res = app(ZernioWhatsappMedia::class)->download($att['account_ref'], $att['media_id']);
                    $bytes = $res['bytes'];
                    $mime = $res['mime'];
                    $path = app(ZernioWhatsappMedia::class)->pathFor((int) $message->business_id, (int) $message->getKey(), (int) $index);
                    Storage::disk(ZernioWhatsappMedia::DISK)->put($path, $bytes);

                    app(ConversationThreads::class)->markAttachment($message, $index, [
                        'status' => 'stored',
                        'path' => $path,
                        'size' => strlen($bytes),
                        'mime' => $mime ?? ($att['mime'] ?? null),
                    ]);
                    $n++;
                } catch (GbpRequestFailed $e) {
                    if ($e->status === 400 || $e->status === 404) {
                        app(ConversationThreads::class)->markAttachment($message, $index, ['status' => 'expired']);
                    } elseif ($e->reason === 'media_too_large') {
                        app(ConversationThreads::class)->markAttachment($message, $index, ['status' => 'too_large']);
                    } elseif ($e->retryable) {
                        throw $e;
                    } else {
                        app(ConversationThreads::class)->markAttachment($message, $index, ['status' => 'failed']);
                    }
                }
            }
        }

        return ['outcome' => 'stored', 'stored' => $n];
    }

    protected function handoff(): array
    {
        return [];
    }
}
