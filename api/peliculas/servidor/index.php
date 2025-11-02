<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once dirname(__DIR__, 2) . '/lib/SupabaseCache.php';

$tipo = $_GET['type'] ?? 'pelicula';
$consulta = $_GET['title'] ?? '';

if (empty($consulta)) {
    http_response_code(400);
    echo json_encode(['error' => 'Se requiere el parámetro "consulta".']);
    exit;
}

if ($tipo === 'serie') {
    $temporada = $_GET['t'] ?? 1;
    $capitulo = $_GET['c'] ?? 1;
    $url = "https://ww4.pelisplushd.to/{$tipo}/{$consulta}/temporada/{$temporada}/capitulo/{$capitulo}";
} else {
    $temporada = null;
    $capitulo = null;
    $url = "https://ww4.pelisplushd.to/{$tipo}/{$consulta}";
}

$cache = SupabaseCache::getInstance();
$keyParts = ['servidor', $tipo, $consulta];
if ($temporada !== null && $capitulo !== null) {
    $keyParts[] = 't' . $temporada;
    $keyParts[] = 'c' . $capitulo;
}
$cacheKey = SupabaseCache::buildKey(...$keyParts);
$shouldUseCache = $cache->isEnabled();

if ($shouldUseCache) {
    $cached = $cache->get($cacheKey);
    if ($cached !== null) {
        header('Content-Type: application/json');
        echo $cached;
        exit;
    }
}

function getWebContent($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept-Language: es-ES,es;q=0.9,en;q=0.8'
        ],
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($response === false || $statusCode >= 400) {
        http_response_code(500);
        echo json_encode(["error" => "Error al obtener contenido", "detalle" => $error ?: $statusCode]);
        exit;
    }

    return $response;
}

$html = getWebContent($url);

$doc = new DOMDocument();
libxml_use_internal_errors(true);
$doc->loadHTML($html);
libxml_clear_errors();

$xpath = new DOMXPath($doc);
$serverItems = $xpath->query('//li[contains(@class, "playurl")]');

$serversByLanguage = [];

function normalizeLanguageName($name) {
    $map = ['ñ' => 'n', 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c'];
    return preg_replace('/[^a-z0-9]+/', '_', strtr(strtolower($name), $map));
}

function extractDomain($url) {
    return parse_url($url, PHP_URL_HOST) ?? '';
}

function modifyUrl($url) {
    return (extractDomain($url) === 'uqload.com') ? 'https://peliculas.makatunga.uy/redirect/?url=' . urlencode($url) : $url;
}

if ($serverItems->length > 0) {
    foreach ($serverItems as $item) {
        $url = modifyUrl($item->getAttribute('data-url'));
        $name = normalizeLanguageName($item->getAttribute('data-name'));
        $domain = extractDomain($url);

        $serversByLanguage[$name][$domain][] = $url;
    }
} else {
    $spanItems = $xpath->query('//span[@lid and @url]');
    foreach ($spanItems as $item) {
        $url = modifyUrl($item->getAttribute('url'));
        $domain = extractDomain($url);
        $serversByLanguage['espanol_latino'][$domain][] = $url;
    }
}

$json = json_encode($serversByLanguage, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

if ($shouldUseCache && $json !== false) {
    $cache->set($cacheKey, $json, 3600); // 1 hora
}

header('Content-Type: application/json');
echo $json !== false ? $json : json_encode(['error' => 'No se pudo generar la respuesta']);
