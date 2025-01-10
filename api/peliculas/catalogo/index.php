<?php
header("Access-Control-Allow-Origin: *"); // Permite solicitudes desde cualquier dominio
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // Métodos permitidos
header("Access-Control-Allow-Headers: Content-Type"); // Encabezados permitidos

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header("HTTP/1.1 200 OK");
    exit();
}

function getRandomUserAgent() {
    $majorVersion = rand(100, 120); // Versión principal del navegador
    $minorVersion = rand(0, 9); // Versión menor
    $chromeVersion = rand(80, 120); // Versión de Chrome
    $webkitVersion = rand(500, 600); // Versión de WebKit
    return "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/{$webkitVersion}.{$minorVersion} (KHTML, like Gecko) Chrome/{$chromeVersion}.0.0.0 Safari/{$webkitVersion}.{$minorVersion}";
}

function getWebContent($url) {
    $randomUserAgent = getRandomUserAgent();
    $options = [
        'http' => [
            'header' => [
                "User-Agent: $randomUserAgent\r\n",
                "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8\r\n",
                "Accept-Language: en-US,en;q=0.5\r\n",
                "Connection: keep-alive\r\n"
            ]
        ]
    ];
    $context = stream_context_create($options);
    return file_get_contents($url, false, $context);
}

function scrapePelisplus($query, $debug = false) {
    $baseUrl = "https://www18.pelisplushd.to";
    $searchUrl = $baseUrl;
    $html = getWebContent($searchUrl);

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

header('Content-Type: application/json');
echo scrapePelisplus($query, $debug);
?>
