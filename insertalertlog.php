<?php
date_default_timezone_set('Asia/Manila');
ignore_user_abort(true); // Allow script to continue if client disconnects
require_once 'config.php';

//$input = isset($_GET["input"]) ? $_GET["input"] : '';

$parts = explode(';', $input);
$paramSymbol = "";
$paramSide = "";
$paramDatetime = "";

if (count($parts) >= 3) {
    $paramSymbol = trim($parts[0]);
    $paramSide = trim($parts[1]);
    $paramDatetime = trim($parts[2]);
} else {
    die("Invalid input format. Expected SYMBOL;SIDE;DATETIME");
}



// Log error to database
function logAlert($conn, $coin, $side,$dateTime) {
    try {
        $stmt = $conn->prepare("INSERT INTO alertlogs (coin, side,dateTime) VALUES ( ?, ?, ?)");
        $stmt->bind_param("sss", $coin, $side,$dateTime);
        $stmt->execute();
        $stmt->close();
        //echo "Error logged: $coin - $errorMessage" . PHP_EOL;
    } catch (Exception $e) {
        echo "Error logging to database: " . $e->getMessage() . PHP_EOL;
    }
}


logAlert($conn, $paramSymbol, $paramSide,$paramDatetime);

?>