<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
ini_set('display_errors', 0); // No mostrar errores al usuario
ini_set('log_errors', 1);     // Registrar errores en el log
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

$baseurl = rtrim(trim((string)$baseurl), '/');
$username = (string)$username; $password = (string)$password; $search = (string)$search;

// so servidores publicos http/https (impede usar o painel para acessar a rede interna)
$bu = parse_url($baseurl);
if (!$bu || empty($bu['host']) || !in_array(strtolower(isset($bu['scheme']) ? $bu['scheme'] : ''), array('http', 'https'), true)) {
    errorResponse("baseurl invalida.");
}
$ips = filter_var($bu['host'], FILTER_VALIDATE_IP) ? array($bu['host']) : @gethostbynamel($bu['host']);
if (!$ips) errorResponse("Servidor nao encontrado.");
foreach ($ips as $ip) {
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) errorResponse("Servidor nao permitido.");
}
$qUser = rawurlencode($username); $qPass = rawurlencode($password);

// Seleccionar tipo de contenido
switch ($tipoid) {
    case '1':
        $url = "$baseurl/player_api.php?username=$qUser&password=$qPass&action=get_live_streams";
        break;
    case '2':
        $url = "$baseurl/player_api.php?username=$qUser&password=$qPass&action=get_vod_streams";
        break;
    case '3':
        $url = "$baseurl/player_api.php?username=$qUser&password=$qPass&action=get_series";
        break;
    default:
        errorResponse("Tipo de búsqueda no válido.");
}

// Ejecutar solicitud cURL
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 90,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 3,
    CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
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
    if (!is_array($stream)) continue;
    if (isset($stream['stream_id']) && (isset($stream['container_extension']) || $tipoid == 1)) {
        $id = $stream['stream_id'];
        $ext = isset($stream['container_extension']) ? $stream['container_extension'] : 'ts';

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
unset($stream);

// Buscar coincidencias si hay término
function searchStreams($streams, $term) {
    $results = [];
    foreach ($streams as $stream) {
        if (is_array($stream) && isset($stream['name']) && stripos((string)$stream['name'], $term) !== false) {
            $results[] = $stream;
        }
    }
    return $results;
}

$results = !empty($search) ? searchStreams($data, $search) : $data;

// Mostrar solo JSON limpio
echo json_encode($results, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
