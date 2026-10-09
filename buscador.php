<?php
header('Content-Type: application/json');
ini_set('display_errors', 0); // No mostrar errores al usuario
ini_set('log_errors', 1);     // Registrar errores en el log
ini_set('error_log', '/var/log/debug-buscador.log'); // Ruta del log (asegúrate de tener permisos)
error_reporting(E_ALL);

// Función para respuestas de error
function errorResponse($msg) {
    echo json_encode(["error" => $msg]);
    exit;
}

// Recibir parámetros
$username = $_GET['username'] ?? '';
$password = $_GET['password'] ?? '';
$search   = $_GET['search'] ?? '';
$tipoid   = $_GET['tipoid'] ?? '';
$baseurl  = $_GET['baseurl'] ?? '';

if (empty($username) || empty($password)) {
    errorResponse("Faltan parámetros de username o password.");
}

if (empty($baseurl)) {
    errorResponse("Falta el parámetro baseurl.");
}

$baseurl = rtrim($baseurl, '/');

// Seleccionar tipo de contenido
switch ($tipoid) {
    case '1':
        $url = "$baseurl/player_api.php?username=$username&password=$password&action=get_live_streams";
        break;
    case '2':
        $url = "$baseurl/player_api.php?username=$username&password=$password&action=get_vod_streams";
        break;
    case '3':
        $url = "$baseurl/player_api.php?username=$username&password=$password&action=get_series";
        break;
    default:
        errorResponse("Tipo de búsqueda no válido.");
}

// Ejecutar solicitud cURL
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 600,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/114.0.0.0 Safari/537.36'
]);

$response = curl_exec($ch);

if ($response === false) {
    $error = curl_error($ch);
    curl_close($ch);
    error_log("cURL Error: $error");
    errorResponse("Error al consultar la API.");
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    error_log("Respuesta HTTP $httpCode al consultar $url");
    errorResponse("Error HTTP al consultar la API.");
}

// Procesar respuesta
$data = json_decode($response, true);
if (!is_array($data)) {
    error_log("Error al decodificar JSON desde: $url");
    errorResponse("Error al decodificar JSON.");
}

// Agregar URL de reproducción
foreach ($data as &$stream) {
    if (isset($stream['stream_id'], $stream['container_extension'])) {
        $id = $stream['stream_id'];
        $ext = $stream['container_extension'];

        if ($tipoid == 2) {
            $stream['url_reproduccion'] = "$baseurl/movie/$username/$password/$id.$ext";
        } elseif ($tipoid == 3) {
            $stream['url_reproduccion'] = "$baseurl/series/$username/$password/$id.$ext";
        } elseif ($tipoid == 1) {
            $stream['url_reproduccion'] = "$baseurl/live/$username/$password/$id.ts";
        } else {
            $stream['url_reproduccion'] = "";
        }
    } else {
        $stream['url_reproduccion'] = "";
    }
}

// Buscar coincidencias si hay término
function searchStreams($streams, $term) {
    $results = [];
    foreach ($streams as $stream) {
        if (isset($stream['name']) && stripos($stream['name'], $term) !== false) {
            $results[] = $stream;
        }
    }
    return $results;
}

$results = !empty($search) ? searchStreams($data, $search) : $data;

// Mostrar solo JSON limpio
echo json_encode($results, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
