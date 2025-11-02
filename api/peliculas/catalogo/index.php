<?php
header("Access-Control-Allow-Origin: *"); // Permite solicitudes desde cualquier dominio
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // Métodos permitidos
header("Access-Control-Allow-Headers: Content-Type"); // Encabezados permitidos

error_reporting(0); // Desactiva la notificación de errores
ini_set('display_errors', 0); // Evita que se muestren en pantalla

require_once dirname(__DIR__, 2) . '/lib/SupabaseCache.php';

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header("HTTP/1.1 200 OK");
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
        http_response_code(500);
        echo json_encode(['error' => 'Error al obtener contenido del sitio', 'detalle' => $error ?: $statusCode]);
        exit();
    }

    return $response;
}

function scrapePelisplus($query, $debug = false) {
    $baseUrl = "https://ww4.pelisplushd.to";
    $html = getWebContent($baseUrl);

    if ($debug) {
        // Mostrar el HTML en crudo si debug está activo
        echo "<pre>";
        echo htmlspecialchars($html);
        echo "</pre>";
        return;
    }

    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $movies = [];
    $series = [];

    // Busca todos los contenedores de resultados
    $results = $xpath->query("//a[@class='Posters-link']");

    foreach ($results as $result) {
        $title = $xpath->query(".//p", $result)->item(0)->nodeValue;
        $link = $result->getAttribute('href');
        $softName = str_replace(['/pelicula/', '/serie/'], '', $link); // Eliminar el prefijo de la URL

        // Extraer la URL de la imagen de la carátula
        $img = $xpath->query(".//img", $result)->item(0);
        $imgUrl = $img ? "https://www18.pelisplushd.to" . $img->getAttribute('src') : ''; // Usar la URL original con el prefijo

        $item = [
            'title' => trim($title),
            'soft-name' => $softName,
            'poster' => $imgUrl, // Incluir la URL modificada de la carátula
        ];

        // Clasificación de resultados
        if (stripos($link, '/pelicula/') !== false) {
            $movies[] = $item;
        } else if (stripos($link, '/serie/') !== false) {
            $series[] = $item;
        }
    }

    return json_encode([
        'movies' => $movies,
        'series' => $series
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

// Obtener el parámetro de búsqueda 'q' de la URL
$query = "";
$debug = isset($_GET['debug']) ? filter_var($_GET['debug'], FILTER_VALIDATE_BOOLEAN) : false; // Control de debug

$cache = SupabaseCache::getInstance();
$cacheEnabled = $cache->isEnabled();
$cacheKey = SupabaseCache::buildKey('catalogo', $query === '' ? 'default' : $query);

if ($cacheEnabled) {
    $cached = $cache->get($cacheKey);
    if ($cached !== null) {
        header('X-Cache: HIT');
        header('Content-Type: application/json');
        echo $cached;
        exit;
    }
}

$result = scrapePelisplus($query, $debug);

if ($cacheEnabled && $result !== null) {
    $cache->set($cacheKey, $result, 8640000); // 100 días
}

header('X-Cache: ' . ($cacheEnabled ? 'MISS' : 'BYPASS'));
header('Content-Type: application/json');
echo $result;
?>
