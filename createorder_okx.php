<?php
date_default_timezone_set('Asia/Manila');
ignore_user_abort(true); // Allow script to continue if client disconnects
require_once 'config.php';

$input = isset($_GET["input"]) ? $_GET["input"] : '';

// SYMBOL;SIDE;GOOGLEID;
$parts = explode(';', $input);
$paramSymbol = "";
$paramSide = "";
$paramUserId = "";

if (count($parts) >= 3) {
    $paramSymbol = trim($parts[0]);
    $paramSide = trim($parts[1]);
    $paramUserId = trim($parts[2]);
} else {
    die("Invalid input format. Expected SYMBOL;SIDE;GOOGLEID.");
}

$query = "SELECT * FROM users WHERE google_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $paramUserId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

//$binance_futures_url = BINANCE_FUTURE_URL;
//$api_key = $user['api_key'];
//$api_secret = $user['api_secret'];

$paramMargin = $user['margin'];
$paramLeverage = $user['leverage'];


//$amount_usdt = $margin;


class OKXTrading {

    public $apiKey = "8305ab39-255f-4e4a-a5dd-dff2753b0bce";
    public $secretKey = "AB3C0619E476262ED8ED460276BAD016";
    public $passphrase = "Elleryc1993$";
    public $baseUrl = 'https://www.okx.com'; // Testnet Base URL
    public $_entryPrice=0;
    public $_quantity=0;
    public $instId = ""; // Swap contract for perpetual futures
    

    public function __construct(){
       // echo $baseUrl;
        
    }
    public function setentryPrice($entryPrice){
        $this->_entryPrice=$entryPrice;
    }

    public function setquantity($quantity){
        $this->_quantity=$quantity;
    }

    public function getentryprice(){
        return $this->_entryPrice;
    }
    public function getquantity(){
        return $this->_quantity;
    }
    

     // ✅ Generate API Signature
     private function generateSignature($timestamp, $method, $endpoint, $body) {
        $message = $timestamp . $method . $endpoint . $body;
        return base64_encode(hash_hmac("sha256", $message, $this->secretKey, true));
    }
    

    // ✅ Send API Request (GET, POST, DELETE)
    public function sendRequest($method, $endpoint, $payload = []) {
        $url = $this->baseUrl . $endpoint;
        $timestamp = number_format(microtime(true), 3, '.', '');
        $body = ($method === "POST" || $method === "DELETE") ? json_encode($payload) : "";

        $headers = [
            "OK-ACCESS-KEY: {$this->apiKey}",
            "OK-ACCESS-SIGN: " . $this->generateSignature($timestamp, $method, $endpoint, $body),
            "OK-ACCESS-TIMESTAMP: $timestamp",
            "OK-ACCESS-PASSPHRASE: {$this->passphrase}",
            "Content-Type: application/json"
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === "POST") {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } elseif ($method === "DELETE") {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        }

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }


    // Step 1: Close All Positions for PEPE Futures (Testnet)
    public function closePositions() {
        $endpoint = "/api/v5/account/close-positions";
        $payload = [
            "instId" => $this->instId,
            "mgnMode" => "isolated",
            "posSide" => "net"
        ];

        return $this->sendRequest("POST", $endpoint, $payload);
    }

    // Step 2: Set Margin Mode to Isolated & Leverage to 3x (Testnet)
    public function setLeverage($leverage) {
        $endpoint = "/api/v5/account/set-leverage";
        $payload = [
            "instId" => $this->instId,
            "lever" => $leverage,
            "mgnMode" => "isolated"
        ];

        return $this->sendRequest("POST", $endpoint, $payload);
    }

    

    public function placeOrder($side, $dollarAmount) {
        $endpoint = "/api/v5/trade/order";
    
        // Step 1: Fetch Current Price
        $price = $this->getCurrentPrice();
        if ($price <= 0) {
            return ["error" => "Failed to fetch price"];
        }
        $this->setentryPrice($price);

        // Step 2: Fetch Lot Size
        $lotSize = $this->getLotSize();
        if ($lotSize <= 0) {
            return ["error" => "Failed to fetch lot size"];
        }
    
        // Step 3: Calculate Quantity
        $quantity = round(($dollarAmount / $price) / $lotSize) * $lotSize;
        $this->setquantity($quantity);
    
        // Ensure the quantity is not zero
        if ($quantity < $lotSize) {
            return ["error" => "Order size too small. Increase dollar amount."];
        }
    
        // Step 4: Prepare API Payload
        $payload = [
            "instId" => $this->instId,
            "tdMode" => "isolated",
            "side" => $side, // "buy" for long, "sell" for short
            "ordType" => "market",
            "sz" => $quantity
        ];
    
        return $this->sendRequest("POST", $endpoint, $payload);
    }
    
