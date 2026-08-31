<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether a follow-up still needs a human (`44` §2).
 *
 * Two cases, deliberately. `44` §2's own "Never" list refuses subtasks,
 * projects, kanban, priorities and dependencies — *"if an owner needs those,
 * that is a different product"* — and every one of them would arrive here
 * first as a third status. The column is a `string` cast to this enum, never a
 * database enum (`CLAUDE.md`'s standing rule), and the *when* of `Done` lives
 * in `done_at`, paired with this by a CHECK so the two cannot disagree about
 * one fact (decision 1323).
 */
enum CrmTaskStatus: string
{
    case Open = 'open';

    case Done = 'done';
}
