<?php
class OKXTrading {
    // API credentials
    public $apiKey = "";//8305ab39-255f-4e4a-a5dd-dff2753b0bce
    public $secretKey = "";//AB3C0619E476262ED8ED460276BAD016
    public $passphrase = "";//Elleryc1993$

    // public $apiKey = "8305ab39-255f-4e4a-a5dd-dff2753b0bce";//
    // public $secretKey = "AB3C0619E476262ED8ED460276BAD016";//
    // public $passphrase = "Elleryc1993$";//
    
    // Use the correct base URL
    public $baseUrl = 'https://www.okx.com'; // Production URL
    // For demo trading use: https://www.okx-sandbox.com
    
    public $_entryPrice = 0;
    public $_quantity = 0;
    public $instId = ""; // Swap contract for perpetual futures
    public $isDemoTrading = true; // Set to true for demo/sandbox trading
    
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing only, enable in production

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
    
    // Get account balance
    public function getAccountBalance() {
        $endpoint = "/api/v5/account/balance";
        return $this->sendRequestGet($endpoint);
    }
    
    // Get positions
    public function getPositions($instType = "SWAP", $instId = "") {
        $endpoint = "/api/v5/account/positions";
        $params = ["instType" => $instType];
        
        if (!empty($instId)) {
            $params["instId"] = $instId;
        }
        
        return $this->sendRequestGet($endpoint, $params);
    }
    
    // Get instrument ID from symbol
    public function getInsId($symbol) {
        $endpoint = "/api/v5/public/instruments";
        $params = ["instType" => "SWAP"];
        
        $response = $this->sendRequestGet($endpoint, $params);
        
        if (!isset($response["data"])) {
            return null;
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
?>