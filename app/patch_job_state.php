<?php

$content = file_get_contents('app/Modules/X-171/Actions/JobStateAction.php');
$content = str_replace(
    "->where('id', \$jobId)\n                ->value('person_id');",
    "->where('business_id', \$businessId)\n                ->where('id', \$jobId)\n                ->value('person_id');",
    $content
);
file_put_contents('app/Modules/X-171/Actions/JobStateAction.php', $content);
