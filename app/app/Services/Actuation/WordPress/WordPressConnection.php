<?php

declare(strict_types=1);

namespace App\Services\Actuation\WordPress;

use App\Enums\WordPressConnectionRefusal;

/**
 * What happened when this platform tried to take, or re-check, a WordPress
 * login.
 *
 * ⚠️ **A REFUSAL IS A RETURN VALUE**, on `CmsAdapter`'s rule. Somebody else's
 * website being down, a password having been revoked inside WordPress, or a role
 * having been changed are ordinary conditions on other people's property, and
 * the caller decides what each means.
 *
 * ⛔ **NOTHING HERE CARRIES THE CREDENTIAL, AND NOTHING HERE CARRIES A VENDOR
 * STRING.** The refusal is one of seven enum cases with a sentence of ours
 * attached; `$roles` is a list of WordPress role slugs, which is the evidence
 * §19.7's gate was actually run and is the only thing a screen needs to explain
 * itself.
 */
final readonly class WordPressConnection
{
    /**
     * @param  list<string>  $roles
     */
    private function __construct(
        public bool $ok,
        public ?WordPressConnectionRefusal $refusal,
        public array $roles = [],
    ) {}

    /**
     * @param  list<string>  $roles
     */
    public static function established(array $roles): self
    {
        return new self(true, null, $roles);
    }

    public static function refused(WordPressConnectionRefusal $refusal): self
    {
        return new self(false, $refusal);
    }

    /**
     * The sentence the owner is shown, or null when nothing went wrong.
     */
    public function ownerMessage(): ?string
    {
        return $this->refusal?->owner();
    }

    /**
     * The one word an `AdapterOutcome` or an `AdapterHealth` carries.
     *
     * ⚠️ **AN EXPLICIT NULL CHECK RATHER THAN `?->value ?? …`**, which Larastan
     * rejects here as an unnecessary nullsafe. It also reads better: a refused
     * connection always has a refusal by construction, and the branch says so.
     */
    public function detail(): string
    {
        return $this->refusal === null ? 'connected' : $this->refusal->value;
    }
}
