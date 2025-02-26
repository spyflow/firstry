<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

error_reporting(E_ALL);
ini_set('display_errors', 1);

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
    $url = "https://www18.pelisplushd.to/{$tipo}/{$consulta}/temporada/{$temporada}/capitulo/{$capitulo}";
} else {
    $url = "https://www18.pelisplushd.to/{$tipo}/{$consulta}";
}

function getWebContent($url) {
    $apiKey = getenv('JEY_API_KEY');
    $scraperApiUrl = 'https://api.scraperapi.com/';

    if (!$apiKey) {
        http_response_code(500);
        echo json_encode(["error" => "API Key no encontrada"]);
        exit;
    }

    $params = http_build_query(['api_key' => $apiKey, 'url' => $url]);
    $fullUrl = "{$scraperApiUrl}?{$params}";

    $response = @file_get_contents($fullUrl);

    if ($response === false) {
        $error = error_get_last();
        http_response_code(500);
        echo json_encode(["error" => "Error al obtener contenido", "detalle" => $error['message']]);
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
    return (extractDomain($url) === 'uqload.com') ? 'https://peliculas.spyflow.link/redirect/?url=' . urlencode($url) : $url;
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

header('Content-Type: application/json');
echo json_encode($serversByLanguage);
