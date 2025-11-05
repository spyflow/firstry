<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

// Lista de User-Agents (rotación aleatoria)
$userAgents = [
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36',
    'Mozilla/5.0 (X11; Linux x86_64) Gecko/20100101 Firefox/122.0',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',
    'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Mobile Safari/537.36',
    'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'
];

$defaultStream = 'https://streaming.comunicacioneschile.net/9358/stream';
$ua = $userAgents[array_rand($userAgents)];
$url = 'https://radionuble.cl/v1/radio-nuble-online/';

// Configuración cURL rápida y optimizada
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,  // No sigue redirecciones innecesarias
    CURLOPT_CONNECTTIMEOUT => 7,
    CURLOPT_TIMEOUT => 7,
    CURLOPT_USERAGENT => $ua,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_ENCODING => '',           // Soporta gzip/deflate si el servidor lo usa
    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, // Evita IPv6 (más rápido en la mayoría de casos)
    CURLOPT_HTTPGET => true,
    CURLOPT_HEADER => false,
    CURLOPT_NOBODY => false,
    CURLOPT_FAILONERROR => true,
]);

$html = curl_exec($ch);
curl_close($ch);

// Buscar la URL del stream en el HTML
if ($html && preg_match('/radio_stream:"(https?:\/\/[^"]+\/stream)"/', $html, $match)) {
    echo json_encode(['url' => $match[1]], JSON_UNESCAPED_SLASHES);
} else {
    echo json_encode(['url' => $defaultStream], JSON_UNESCAPED_SLASHES);
}
?>
