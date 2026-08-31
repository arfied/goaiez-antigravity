<?php

declare(strict_types=1);

namespace App\Services\Actuation;

use App\Enums\SiteChangeActor;
use App\Services\AuditService;

/**
 * Who is applying or undoing a site change, in the one shape that cannot be
 * half-answered.
 *
 * ⛔ **THE USER ID IS REQUIRED BY CONSTRUCTION FOR A HUMAN AND IMPOSSIBLE FOR
 * AUTOPILOT.** The alternative — a nullable id beside a kind — is a shape in
 * which "the owner undid this" can be recorded without saying which owner, and
 * `29` §2 rule 42 makes a revert a sensitive action, which means the audit row
 * has to name somebody. A runtime guard would have caught that too; a
 * constructor that cannot express it never has to.
 *
 * ⚠️ **TWO AUDIENCES, ONE OBJECT.** `$kind` is what `site_changes.rolled_back_by`
 * stores, because the feed, the quarantine and the undo screen branch on the
 * *kind* of decision. {@see self::auditActor()} is what the append-only log
 * stores, in {@see AuditService}'s existing `autopilot` /
 * `user:14` vocabulary, because an auditor asks a different question.
 */
final readonly class ActuationActor
{
    private function __construct(
        public SiteChangeActor $kind,
        public ?int $userId,
    ) {}

    /**
     * The platform decided for itself.
     */
    public static function autopilot(): self
    {
        return new self(SiteChangeActor::Autopilot, null);
    }

    /**
     * The business owner asked, and this is who they are.
     */
    public static function owner(int $userId): self
    {
        return new self(SiteChangeActor::Owner, $userId);
    }

    /**
     * Platform staff acted on the tenant's behalf.
     */
    public static function staff(int $userId): self
    {
        return new self(SiteChangeActor::Staff, $userId);
    }

    /**
     * The actor string the audit log stores.
     *
     * `AuditService`'s docblock: the actor is a string rather than a user
     * foreign key *because automation is a first-class actor here*, and a
     * nullable user id would model "nobody did this", which is never true.
     */
    public function auditActor(): string
    {
        return $this->userId === null
            ? $this->kind->value
            : 'user:'.$this->userId;
    }
}
