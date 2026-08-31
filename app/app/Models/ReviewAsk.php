<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Services\Agent\ReviewAskBridge;
use Database\Factories\ReviewAskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * That the assistant offered one thread the feedback page once — T176 P12.
 *
 * ⛔ **NOT A REVIEW, NOT AN INVITE LEDGER ENTRY, AND NOT A DESTINATION CLICK.**
 * Three pipelines meet near this row and none of them is this one:
 *
 *  - a **Google review** is ingested from Google and is never held, hidden,
 *    approved or moderated by anything in this application;
 *  - a **first-party review** is a `reviews` row the customer wrote on the
 *    feedback page, which is downstream of this and may or may not follow;
 *  - a **destination click** is a `destination_clicks` row and is never recorded
 *    or reported as a review (113).
 *
 * This is none of the three. It is *"we gave them the address, once"*, which is
 * the only claim this table is allowed to make.
 *
 * ⚠️ **WRITTEN ONLY BY {@see ReviewAskBridge::record()}**, after the outbound
 * message that carried the link actually left. Writing it when the link was
 * merely *offered to the model* would burn a business's one ask on a turn where
 * the model chose not to use it.
 *
 * @property-read int $id
 * @property int $business_id
 * @property ?int $location_id
 * @property int $conversation_id
 * @property ?int $customer_id
 * @property ?int $short_link_id
 * @property Carbon $offered_at
 */
final class ReviewAsk extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<ReviewAskFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'offered_at' => 'datetime',
        ];
    }
}
