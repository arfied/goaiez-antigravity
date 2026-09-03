<?php
$content = file_get_contents('app/app/Services/Voice/VoiceCalls.php');
$content = str_replace("'basis' => 'Inbound call',", "", $content);
file_put_contents('app/app/Services/Voice/VoiceCalls.php', $content);
