<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\MessageDirection;
use App\Enums\MessageSenderType;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One message in a conversation (DATA-MODEL §5.9).
 *
 * The most sensitive free text in the system. For a PHI tenant these bodies are
 * not visible to CS agents at all (`29` §19.5) and never reach L3.
 *
 * ⚠️ **THIS TABLE HAD NO WRITER UNTIL P18** — decision 272's shape, and the
 * reason `direction` and `sender_type` shipped as bare strings with no PHP enum
 * beside them. `ConversationThreads` is the one writer and a chokepoint lint in
 * `tests/Feature/Architecture/InboxTest.php` holds it there.
 *
 * @property-read int $id
 * @property int $business_id
 * @property int $conversation_id
 * @property MessageDirection $direction
 * @property MessageSenderType $sender_type
 * @property ?string $sender_id
 * @property ?string $body
 * @property ?string $provider_msg_id
 * @property ?array<int, array<string, mixed>> $attachments
 * @property ?Carbon $created_at
 */
final class Message extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'ai_confidence' => 'float',
            // ⚠️ **CAST SO A READ IS TYPED, NOT SO A SECOND WRITER CAN SET
            // THEM.** The Inbox decides whose words it is showing from
            // `direction`, and rail 4's "who was speaking" question is answered
            // from `sender_type`; a screen comparing bare strings is one typo
            // away from attributing the customer's message to the business.
            'direction' => MessageDirection::class,
            'sender_type' => MessageSenderType::class,
        ];
    }
}
