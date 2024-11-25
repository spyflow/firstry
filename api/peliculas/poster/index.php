<?php

// Verificar si el parámetro 'file' está presente en la URL
if (isset($_GET['file'])) {
    // Obtener el valor del parámetro 'file'
    $filename = $_GET['file'];

    // Validar que el nombre del archivo no contenga caracteres inseguros
    $filename = basename($filename);

    // Definir la URL de destino
    $url = "https://www18.pelisplushd.to/poster/" . urlencode($filename);

    // Inicializar una sesión cURL
    $ch = curl_init();

    // Configurar cURL
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Ejecutar la solicitud y obtener la respuesta
    $response = curl_exec($ch);

    // Verificar si hubo un error
    if (curl_errno($ch)) {
        echo "Error en la solicitud: " . curl_error($ch);
    } else {
        // Mostrar la respuesta
        header("Content-Type: image/jpeg"); // Asumiendo que es una imagen
        echo $response;
    }

    // Cerrar la sesión cURL
    curl_close($ch);
} else {
    echo "Parámetro 'file' no especificado.";
}

?>