<?php
ini_set('display_errors', 0);
ob_start();

// A API do app (api2.php) nao usa sessao: define LF_NO_SESSION antes de incluir este arquivo.
if (!defined('LF_NO_SESSION')) {
    if (session_status() === PHP_SESSION_NONE) {
        $lf_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        session_set_cookie_params(array('lifetime' => 0, 'path' => '/', 'secure' => $lf_https, 'httponly' => true, 'samesite' => 'Lax'));
        session_start();
    }
    header("Content-Type: text/html;charset=UTF-8");
}

// ============================================================
//  >>> DADOS DO BANCO DE DADOS: PREENCHA AQUI <<<
// ============================================================
if(isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST']=="localhost"){

	DEFINE('DB_USER', 'TU_USER_rokuadmin');
	DEFINE('DB_PASSWORD', 'TU_PASSWORD');
	DEFINE('DB_HOST', 'localhost');
	DEFINE('DB_NAME', 'TU_USER_rokuadmin');

} else {

	DEFINE('DB_USER', 'TU_USER_rokuadmin');
	DEFINE('DB_PASSWORD', 'TU_PASSWORD');
	DEFINE('DB_HOST', 'localhost');
	DEFINE('DB_NAME', 'TU_USER_rokuadmin');

}

mysqli_report(MYSQLI_REPORT_OFF);
$mysqli = @new mysqli(DB_HOST,DB_USER,DB_PASSWORD,DB_NAME);
if ($mysqli->connect_errno) {
    error_log('[painel] falha na conexao com o banco: ' . $mysqli->connect_error);
    if (defined('LF_NO_SESSION')) {
        header('Content-Type: application/json');
        http_response_code(503);
        die(json_encode(array('status' => 'error', 'message' => 'Servidor indisponivel. Tente novamente.')));
    }
    die("Falha na conexao com o banco de dados. Confira os dados em includes/db_helper.php");
} else {
    $mysqli->set_charset('utf8mb4');

    require_once(__DIR__ . '/core.php');
    lf_migrate();

    $setting_qry="SELECT * FROM tbl_settings where id='1'";
    $setting_result=mysqli_query($mysqli,$setting_qry);
    $settings_details=mysqli_fetch_assoc($setting_result);
    if (!is_array($settings_details)) { $settings_details = array('onesignal_app_id' => '', 'onesignal_rest_key' => '', 'app_name' => 'Painel', 'app_logo' => '', 'api_key' => ''); }

    define("APP_API_KEY",'UzCbzsPZhsH8aeh1JlsK0gR0nYtmpgwcjtXm9g9lAUt4p');
    define("ONESIGNAL_APP_ID",$settings_details['onesignal_app_id']);
    define("ONESIGNAL_REST_KEY",$settings_details['onesignal_rest_key']);

    define("APP_NAME",$settings_details['app_name']);
    define("APP_LOGO",$settings_details['app_logo']);
    define("APP_TOKEN",$settings_details['api_key']);

    if(isset($_SESSION['id'])){
    	$profile_details = lf_me();
    	define("PROFILE_IMG", $profile_details ? $profile_details['image'] : '');
    }
}
?>