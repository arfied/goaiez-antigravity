<?php

declare(strict_types=1);

namespace App\Livewire\Setup\Concerns;

use App\Enums\WizardStep;
use App\Models\WizardProgress;
use App\Services\Setup\SetupFlow;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Auth;

/**
 * What every wizard step shares: its progress row, and how it moves on.
 *
 * Deliberately thin. Every *rule* lives in SetupFlow — this trait exists so
 * five components do not each re-fetch the same row, not so behaviour can hide
 * in a trait.
 */
trait SetupStep
{
    public WizardProgress $progress;

    public function mountSetupStep(): void
    {
        $user = Auth::user();

        abort_if($user === null, 403);

        // ⛔ A SIGNED-IN ACCOUNT WITH NO TENANT IS A REAL SHAPE, NOT A BROKEN
        // ONE, AND THIS SCREEN USED TO ANSWER IT WITH A STACK TRACE — 5450.
        // Platform staff have no business and never will: staff reach tenant
        // data through impersonation, which is why `impersonation_sessions` is
        // the one table that names a tenant without taking the tenancy trait.
        // `ResolveTenant` deliberately passes such a request through with no
        // tenant established — its docblock says so, and says the destination
        // "belongs with auth" rather than with the middleware — so the decision
        // is this route's to make, and it was not being made.
        //
        // 403 rather than a redirect: every other tenant-only surface in this
        // application answers a tenantless request the same way, eight of them
        // before this one, and a wizard that bounced staff somewhere else would
        // be inventing a destination for an account the wizard has nothing to
        // say to.
        abort_if(Tenancy::id() === null, 403);

        $this->progress = app(SetupFlow::class)->progressFor($user);

        app(SetupFlow::class)->advanceTo($this->progress, $this->step());
    }

    abstract protected function step(): WizardStep;

    /**
     * Record this step as answered and move to the next one.
     *
     * @param  array<string, mixed>  $answer
     */
    protected function answerAndContinue(array $answer = []): void
    {
        $flow = app(SetupFlow::class);

        $flow->markAnswered($this->progress, $this->step(), $answer);

        $next = $this->step()->next();

        if ($next !== null) {
            $this->redirectRoute($next->routeName(), navigate: true);
        }
    }
}
