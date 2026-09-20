<?php
$file = 'app/app/Console/Commands/BriefCommand.php';
$content = file_get_contents($file);
$content = str_replace('R197.', 'the warehouse fence.', $content);
$content = str_replace('R197 — a van', 'the warehouse fence — a van', $content);
$content = str_replace("'R197'", "'the warehouse fence'", $content);
$content = str_replace('R184 — the ATS fence', 'the ATS fence', $content);
$content = str_replace("'R184'", "'the ATS fence'", $content);
file_put_contents($file, $content);
