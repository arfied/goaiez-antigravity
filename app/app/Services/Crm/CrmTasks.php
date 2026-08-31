<?php

declare(strict_types=1);

namespace App\Services\Crm;

use App\Enums\CrmTaskStatus;
use App\Models\CrmTask;
use App\Models\Customer;
use App\Models\User;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;

/**
 * Follow-ups — the *"don't let me forget"* layer (`44` §2), and the writer
 * `crm_tasks` never had.
 *
 * ⚠️ **THE ONLY READER AND WRITER OF `crm_tasks`**, held there by a lint beside
 * `CrmNotes`' in `tests/Feature/Architecture/CrmTest.php`. The table was
 * decision 272's shape — model, factory, RLS policy, isolation test, nothing in
 * `app/` ever touching it (1221) — and the mistake the lint catches is the next
 * screen wanting a task and growing its own query, whose ordering and snooze
 * semantics then drift from these.
 *
 * ## Three rules that are this file rather than the schema
 *
 * **Snoozing never moves `due_at`** (decision 1322). The tempting
 * implementation writes `due_at = due_at + 1 day`, and it destroys the value
 * "overdue" is measured against — the feature would erase its own evidence.
 * `snoozed_until` is its own column and `snooze()` writes it alone.
 *
 * **Completion writes the state and its timestamp together**, or the CHECK
 * refuses the row (1323). Two columns that can disagree about one fact is what
 * got `customers.is_suppressed` dropped (286).
 *
 * **The open-task cap is a registry seed, never a literal** (`crm.task_open_max`,
 * doc `44` §1 — 200). The load-bearing property is that moving the seed moves
 * the refusal (512's rule); a call-site fallback is exactly what `38` Part 2's
 * lint refuses (505).
 *
 * ⚠️ **NO ASSIGNEE SURFACE** (1324) — see `CrmTask`'s docblock. `created_by` is
 * written because `44` event 216 needs an author; `assigned_to` stays null
 * until team seats exist.
 */
final class CrmTasks
{
    /**
     * One line, per `44` §2 — "what (one line)". Long enough for "check the
     * crown fits before Thursday", short enough that nobody writes the project
     * plan §2's "Never" list refuses.
     */
    public const int MAX_TITLE_LENGTH = 200;

    public function __construct(private readonly DefaultsRegistry $defaults) {}

    /**
     * "Remind me" — create a follow-up on a contact.
     *
     * ⚠️ A null `$dueAt` is a real case, not a gap: `44` §2's fourth choice is
     * "pick a date", and a reminder with no date is one the owner wants kept
     * rather than scheduled. It sorts after every dated task and never counts
     * as due today.
     */
    public function remind(Customer $customer, string $title, ?CarbonImmutable $dueAt, User $author): CrmTask
    {
        Tenancy::idOrFail();

        if ($this->defaults->value('crm.tasks_enabled') !== true) {
            throw new RuntimeException(
                'Follow-ups are switched off for the platform (crm.tasks_enabled).',
            );
        }

        $title = trim($title);

        if ($title === '') {
            throw new InvalidArgumentException(
                'A follow-up needs a line saying what to do. An empty one is a reminder '
                .'that reminds nobody of anything.',
            );
        }

        if (mb_strlen($title) > self::MAX_TITLE_LENGTH) {
            throw new InvalidArgumentException(
                'A follow-up is one line, at most '.self::MAX_TITLE_LENGTH.' characters. '
                .'The form refuses this first; reaching here means a caller skipped validation.',
            );
        }

        // ⚠️ THE CAP READS THE REGISTRY EVERY TIME, DELIBERATELY. `44` §1 calls
        // it a hoarding guard; the number is an Ops decision and moving the
        // seed must move the refusal (512's rule), which a memoised copy or a
        // call-site fallback would both quietly break (505).
        $cap = $this->defaults->int('crm.task_open_max');

        if ($this->openQuery()->count() >= $cap) {
            throw new RuntimeException(
                'This account already has '.$cap.' open follow-ups, which is the ceiling '
                .'(crm.task_open_max). Complete some before adding more.',
            );
        }

        return CrmTask::query()->create([
            'customer_id' => $customer->getKey(),
            'title' => $title,
            'due_at' => $dueAt,
            'status' => CrmTaskStatus::Open,
            'created_by' => $author->getKey(),
            'created_at' => now(),
        ]);
    }

    /**
     * One task, or a 404 — the same boundary shape as
     * `CustomerDirectory::find()`: the global scope refuses a foreign id with
     * the same answer a missing one gets, and RLS sits beneath it.
     */
    public function find(int $taskId): CrmTask
    {
        Tenancy::idOrFail();

        return CrmTask::query()->findOrFail($taskId);
    }

    /**
     * Mark a follow-up done.
     *
     * The state and its moment land in one UPDATE — the CHECK refuses either
     * alone (1323). Completing twice is the ordinary case of a double tap, not
     * an error, and the first `done_at` is the one that survives: overwriting
     * it would rewrite when the work happened.
     */
    public function complete(int $taskId): CrmTask
    {
        $task = $this->find($taskId);

        if ($task->status === CrmTaskStatus::Done) {
            return $task;
        }

        $task->status = CrmTaskStatus::Done;
        $task->done_at = now();
        $task->save();

        return $task;
    }

