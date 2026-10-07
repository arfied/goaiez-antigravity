<?php

declare(strict_types=1);

namespace App\Services\SiteClone;

use App\Models\SiteCloneJob;
use App\Support\PublicAddress;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

final class SiteCloneJobs
{
    public const MESSAGES = [
        'queued' => 'Queued — your clone starts in a moment.',
        'cancelled' => 'Cancelled.',
        'preparing' => 'Preparing to clone...',
        'cloning' => 'Cloning website...',
        'screenshots' => 'Taking screenshots...',
        'building' => 'Building new site...',
        'importing' => 'Importing content...',
        'done' => 'Done.',
        'failed' => 'Failed.',
    ];

    public function request(int $businessId, int $userId, string $url, bool $attested): array
    {
        if (! $attested) {
            return ['status' => 'refused', 'reason' => 'not_attested'];
        }

        $parsed = parse_url($url);
        if (! isset($parsed['scheme']) || ! in_array($parsed['scheme'], ['http', 'https']) || ! isset($parsed['host'])) {
            return ['status' => 'refused', 'reason' => 'bad_url'];
        }

        $host = $parsed['host'];
        if (! PublicAddress::reaches($host)) {
            return ['status' => 'refused', 'reason' => 'private_address'];
        }

        $activeJob = SiteCloneJob::where('business_id', $businessId)
            ->whereIn('status', SiteCloneJob::ACTIVE)
            ->first();
        if ($activeJob) {
            return ['status' => 'refused', 'reason' => 'already_running', 'job_id' => $activeJob->id];
        }

        $activeJobUser = SiteCloneJob::where('user_id', $userId)
            ->whereIn('status', SiteCloneJob::ACTIVE)
            ->first();
        if ($activeJobUser) {
            return ['status' => 'refused', 'reason' => 'already_running', 'job_id' => $activeJobUser->id];
        }

        try {
            $job = SiteCloneJob::create([
                'business_id' => $businessId,
                'user_id' => $userId,
                'url' => $url,
                'host' => $host,
                'slug' => str_replace('.', '-', strtolower($host)),
                'status' => SiteCloneJob::QUEUED,
                'progress' => 0,
                'message' => self::MESSAGES['queued'],
            ]);

            return ['status' => 'queued', 'job_id' => $job->id];
        } catch (QueryException $e) {
            if ($e->getCode() === '23505') {
                $activeJob = SiteCloneJob::where('business_id', $businessId)
                    ->whereIn('status', SiteCloneJob::ACTIVE)
                    ->first() ?? SiteCloneJob::where('user_id', $userId)
                    ->whereIn('status', SiteCloneJob::ACTIVE)
                    ->first();
                if ($activeJob) {
                    return ['status' => 'refused', 'reason' => 'already_running', 'job_id' => $activeJob->id];
                }
            }
            throw $e;
        }
    }

    public function cancel(int $businessId, int $jobId): array
    {
        $job = SiteCloneJob::where('business_id', $businessId)
            ->where('id', $jobId)
            ->whereIn('status', SiteCloneJob::ACTIVE)
            ->first();

        if (! $job) {
            return ['status' => 'refused', 'reason' => 'not_active'];
        }

        $job->update([
            'status' => SiteCloneJob::CANCELLED,
            'finished_at' => now(),
            'message' => self::MESSAGES['cancelled'],
        ]);

        return ['status' => 'cancelled'];
    }

    public function active(int $businessId): ?SiteCloneJob
    {
        return SiteCloneJob::where('business_id', $businessId)
            ->whereIn('status', SiteCloneJob::ACTIVE)
            ->first();
    }

    public function history(int $businessId, int $limit = 20): Collection
    {
        return SiteCloneJob::where('business_id', $businessId)
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function ownerSentence(string $reason): string
    {
        return match (true) {
            $reason === 'private_address' => 'That address is not a public website.',
            $reason === 'bad_url' => 'Enter a full web address starting with http:// or https://.',
            $reason === 'not_attested' => "Confirm you may use this website's content.",
            $reason === 'already_running' => 'A clone is already running — see its progress below.',
            default => 'Unknown error.',
        };
    }
}
