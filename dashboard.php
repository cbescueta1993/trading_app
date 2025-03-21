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
                <a href="error.php" class="card text-center p-3 shadow text-decoration-none text-dark">
                    <i class="fas fa-cog fa-3x"></i>
                    <h5 class="mt-2">Error logs</h5>
                </a>
            </div>
        </div>
        
        <div class="row mt-5">
            <div class="col-md-4">
                <div class="card p-3 shadow">
                    <h5>Wallet Balance</h5>
                    <p>
                        <?php
                        $response = binance_futures_request("/fapi/v2/balance", [], "GET");

                        if (!is_array($response)) {
                            die("Error: Invalid response from Binance API");
                        }
                        
                        if (isset($response['code'])) {
                            die("Error: " . $response['msg']);
                        }
                        
                        // Loop through the balances and display them
                        foreach ($response as $balance) {
                            echo "Asset: " . $balance['asset'] . " - Balance: " . $balance['balance'] . "<br>";
                        }
                        
                        ?>
                    </p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-3 shadow">
                    <h5>Open Positions</h5>
                    <p><?php 
                    // **Get Open Positions**
                            $response = binance_futures_request('/fapi/v2/positionRisk', [], "GET");

                            if (isset($response['code'])) {
                                die("Error: " . $response['msg']);
                            }

                            // **Count Open Positions**
                            $open_positions = 0;
                            foreach ($response as $position) {
                                if (abs(floatval($position['positionAmt'])) > 0) { // Check if position is non-zero
                                    $open_positions++;
                                }
                            }

                            echo "Number of Open Positions: $open_positions\n";
                    
                    ?>
                    </p>
                </div>
            </div>
            
        </div>
    </div>
</body>
</html>
