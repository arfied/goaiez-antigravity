<?php

declare(strict_types=1);

namespace App\Modules\X181\Actions;

use App\Modules\X181\Models\QaTicket;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class QaTicketReadAction
{
    /**
     * @return Collection<int, QaTicket>
     */
    public function getBreachedSlaTickets(int $businessId): Collection
    {
        return QaTicket::where('business_id', $businessId)
            ->whereIn('status', ['open', 'in_progress'])
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<=', now())
            ->orderBy('sla_due_at', 'asc')
            ->get();
    }

    /**
     * @return Collection<int, QaTicket>
     */
    public function getReopenedTickets(int $businessId): Collection
    {
        return QaTicket::where('business_id', $businessId)
            ->whereNotNull('reopened_at')
            ->get();
    }

    /**
     * @return Collection<int, QaTicket>
     */
    public function getOpenTicketsSince(int $businessId, Carbon $since, bool $breached): Collection
    {
        $query = QaTicket::where('business_id', $businessId)
            ->where('created_at', '>=', $since)
            ->where('status', 'open');

        if ($breached) {
            $query->where('sla_due_at', '<=', now());
        } else {
            $query->where('sla_due_at', '>', now());
        }

        return $query->get();
    }

    public function countOpenTicketsSince(int $businessId, Carbon $since, bool $breached): int
    {
        $query = QaTicket::where('business_id', $businessId)
            ->where('created_at', '>=', $since)
            ->where('status', 'open');

        if ($breached) {
            $query->where('sla_due_at', '<=', now());
        } else {
            $query->where('sla_due_at', '>', now());
        }

        return $query->count();
    }

    public function findByReviewRequestId(int $businessId, int $reviewRequestId): ?QaTicket
    {
        return QaTicket::where('business_id', $businessId)
            ->where('review_request_id', $reviewRequestId)
            ->first();
    }

    /**
     * @return Collection<int, QaTicket>
     */
    public function getTicketsByTab(int $businessId, string $tab): Collection
    {
        $query = QaTicket::where('business_id', $businessId);

        if ($tab === 'open') {
            $query->whereIn('status', ['open', 'in_progress'])->orderBy('sla_due_at', 'asc');
        } else {
            $query->where('status', 'resolved')->orderBy('resolved_at', 'desc');
        }

        return $query->get();
    }

    public function countTickets(int $businessId): int
    {
        return QaTicket::where('business_id', $businessId)->count();
    }

    public function latestAwaitingCsatForPerson(int $businessId, int $personId): ?QaTicket
    {
        return QaTicket::where('business_id', $businessId)
            ->where('person_id', $personId)
            ->whereNotNull('csat_requested_at')
            ->whereNotExists(function (Builder $query) {
                $query->select(DB::raw(1))
                    ->from('csat_answers')
                    ->whereColumn('csat_answers.qa_ticket_id', 'qa_tickets.id');
            })
            ->orderBy('csat_requested_at', 'desc')
            ->first();
    }

    public function findById(int $businessId, int $ticketId): QaTicket
    {
        return QaTicket::where('business_id', $businessId)->findOrFail($ticketId);
    }

    /**
     * @param  list<int>  $ticketIds
     * @return list<int>
     */
    public function awaitingCsatIds(int $businessId, array $ticketIds): array
    {
        if ($ticketIds === []) {
            return [];
        }

        return QaTicket::where('business_id', $businessId)
            ->whereIn('id', $ticketIds)
            ->whereNotNull('csat_requested_at')
            ->whereNotExists(function (Builder $query) {
                $query->select(DB::raw(1))
                    ->from('csat_answers')
                    ->whereColumn('csat_answers.qa_ticket_id', 'qa_tickets.id');
            })
            ->pluck('id')
            ->all();
    }
}
