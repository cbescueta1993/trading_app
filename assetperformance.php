<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
// Fetch Data from OKX API
function fetch_okx_data()
{
    
    // OKX API Credentials
    $apiKey = '8305ab39-255f-4e4a-a5dd-dff2753b0bce';
    $secretKey = 'AB3C0619E476262ED8ED460276BAD016';
    $passphrase = 'Elleryc1993$';
    $timestamp = gmdate('Y-m-d\TH:i:s\Z');
    $method = 'GET';
    $requestPath = '/api/v5/account/bills';
    $body = '';

    

    $signature = generateSignature($timestamp, $method, $requestPath, $body, $secretKey);
    $headers = [
        'OK-ACCESS-KEY: ' . $apiKey,
        'OK-ACCESS-SIGN: ' . $signature,
        'OK-ACCESS-TIMESTAMP: ' . $timestamp,
        'OK-ACCESS-PASSPHRASE: ' . $passphrase,
        'Content-Type: application/json'
    ];

    $ch = curl_init('https://www.okx.com' . $requestPath);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);
    //$data = json_decode($response, true);


    return json_decode($response, true);
}

function generateSignature($timestamp, $method, $requestPath, $body, $secretKey) {
    $preHash = $timestamp . $method . $requestPath . $body;
    return base64_encode(hash_hmac('sha256', $preHash, $secretKey, true));
}

// Insert or Update Data
function save_data($data, $conn)
{
    //print_r($data);
    //die();
    foreach ($data['data'] as $trade) {
        $orderid = $trade['billId'];
        $fillTime = $trade['ts']; // Example timestamp from API
        $datetime = new DateTime();
        $datetime->setTimestamp($fillTime / 1000); // Convert milliseconds to seconds
        $mysqlDate = $datetime->format('Y-m-d H:i:s');
        $date = $mysqlDate;
        $time = date("H:i:s", strtotime($trade['ts'] / 1000));
        $coin = $trade['instId'];
        $side = "";
        $pnl = $trade['pnl'];
        $pnlpercentage = "";

        $sql = "INSERT INTO pnl_data (orderid, date, coin, side, pnl, pnlpercentage)
                VALUES ('$orderid', '$date', '$coin', '$side', '$pnl', '$pnlpercentage')
                ON DUPLICATE KEY UPDATE
                date = VALUES(date),
                coin = VALUES(coin),
                side = VALUES(side),
                pnl = VALUES(pnl),
                pnlpercentage = VALUES(pnlpercentage)";

        $conn->query($sql);
    }
}

// Fetch OKX data and save to DB
if (isset($_GET['fetch'])) {
    $data = fetch_okx_data();
    save_data($data, $conn);
}

// Fetch total PNL per coin
$month = isset($_GET['month']) ? $_GET['month'] : date("m");
$year = isset($_GET['year']) ? $_GET['year'] : date("Y");

$query = "SELECT coin, SUM(pnl) AS total_pnl FROM pnl_data 
          WHERE MONTH(date) = '$month' AND YEAR(date) = '$year' 
          GROUP BY coin";
$result = $conn->query($query);

// Get grand total PNL
$total_pnl_query = "SELECT SUM(pnl) AS grand_total FROM pnl_data 
                    WHERE MONTH(date) = '$month' AND YEAR(date) = '$year'";
$total_result = $conn->query($total_pnl_query);
$grand_total = $total_result->fetch_assoc()['grand_total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OKX PNL Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Dashboard</a>
        </div>
    </nav>
<div class="container mt-4">
    <h2>OKX PNL Report</h2>
    <form method="GET">
        <label for="month">Month:</label>
        <select name="month" class="form-control">
            <?php for ($m = 1; $m <= 12; $m++) {
                echo "<option value='$m' " . ($month == $m ? "selected" : "") . ">$m</option>";
            } ?>
        </select>
        
        <label for="year">Year:</label>
        <select name="year" class="form-control">
            <?php for ($y = 2020; $y <= date("Y"); $y++) {
                echo "<option value='$y' " . ($year == $y ? "selected" : "") . ">$y</option>";
            } ?>
        </select>

        <button type="submit" class="btn btn-primary mt-2">Filter</button>
        <a href="?fetch=1" class="btn btn-success mt-2">Fetch OKX Data</a>
    </form>

    <h3 class="mt-4">Total PNL per Coin</h3>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Coin</th>
                <th>Total PNL</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()) {
                echo "<tr><td>{$row['coin']}</td><td>{$row['total_pnl']}</td></tr>";
            } ?>

            <tr>
            <td><strong>Grand Total</strong></td>
            <td><strong><?php echo number_format($grand_total, 2); ?></strong></td>
            </tr>
        </tbody>
    </table>
</div>
</body>
</html>
