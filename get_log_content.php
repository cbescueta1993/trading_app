<?php
$logFile = 'alertlog_okx.txt';

if (file_exists($logFile)) {
    $content = file_get_contents($logFile);
    echo $content;
} else {
    http_response_code(404);
    echo 'Log file not found';
}
?>