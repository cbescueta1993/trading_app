<?php
$input = file_get_contents("php://input");
//file_put_contents("alertlog_okx.txt", $input . PHP_EOL, FILE_APPEND);

function logToFile($message, $retries = 3, $delayMicroseconds = 200000) {
    $logFile = 'alertlog_okx.txt';
    //$timestamp = date('Y-m-d H:i:s');
    $logEntry = "$message" . PHP_EOL;

    $attempt = 0;
    while ($attempt < $retries) {
        $fileHandle = fopen($logFile, 'a');
        if ($fileHandle) {
            if (flock($fileHandle, LOCK_EX)) {
                fwrite($fileHandle, $logEntry);
                flock($fileHandle, LOCK_UN);
                fclose($fileHandle);
                return true; // success
            } else {
                fclose($fileHandle);
                error_log("Could not lock the log file on attempt #".($attempt + 1));
            }
        } else {
            error_log("Could not open the log file on attempt #".($attempt + 1));
        }

        $attempt++;
        usleep($delayMicroseconds); // wait before retrying
    }

    return false; // failed after retries
}

//$input='XRPUSDT;BUY;09/10/1993';
logToFile($input);
exit;


?>