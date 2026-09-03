<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');
$target = "    private function signUp(string \$businessName, string \$phone): array\n    {\n        throw \$this->todo('sign up with exactly two fields — a third is a P-207 violation');\n    }";
$replacement = <<<PHP
    private function signUp(string \$businessName, string \$phone): array
    {
        \$owner = \App\Models\User::factory()->create();
        \$res = app(\App\Modules\X118\Actions\OnboardingStartAction::class)->handle(\$owner, \$businessName, \$phone);
        
        // P-207 check
        if (\$res['asked_fields_count'] !== 2) {
            throw new \RuntimeException("HARD RULE VIOLATION: P-207 requires exactly two fields.");
        }

        \$biz = \App\Models\Business::find(\$res['business_id']);
        return \$biz->toArray();
    }
PHP;
$content = str_replace($target, $replacement, $content);
file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
