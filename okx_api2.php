<?php 
class OKXTrading {
    // API credentials - make sure these match your environment (demo or live)
    public $apiKey = "";//8305ab39-255f-4e4a-a5dd-dff2753b0bce
    public $secretKey = "";//AB3C0619E476262ED8ED460276BAD016
    public $passphrase = "";//Elleryc1993$
    
    // Use the correct base URL
    public $baseUrl = ''; // Production URL
    // For demo trading use: https://www.okx-sandbox.com
    
    public $_entryPrice = 0;
    public $_quantity = 0;
    public $instId = ""; // Swap contract for perpetual futures
    public $isDemoTrading = false; // Set to true for demo/sandbox trading
    
    public function __construct($param1, $param2, $param3) {
        // If using demo trading, update the base URL accordingly
        
        $this->baseUrl = 'https://www.okx.com'; // Demo/Sandbox URL
        
        $this->apiKey = $param1;
        $this->secretKey = $param2;
        $this->passphrase = $param3;
        //echo "apikey".$apiKey.$secretKey.$passphrase;
        $this->setapiKey($param1);
        $this->setsecretKey($param2);
        $this->setpassphrase($param3);
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

    public function getWalletBalance(){
        try {
            $totalbal =0;
            $balanceEndpoint = "/api/v5/account/balance";
            $balanceResponse = $this->sendRequestGet($balanceEndpoint);

            if (isset($balanceResponse["code"]) && $balanceResponse["code"] === "0") {
                if (isset($balanceResponse["data"]) && !empty($balanceResponse["data"])) {
                    if (isset($balanceResponse["data"][0]["totalEq"])) {
                        $formatted = sprintf("%.4f", $balanceResponse["data"][0]["totalEq"]);
                        $totalbal= $formatted; 
                    }
                } else {
                    $totalbal= -1; 
                }
            } else {
                $totalbal= -1; 
            }
        } catch (Exception $e) {
            $totalbal= -1; 
        }
        return $totalbal;
    }

}

?>