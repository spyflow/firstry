<?php
header("Access-Control-Allow-Origin: *"); // Permite solicitudes desde cualquier dominio
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // Métodos permitidos
header("Access-Control-Allow-Headers: Content-Type"); // Encabezados permitidos

// Recibir los parámetros de la URL
$tipo = isset($_GET['type']) ? $_GET['type'] : 'pelicula';
$id = isset($_GET['title']) ? $_GET['title'] : '';

if (!$id) {
    echo json_encode(['error' => 'Nombre de la serie o película no especificado']);
    exit;
}

// Reemplazar espacios por guiones
$idGuiones = str_replace(' ', '-', strtolower($id));

// Construir la URL de la serie o película
$url = "https://www18.pelisplushd.to/$tipo/$idGuiones";

// Obtener el contenido HTML de la página
$html = file_get_contents($url);
if ($html === false) {
    echo json_encode(['error' => 'No se pudo obtener el contenido de la página']);
    exit;
}

// Crear un DOMDocument y cargar el HTML
$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadHTML($html);
libxml_clear_errors();

// Crear un XPath para consultar el DOM
$xpath = new DOMXPath($dom);

// Obtener el título desde <h1>
$tituloNode = $xpath->query('//h1[contains(@class, "m-b-5")]')->item(0);
$titulo = $tituloNode ? trim($tituloNode->textContent) : 'Título no disponible';

// Obtener el año desde el <div> con la clase "sectionDetail mb15"
$anioNode = $xpath->query('//div[contains(@class, "sectionDetail mb15")]/span[text()="Fecha de estreno:"]/following-sibling::text()[1]')->item(0);
$anio = $anioNode ? trim($anioNode->textContent) : 'Año no disponible';

// Obtener la sinopsis desde el <p> y <div>
$sinopsisNode = $xpath->query('//p[contains(@class, "font-size-13")]/following-sibling::div[contains(@class, "text-large")]')->item(0);
$sinopsis = $sinopsisNode ? trim($sinopsisNode->textContent) : 'Sinopsis no disponible';

// Generar la URL del póster
$poster = "https://www18.pelisplushd.to/poster/$idGuiones-thumb.jpg";

// Preparar el resultado
$resultado = [
    'titulo' => $titulo,
    'anio' => $anio,
    'sinopsis' => $sinopsis,
    'poster' => $poster,
];

if ($tipo === 'serie') {
    // Obtener todas las temporadas y sus respectivos capítulos
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

    // Añadir la información de temporadas y capítulos al resultado
    $resultado['temporadas'] = [];
    foreach ($temporadas as $temporada => $cantidadCapitulos) {
        $resultado['temporadas'][] = [
            'temporada' => $temporada,
            'capitulos' => $cantidadCapitulos
        ];
    }

    // Añadir el número total de temporadas al resultado
    $resultado['total_temporadas'] = count($temporadas);
}

// Devolver el resultado como JSON
header('Content-Type: application/json');
echo json_encode($resultado);

?>

