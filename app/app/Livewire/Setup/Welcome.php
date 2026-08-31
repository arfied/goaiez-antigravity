<?php

declare(strict_types=1);

namespace App\Livewire\Setup;

use App\Enums\WizardStep;
use App\Livewire\Setup\Concerns\SetupStep;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The wizard's first screen, and the first thing in this codebase that reads the
 * audit pre-fill.
 *
 * `wizard_progress.data` has carried the business name, findings and categories
 * since row 2 slice H (decision 274), and until now the only thing that read it
 * was MeResource — an API shape with no screen behind it.
 *
 * ⚠️ THE PRE-FILL LIVES UNDER THE `audit` KEY, NOT AT THE TOP LEVEL.
 * `TenantProvisioner::prefill()` writes `data['audit']['name']`,
 * `data['audit']['findings']` and `data['audit']['categories']` — nested
 * deliberately, so the audit token stays alongside the fields it is
 * provenance for rather than colliding with `SetupFlow::ANSWERS_KEY` at the
 * top level (decision 274 keeps the token non-dereferenceable; this reads the
 * copied fields next to it, never the token itself).
 *
 * ⚠️ THE PRE-FILL IS OPTIONAL AND ITS ABSENCE IS NORMAL. A person who registers
 * without running the free audit first has an empty `data`, and decision 192
 * prunes audits at 90 days, so a resumed wizard can legitimately find nothing.
 * Every read here is null-safe on purpose.
 */
#[Layout('components.setup.layout')]
final class Welcome extends Component
{
    use SetupStep;

    public function mount(): void
    {
        $this->mountSetupStep();
    }

    public function continue(): void
    {
        $this->answerAndContinue(['seen' => true]);
    }

    public function render(): View
    {
        $audit = ($this->progress->data ?? [])['audit'] ?? [];

        return view('livewire.setup.welcome', [
            'businessName' => $audit['name'] ?? null,
            'findings' => $audit['findings'] ?? [],
        ]);
    }

    protected function step(): WizardStep
    {
        return WizardStep::Welcome;
    }
}
