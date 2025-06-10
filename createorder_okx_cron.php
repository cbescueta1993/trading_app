<?php
date_default_timezone_set('Asia/Manila');
ignore_user_abort(true); // Allow script to continue if client disconnects
require_once 'config.php';
require_once 'okx_api2.php'; // or include_once

$filename = "alertlog_okx.txt";

// Log error to database if dateTime does not already exist
function logAlertIfNotExists($conn, $coin, $side, $dateTime) {
    // Check if the record with same dateTime already exists
    $checkStmt = $conn->prepare("SELECT id FROM alertlogs WHERE dateTime = ? and coin= ?");
    $checkStmt->bind_param("ss", $dateTime,$coin);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows == 0) {
        // Insert new log
        $insertStmt = $conn->prepare("INSERT INTO alertlogs (coin, side, dateTime) VALUES (?, ?, ?)");
        $insertStmt->bind_param("sss", $coin, $side, $dateTime);
        $insertStmt->execute();
        $insertStmt->close();
    }

    $checkStmt->close();
}

if (file_exists($filename)) {
    $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $parts = explode(';', trim($line));

        if (count($parts) === 3) {
            $symbol = trim($parts[0]);
            $side = trim($parts[1]);
            $dateTime = trim($parts[2]);

            logAlertIfNotExists($conn, $symbol, $side, $dateTime);
        }
    }

    echo "Processing complete.";
} else {
    echo "File not found: $filename";
}

//$userid = $argv[1] ?? "";
$userid = isset($_GET["userid"]) ? $_GET["userid"] : '';//"102871033794724054940" 12
//public_html/tradingapp/createorder_okx_cron.php?userid=102871033794724054940
// SYMBOL;SIDE;GOOGLEID;
//$parts = explode(';', $input);
$paramSymbol = "";
$paramSide = "";
$paramUserId = $userid;
$paramSymbolId=""; 

$query = "SELECT * FROM alertlogs where isexecuted = 0 LIMIT 1";
$stmt = $conn->prepare($query);
//$stmt->bind_param("s", $paramUserId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $alertlogs = $result->fetch_assoc();
} else {
    die("No alertlogs found");
}

$paramSymbol = $alertlogs['coin'];
$paramSide = $alertlogs['side'];
$paramSymbolId = $alertlogs['id']; 

$query = "SELECT * FROM users WHERE google_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $paramUserId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
} else {
    die("No user found.");
}


$paramMargin = $user['margin'];
$paramLeverage = $user['leverage'];



// Log trade to database
function log_trade($conn, $google_id, $symbol, $side, $quantity, $entry_price, $leverage, $margin, $status) {
    try {
        $query = "INSERT INTO trade_journal (google_id, symbol, side, quantity, entry_price, leverage, margin, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssddds", $google_id, $symbol, $side, $quantity, $entry_price, $leverage, $margin, $status);
        $stmt->execute();
        echo "Trade logged successfully: $symbol $side" . PHP_EOL;
    } catch (Exception $e) {
        echo "Error logging trade: " . $e->getMessage() . PHP_EOL;
    }
}

// Log error to database
function logError($conn, $coinName, $errorMessage, $userId) {
    try {
        $stmt = $conn->prepare("INSERT INTO error_logs (coin_name, error_message, google_id) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $coinName, $errorMessage, $userId);
        $stmt->execute();
        $stmt->close();
        echo "Error logged: $coinName - $errorMessage" . PHP_EOL;
    } catch (Exception $e) {
        echo "Error logging to database: " . $e->getMessage() . PHP_EOL;
    }
}

// Log error to database
function updateAlertLogs($conn, $alertlogsid) {
    try {
        $stmt = $conn->prepare("UPDATE alertlogs SET isexecuted = 1 WHERE id = ?");
        $stmt->bind_param("s", $alertlogsid);
        $stmt->execute();
        $stmt->close();
        //echo "Error alert logged: $alertlogsid - $errorMessage" . PHP_EOL;
    } catch (Exception $e) {
        echo "Error logging to database: " . $e->getMessage() . PHP_EOL;
    }
}

// Create trading instance
$okx = new OKXTrading($user['apiKeyOkx'], $user['secretKeyOkx'], $user['passPhraseOkx']);

//$okx->setapiKey($user['apiKeyOkx']);
//$okx->setsecretKey($user['secretKeyOkx']);
//$okx->setpassphrase($user['passPhraseOkx']);

$okxWalletBalance=$okx->getWalletBalance();

$paramMargin=round(($okxWalletBalance*0.96),2);

// Set symbol and get instrument ID
echo "Searching for instrument ID for symbol: $paramSymbol" . PHP_EOL;
$instrumentId = $okx->getInsId($paramSymbol);

if ($instrumentId === null) {
    $errorMsg = "Instrument ID not found for symbol: $paramSymbol";
    logError($conn, $paramSymbol, $errorMsg, $paramUserId);
    die($errorMsg);
}

$okx->instId = $instrumentId;
echo "Instrument ID found: " . $instrumentId . PHP_EOL;

// Step 1: Close any existing positions
$closeResponse = $okx->closePositions();
echo "Close positions response: " . json_encode($closeResponse) . PHP_EOL;

// Step 2: Set leverage
$leverageResponse = $okx->setLeverage($paramLeverage);
echo "Set leverage response: " . json_encode($leverageResponse) . PHP_EOL;

// Step 3: Place the order with retry mechanism
$orderResponse = $okx->placeOrderWithRetry(strtolower($paramSide), $paramMargin, $paramLeverage);
echo "Order response: " . json_encode($orderResponse) . PHP_EOL;

// Retry tracking variable
$orderPlaced = false;

// Handle the order response
if (isset($orderResponse["data"]) && isset($orderResponse["data"][0]["ordId"]) && !empty($orderResponse["data"][0]["ordId"])) {
    $entryPrice = $okx->getentryprice();
    $quantity = $okx->getquantity();
    $status = "Success";
    log_trade($conn, $paramUserId, $paramSymbol, $paramSide, $quantity, $entryPrice, $paramLeverage, $paramMargin, $status);
    echo "Order placed successfully!" . PHP_EOL;
    $orderPlaced = true;
    updateAlertLogs($conn, $paramSymbolId);
} else {
    $errorMsg = json_encode($orderResponse);
    logError($conn, $paramSymbol, $errorMsg, $paramUserId);
    echo "Error placing order after all retries: " . $errorMsg . PHP_EOL;
}

// Additional error handling and logging
if (!$orderPlaced) {
    $finalErrorLog = "Failed to place order for symbol $paramSymbol after multiple attempts.";
    logError($conn, $paramSymbol, $finalErrorLog, $paramUserId);
}
?>