    // ✅ Fetch Current Market Price
    public function getCurrentPrice() {
        $endpoint = "/api/v5/market/ticker";
        $params = ["instId" => $this->instId];
    
        $response = $this->sendRequestGet($endpoint, $params);
        
        if (!isset($response["data"][0]["last"])) {
            return 0;
        }
    
        return (float) $response["data"][0]["last"];
    }
    
    // ✅ Fetch Lot Size of the Instrument
    public function getLotSize() {
        $endpoint = "/api/v5/public/instruments";
        $params = ["instType" => "SWAP"];
    
        $response = $this->sendRequestGet($endpoint, $params);
    
        if (!isset($response["data"])) {
            return 0;
        }
    
        foreach ($response["data"] as $instrument) {
            if ($instrument["instId"] === $this->instId) {
                return (float) $instrument["lotSz"]; // Lot size of the instrument
            }
        }
    
        return 0;
    }

    // ✅ Proper GET Request Handling
    public function sendRequestGet($endpoint, $params = []) {
        $queryString = http_build_query($params);
        $url = $this->baseUrl . $endpoint . '?' . $queryString;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    public function getInsId($symbol) {
        $endpoint = "/api/v5/public/instruments";
        $payload = ["instType" => "SWAP"]; // Get perpetual futures (swap contracts)

        $response = $this->sendRequestGet($endpoint, $payload);

        if (!isset($response["data"])) {
            return "Error fetching instruments.";
        }

        foreach ($response["data"] as $instrument) {
            
            if (stripos($instrument["instId"], strtoupper($symbol)) !== false) {
                return $instrument["instId"]; // Return the first matching instrument ID
            }
        }

        return "Instrument ID not found for $symbol.";
    }

    
}

function log_trade($conn, $google_id, $symbol, $side, $quantity, $entry_price, $leverage, $margin, $status) {
    try{
        $query = "INSERT INTO trade_journal (google_id, symbol, side, quantity, entry_price, leverage, margin, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssddds", $google_id, $symbol, $side, $quantity, $entry_price, $leverage, $margin, $status);
        $stmt->execute();
    }catch(Exception $e){
        echo $e;
    }
    
}

function logError($conn,$coinName, $errorMessage) {
    try{
        $stmt = $conn->prepare("INSERT INTO error_logs (coin_name, error_message,google_id) VALUES (?, ?,?)");
        $stmt->bind_param("sss", $coinName, $errorMessage,$paramUserId);
        $stmt->execute();
        $stmt->close();
    }catch(Exception $e){
        echo $e;
    }
    
}

// Run the trading functions on Testnet
$okx = new OKXTrading();

$instrumentId = $okx->getInsId($paramSymbol);
$okx->instId = $instrumentId;
//echo "instrumentId: " . $instrumentId . "\n";

// Step 1: Close Positions
$closeResponse = $okx->closePositions();
//echo "Closed Positions: " . json_encode($closeResponse) . "\n";

// Step 2: Set Leverage
$leverageResponse = $okx->setLeverage($paramLeverage);
//echo "Leverage Set: " . json_encode($leverageResponse) . "\n";

// // Step 3: Place a Long Order (Testnet)
$orderResponse = $okx->placeOrder($paramSide,$paramMargin); // Change to "sell" for a short position

if (isset($orderResponse["data"][0]["ordId"])) {
    if(strlen($orderResponse["data"][0]["ordId"])>0){
        $entryPrice = $okx->getentryprice();
        $quantity = $okx->getquantity();
        $status = "Success";
        log_trade($conn, $paramUserId, $paramSymbol, $paramSide, $quantity, $entryPrice, $paramLeverage, $paramMargin, $status);
        echo "Order Placed: " . json_encode($orderResponse) . "\n";
    }else{
        echo "Error: ".$paramSymbol."-".json_encode($orderResponse). "\n";
        logError($conn,$paramSymbol, json_encode($orderResponse));
    }
    
} else {
    logError($conn,$paramSymbol, json_encode($orderResponse));
    echo "Error: ".$paramSymbol."-".json_encode($orderResponse). "\n";
}
?>
