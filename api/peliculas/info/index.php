<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

error_reporting(0); // Desactiva la notificación de errores
ini_set('display_errors', 0); // Evita que se muestren en pantalla

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
    $apiKey = getenv('JEY_API_KEY');
    $scraperApiUrl = 'https://api.scraperapi.com/';

    if (!$apiKey) {
        http_response_code(500);
        echo json_encode(['error' => 'API Key no encontrada en las variables de entorno.']);
        exit();
    }

    $params = ['api_key' => $apiKey, 'url' => $url];
    $fullUrl = $scraperApiUrl . '?' . http_build_query($params);

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10,
            'header' => "User-Agent: Mozilla/5.0\r\n"
        ]
    ]);

    $response = @file_get_contents($fullUrl, false, $context);

    if ($response === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al realizar la solicitud.']);
        exit();
    }

    return $response;
}

// Reemplazar espacios por guiones en el título
$idGuiones = str_replace(' ', '-', strtolower($id));

// Construir la URL de la serie o película
$url = "https://www18.pelisplushd.to/$tipo/$idGuiones";
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
header('Content-Type: application/json');
echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
