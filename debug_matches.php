<?php
$output = shell_exec('cd app && php artisan doctor --stage=capability');
$output = preg_replace('/\x1b\[[0-9;]*m/', '', $output);
preg_match_all('/· ((?:X|C)-[A-Za-z0-9]+) · (G\d+-\d+|N-\d+(?:-\d+)?): the ⑤ names no refusal/', $output, $matches, PREG_SET_ORDER);
var_dump(count($matches));
