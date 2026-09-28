<?php

declare(strict_types=1);

namespace App\Modules\X220\Actions;

use App\Enums\AiTask;
use App\Modules\X220\Events\EvalCompleted;
use App\Modules\X220\Events\EvalRegressed;
use App\Modules\X220\Models\AiPrompt;
use App\Modules\X220\Models\GoldenSet;
use App\Services\Ai\AiRequest;
use App\Services\Ai\AiRouter;
use App\Services\Ai\AiSpend;
use App\Services\Config\DefaultsRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class EvalRunAction
{
    public function handle(int $businessId, int $promptId, ?int $goldenSetId = null): array
    {
        $prompt = AiPrompt::where('business_id', $businessId)->findOrFail($promptId);

        $golden = $goldenSetId
            ? GoldenSet::where('business_id', $businessId)->findOrFail($goldenSetId)
            : GoldenSet::where('business_id', $businessId)->where('prompt_id', $promptId)->first();

        if (! $golden) {
            return [
                'status' => 'not_run',
                'reason' => 'no_golden_set',
                'test_cases_count' => 0,
            ];
        }

        $spend = app(AiSpend::class);
        if (! $spend->allows()) {
            return [
                'status' => 'refused',
                'reason' => 'budget',
                'test_cases_count' => count($golden->test_cases ?? []),
            ];
        }

        $threshold = $golden->score_threshold;
        $testCases = $golden->test_cases ?? [];
        $expectedOutputs = $golden->expected_outputs ?? [];

        $defaults = app(DefaultsRegistry::class);
        $maxCases = $defaults->int('ai.eval.max_cases_per_run');
        $passThreshold = $defaults->int('ai.eval.similarity_pass_pct');

        $casesToRun = array_slice($testCases, 0, $maxCases);
        $totalCases = count($casesToRun);

        if ($totalCases === 0) {
            return [
                'status' => 'not_run',
                'reason' => 'no_cases',
                'test_cases_count' => 0,
            ];
        }

        $router = app(AiRouter::class);

        // Map job class/prompt key to task.
        $taskValue = $prompt->prompt_key;
        $task = AiTask::tryFrom($taskValue);
        if (! $task) {
            // fallback
            $jobTask = is_string($prompt->job_class) ? (AiTask::tryFrom($prompt->job_class) ?? AiTask::Conversation) : AiTask::Conversation;
            $task = match ($jobTask) {
                AiTask::ReplyGeneration => AiTask::ReplyGeneration,
                AiTask::ReviewAnalysis => AiTask::ReviewAnalysis,
                AiTask::Moderation => AiTask::Moderation,
                AiTask::Conversation => AiTask::Conversation,
                AiTask::KnowledgeEmbedding => AiTask::KnowledgeEmbedding,
                AiTask::SiteCopy => AiTask::SiteCopy,
                AiTask::SiteImage => AiTask::SiteImage,
            };
        }

        $startedAt = Carbon::now();
        $casesPassed = 0;
        $cost = 0;
        $results = [];

        $modelRequested = $spend->modelFor($task)->value;
        $modelServed = null;

        foreach ($casesToRun as $index => $case) {
            $input = $case['input'] ?? '';
            $expected = $expectedOutputs[$index]['expected'] ?? '';

            $request = new AiRequest(
                task: $task,
                prompt: $input,
                system: $prompt->body,
                promptKey: $prompt->prompt_key
            );

            $response = $router->dispatch($request);

            $cost += $response->costInHundredthsOfCents();
            $modelServed = $response->model->value;

            $passed = false;
            $similarityPct = 0;
            $gotText = '';

            if (! $response->refused && $response->failureReason === null) {
                $gotText = $response->text ?? '';

                $gotNorm = strtolower(preg_replace('/\s+/', ' ', trim($gotText)));
                $expNorm = strtolower(preg_replace('/\s+/', ' ', trim($expected)));

                if ($expNorm !== '') {
                    similar_text($gotNorm, $expNorm, $percent);
                    $similarityPct = (int) round($percent);
                    if ($similarityPct >= $passThreshold) {
                        $passed = true;
                    }
                } elseif ($gotNorm === '') {
                    $similarityPct = 100;
                    $passed = true;
                }
            }

            if ($passed) {
                $casesPassed++;
            }

            $results[] = [
                'input_hash' => hash('sha256', $input),
                'expected_hash' => hash('sha256', $expected),
                'got_hash' => hash('sha256', $gotText),
                'similarity_pct' => $similarityPct,
                'passed' => $passed,
            ];
        }

        $finishedAt = Carbon::now();
        $scorePct = (int) round(($casesPassed / $totalCases) * 100);
        $overallPassed = $scorePct >= $threshold;

        $runId = DB::table('x220_eval_runs')->insertGetId([
            'business_id' => $businessId,
            'golden_set_id' => $golden->id,
            'prompt_id' => $prompt->id,
            'prompt_version' => $prompt->version,
            'model_requested' => $modelRequested,
            'model_served' => $modelServed,
            'cases_total' => $totalCases,
            'cases_passed' => $casesPassed,
            'score_pct' => $scorePct,
            'threshold_pct' => $threshold,
            'passed' => $overallPassed,
            'cost_hundredths_cents' => $cost,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'results' => json_encode($results),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $row = (array) DB::table('x220_eval_runs')->find($runId);

        Event::dispatch(new EvalCompleted($businessId, $prompt->id, $scorePct, $overallPassed));
        if (! $overallPassed) {
            Event::dispatch(new EvalRegressed($businessId, $prompt->id, $scorePct, $threshold));
        }

        $row['status'] = 'run';
        $row['test_cases_count'] = $totalCases;

        return $row;
    }
}
