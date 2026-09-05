<?php
$config = file_get_contents('app/config/features.php');

$replacements = [
    'C-Ai' => 'Automations & Assistant',
    'X-119' => 'Settings',
    'X-121' => 'Settings',
    'X-123' => 'Settings',
    'X-126' => 'Settings',
    'X-128' => 'Settings',
    'X-148' => 'Settings',
    'X-149' => 'Settings',
    'X-154' => 'Settings',
    'X-160' => 'Settings',
    'X-161' => 'Settings',
    'X-219' => 'Settings',
    'X-220' => 'Settings',
];

// Instead of parsing, we can just inject them into the 'Settings' entry's modules list for now.
// "Settings" is one of the entries.
// Let's just find "Settings" and add them all there.
// Actually, I can just write a regex to add them to 'Settings' modules array.
$config = preg_replace_callback(
    "/'label' => 'Settings',(.*?)'modules' => \\[(.*?)\\]/s",
    function($matches) {
        $modules = $matches[2];
        foreach (['C-Ai', 'X-119', 'X-121', 'X-123', 'X-126', 'X-128', 'X-148', 'X-149', 'X-154', 'X-160', 'X-161', 'X-219', 'X-220'] as $m) {
            if (strpos($modules, "'$m'") === false) {
                $modules .= "                '$m',\n";
            }
        }
        return "'label' => 'Settings'," . $matches[1] . "'modules' => [" . $modules . "]";
    },
    $config
);

file_put_contents('app/config/features.php', $config);
