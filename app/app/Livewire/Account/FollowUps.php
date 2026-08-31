<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Models\CrmTask;
use App\Services\Crm\CrmTasks;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

/**
 * The follow-ups list (`44` §2) — *"due today on top, overdue plainly marked"*.
 *
 * Under More in the owner's nav, which is where `44` §2 puts it, with the
 * due-today count badging that tab. Every row is a contact, the one line the
 * owner wrote, and [Done] [Snooze 1d/1w] [Open contact] — and nothing else,
 * because §2's own "Never" list refuses subtasks, projects, kanban, priorities
 * and dependencies.
 *
 * ⚠️ **THE GROUPS USE `CrmTasks::isDueNow()`, THE SAME RULE THE BADGE COUNTS
 * BY.** A screen that grouped by its own predicate is how the badge says 3
 * while the list marks 2; a feature test drives both across the edges and
 * asserts they agree.
 */
#[Layout('components.account.layout')]
final class FollowUps extends Component
{
    /**
     * Mark a follow-up done. The service writes the state and its timestamp
     * together — the CHECK refuses either alone (decision 1323).
     */
    public function complete(CrmTasks $tasks, int $taskId): void
    {
        abort_if(Tenancy::id() === null, 403);

        $tasks->complete($taskId);

        Toaster::success('Done.');
    }

    /**
     * `44` §2's two snooze choices, and only those two. The service writes
     * `snoozed_until` and never touches `due_at` — snoozing must not destroy
     * the date "overdue" is measured against (decision 1322).
     */
    public function snooze(CrmTasks $tasks, int $taskId, string $period): void
    {
        abort_if(Tenancy::id() === null, 403);

        $until = match ($period) {
            'day' => CarbonImmutable::now()->addDay(),
            'week' => CarbonImmutable::now()->addWeek(),
            default => abort(422),
        };

        $tasks->snooze($taskId, $until);

        Toaster::success('Snoozed.');
    }

    public function render(CrmTasks $tasks): View
    {
        // Refused rather than resolved when there is no tenant, for the reason
        // every sibling gives: internal staff belong to no business by design
        // (`28` §9.1), so a signed-in support agent typing this URL is the
        // ordinary way to arrive here with nothing resolved.
        abort_if(Tenancy::id() === null, 403);

        $open = $tasks->list();

        $isDueNow = fn (CrmTask $task): bool => CrmTasks::isDueNow($task);
        $isSnoozed = fn (CrmTask $task): bool => $task->snoozed_until !== null && $task->snoozed_until->isFuture();

        return view('livewire.account.follow-ups', [
            'dueNow' => $open->filter($isDueNow)->values(),
            'later' => $open->reject($isDueNow)->reject($isSnoozed)->values(),
            'snoozed' => $open->reject($isDueNow)->filter($isSnoozed)->values(),
        ]);
    }
}
