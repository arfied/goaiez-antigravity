<?php
$src = "@ingress browser page.loaded";
preg_match_all('/@ingress\s+([a-z][a-z0-9_.]+)/', $src, $mi);
print_r($mi);
