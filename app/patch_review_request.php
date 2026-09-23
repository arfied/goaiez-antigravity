<?php

$content = file_get_contents('app/Modules/C-Reviews/Actions/ReviewRequestAction.php');
$content = str_replace('private const CADENCE_WINDOW_DAYS = 30;', 'public const CADENCE_WINDOW_DAYS = 30;
    public const LOW_CSAT_BELOW = 7;
    public const LOW_CSAT_JOB_AGE_DAYS = 60;

    public function __construct(
        private readonly \App\Support\DefaultsRegistry $registry
    ) {}

    private function cadenceWindowDays(): int
    {
        return $this->registry->int(\'reviews.request.cadence_window_days\');
    }

    private function lowCsatBelow(): int
    {
        return $this->registry->int(\'reviews.request.low_csat_below\');
    }

    private function lowCsatJobAgeDays(): int
    {
        return $this->registry->int(\'reviews.request.low_csat_job_age_days\');
    }', $content);

$content = str_replace('if ($csatScore !== null && $csatScore < 7 && ($jobAgeDays ?? 0) >= 60) {', 'if ($csatScore !== null && $csatScore < $this->lowCsatBelow() && ($jobAgeDays ?? 0) >= $this->lowCsatJobAgeDays()) {', $content);
$content = str_replace('->where(\'created_at\', \'>=\', Carbon::now()->subDays(self::CADENCE_WINDOW_DAYS))', '->where(\'created_at\', \'>=\', Carbon::now()->subDays($this->cadenceWindowDays()))', $content);

file_put_contents('app/Modules/C-Reviews/Actions/ReviewRequestAction.php', $content);
