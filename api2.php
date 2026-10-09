<?php
// ============================================================
//  API DO APP (v2) - Roku / Android / Samsung / LG
//
//  1) Configuracao do app (compativel com os apps antigos):
//     GET  api2.php?cliente=USUARIO_DO_PAINEL
//     -> { bienvenida, titulo, logo, fondo, servidores[], LogoMenu, LogoLogin,
//          login_mode: "direct"|"code", api_version: 2 }
//        No modo "code" a lista "servidores" vem vazia: o app precisa do codigo.
//
//  2) Login por CODIGO DE PARCERIA:
//     POST api2.php?action=partner_login   { code, device_id, platform, mac, model, app_version, username }
//     -> { status:"success", servidores:[{title, hdPosterUrl}], dns_title, parceiro }
//     -> { status:"error", error:"invalid_code"|"blocked"|"limit", message }
//
//  3) Sinal de vida do dispositivo (a cada ~2 min e ao logar):
//     POST api2.php?action=heartbeat   { code | cliente (+host), device_id, platform, mac, model, app_version, username }
//     -> { status:"success" } | { status:"blocked", message }
//
//  4) Ativacao por MAC (a revenda cadastra o MAC do aparelho no painel com DNS + usuario + senha):
//     POST api2.php?action=device_login   { mac, device_id, platform, model, app_version, app }
//     -> { status:"success", servidores:[{title, hdPosterUrl}], username, password, code, activation }
//     -> { status:"none" }  (MAC sem ativacao: o app mostra o login normal)
//     -> { status:"blocked", message }
//
//  Os parametros podem vir por JSON (corpo), POST de formulario ou na URL.
// ============================================================
define('LF_NO_SESSION', true);
include("includes/db_helper.php");
include("includes/lb_helper.php");

$file_path = getBaseUrl();

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$jsonData = json_decode(file_get_contents('php://input'), true);
if (!is_array($jsonData)) { $jsonData = array(); }
$in = array_merge($_GET, $_POST, $jsonData);

