<?php

$content = file_get_contents('app/tests/Modules/X-103/Screens/SiteBuildScreenTest.php');

$content = preg_replace("/\\\$biz->update\(\['industry' => 'trades', 'site_variant' => 'c'\]\);\n\s*/", "", $content);

file_put_contents('app/tests/Modules/X-103/Screens/SiteBuildScreenTest.php', $content);
