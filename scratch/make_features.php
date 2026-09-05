<?php
$json = json_decode(file_get_contents('.agents/supervisor/NAVIGATION.json'), true);
$content = "<?php\n\nreturn [\n    'entries' => [\n";
foreach ($json['entries'] as $entry) {
    $content .= "        [\n";
    $content .= "            'label' => '{$entry['label']}',\n";
    $content .= "            'blurb' => '{$entry['blurb']}',\n";
    $content .= "            'modules' => [\n";
    foreach ($entry['modules'] as $mod) {
        $content .= "                '{$mod}',\n";
    }
    $content .= "            ],\n";
    $content .= "        ],\n";
}
$content .= "    ],\n    'deferred' => [\n";
foreach ($json['deferred'] as $def) {
    $content .= "        '{$def}',\n";
}
$content .= "    ],\n];\n";
file_put_contents('app/config/features.php', $content);
