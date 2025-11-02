<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

error_reporting(0); // Desactiva la notificación de errores
ini_set('display_errors', 0); // Evita que se muestren en pantalla

require_once dirname(__DIR__, 2) . '/lib/SupabaseCache.php';

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
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
        responseJson(['error' => 'Error al obtener datos del sitio', 'detalle' => $error ?: $statusCode], 500);
    }

    return $response;
}

function scrapePelisplus($query, $debug = false) {
    $baseUrl = "https://ww4.pelisplushd.to";
    $searchUrl = $baseUrl . "/search?s=" . urlencode($query);
    $html = getWebContent($searchUrl);

    if ($debug) {
        return ['html' => htmlentities($html)];
    }

    $dom = new DOMDocument();
    @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    $xpath = new DOMXPath($dom);

    $movies = [];
    $series = [];

    foreach ($xpath->query("//a[contains(@class, 'Posters-link')]") as $result) {
        $titleNode = $xpath->query(".//p", $result)->item(0);
        $imgNode = $xpath->query(".//img", $result)->item(0);

        $title = $titleNode ? trim($titleNode->nodeValue) : 'Sin título';
        $link = $result->getAttribute('href');
        $softName = str_replace(['/pelicula/', '/serie/'], '', $link);

        // Asegurar URL absoluta
        $imgUrl = $imgNode ? (strpos($imgNode->getAttribute('src'), 'http') === 0 ? $imgNode->getAttribute('src') : "https://www18.pelisplushd.to" . $imgNode->getAttribute('src')) : '';

        $item = ['title' => $title, 'soft-name' => $softName, 'poster' => $imgUrl];

        if (stripos($link, '/pelicula/') !== false) {
            $movies[] = $item;
        } elseif (stripos($link, '/serie/') !== false) {
            $series[] = $item;
        }
    }

    // Invertir el orden de los elementos en las categorías de películas y series
    $movies = array_reverse($movies);
    $series = array_reverse($series);

    return ['movies' => $movies, 'series' => $series];
}

function responseJson($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

$query = isset($_GET['q']) ? $_GET['q'] : '';
$debug = isset($_GET['debug']) ? filter_var($_GET['debug'], FILTER_VALIDATE_BOOLEAN) : false;

$cache = SupabaseCache::getInstance();
$shouldUseCache = !$debug && $cache->isEnabled();
$cacheKey = SupabaseCache::buildKey('buscar', $query === '' ? 'empty' : $query);

if ($shouldUseCache) {
    $cached = $cache->get($cacheKey);
    if ($cached !== null) {
        header('Content-Type: application/json');
        echo $cached;
        exit;
    }
}

$data = scrapePelisplus($query, $debug);
$json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

if ($shouldUseCache && $json !== false) {
    $cache->set($cacheKey, $json, 300); // 5 minutos
}

header('Content-Type: application/json');
echo $json !== false ? $json : json_encode(['error' => 'No se pudo procesar la respuesta']);
?>
