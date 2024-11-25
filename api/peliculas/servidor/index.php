<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

error_reporting(E_ALL);
ini_set('display_errors', 1);

$tipo = isset($_GET['type']) ? $_GET['type'] : 'pelicula';
$consulta = isset($_GET['title']) ? $_GET['title'] : '';

if (empty($consulta)) {
    echo json_encode(['error' => 'Se requiere el parámetro "consulta".']);
    exit;
}

if ($tipo === 'serie') {
    $temporada = isset($_GET['t']) ? $_GET['t'] : 1;
    $capitulo = isset($_GET['c']) ? $_GET['c'] : 1;
    $url = "https://www18.pelisplushd.to/{$tipo}/{$consulta}/temporada/{$temporada}/capitulo/{$capitulo}";
} else {
    $url = "https://www18.pelisplushd.to/{$tipo}/{$consulta}";
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$html = curl_exec($ch);

if (curl_errno($ch)) {
    echo json_encode(['error' => 'Error en cURL: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}

curl_close($ch);

$debug = false;

function normalizeLanguageName($name) {
    $name = strtolower($name);
    $name = str_replace(['ñ', 'á', 'é', 'í', 'ó', 'ú', 'ü', 'ç'], ['n', 'a', 'e', 'i', 'o', 'u', 'u', 'c'], $name);
    return preg_replace('/[^a-z0-9]+/', '_', $name);
}

function extractDomain($url) {
    $parsedUrl = parse_url($url);
    $host = $parsedUrl['host'] ?? '';

    if (preg_match('/^([^.]+\.)?([^.]+\.[^.]+)$/', $host, $matches)) {
        return $matches[2];
    }
    return $host;
}

function modifyUrl($url) {
    $domain = extractDomain($url);
    if ($domain === 'uqload.com') {
        return 'https://api.makatunga.uy/api/proxy/?url=' . urlencode($url);
    }
    return $url;
}

$doc = new DOMDocument();
libxml_use_internal_errors(true);
$doc->loadHTML($html);
libxml_clear_errors();

$xpath = new DOMXPath($doc);
$serverItems = $xpath->query('//li[contains(@class, "playurl")]');

$serversByLanguage = [];

if ($serverItems->length > 0) {
    foreach ($serverItems as $item) {
        $url = $item->getAttribute('data-url');
        $url = modifyUrl($url);
        $name = $item->getAttribute('data-name');
        
        $normalizedLanguage = normalizeLanguageName($name);
        $domain = extractDomain($url);

        if (!isset($serversByLanguage[$normalizedLanguage])) {
            $serversByLanguage[$normalizedLanguage] = [];
        }
        if (!isset($serversByLanguage[$normalizedLanguage][$domain])) {
            $serversByLanguage[$normalizedLanguage][$domain] = [];
        }
        $serversByLanguage[$normalizedLanguage][$domain][] = $url;
    }
} else {
    $spanItems = $xpath->query('//span[@lid and @url]');
    
    foreach ($spanItems as $item) {
        $url = $item->getAttribute('url');
        $url = modifyUrl($url);
        $lid = $item->getAttribute('lid');
        
        $normalizedLanguage = 'espanol_latino';  // Puedes ajustar este valor si necesitas un nombre específico
        $domain = extractDomain($url);

        if (!isset($serversByLanguage[$normalizedLanguage])) {
            $serversByLanguage[$normalizedLanguage] = [];
        }
        if (!isset($serversByLanguage[$normalizedLanguage][$domain])) {
            $serversByLanguage[$normalizedLanguage][$domain] = [];
        }
        $serversByLanguage[$normalizedLanguage][$domain][] = $url;
    }
}

if ($debug) {
    echo '<h2>Información de depuración:</h2>';
    echo '<p><strong>Contenido HTML:</strong></p>';
    echo '<pre>' . htmlspecialchars($html) . '</pre>';
    echo '<p><strong>URLs de los servidores organizadas por idioma:</strong></p>';
    echo '<pre>' . htmlspecialchars(print_r($serversByLanguage, true)) . '</pre>';
}

header('Content-Type: application/json');
echo json_encode($serversByLanguage);
?>