function api_param($k, $max = 255) {
    global $in;
    if (!isset($in[$k]) || is_array($in[$k])) return '';
    return substr(trim((string)$in[$k]), 0, $max);
}
function api_out($payload, $code = 200) {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function api_fail($error, $message, $code = 200) {
    api_out(array('status' => 'error', 'error' => $error, 'message' => $message), $code);
}
function api_device($auto = false) {
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr(trim($_SERVER['HTTP_USER_AGENT']), 0, 255) : '';
    $key = api_param('device_id', 80);
    $rawMac = api_param('mac', 40);
    $mac = lf_norm_mac($rawMac);
    if ($mac === '') $mac = lf_norm_mac($key);          // apps que mandam o MAC so em device_id
    if ($mac === '') $mac = strtoupper($rawMac);
    if ($key === '' && $mac !== '') $key = $mac;
    $isAuto = false;
    if ($key === '' && $auto) { $key = 'auto-' . substr(md5(lf_client_ip() . '|' . $ua), 0, 20); $isAuto = true; }
    return array(
        'key' => $key, 'mac' => $mac, 'auto' => $isAuto,
        'platform' => lf_norm_platform(api_param('platform', 30), $ua),
        'model' => api_param('model', 120), 'version' => api_param('app_version', 30),
        'username' => api_param('username', 120), 'app' => api_param('app', 60), 'ip' => lf_client_ip(), 'ua' => $ua
    );
}
function api_servers($rows) {
    $out = array();
    // Cada DNS pode ter uma reserva. Ela vai de duas formas:
    //  - no campo "backup" da principal (apps novos trocam sozinhos se a principal cair no meio do uso);
    //  - como mais um servidor na lista, logo depois da principal (todo app tenta os servidores em ordem
    //    ao entrar, entao ate as versoes antigas entram pela reserva quando a principal nao responde).
    foreach ($rows as $r) {
        $main = html_entity_decode($r['dns_base'], ENT_QUOTES, 'UTF-8');
        $bk = isset($r['dns_backup']) ? rtrim(trim(html_entity_decode((string)$r['dns_backup'], ENT_QUOTES, 'UTF-8')), '/') : '';
        if ($bk === rtrim($main, '/')) $bk = '';
        $item = array('title' => $r['dns_title'], 'hdPosterUrl' => $main);
        if ($bk !== '') $item['backup'] = $bk;
        $out[] = $item;
        if ($bk !== '') $out[] = array('title' => $r['dns_title'], 'hdPosterUrl' => $bk, 'backup' => $main, 'reserva' => true);
    }
    return $out;
}
function api_limit_msg($err) {
    if ($err === 'blocked') return 'Este dispositivo foi bloqueado. Fale com o seu fornecedor.';
    return 'Limite de dispositivos atingido. Fale com o seu fornecedor.';
}

$action = api_param('action', 40);
if ($action === '') $action = 'app_details';

switch ($action) {

    // --------------------------------------------------------
    //  LOGIN POR CODIGO DE PARCERIA
    // --------------------------------------------------------
    case 'partner_login':
    case 'resolve_code': {
        $code = lf_only_digits(api_param('code', 20));
        if (strlen($code) < 4) api_fail('invalid_code', 'Informe o codigo de parceria.');

        $dns = db_row("SELECT d.*, a.username AS owner_name, a.status AS owner_status, a.max_devices
                       FROM tbl_dns d INNER JOIN tbl_admin a ON a.id = d.owner_id
                       WHERE d.partner_code = ? LIMIT 1", array($code));
        if (!$dns || (int)$dns['status'] !== 1 || (int)$dns['owner_status'] !== 1) {
            usleep(700000); // dificulta tentativa de adivinhar codigos
            api_fail('invalid_code', 'Codigo de parceria invalido ou desativado.');
        }

        $owner = array('id' => (int)$dns['owner_id'], 'max_devices' => (int)$dns['max_devices']);
        $res = lf_touch_device($owner, (int)$dns['id'], api_device(false));
        if (!$res['ok']) api_fail($res['error'], api_limit_msg($res['error']));

        api_out(array(
            'status' => 'success',
            'servidores' => api_servers(array($dns)),
            'dns_title' => $dns['dns_title'],
            'parceiro' => $dns['owner_name'],
            'code' => $code
        ));
    }

    // --------------------------------------------------------
    //  ATIVACAO POR MAC
    // --------------------------------------------------------
    case 'device_login':
    case 'mac_login': {
        $dev = api_device(false);
        $act = lf_activation_row($dev['mac']);
        if (!$act) api_out(array('status' => 'none', 'mac' => lf_norm_mac($dev['mac'])));
        if ((int)$act['status'] === 0) api_out(array('status' => 'blocked', 'error' => 'blocked', 'message' => api_limit_msg('blocked')));
        if ((int)$act['dns_status'] !== 1 || (int)$act['owner_status'] !== 1) api_out(array('status' => 'none', 'mac' => $act['mac']));

        lf_touch_device(array('id' => (int)$act['owner_id'], 'max_devices' => 0), (int)$act['act_dns_id'], $dev);
        api_out(array(
            'status' => 'success',
            'servidores' => api_servers(array($act)),
            'username' => $act['act_user'],
            'password' => $act['act_pass'],
            'code' => $act['partner_code'],
            'name' => $act['note'],
            'activation' => lf_activation_sig($act)
        ));
    }

    // --------------------------------------------------------
    //  SINAL DE VIDA DO DISPOSITIVO
    // --------------------------------------------------------
    case 'heartbeat':
    case 'device_ping': {
        $dev = api_device(true);

        // aparelho ativado por MAC: o registro e o da revenda que ativou (nao cria outro no dono do painel)
        $act = lf_activation_row($dev['mac']);
        if ($act) {
            if ((int)$act['status'] === 0) api_out(array('status' => 'blocked', 'error' => 'blocked', 'message' => api_limit_msg('blocked')));
            lf_touch_device(array('id' => (int)$act['owner_id'], 'max_devices' => 0), (int)$act['act_dns_id'], $dev);
            api_out(array('status' => 'success', 'login_mode' => lf_login_mode(), 'activation' => lf_activation_sig($act)));
        }
        $code = lf_only_digits(api_param('code', 20));
        $owner = null; $dnsId = 0;

        if ($code !== '') {
            $dns = db_row("SELECT d.id, d.status, d.owner_id, a.status AS owner_status, a.max_devices
                           FROM tbl_dns d INNER JOIN tbl_admin a ON a.id = d.owner_id WHERE d.partner_code = ? LIMIT 1", array($code));
            if (!$dns || (int)$dns['status'] !== 1 || (int)$dns['owner_status'] !== 1) {
                api_out(array('status' => 'blocked', 'error' => 'invalid_code', 'message' => 'Codigo de parceria desativado. Fale com o seu fornecedor.'));
            }
            $owner = array('id' => (int)$dns['owner_id'], 'max_devices' => (int)$dns['max_devices']);
            $dnsId = (int)$dns['id'];
        } else {
            $cliente = api_param('cliente', 100);
            $a = $cliente !== '' ? db_row("SELECT id, max_devices, status FROM tbl_admin WHERE username = ? LIMIT 1", array($cliente)) : null;
            if (!$a || (int)$a['status'] !== 1) api_out(array('status' => 'success'));
            $owner = array('id' => (int)$a['id'], 'max_devices' => (int)$a['max_devices']);
            // tenta descobrir qual DNS o cliente esta usando (pelo host informado: principal ou reserva)
            $host = strtolower(rtrim(api_param('host', 255), '/'));
            if ($host !== '') {
                foreach (db_all("SELECT id, dns_base, dns_backup FROM tbl_dns WHERE owner_id = ?", array($owner['id'])) as $d) {
                    foreach (array($d['dns_base'], $d['dns_backup']) as $u) {
                        $base = strtolower(rtrim(html_entity_decode((string)$u, ENT_QUOTES, 'UTF-8'), '/'));
                        if ($base !== '' && (strpos($host, $base) !== false || strpos($base, $host) !== false)) { $dnsId = (int)$d['id']; break 2; }
                    }
                }
            }
        }

        $res = lf_touch_device($owner, $dnsId, $dev);
        if (!$res['ok']) api_out(array('status' => 'blocked', 'error' => $res['error'], 'message' => api_limit_msg($res['error'])));
        api_out(array('status' => 'success', 'login_mode' => lf_login_mode()));
    }

    case 'deeplink': {
        $u = api_param('username'); $p = api_param('password');
        if ($u === 'deeplink' && $p === 'deeplink') api_out(array('status' => 'success', 'error' => 'Login success'));
        api_out(array('status' => 'error', 'error' => 'Invalid Login'));
    }

    // --------------------------------------------------------
    //  CONFIGURACAO DO APP + SERVIDORES (formato antigo, mantido)
    // --------------------------------------------------------
    case 'app_details':
    default: {
        $cliente = api_param('cliente', 100);
        if ($cliente === '') api_fail('no_client', 'Cliente no especificado.', 400);

        $mode = lf_login_mode();
        $data_arr = array('app_name' => '', 'app_titulo' => '', 'app_logo' => '', 'bg_main' => '', 'bg_login' => '', 'bg_banner' => '');
        $app = db_row("SELECT * FROM tbl_app WHERE cliente = ? LIMIT 1", array($cliente));
        if ($app) {
            $data_arr['app_name']   = $app['app_name'];
            $data_arr['app_titulo'] = $app['app_titulo'];
            foreach (array('app_logo', 'bg_main', 'bg_login', 'bg_banner') as $k) {
                $data_arr[$k] = !empty($app[$k]) ? $file_path . "images/" . $app[$k] : "";
            }
        }

        $jsonObj = array();
        $jsonObj['bienvenida'] = $data_arr['app_name'];
        $jsonObj['titulo']     = $data_arr['app_titulo'];
        $jsonObj['logo']       = $data_arr['app_logo'];
        $jsonObj['fondo']      = $data_arr['bg_main'];

        // No modo "codigo" os servidores so sao entregues por partner_login
        $jsonObj['servidores'] = array();
        $owner = db_row("SELECT id, max_devices, status FROM tbl_admin WHERE username = ? LIMIT 1", array($cliente));
        if ($mode === 'direct' && (!$owner || (int)$owner['status'] === 1)) {
            $jsonObj['servidores'] = api_servers(db_all("SELECT dns_title, dns_base, dns_backup FROM tbl_dns WHERE status = 1 AND cliente = ? ORDER BY id DESC", array($cliente)));
        }

        $jsonObj['LogoMenu']    = $data_arr['bg_banner'];
        $jsonObj['LogoLogin']   = $data_arr['bg_login'];
        $jsonObj['login_mode']  = $mode;
        $jsonObj['api_version'] = 4;

        // registra o aparelho ja na abertura do app (com MAC, plataforma e modelo quando o app envia;
        // apps antigos, sem identificacao, entram pelo IP so para estatistica)
        $dev0 = api_device(true);
        $act0 = lf_activation_row($dev0['mac']);
        if ($act0) {
            lf_touch_device(array('id' => (int)$act0['owner_id'], 'max_devices' => 0), (int)$act0['act_dns_id'], $dev0);
        } else if ($owner && (int)$owner['status'] === 1) {
            lf_touch_device(array('id' => (int)$owner['id'], 'max_devices' => 0), 0, $dev0);
        }

        echo json_encode($jsonObj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
