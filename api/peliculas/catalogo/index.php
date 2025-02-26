<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

function getWebContent($url) {
    $apiKey = getenv('JEY_API_KEY');
    $scraperApiUrl = "https://api.scraperapi.com/?api_key=$apiKey&url=" . urlencode($url);

    if (!$apiKey) {
        responseJson(['error' => 'API Key no configurada'], 500);
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10,
            'header' => "User-Agent: Mozilla/5.0 (compatible; Bot/1.0)\r\n"
        ]
    ]);

    $response = @file_get_contents($scraperApiUrl, false, $context);

    if ($response === false) {
        responseJson(['error' => 'Error al obtener datos de la API'], 500);
    }

    return $response;
}

function scrapePelisplus($debug = false) {
    $baseUrl = "https://www18.pelisplushd.to";
    $html = getWebContent($baseUrl);

    if ($debug) {
        responseJson(['html' => htmlentities($html)]);
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
        $imgUrl = $imgNode ? "https://www18.pelisplushd.to" . $imgNode->getAttribute('src') : '';

        $item = ['title' => $title, 'soft-name' => $softName, 'poster' => $imgUrl];

        if (stripos($link, '/pelicula/') !== false) {
            $movies[] = $item;
        } elseif (stripos($link, '/serie/') !== false) {
            $series[] = $item;
        }
    }

    responseJson(['movies' => $movies, 'series' => $series]);
}

function responseJson($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

$debug = isset($_GET['debug']) ? filter_var($_GET['debug'], FILTER_VALIDATE_BOOLEAN) : false;
scrapePelisplus($debug);
?>