    /**
     * Put a follow-up out of the way until a later moment.
     *
     * ⚠️ **WRITES `snoozed_until` AND NOTHING ELSE.** Moving `due_at` instead is
     * the tempting implementation and destroys the date "overdue" is measured
     * against (1322) — a task due Monday and snoozed twice would read as never
     * having been late.
     */
    public function snooze(int $taskId, CarbonImmutable $until): CrmTask
    {
        $task = $this->find($taskId);

        $task->snoozed_until = Carbon::instance($until);
        $task->save();

        return $task;
    }

    /**
     * Open follow-ups due now — due today or overdue, and not snoozed past this
     * moment. This is the count that badges the More tab (`44` §2).
     *
     * "Today" is the application timezone rather than the location's: the badge
     * is one number for the whole account, `locations.timezone` is nullable,
     * and a count that shifted per location would disagree with the list it
     * summarises.
     */
    public function dueTodayCount(): int
    {
        Tenancy::idOrFail();

        return $this->dueNow($this->openQuery())->count();
    }

    /**
     * Every open follow-up, for the screen: soonest due first, undated last.
     *
     * ⚠️ `due_at ASC NULLS LAST` said out loud even though ASC is Postgres's
     * nulls-last direction by default — the convention lint is right to insist,
     * and an ordering that is only correct by default is one a later `DESC`
     * edit breaks silently. The screen groups these into due-now, later and
     * snoozed; the grouping is presentation and this ordering is the contract.
     *
     * @return Collection<int, CrmTask>
     */
    public function list(): Collection
    {
        Tenancy::idOrFail();

        return $this->openQuery()
            ->with('customer')
            ->orderByRaw('due_at ASC NULLS LAST')
            ->orderBy('id')
            ->get();
    }

    /**
     * A contact's follow-ups, open and done, for the profile's timeline.
     *
     * @return Collection<int, CrmTask>
     */
    public function forCustomer(Customer $customer): Collection
    {
        Tenancy::idOrFail();

        return CrmTask::query()
            ->where('customer_id', $customer->getKey())
            ->orderByDesc('id')
            ->get();
    }

    /**
     * The customer ids with an open follow-up — the tasks half of `34` §1.1's
     * "Needs follow-up" chip, handed to `CustomerDirectory` as a subquery so
     * the directory never touches this table itself (the chokepoint lint).
     *
     * @return Builder<CrmTask>
     */
    public function customersWithOpenTasks(): Builder
    {
        return $this->openQuery()->select('customer_id');
    }

    /**
     * Whether a follow-up is due now rather than later: due today or earlier,
     * and not snoozed past this moment.
     *
     * ⚠️ This is the screen's grouping rule and `dueNow()` below is the badge's
     * SQL — deliberately two expressions of one rule, because the badge must be
     * one cheap COUNT on every page render while the screen already holds the
     * rows. A feature test drives both across the edge cases and asserts they
     * agree, which is what stops the badge saying 3 while the list marks 2.
     */
    public static function isDueNow(CrmTask $task): bool
    {
        if ($task->status !== CrmTaskStatus::Open || $task->due_at === null) {
            return false;
        }

        if ($task->snoozed_until !== null && $task->snoozed_until->isFuture()) {
            return false;
        }

        return $task->due_at->lte(now()->endOfDay());
    }

    /**
     * @param  Builder<CrmTask>  $query
     * @return Builder<CrmTask>
     */
    private function dueNow(Builder $query): Builder
    {
        return $query
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now()->endOfDay())
            ->where(function (Builder $builder): void {
                $builder->whereNull('snoozed_until')
                    ->orWhere('snoozed_until', '<=', now());
            });
    }

    /**
     * Every open follow-up whose contact is still in the owner's book.
     *
     * ⚠️ **THE DELETED FILTER IS HERE AND NOWHERE ELSE, WHICH IS WHY IT REACHES
     * ALL THREE READERS** (1544) — the screen, the due-today badge and §1.1's
     * chip. Written at any one of them, the other two would keep showing a
     * reminder whose "Open contact" link leads to a tombstone.
     *
     * ⚠️ **A READ FILTER, NEVER A WRITE.** The `crm_tasks` rows are untouched, so
     * restoring a contact inside the seven days brings their reminders back
     * exactly as they were. Completing or deleting the tasks on delete is the
     * tempting implementation and it is decision 890's defect precisely: a
     * pause that suppressed invites by emptying the routed snapshot destroyed
     * them, and only a measurement showed it.
     *
     * ⚠️ **DELIBERATELY NOT 1504'S ARCHIVE RULE.** An archived contact keeps
     * their follow-ups on the list, because archive is tidying and the reminder
     * still makes sense — the owner can open them and act. Delete says the
     * person is gone from the book, and a reminder pointing at somebody no room
     * lists is a trap rather than a memory.
     *
     * `forCustomer()` is deliberately NOT filtered: it renders one contact's own
     * history on their own profile, which stays intact through both states.
     *
     * @return Builder<CrmTask>
     */
    private function openQuery(): Builder
    {
        return CrmTask::query()
            ->where('status', CrmTaskStatus::Open)
            ->whereHas('customer', fn (Builder $query): Builder => $query->whereNull('deleted_at'));
    }
}
