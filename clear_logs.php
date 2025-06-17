<?php
$logFile = 'alertlog_okx.txt';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (file_exists($logFile)) {
        file_put_contents($logFile, ''); // Clear contents
        echo "✅ Logs cleared successfully.";
    } else {
        echo "⚠️ Log file does not exist.";
    }
} else {
    echo "❌ Invalid request.";
}
