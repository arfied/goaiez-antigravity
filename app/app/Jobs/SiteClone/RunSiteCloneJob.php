<?php

declare(strict_types=1);

namespace App\Jobs\SiteClone;

use App\Models\SiteCloneJob;
use App\Services\Config\DefaultsRegistry;
use App\Services\SiteClone\SiteCloneJobs;
use App\Support\PlatformCredentials;
use App\Support\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

final class RunSiteCloneJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1620;

    public function __construct(
        public readonly int $jobId,
        public readonly int $businessId
    ) {}

    public function handle(SiteCloneJobs $jobs, DefaultsRegistry $registry): void
    {
        Tenancy::actingAs($this->businessId, function () use ($registry) {
            $row = SiteCloneJob::find($this->jobId);
            if (! $row || $row->status !== SiteCloneJob::QUEUED) {
                return;
            }

            $max = $registry->int('sites.clone.max_concurrent');
            $lockN = null;
            for ($n = 1; $n <= $max; $n++) {
                $ok = DB::selectOne('select pg_try_advisory_lock(hashtext(?)) as ok', ["site-clone-slot-{$n}"]);
                if ($ok->ok === true) {
                    $lockN = $n;
                    break;
                }
            }

            if ($lockN === null) {
                $this->release(60);

                return;
            }

            try {
                if (! PlatformCredentials::has('anthropic_api_key')) {
                    $row->update([
                        'status' => SiteCloneJob::FAILED,
                        'error' => SiteCloneJobs::ERRORS['no_credential'],
                        'internal_error' => 'anthropic_api_key absent',
                        'finished_at' => now(),
                    ]);

                    return;
                }

                $workDir = config('site_clone.root').'/'.$this->businessId.'/'.$row->slug;

                $row->update([
                    'status' => SiteCloneJob::RUNNING,
                    'started_at' => now(),
                    'heartbeat_at' => now(),
                    'message' => SiteCloneJobs::MESSAGES['preparing'],
                    'progress' => 2,
                    'work_dir' => $workDir,
                    'logs' => [SiteCloneJobs::MESSAGES['preparing']],
                ]);

                $env = [
                    'ANTHROPIC_API_KEY' => PlatformCredentials::get('anthropic_api_key'),
                    'CLONE_ROOT' => config('site_clone.root'),
                    'HOME' => getenv('HOME'),
                    'PATH' => getenv('PATH'),
                ];

                $process = new Process(
                    ['setsid', 'bash', config('site_clone.runner'), $row->url, (string) $this->businessId],
                    base_path('..'),
                    $env,
                    null,
                    null
                );
                $process->setTimeout(null);
                $process->start();

                $pid = $process->getPid();
                $row->update(['pid' => $pid]);

                $lastHeartbeat = time();
                $lastStatusCheck = time();

                $streamPos = 0;
                $streamLinesCount = 0;

                $setMessage = function ($msg) use ($row) {
                    if ($msg) {
                        $row->message = $msg;
                        $logsAttr = $row->getAttribute('logs');
                        $logs = is_array($logsAttr) ? $logsAttr : (is_string($logsAttr) ? json_decode($logsAttr, true) : []);
                        if (! is_array($logs)) {
                            $logs = [];
                        }
                        if (empty($logs) || $logs[count($logs) - 1] !== $msg) {
                            $logs[] = $msg;
                            if (count($logs) > 10) {
                                $logs = array_slice($logs, -10);
                            }
                            $row->setAttribute('logs', $logs);
                        }
                    }
                };

                while ($process->isRunning()) {
                    usleep(1000000); // 1 second

                    $out = $process->getIncrementalOutput();
                    if ($out) {
                        $lines = explode("\n", rtrim($out, "\n"));
                        foreach ($lines as $line) {
                            if (str_starts_with($line, '## step ')) {
                                $step = substr($line, 8);
                                $progress = null;
                                $message = null;

                                if (in_array($step, ['setup workdir', 'copy template', 'mcp.json'])) {
                                    $message = SiteCloneJobs::MESSAGES['preparing'];
                                    $progress = 5;
                                } elseif ($step === 'run claude') {
                                    $message = SiteCloneJobs::MESSAGES['cloning'];
                                    $progress = 10;
                                } elseif ($step === 'static render') {
                                    $message = SiteCloneJobs::MESSAGES['screenshots'];
                                    $progress = 70;
                                } elseif (in_array($step, ['html-to-bundle', 'import'])) {
                                    $message = SiteCloneJobs::MESSAGES['importing'];
                                    if ($step === 'html-to-bundle') {
                                        $progress = 85;
                                    }
                                    if ($step === 'import') {
                                        $progress = 95;
                                    }
                                } elseif ($step === 'report.md') {
                                    $progress = 98;
                                }

                                if ($progress !== null) {
                                    $row->progress = $progress;
                                }
                                $setMessage($message);
                                $row->save();
                            }
                        }
                    }

                    $streamPath = $workDir.'/evidence/claude.stream.jsonl';
                    if (file_exists($streamPath)) {
                        $fp = fopen($streamPath, 'r');
                        if ($fp) {
                            fseek($fp, $streamPos);
                            while (($line = fgets($fp)) !== false) {
                                $streamLinesCount++;
                                $json = json_decode($line, true);
                                if ($json && isset($json['type'])) {
                                    if ($json['type'] === 'assistant' && isset($json['message']['content'])) {
                                        foreach ($json['message']['content'] as $content) {
                                            if (isset($content['type']) && $content['type'] === 'tool_use') {
                                                if (str_starts_with($content['name'], 'mcp__playwright__')) {
                                                    $setMessage(SiteCloneJobs::MESSAGES['screenshots']);
                                                    $row->save();
                                                } elseif (in_array($content['name'], ['Write', 'Edit'])) {
                                                    $setMessage(SiteCloneJobs::MESSAGES['building']);
                                                    $row->save();
                                                }
                                            }
                                        }
                                    } elseif ($json['type'] === 'result' && isset($json['total_cost_usd'])) {
                                        if (is_numeric($json['total_cost_usd'])) {
                                            $row->cost_usd = (float) $json['total_cost_usd'];
                                            $row->save();
                                        }
                                    }
                                }
                            }
                            $streamPos = ftell($fp);
                            fclose($fp);

                            if ($row->progress >= 10 && $row->progress < 65) {
                                $row->progress = min(65, 10 + intdiv(55 * $streamLinesCount, 400));
                                $row->save();
                            }
                        }
                    }

                    $now = time();
                    if ($now - $lastHeartbeat >= 15) {
                        $row->update(['heartbeat_at' => now()]);
                        $lastHeartbeat = $now;
                    }

                    if ($now - $lastStatusCheck >= 5) {
                        $freshRow = SiteCloneJob::find($this->jobId);
                        if ($freshRow && $freshRow->status === SiteCloneJob::CANCELLED) {
                            posix_kill(-$pid, SIGTERM);
                            $waitStart = time();
                            while (posix_kill(-$pid, 0) && time() - $waitStart < 10) {
                                usleep(100000);
                            }
                            if (posix_kill(-$pid, 0)) {
                                posix_kill(-$pid, SIGKILL);
                            }
                            if ($row->work_dir !== null && str_starts_with($row->work_dir, config('site_clone.root'))) {
                                File::deleteDirectory($workDir);
                            }
                            $row->update(['finished_at' => now()]);
                            $process->stop(0);
                            return;
                        }
                        $lastStatusCheck = $now;
                    }

                    if (now()->getTimestamp() - $row->started_at->getTimestamp() > $registry->int('sites.clone.timeout_seconds')) {
                        posix_kill(-$pid, SIGTERM);
                        $waitStart = time();
                        while (posix_kill(-$pid, 0) && time() - $waitStart < 10) {
                            usleep(100000);
                        }
                        if (posix_kill(-$pid, 0)) {
                            posix_kill(-$pid, SIGKILL);
                        }
                        $row->update([
                            'status' => SiteCloneJob::FAILED,
                            'error' => SiteCloneJobs::ERRORS['timeout'],
                            'internal_error' => 'timeout',
                            'finished_at' => now(),
                        ]);
                        if ($row->work_dir !== null && str_starts_with($row->work_dir, config('site_clone.root'))) {
                            File::deleteDirectory($workDir);
                        }
                        $process->stop(0);
                        return;
                    }
                }

                $rc = $process->getExitCode();
                $bundleExists = file_exists($workDir.'/evidence/bundle.json');

                $row->update(['heartbeat_at' => now()]);

                if ($rc === 0 && $bundleExists) {
                    $setMessage(SiteCloneJobs::MESSAGES['done']);
                    $row->update([
                        'status' => SiteCloneJob::DONE,
                        'progress' => 100,
                        'message' => $row->message,
                        'logs' => $row->getAttribute('logs'),
                        'finished_at' => now(),
                    ]);
                } else {
                    $outAll = $process->getOutput();
                    $errAll = $process->getErrorOutput();
                    $combined = $errAll.$outAll;
                    if (strlen($combined) > 2000) {
                        $combined = substr($combined, -2000);
                    }
                    $row->update([
                        'status' => SiteCloneJob::FAILED,
                        'error' => SiteCloneJobs::ERRORS['failed'],
                        'internal_error' => $combined,
                        'finished_at' => now(),
                    ]);
                    if ($row->work_dir !== null && str_starts_with($row->work_dir, config('site_clone.root'))) {
                        File::deleteDirectory($workDir);
                    }
                }

            } finally {
                DB::selectOne('select pg_advisory_unlock(hashtext(?))', ["site-clone-slot-{$lockN}"]);
            }
        });
    }
}
