<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PlatformMailSendFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One message handed to a transport — the rolling-window ceiling meter (2095).
 *
 * NOT TENANT-OWNED and carrying no tenant at all: the Workspace ceiling is a
 * fact about our own sending account, shared across every tenant, and the
 * platform mail competing for it belongs to no tenant in the first place. On
 * the `TenancyTest` scope allowlist with the argument in the creating
 * migration.
 *
 * ⚠️ **`MailQuota` IS THE ONLY WRITER AND THE ONLY READER**, held there by a
 * chokepoint lint. A second writer would be a second definition of what counts
 * against a limit somebody else enforces.
 *
 * @property-read int $id
 * @property string $mailer
 * @property string $sending_account
 */
final class PlatformMailSend extends Model
{
    /** @use HasFactory<PlatformMailSendFactory> */
    use HasFactory;

    public const null CREATED_AT = null;

    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sent_at' => 'immutable_datetime'];
    }
}
