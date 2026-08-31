<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InboundKeyword;
use App\Enums\OutreachChannel;
use App\Services\Consent\ConsentService;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One message a customer sent us, and what we decided it meant.
 *
 * NOT TENANT-OWNED, on the `TenancyTest` scope allowlist with its reason
 * there — the third table of `OptOut`'s shape. An inbound carrier STOP arrives
 * with a sender, our receiving number and a body, and **nothing in it names a
 * tenant**: there is one shared Lane A sending number and no map from a number
 * to a business until slice 6. A global scope would hide every row from the one
 * query whose job is to refuse a redelivery.
 *
 * ⚠️ **APPEND-ONLY, AND THE REASON IS MECHANICAL BEFORE IT IS EVIDENTIAL.** Like
 * {@see StripeEvent}, this is the thing that makes a redelivery a no-op:
 * deleting a row re-arms the HELP auto-reply and the audit entry for a webhook
 * the carrier may still redeliver. It is *also* the record that somebody sent
 * STOP, which is the second reason and the one that would matter in a dispute.
 *
 * ⚠️ **IT HOLDS NO MESSAGE BODY AND NO CUSTOMER'S PHONE NUMBER** — only a keyed
 * hash and the keyword we resolved. The migration's docblock argues both; the
 * short form is that a platform-wide table of numbers is a marketing list, and
 * free text written by a member of the public sitting beside their number under
 * no tenant's retention policy is the one combination this schema does not have
 * anywhere else and should not acquire here.
 *
 * ⚠️ **THE WORD `CUSTOMER'S` IN THAT SENTENCE IS LOAD-BEARING AND WAS ADDED BY
 * SLICE 6** (1623). `to_number` holds a phone number in plain text, and it is
 * **ours** — the number the carrier delivered this message *to*, the same string
 * `phone_numbers.e164` carries. It is the one column on this table that must not
 * be hashed, because the question it exists to answer is which of our own
 * numbers a STOP arrived on, and a hash answers it only by accident. Nobody may
 * read this paragraph as permission to record the sender in plain text beside
 * it: that is the value that stays hashed, and the two rules sit one line apart
 * in the writer for exactly that reason.
 *
 * ⚠️ **THE APPEND-ONLY GUARDS BELOW ARE NOW DRIVEN, AND THEY WERE NOT UNTIL
 * 2026-08-23** (8329). Nothing anywhere in the suite ever made this model refuse
 * an update or a delete, so both `LogicException`s could have been deleted with
 * the whole build green — a refusal nobody drives is a refusal nobody has
 * proved. `tests/Feature/Sms/InboundMessageRecordTest.php` is where they are
 * driven, and both were confirmed by removing them.
 *
 * ⚠️ **AND WHAT THEY DO NOT REACH IS A QUERY BUILDER.** `DB::table
 * ('inbound_messages')->update(…)` and `->delete()` fire no model event — the
 * gap every append-only table in this schema carries. `opt_outs`,
 * `suppression_lifts` and `suppression_list` answer it with a raw-route lint in
 * `tests/Feature/Architecture/ConsentTest.php`; this table had none, and now has
 * one beside the guard tests. ⛔ **The horizon it protects is unruled** — 7954(b)
 * records that no written ruling was ever found for this table's retention — so
 * a pruner here would be a decision nobody has made, wearing the shape of
 * maintenance.
 *
 * ⚠️ **IT IS NOT THE SUPPRESSION.** A row here records that a message arrived
 * and how it was read; what actually stops a send is the `opt_outs` row
 * {@see ConsentService::suppress()} writes. Reading this
 * table to decide whether to send would be a second implementation of the gate,
 * and the two would disagree the first time a STOP was recorded and the
 * suppression failed to write.
 *
 * @property-read int $id
 * @property string $provider_message_id
 * @property OutreachChannel $identifier_type
 * @property string $value_hash
 * @property ?string $to_number
 * @property InboundKeyword $keyword
 */
final class InboundMessage extends Model
{
    public const null UPDATED_AT = null;

    /**
     * What `message_cost_entries.ref_type` calls a row of this table (4804).
     *
     * ⚠️ **A CONSTANT ON THE MODEL RATHER THAN A LITERAL IN THE WRITER**, on
     * {@see OutreachMessage}'s own precedent and for its reason: a `ref_type`
     * that no longer matches the table it names is a silent orphan in a ledger
     * nobody reconciles, and putting the string on the thing it names makes a
     * rename a compile-time edit instead.
     *
     * ⛔ **THE POINTER IS ONE-WAY AND CROSSES A TENANCY BOUNDARY, WHICH IS THE
     * PART TO READ TWICE.** `message_cost_entries` is tenant-owned and
     * RLS-`FORCE`d; this table deliberately is not (see the class docblock).
     * So a cost row can name an inbound row, and **a reader holding the inbound
     * row cannot get back to the cost row without a tenant** — which is correct.
     * The attribution lives on the cost side because that is the side that has
     * a business.
     */
    public const string COST_REFERENCE_TYPE = 'inbound_message';

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException(
                'inbound_messages is append-only. A row records that a message arrived and how '
                .'it was read; rewriting the keyword changes what we claim somebody asked for, '
                .'and rewriting the id re-arms a redelivery the carrier may still send.'
            );
        });

        self::deleting(function (): never {
            throw new LogicException(
                'inbound_messages is append-only. Deleting a row re-arms the HELP auto-reply and '
                .'the audit entry for a webhook the carrier may still redeliver — and, if the row '
                .'was a STOP, destroys the record that we were told to stop.'
            );
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'identifier_type' => OutreachChannel::class,
            'keyword' => InboundKeyword::class,
            'received_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
