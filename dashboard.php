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
include 'okx_api.php'; // Create this file with the OKXTrading class

$timestamp = round(microtime(true) * 1000); // Ensure timestamp is included
$recvWindow = 10000; // Recommended recvWindow to prevent timing issues

// Initialize OKX API
$okx = new OKXTrading();
// $okx->apiKey = $user['apiKeyOkx'];//"8305ab39-255f-4e4a-a5dd-dff2753b0bce";
// $okx->secretKey = $user['secretKeyOkx'];//"AB3C0619E476262ED8ED460276BAD016";
// $okx->passphrase = $user['passPhraseOkx'];//"Elleryc1993$";

$okx->apiKey = "8305ab39-255f-4e4a-a5dd-dff2753b0bce";
$okx->secretKey = "AB3C0619E476262ED8ED460276BAD016";
$okx->passphrase = "Elleryc1993$";
$okx->isDemoTrading = false; // Set to true for demo/sandbox trading or false for live trading
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
            <a class="navbar-brand" href="#">Trading Dashboard</a>
            <a href="logout.php" class="btn btn-outline-light">Logout</a>
        </div>
    </nav>

    <div class="container text-center mt-5">
        <h3>Welcome, <?= $_SESSION['name'] ?></h3>
        <p><strong>Google email:</strong> <?= $_SESSION['email'] ?></p>
        <p><strong>Google ID:</strong> <?= $_SESSION['user_id'] ?></p>
        
        <div class="row mt-5">
            <div class="col-md-4">
                <a href="history.php" class="card text-center p-3 shadow text-decoration-none text-dark">
                    <i class="fas fa-history fa-3x"></i>
                    <h5 class="mt-2">History</h5>
                </a>
            </div>
            <div class="col-md-4">
                <a href="settings.php" class="card text-center p-3 shadow text-decoration-none text-dark">
                    <i class="fas fa-cog fa-3x"></i>
                    <h5 class="mt-2">Settings</h5>
                </a>
            </div>
            <div class="col-md-4">
                <a href="cryptocoins.php" class="card text-center p-3 shadow text-decoration-none text-dark">
                    <i class="fas fa-cog fa-3x"></i>
                    <h5 class="mt-2">Assets List</h5>
                </a>
            </div>
            <div class="col-md-4">
                <a href="assetperformance.php" class="card text-center p-3 shadow text-decoration-none text-dark">
                    <i class="fas fa-cog fa-3x"></i>
                    <h5 class="mt-2">Assets Performance</h5>
                </a>
            </div>
            <div class="col-md-4">
                <a href="error.php" class="card text-center p-3 shadow text-decoration-none text-dark">
                    <i class="fas fa-exclamation-triangle fa-3x"></i>
                    <h5 class="mt-2">Error logs</h5>
                </a>
            </div>
            <div class="col-md-4">
                <a href="okx_wallet.php" class="card text-center p-3 shadow text-decoration-none text-dark">
                    <i class="fas fa-exclamation-triangle fa-3x"></i>
                    <h5 class="mt-2">OKX WALLET</h5>
                </a>
            </div>
            <div class="col-md-4">
                <a href="binance_wallet.php" class="card text-center p-3 shadow text-decoration-none text-dark">
                    <i class="fas fa-exclamation-triangle fa-3x"></i>
                    <h5 class="mt-2">BINANCE WALLET</h5>
                </a>
            </div>
            <div class="col-md-4">
                <a href="maintenance.php" class="card text-center p-3 shadow text-decoration-none text-dark">
                    <i class="fas fa-exclamation-triangle fa-3x"></i>
                    <h5 class="mt-2">MAINTENANCE</h5>
                </a>
            </div>
        </div>
        
        <div class="row mt-5">

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>