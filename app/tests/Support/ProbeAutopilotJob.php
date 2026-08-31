<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Jobs\AutopilotJob;
use RuntimeException;

/**
 * A concrete AutopilotJob for testing the base class contract.
 *
 * Records side effects in a static tally rather than touching the database, so a
 * test can distinguish "ran once" from "ran twice" independently of whatever the
 * run rows say — which matters, because the run rows are one of the things under
 * test.
 */
final class ProbeAutopilotJob extends AutopilotJob
{
    /** @var array<string, int> */
    public static array $sideEffects = [];

    public bool $executable = true;

    public bool $enabled = true;

    public bool $shouldThrow = false;

    public ?string $key = null;

    /**
     * The thing this run is about, as the fourteen real overrides carry one.
     *
     * ⚠️ ABSENT WHEN UNSET RATHER THAN PRESENT-AND-NULL, so every test written
     * before 6743 sees the identical bag and none of them had to change. The
     * shape is the real overrides' shape exactly — `parent::input() +
     * ['review_id' => …]` — so what this models is the presence of a subject
     * id, never a different bag.
     */
    public ?int $subjectId = null;

    /**
     * Both directions of the base class's claim contract.
     *
     * ⚠️ SET BY `execute()` RATHER THAN LEFT AS A BARE DEFAULT, and the existing
     * "same key produces one side effect, not two" test is what forced it. A flat
     * `false` releases the claim after a **successful** run too, so a second
     * dispatch of the same key runs again — the probe would have been modelling
     * a job that is not idempotent at all, and every new test here would have
     * passed while proving the opposite of the contract.
     *
     * Pre-setting it true before `handle()` reproduces the shipped defect: the
     * throw happens before `execute()` can touch this, so the claim is kept, the
     * retry collides with the job's own failed attempt, and `execute()` is never
     * reached.
     */
    public bool $claimSpent = false;

    public static function reset(): void
    {
        self::$sideEffects = [];
    }

    public static function count(string $path = 'execute'): int
    {
        return self::$sideEffects[$path] ?? 0;
    }

    /**
     * Settable so one test can run two *different* automations that happen to
     * share an idempotency key — the case that makes the release query's
     * automation predicate falsifiable. Mutation found it survived without this.
     */
    public string $automation = 'probe_automation';

    public function automationKey(): string
    {
        return $this->automation;
    }

    protected function idempotencyKey(): ?string
    {
        return $this->key;
    }

    /**
     * @return array<string, mixed>
     */
    protected function input(): array
    {
        return $this->subjectId === null
            ? parent::input()
            : parent::input() + ['subject_id' => $this->subjectId];
    }

    protected function claimIsSpent(): bool
    {
        return $this->claimSpent;
    }

    protected function isEnabled(): bool
    {
        return $this->enabled;
    }

    protected function canExecute(): bool
    {
        return $this->executable;
    }

    protected function execute(): ?array
    {
        if ($this->shouldThrow) {
            throw new RuntimeException('provider exploded');
        }

        self::$sideEffects['execute'] = self::count('execute') + 1;

        // Past the side effect, so the claim is earned — the same place the real
        // jobs set theirs.
        $this->claimSpent = true;

        return ['path' => 'execute'];
    }

    protected function handoff(): ?array
    {
        self::$sideEffects['handoff'] = self::count('handoff') + 1;

        $this->claimSpent = true;

        return ['path' => 'handoff'];
    }
}
