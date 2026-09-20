<?php
require 'test_parse.php';
$module = 'X-150';
$filtered = array_filter($caps, fn ($c) => str_contains($c['parent'], $module));
var_dump(isset($filtered['N-054']));
var_dump($filtered['N-054']['assertion']);
