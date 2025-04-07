<?php
date_default_timezone_set('Asia/Manila');
ignore_user_abort(true); // Allow script to continue if client disconnects
require_once 'config.php';

$userid = $argv[1] ?? "";
//$userid = isset($_GET["userid"]) ? $_GET["userid"] : '';//"102871033794724054940"
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

class OKXTrading {
    // API credentials - make sure these match your environment (demo or live)
    public $apiKey = "8305ab39-255f-4e4a-a5dd-dff2753b0bce";
    public $secretKey = "AB3C0619E476262ED8ED460276BAD016";
    public $passphrase = "Elleryc1993$";
    
    // Use the correct base URL
    public $baseUrl = 'https://www.okx.com'; // Production URL
    // For demo trading use: https://www.okx-sandbox.com
    
    public $_entryPrice = 0;
    public $_quantity = 0;
    public $instId = ""; // Swap contract for perpetual futures
    public $isDemoTrading = false; // Set to true for demo/sandbox trading
    
    public function __construct() {
        // If using demo trading, update the base URL accordingly
        if ($this->isDemoTrading) {
            $this->baseUrl = 'https://www.okx.com'; // Demo/Sandbox URL
        }
    }
    
    public function setentryPrice($entryPrice) {
        $this->_entryPrice = $entryPrice;
    }

    public function setquantity($quantity) {
        $this->_quantity = $quantity;
    }

    public function setapiKey($param_apiKey) {
        $this->apiKey = $param_apiKey;
    }

    public function setsecretKey($param_secretKey) {
        $this->secretKey = $param_secretKey;
    }

    public function setpassphrase($param_passphrase) {
        $this->passphrase = $param_passphrase;
    }

    public function getapiKey() {
        return $this->apiKey;
    }

    public function getsecretKey() {
        return $this->secretKey;
    }

    public function getpassphrase() {
        return $this->passphrase;
    }

    public function getentryprice() {
        return $this->_entryPrice;
    }
    
    public function getquantity() {
        return $this->_quantity;
    }
    
    // Generate API Signature
    private function generateSignature($timestamp, $method, $endpoint, $body) {
        $message = $timestamp . $method . $endpoint . $body;
        return base64_encode(hash_hmac("sha256", $message, $this->secretKey, true));
    }
    
    // Send API Request (GET, POST, DELETE)
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
        
        // Add the demo trading header if in demo mode
        if ($this->isDemoTrading) {
            $headers[] = "x-simulated-trading: 1";
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE); // For testing only, enable in production

        if ($method === "POST") {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } elseif ($method === "DELETE") {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        
        if ($error) {
            return ["error" => "CURL Error: " . $error];
        }
        
        curl_close($ch);
        return json_decode($response, true);
    }

    // Send GET Request with parameters
    public function sendRequestGet($endpoint, $params = []) {
        if (!empty($params)) {
            $queryString = http_build_query($params);
            $endpoint = $endpoint . '?' . $queryString;
        }
        
        return $this->sendRequest("GET", $endpoint);
    }

    // Validate if instrument ID exists before proceeding
    public function validateInstrumentId() {
        if (empty($this->instId)) {
            return false;
        }
        
        $endpoint = "/api/v5/public/instruments";
        $params = ["instType" => "SWAP", "instId" => $this->instId];
        
        $response = $this->sendRequestGet($endpoint, $params);
        
        if (!isset($response["data"]) || empty($response["data"])) {
            return false;
        }
        
        return true;
    }

    // Close All Positions for a specific instrument with a 3-times retry mechanism
    public function closePositions() {
        $positionsEndpoint = "/api/v5/account/positions";
        $params = ["instId" => $this->instId];
        $positions = $this->sendRequestGet($positionsEndpoint, $params);
        
        // If no positions or error, return early
        if (!isset($positions["data"]) || empty($positions["data"])) {
            return ["msg" => "No positions to close", "code" => "0"];
        }

        $endpoint = "/api/v5/trade/close-position";
        $payload = [
            "instId" => $this->instId,
            "mgnMode" => "isolated",
            "posSide" => "net"
        ];

        $maxRetries = 3;
        $attempt = 0;
        $response = null;

        while ($attempt < $maxRetries) {
            $response = $this->sendRequest("POST", $endpoint, $payload);
            
            if (isset($response["code"]) && $response["code"] === "0") {
                // Successfully closed the position
                return $response;
            }
            
            $attempt++;
            sleep(2); // Wait 2 seconds before retrying
        }

        return ["msg" => "Failed to close position after 3 attempts", "code" => "-1", "response" => $response];
    }


    // Set Margin Mode to Isolated & Leverage
    public function setLeverage($leverage) {
        $endpoint = "/api/v5/account/set-leverage";
        $payload = [
            "instId" => $this->instId,
            "lever" => (string)$leverage,
            "mgnMode" => "isolated"
        ];

        return $this->sendRequest("POST", $endpoint, $payload);
    }

    // Get instrument details
    public function getInstrumentDetails() {
        $endpoint = "/api/v5/public/instruments";
        $params = ["instType" => "SWAP", "instId" => $this->instId];
        
        $response = $this->sendRequestGet($endpoint, $params);
        
        if (!isset($response["data"]) || empty($response["data"])) {
            return null;
        }
        
        foreach ($response["data"] as $instrument) {
            if ($instrument["instId"] === $this->instId) {
                return $instrument;
            }
        }
        
        return null;
    }

