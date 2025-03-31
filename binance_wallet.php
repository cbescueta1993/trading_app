<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
$user_id = $_SESSION['user_id'];
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

include 'binance_api.php';

// Include OKX API class
// include 'okx_api.php'; // Create this file with the OKXTrading class

$timestamp = round(microtime(true) * 1000); // Ensure timestamp is included
$recvWindow = 10000; // Recommended recvWindow to prevent timing issues

// Initialize OKX API
// $okx = new OKXTrading();
// $okx->apiKey = $user['apiKeyOkx'];//"8305ab39-255f-4e4a-a5dd-dff2753b0bce";
// $okx->secretKey = $user['secretKeyOkx'];//"AB3C0619E476262ED8ED460276BAD016";
// $okx->passphrase = $user['passPhraseOkx'];//"Elleryc1993$";

// // $okx->apiKey = "8305ab39-255f-4e4a-a5dd-dff2753b0bce";
// // $okx->secretKey = "AB3C0619E476262ED8ED460276BAD016";
// // $okx->passphrase = "Elleryc1993$";
// $okx->isDemoTrading = false; // Set to true for demo/sandbox trading or false for live trading
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
	<link rel="icon" type="image/x-icon" href="favicon.ico"></link>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-dark">
        <div class="container d-flex justify-content-between">
            <a class="navbar-brand" href="index.php">Trading Dashboard</a>
            
        </div>
    </nav>

    <div class="col-md-6">
                <div class="card p-3 shadow mb-4">
                    <h5>Binance Wallet Balance</h5>
                    <p>
                        <?php
                        $response = binance_futures_request("/fapi/v2/balance", ["timestamp" => $timestamp,"recvWindow" => $recvWindow], "GET");

                        if (!is_array($response)) {
                            echo "Error: Invalid response from Binance API";
                        } elseif (isset($response['code'])) {
                            echo "Error: " . $response['msg'];
                        } else {
                            // Loop through the balances and display them
                            foreach ($response as $balance) {
                                echo "Asset: " . $balance['asset'] . " - Balance: " . $balance['balance'] . "<br>";
                            }
                        }
                        ?>
                    </p>
                </div>

                <div class="card p-3 shadow mb-4">
                    <h5>Binance Open Positions</h5>
                    <p>
                        <?php 
                        // Get Open Positions from Binance
                        $response = binance_futures_request('/fapi/v3/positionRisk', ["timestamp" => $timestamp,"recvWindow" => $recvWindow], "GET");

                        if (!is_array($response)) {
                            echo "Error: Invalid response from Binance API";
                        } elseif (isset($response['code'])) {
                            echo "Error: " . $response['msg'];
                        } else {
                            // Count Open Positions
                            $open_positions = 0;
                            $position_details = [];
                            
                            foreach ($response as $position) {
                                if (abs(floatval($position['positionAmt'])) > 0) { // Check if position is non-zero
                                    $open_positions++;
                                    $position_details[] = $position;
                                }
                            }

                            echo "<div class='mb-3'>Number of Open Positions: $open_positions</div>";

                            // Display position details if any
                            if ($open_positions > 0) {
                                echo "<table class='table table-sm table-striped'>";
                                echo "<thead><tr><th>Symbol</th><th>Amount</th><th>Entry Price</th><th>PnL</th></tr></thead>";
                                echo "<tbody>";
                                
                                foreach ($position_details as $pos) {
                                    $pnl = floatval($pos['unRealizedProfit']);
                                    $pnlClass = $pnl >= 0 ? 'text-success' : 'text-danger';
                                    
                                    echo "<tr>";
                                    echo "<td>" . $pos['symbol'] . "</td>";
                                    echo "<td>" . $pos['positionAmt'] . "</td>";
                                    echo "<td>" . $pos['entryPrice'] . "</td>";
                                    echo "<td class='$pnlClass'>" . $pnl . "</td>";
                                    echo "</tr>";
                                }
                                
                                echo "</tbody></table>";
                            }
                        }
                        ?>
                    </p>
                </div>
            </div>
</body>
</html>