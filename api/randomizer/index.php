<?php

function getGeminiResponse() {
    $apiKey = getenv("GEMINI"); // Obtener la API Key desde variables de entorno en Vercel
    $url = "https://generativelanguage.googleapis.com/v1/models/gemini-2.0-flash:generateContent?key=" . $apiKey;

    $data = [
        "model" => "gemini-2.0-flash",
        "systemInstruction" => "Después de recibir el mensaje 'A', genera una página HTML completa con CSS y JavaScript incluidos. 
        La página debe ser totalmente aleatoria y seguir los principios de 'Clean Code'. No debes pedir más información, solo responde con el código.",
        "generationConfig" => [
            "temperature" => 1,
            "topP" => 0.95,
            "topK" => 40,
            "maxOutputTokens" => 8192,
            "responseMimeType" => "text/plain"
        ],
        "history" => [
            ["role" => "user", "parts" => [["text" => "A"]]]
        ]
    ];

    $options = [
        "http" => [
            "header"  => "Content-Type: application/json\r\n",
            "method"  => "POST",
            "content" => json_encode($data)
        ]
    ];

    $context  = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    if ($response === FALSE) {
        return "<h1>Error al obtener respuesta de la API.</h1>";
    }

    $responseData = json_decode($response, true);
    
    if (isset($responseData["candidates"][0]["content"]["parts"][0]["text"])) {
        return $responseData["candidates"][0]["content"]["parts"][0]["text"];
    }

    return "<h1>No se recibió una respuesta válida de la API.</h1>";
}

// Generar la respuesta de la IA y mostrarla como HTML
echo getGeminiResponse();
