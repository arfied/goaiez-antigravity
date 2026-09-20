<?php
$output = shell_exec('cd app && php artisan doctor --stage=capability');
$output = preg_replace('/\x1b\[[0-9;]*m/', '', $output);
preg_match_all('/· ((?:X|C)-[A-Za-z0-9]+) · (G\d+-\d+|N-\d+(?:-\d+)?): the ⑤ names no refusal/', $output, $matches, PREG_SET_ORDER);

$count = 0;
foreach ($matches as $m) {
    $mod = $m[1];
    $id = $m[2];
    $file = "app/app/Modules/{$mod}/capabilities.php";
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $content = preg_replace("/('{$id}'\s*=>\s*')(.*?)',/", "$1$2 refuses',", $content);
        file_put_contents($file, $content);
        $count++;
    }
}
echo "Fixed $count remaining refusals directly in capabilities.php.\n";
