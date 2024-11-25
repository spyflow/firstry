<?php
header("Access-Control-Allow-Origin: *"); // Permite solicitudes desde cualquier dominio
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // Métodos permitidos
header("Access-Control-Allow-Headers: Content-Type"); // Encabezados permitidos

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header("HTTP/1.1 200 OK");
    exit();
}



function getWebContent($url) {
    $options = [
        'http' => [
            'header' => [
                "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36\r\n"
            ]
        ]
    ];
    $context = stream_context_create($options);
    return file_get_contents($url, false, $context);
}

function scrapePelisplus($query, $debug = false) {
    $baseUrl = "https://www18.pelisplushd.to";
    $searchUrl = $baseUrl . "/search?s=" . urlencode($query);
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
        $softName = str_replace('/pelicula/', '', $link); // Eliminar el prefijo de la URL
        $softName = str_replace('/serie/', '', $softName); // Eliminar el prefijo de la URL


        // Extraer la URL de la imagen de la carátula y modificarla
        $img = $xpath->query(".//img", $result)->item(0);
        $imgFilename = $img ? basename($img->getAttribute('src')) : ''; // Obtener solo el nombre del archivo
        $imgUrl = $imgFilename ? "https://video.makatunga.uy/peliculas/poster/?file=" . $imgFilename : '';

        $item = [
            'title' => trim($title),
            'soft-name' => $softName,
            'poster' => $imgUrl, // Incluir la URL completa de la carátula
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
$query = isset($_GET['q']) ? $_GET['q'] : ''; // Corrección del nombre del parámetro 'q'
$debug = isset($_GET['debug']) ? filter_var($_GET['debug'], FILTER_VALIDATE_BOOLEAN) : false; // Control de debug

header('Content-Type: application/json');
echo scrapePelisplus($query, $debug);

?>

