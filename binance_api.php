<?php
require_once 'config.php';

function getMarketPrice($symbol) {
    global $api_key, $binance_futures_url,$symbol;

    try {
        $url = $binance_futures_url . "/fapi/v1/ticker/price?symbol=" . urlencode($symbol);
        $headers = ["X-MBX-APIKEY: $api_key"];

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Set timeout for better reliability

        // Execute request
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            throw new Exception('cURL Error: ' . curl_error($ch));
        }

        curl_close($ch);

        // Decode JSON response
        $data = json_decode($response, true);

        // Validate API response
        if (!isset($data['price'])) {
            throw new Exception("Invalid API response: " . json_encode($data));
        }

        return floatval($data['price']); // Return price as float for consistency

    } catch (Exception $e) {
        logError($symbol, $e->getMessage()); // Log error to database
        //logTrade("$symbol;".$e->getMessage());
		//die("$symbol;".$e->getMessage());
        return null; // Return null in case of failure
    }
}

function getSymbolPrecision($symbol) {
    global $api_key, $binance_futures_url;

    try {
        $headers = ["X-MBX-APIKEY: $api_key"];
        $exchange_url = $binance_futures_url . "/fapi/v1/exchangeInfo";

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $exchange_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Execute request
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            throw new Exception('cURL Error: ' . curl_error($ch));
        }

        curl_close($ch);

        // Decode JSON response
        $exchangeData = json_decode($response, true);

        // Validate API response
        if (!isset($exchangeData['symbols']) || !is_array($exchangeData['symbols'])) {
            throw new Exception('Invalid API response: ' . $response);
        }

        // Default precision values
        $precision = [
            'price' => 2,  // Default price precision
            'qty' => 2,    // Default quantity precision
            'minQty' => 0.01 // Default minimum quantity
        ];

        // Find the symbol and extract precision details
        foreach ($exchangeData['symbols'] as $s) {
            if (isset($s['symbol']) && $s['symbol'] === $symbol) {
                foreach ($s['filters'] as $filter) {
                    if ($filter['filterType'] === 'PRICE_FILTER') {
                        $precision['price'] = abs(log10(floatval($filter['tickSize'])));
                    }
                    if ($filter['filterType'] === 'LOT_SIZE') {
                        $precision['qty'] = abs(log10(floatval($filter['stepSize'])));
                        $precision['minQty'] = floatval($filter['minQty']);
                    }
                }
                return $precision;
            }
        }

        // If symbol is not found, throw an error
        throw new Exception("Symbol $symbol not found in exchange info");

    } catch (Exception $e) {
        logError($symbol, $e->getMessage()); // Log error to database
        //logTrade("$symbol;".$e->getMessage());
		//die("$symbol;".$e->getMessage());
        return [
            'price' => 2,
            'qty' => 2,
            'minQty' => 0.01
        ]; // Return default values in case of an error
    }
}


function getDynamicMinNotional($symbol) {
    global $api_key, $binance_futures_url;

    try {
        $headers = ["X-MBX-APIKEY: $api_key"];
        $exchange_url = $binance_futures_url . "/fapi/v1/exchangeInfo";

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $exchange_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Execute request
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            throw new Exception('cURL Error: ' . curl_error($ch));
        }

        curl_close($ch);

        // Decode JSON response
        $exchangeData = json_decode($response, true);

        // Validate API response
        if (!isset($exchangeData['symbols']) || !is_array($exchangeData['symbols'])) {
            throw new Exception('Invalid API response: ' . $response);
        }

        // Default minimum notional value
        $minNotional = 5.0;

        // Find the symbol and extract MIN_NOTIONAL filter
        foreach ($exchangeData['symbols'] as $s) {
            if (isset($s['symbol']) && $s['symbol'] === $symbol) {
                foreach ($s['filters'] as $filter) {
                    if ($filter['filterType'] === 'MIN_NOTIONAL') {
                        $minNotional = floatval($filter['notional']);
                        return $minNotional;
                    }
                }
                break;
            }
        }

        // If symbol is not found, throw an error
        throw new Exception("Symbol $symbol not found in exchange info");

    } catch (Exception $e) {
        //logTrade("$symbol;".$e->getMessage());
		//die("$symbol;".$e->getMessage());
        return 5.0; // Return default value in case of an error
    }
}


function getPercentPriceFilter($symbol) {
    global $api_key, $binance_futures_url;

    try {
        $headers = ["X-MBX-APIKEY: $api_key"];
        $exchange_url = $binance_futures_url . "/fapi/v1/exchangeInfo";

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $exchange_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Execute request
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            throw new Exception('cURL Error: ' . curl_error($ch));
        }

        curl_close($ch);

        // Decode JSON response
        $exchangeData = json_decode($response, true);

        // Validate API response
        if (!isset($exchangeData['symbols']) || !is_array($exchangeData['symbols'])) {
            throw new Exception('Invalid API response: ' . $response);
        }

        // Search for the symbol and extract the PERCENT_PRICE filter
        foreach ($exchangeData['symbols'] as $s) {
            if (isset($s['symbol']) && $s['symbol'] === $symbol) {
                foreach ($s['filters'] as $filter) {
                    if ($filter['filterType'] === 'PERCENT_PRICE') {
                        return [
                            'minPrice' => isset($filter['minPrice']) ? floatval($filter['minPrice']) : 0,
                            'maxPrice' => isset($filter['maxPrice']) ? floatval($filter['maxPrice']) : PHP_FLOAT_MAX
                        ];
                    }
                }
                break;
            }
        }

        // If symbol not found, throw an error
        throw new Exception("Symbol $symbol not found in exchange info");

    } catch (Exception $e) {
        logError($symbol, $e->getMessage()); // Log error to database
        //logTrade("$symbol;".$e->getMessage());
		//die("$symbol;".$e->getMessage());
        return [
            'minPrice' => 0,
            'maxPrice' => PHP_FLOAT_MAX // Safe defaults
        ];
    }
}

function binance_futures_request($url, $params = [], $method) {
    global $api_key, $api_secret, $binance_futures_url;

    $params['timestamp'] = round(microtime(true) * 1000); // Ensure timestamp is included
    $params['recvWindow'] = 10000; // Recommended recvWindow to prevent timing issues

    // Construct query string
    $query = http_build_query($params, '', '&');

    // Generate the HMAC signature
    $signature = hash_hmac('sha256', $query, $api_secret);

    // Append signature to params
    $query .= "&signature=$signature";

    // Set headers
    $headers = ["X-MBX-APIKEY: $api_key"];

    $ch = curl_init();
    $final_url = "$binance_futures_url$url";

    // Handle HTTP methods
    switch ($method) {
        case "GET":
            $final_url .= "?$query";
            curl_setopt($ch, CURLOPT_URL, $final_url);
            break;

        case "POST":
            curl_setopt($ch, CURLOPT_URL, $final_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
            break;

        case "DELETE":
            $final_url .= "?$query";
            curl_setopt($ch, CURLOPT_URL, $final_url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            break;

        default:
            die("Invalid HTTP method: $method");
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Execute the request
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}


function logError($coinName, $errorMessage) {
    global $conn; // Ensure $conn is defined in config.php
    global $user_id;

    $stmt = $conn->prepare("INSERT INTO error_logs (coin_name, error_message,google_id) VALUES (?, ?,?)");
    $stmt->bind_param("sss", $coinName, $errorMessage,$user_id);
    $stmt->execute();
    $stmt->close();
}
?>