<?php

date_default_timezone_set('Asia/Manila');
ignore_user_abort(true); // Allow script to continue if client disconnects

require_once 'config.php';

$input = isset($_GET["input"]) ? $_GET["input"] : '';

// SYMBOL;SIDE;GOOGLEID;
$parts = explode(';', $input);
$symbol = "";
$side = "";
$user_id = "";

if (count($parts) >= 3) {
    $symbol = trim($parts[0]);
    $side = trim($parts[1]);
    $user_id = trim($parts[2]);
} else {
    die("Invalid input format. Expected SYMBOL;SIDE;GOOGLEID.");
}

$query = "SELECT * FROM users WHERE google_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$binance_futures_url = BINANCE_FUTURE_URL;
$api_key = $user['api_key'];
$api_secret = $user['api_secret'];
$margin = $user['margin'];
$leverage = $user['leverage'];
$amount_usdt = $margin;

include "binance_api.php";

function cancel_all_futures_orders($symbol) {
    binance_futures_request("/fapi/v1/allOpenOrders", ["symbol" => $symbol], "DELETE");
}

function get_futures_position($symbol) {
    $positions = binance_futures_request("/fapi/v2/positionRisk", ["symbol" => $symbol], "GET");
    if (!is_array($positions) || count($positions) == 0) {
        return 0;
    }
    foreach ($positions as $position) {
        if (isset($position['symbol']) && $position['symbol'] == $symbol) {
            return floatval($position['positionAmt']);
        }
    }
    return 0;
}

function close_futures_position($symbol) {
    $positionAmt = get_futures_position($symbol);
    if ($positionAmt != 0) {
        $_side = ($positionAmt > 0) ? "SELL" : "BUY";
        binance_futures_request("/fapi/v1/order", [
            "symbol" => $symbol,
            "side" => $_side,
            "type" => "MARKET",
            "quantity" => abs($positionAmt)
        ], "POST");
    }
}

// Function to insert logs into `trade_journal` table
function log_trade($conn, $google_id, $symbol, $side, $quantity, $entry_price, $leverage, $margin, $status) {
    $query = "INSERT INTO trade_journal (google_id, symbol, side, quantity, entry_price, leverage, margin, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssssddds", $google_id, $symbol, $side, $quantity, $entry_price, $leverage, $margin, $status);
    $stmt->execute();
}

cancel_all_futures_orders($symbol);
usleep(10000);
close_futures_position($symbol);
usleep(10000);

$leverage_response = binance_futures_request('/fapi/v1/leverage', [
    'symbol' => $symbol,
    'leverage' => $leverage
], "POST");

usleep(10000);

$entryPrice = getMarketPrice($symbol);
$precision = getSymbolPrecision($symbol);
$precisionQty = $precision['qty'];
$minQty = $precision['minQty'];
$minNotional = getDynamicMinNotional($symbol);

$quantity = round((($amount_usdt * $leverage) / $entryPrice), $precisionQty);
if ($quantity < $minQty) {
    $quantity = $minQty;
}
$notional = $quantity * $entryPrice;
if ($notional < $minNotional) {
    $quantity = round(($minNotional / $entryPrice), $precisionQty);
}
$quantity = number_format($quantity, $precisionQty, '.', '');

$attempts = 0;
$success = false;


while ($attempts < 3 && !$success) {
    $order_response = binance_futures_request('/fapi/v1/order', [
        'symbol' => $symbol,
        'side' => $side,
        'type' => 'MARKET',
        'quantity' => $quantity
    ], "POST");

    if (isset($order_response['orderId'])) {
        echo "SUCCESS";
        $success = true;
    } else {
        $attempts++;
        if ($attempts < 3) {
            usleep(500000);
        }
    }
}

if (!$success) {
    log_trade($conn, $user_id, $symbol, $side, $quantity, $entryPrice, $leverage, $margin, 'FAILED');
}else{
    log_trade($conn, $user_id, $symbol, $side, $quantity, $entryPrice, $leverage, $margin, 'SUCCESS');
}

?>