    // Get precision from lot size
    public function getPrecision($lotSize) {
        if (strpos($lotSize, '.') !== false) {
            return strlen(substr(strrchr($lotSize, "."), 1));
        }
        return 0;
    }

    // Get current market price
    public function getCurrentPrice() {
        $endpoint = "/api/v5/market/ticker";
        $params = ["instId" => $this->instId];
    
        $response = $this->sendRequestGet($endpoint, $params);
        
        if (!isset($response["data"][0]["last"])) {
            return 0;
        }
    
        return (float) $response["data"][0]["last"];
    }

    // Place an order
    public function placeOrder($side, $dollarAmount, $leverage) {
        // Validate instrument ID exists
        if (!$this->validateInstrumentId()) {
            return ["error" => "Invalid instrument ID: " . $this->instId];
        }
        
        // Step 1: Fetch market price
        $entryPrice = $this->getCurrentPrice();
        if ($entryPrice <= 0) {
            return ["error" => "Failed to fetch market price"];
        }

        // Step 2: Fetch instrument details
        $instrument = $this->getInstrumentDetails();
        if (!$instrument) {
            return ["error" => "Failed to fetch instrument details"];
        }

        // Step 3: Extract necessary instrument details
        $lotSize = floatval($instrument['lotSz'] ?? '0.001');
        $minSize = floatval($instrument['minSz'] ?? '0.001');
        $ctVal = floatval($instrument['ctVal'] ?? '1');
        
        echo "Lot Size: " . $lotSize . PHP_EOL;
        echo "Min Size: " . $minSize . PHP_EOL;
        echo "Entry Price: " . $entryPrice . PHP_EOL;
        
        // Calculate quantity based on leverage, dollar amount, and entry price
        $quantity = ($dollarAmount * $leverage) / ($entryPrice * $ctVal);
        echo "Initial quantity calculation: " . $quantity . PHP_EOL;

        // Round quantity to nearest valid lot size
        $precision = $this->getPrecision($lotSize);
        $quantity = floor($quantity / $lotSize) * $lotSize;
        $quantity = number_format($quantity, $precision, '.', '');
        
        // Ensure quantity meets minimum size requirement
        if (floatval($quantity) < $minSize) {
            $quantity = $minSize;
        }
        
        echo "Final quantity: " . $quantity . PHP_EOL;
        
        // Store values for later use
        $this->setentryPrice($entryPrice);
        $this->setquantity($quantity);
    
        // Prepare API Payload
        $payload = [
            "instId" => $this->instId,
            "tdMode" => "isolated",
            "side" => $side, // "buy" for long, "sell" for short
            "ordType" => "market",
            "sz" => (string) $quantity
        ];

        echo "Sending order: " . json_encode($payload) . PHP_EOL;
        return $this->sendRequest("POST", "/api/v5/trade/order", $payload);
    }

    // New method to handle retry logic for order placement
    public function placeOrderWithRetry($side, $dollarAmount, $leverage, $maxRetries = 3) {
        $retries = 0;
        $lastError = null;

        while ($retries < $maxRetries) {
            try {
                // Attempt to place the order
                $orderResponse = $this->placeOrder($side, $dollarAmount, $leverage);
                
                // Check if order was successful
                if (isset($orderResponse["data"]) && 
                    isset($orderResponse["data"][0]["ordId"]) && 
                    !empty($orderResponse["data"][0]["ordId"])) {
                    return $orderResponse;
                }
                
                // If not successful, store the error
                $lastError = $orderResponse;
                
                // Increment retry counter
                $retries++;
                
                // Wait before next retry (exponential backoff)
                $waitTime = pow(2, $retries); // 2, 4, 8 seconds
                echo "Order placement failed. Retry attempt $retries. Waiting $waitTime seconds." . PHP_EOL;
                sleep($waitTime);
                
                // Refresh instrument ID and re-validate
                $this->instId = $this->getInsId(substr($this->instId, 0, strpos($this->instId, "-")));
            } catch (Exception $e) {
                $lastError = ["error" => $e->getMessage()];
                $retries++;
                sleep(pow(2, $retries));
            }
        }
        
        // If all retries fail, return the last error
        return $lastError;
    }

    // Get instrument ID from symbol with a 3-times retry mechanism
    public function getInsId($symbol) {
        $endpoint = "/api/v5/public/instruments";
        $params = ["instType" => "SWAP"];
        
        $maxRetries = 3;
        $attempt = 0;
        $response = null;

        while ($attempt < $maxRetries) {
            $response = $this->sendRequestGet($endpoint, $params);

            if (isset($response["data"])) {
                break; // Exit loop if data is received
            }

            $attempt++;
            sleep(2); // Wait 2 seconds before retrying
        }

        if (!isset($response["data"])) {
            return null; // Return null if no valid response after retries
        }

        $symbol = strtoupper($symbol);

        foreach ($response["data"] as $instrument) {
            if (strpos($instrument["instId"], $symbol . "-USDT-SWAP") !== false) {
                return $instrument["instId"];
            }
        }

        // Try alternative naming patterns
        foreach ($response["data"] as $instrument) {
            if (strpos($instrument["instId"], $symbol) !== false && 
                strpos($instrument["instId"], "USDT-SWAP") !== false) {
                return $instrument["instId"];
            }
        }

        return null;
    }

}

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
$okx = new OKXTrading();

$okx->setapiKey($user['apiKeyOkx']);
$okx->setsecretKey($user['secretKeyOkx']);
$okx->setpassphrase($user['passPhraseOkx']);

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