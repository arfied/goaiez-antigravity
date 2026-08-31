<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Exceptions\MailNotDeliverable;
use App\Jobs\AutopilotJob;
use RuntimeException;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Throwable;

/**
 * A concrete `AutopilotJob` for driving what happens when the queue gives up.
 *
 * ⛔ **A NAMED CLASS RATHER THAN AN ANONYMOUS ONE IN THE TEST FILE, AND THE
 * REASON IS A REAL CONSTRAINT ON EVERY `failed()` HOOK IN THIS CODEBASE.**
 * `CallQueuedHandler::failed()` calls `unserialize($data['command'])` — it
 * rebuilds the job from the **payload** rather than using the instance that
 * threw, on every connection including `sync`. An anonymous class cannot be
 * unserialized, so a probe declared inside a test reaches `failed()` **never**
 * and the test goes green having proved nothing. Measured, not reasoned: the
 * first draft of `AutomationRunFailureTest` did exactly that.
 *
 * ⚠️ **AND THE SAME FACT BOUNDS WHAT A HOOK MAY READ.** Nothing `handle()` set
 * on `$this` survives to `failed()` — not the `AutomationRun` it opened, not a
 * flag it flipped — because that is a different object built from the
 * constructor arguments. Only the serialised public state is there.
 *
 * ⚠️ **{@see ProbeAutopilotJob} IS NOT THIS AND THE TWO ARE DELIBERATELY NOT
 * ONE.** That one models the base class's *contract* — the gates, the claim, the
 * two arms — and its `execute()` throws a fixed message on purpose. This models
 * the *abandonment*, needs the message to be chosen by the caller so a leak is
 * falsifiable, and needs an `onAbandoned()` override. Folding the two would put
 * a settable exception message on the probe every other base-class test uses.
 */
final class AbandonedAutopilotProbeJob extends AutopilotJob
{
    /**
     * How many times {@see self::onAbandoned()} has run.
     *
     * ⚠️ **STATIC, BECAUSE THE INSTANCE DOES NOT SURVIVE** — see the class
     * docblock. A counter on `$this` is written on an object the test never sees.
     */
    public static int $cleanups = 0;

    public string $automation = 'probe.abandoned';

    /** What `execute()` throws. Set it to something that must never leave. */
    public string $message = 'the provider exploded';

    /** Whether the subclass's own abandonment work throws, as one could. */
    public bool $cleanupThrows = false;

    /**
     * Which of `MailFailure::runError()`'s three arms `execute()` throws into.
     *
     * ⚠️ **A CODE AND A FLAG RATHER THAN A `Throwable` PROPERTY, BECAUSE THE
     * JOB IS SERIALISED** — see the class docblock: the instance the test holds
     * is not the instance that runs, and only public scalar state survives the
     * hop. Both default to the pre-existing `RuntimeException` arm, so every
     * test written before 11334 sees exactly what it saw.
     */
    public ?int $smtpCode = null;

    public bool $undeliverable = false;

    public static function reset(): void
    {
        self::$cleanups = 0;
    }

    public function automationKey(): string
    {
        return $this->automation;
    }

    protected function execute(): ?array
    {
        if ($this->undeliverable) {
            throw MailNotDeliverable::ceilingNotStated('smtp', $this->message);
        }

        if ($this->smtpCode !== null) {
            throw new UnexpectedResponseException($this->message, $this->smtpCode);
        }

        throw new RuntimeException($this->message);
    }

    protected function handoff(): ?array
    {
        return null;
    }

    protected function onAbandoned(?Throwable $exception): void
    {
        self::$cleanups++;

        if ($this->cleanupThrows) {
            throw new RuntimeException('and so did the cleanup');
        }
    }
}
