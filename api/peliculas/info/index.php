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

// Recibir los parámetros de la URL
$tipo = isset($_GET['type']) ? $_GET['type'] : 'pelicula';
$id = isset($_GET['title']) ? $_GET['title'] : '';

if (!$id) {
    echo json_encode(['error' => 'Nombre de la serie o película no especificado']);
    exit();
}

// Función para obtener contenido web con file_get_contents()
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

// Reemplazar espacios por guiones en el título
$idGuiones = str_replace(
    ' ',
    '-',
    (function (string $value): string {
        $normalised = function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);

        return $normalised;
    })($id)
);

// Construir la URL de la serie o película
$cache = SupabaseCache::getInstance();
$cacheKey = SupabaseCache::buildKey('info', $tipo, $idGuiones);
$cacheEnabled = $cache->isEnabled();

if ($cacheEnabled) {
    $cached = $cache->get($cacheKey);
    if ($cached !== null) {
        header('X-Cache: HIT');
        header('Content-Type: application/json');
        echo $cached;
        exit;
    }
}

$url = "https://ww4.pelisplushd.to/$tipo/$idGuiones";
$html = getWebContent($url);

// Cargar el HTML en un DOMDocument
$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
libxml_clear_errors();

$xpath = new DOMXPath($dom);

// Obtener título
$tituloNode = $xpath->query('//h1[contains(@class, "m-b-5")]')->item(0);
$titulo = $tituloNode ? trim($tituloNode->textContent) : 'Título no disponible';

// Obtener año
$anioNode = $xpath->query('//div[contains(@class, "sectionDetail")]/span[contains(text(),"Fecha de estreno")]/following-sibling::node()')->item(0);
$anio = $anioNode ? trim($anioNode->textContent) : 'Año no disponible';

// Obtener sinopsis
$sinopsisNode = $xpath->query('//p[contains(@class, "font-size-13")]/following-sibling::div[contains(@class, "text-large")]')->item(0);
$sinopsis = $sinopsisNode ? trim($sinopsisNode->textContent) : 'Sinopsis no disponible';

// URL del póster
$poster = "https://www18.pelisplushd.to/poster/$idGuiones-thumb.jpg";

// Preparar la respuesta manteniendo la estructura original
$resultado = [
    'titulo' => $titulo,
    'anio' => $anio,
    'sinopsis' => $sinopsis,
    'poster' => $poster,
];

if ($tipo === 'serie') {
    // Obtener temporadas y capítulos
    $temporadaNodes = $xpath->query('//a[contains(@href, "/temporada/")]');
    $temporadas = [];

    foreach ($temporadaNodes as $node) {
        $href = $node->getAttribute('href');
        if (preg_match('/\/temporada\/(\d+)\/capitulo\/(\d+)/', $href, $matches)) {
            $temporada = $matches[1];
            $capitulo = $matches[2];

            if (!isset($temporadas[$temporada])) {
                $temporadas[$temporada] = 0;
            }
            $temporadas[$temporada]++;
        }
    }

    // Añadir temporadas al resultado
    $resultado['temporadas'] = [];
    foreach ($temporadas as $temporada => $cantidadCapitulos) {
        $resultado['temporadas'][] = [
            'temporada' => $temporada,
            'capitulos' => $cantidadCapitulos
        ];
    }

    $resultado['total_temporadas'] = count($temporadas);
}

// Enviar respuesta JSON sin cambiar la estructura original
$json = json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

if ($cacheEnabled && $json !== false) {
    $cache->set($cacheKey, $json, 8640000); // 100 días
}

header('X-Cache: ' . ($cacheEnabled ? 'MISS' : 'BYPASS'));
header('Content-Type: application/json');
echo $json !== false ? $json : json_encode(['error' => 'No se pudo generar la respuesta']);
?>
