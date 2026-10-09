<?php
// ============================================================
//  LANDING PAGE DE ATIVACAO + MERCADO PAGO
//  - o cliente informa o MAC do aparelho e o link M3U (Xtream)
//  - paga por Pix (QR na propria pagina) ou cartao (Checkout Pro)
//  - pagamento aprovado => o MAC e ativado com a DNS/usuario/senha do link
//    e o app entra sozinho pela rota que ja existe (api2.php?action=device_login).
//    Nenhuma alteracao nos apps e necessaria.
// ============================================================
if (!isset($mysqli)) { exit('No direct script access allowed'); }

define('LP_SOURCE', 'site');
define('MP_API', 'https://api.mercadopago.com');

// ---------- configuracao (chave/valor) ----------
function lp_defaults() {
    return array(
        'enabled'        => '0',
        'owner_id'       => '0',
        'title'          => 'Ative seu aplicativo',
        'subtitle'       => 'Informe o MAC que aparece na tela do app e o seu link M3U. Pagou, ativou: o app entra sozinho.',
        'whatsapp'       => '',
        'mp_access_token'=> '',
        'mp_public_key'  => '',
        'mp_webhook_secret' => '',
        'pay_pix'        => '1',
        'pay_card'       => '1',
        'pix_minutes'    => '30',
        'check_m3u'      => '1',
        'color'          => '#7c3aed',
    );
}
function lp_cfg($key = null) {
    static $cfg = null;
    if ($cfg === null) {
        $cfg = lp_defaults();
        foreach (db_all("SELECT k, v FROM tbl_lp_config") as $r) $cfg[$r['k']] = $r['v'];
    }
    if ($key === null) return $cfg;
    return isset($cfg[$key]) ? $cfg[$key] : '';
}
function lp_cfg_save($values) {
    foreach ($values as $k => $v) {
        db_query("INSERT INTO tbl_lp_config (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)", array((string)$k, (string)$v));
    }
}
// dono das ativacoes feitas pelo site (padrao: o primeiro ADMIN)
function lp_owner() {
    $id = (int)lp_cfg('owner_id');
    $o = $id > 0 ? db_row("SELECT * FROM tbl_admin WHERE id = ?", array($id)) : null;
    if (!$o) $o = db_row("SELECT * FROM tbl_admin WHERE admin_type = ? AND status = 1 ORDER BY id ASC LIMIT 1", array(ROLE_ADMIN));
    return $o;
}
function lp_mp_ready() { return trim(lp_cfg('mp_access_token')) !== ''; }
function lp_money($v) { return 'R$ ' . number_format((float)$v, 2, ',', '.'); }
function lp_base_url() {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/')), '/');
    return ($https ? 'https' : 'http') . '://' . $host . $dir . '/';
}

// ============================================================
//  LINK M3U  ->  DNS + usuario + senha
//  aceita: http://srv.com:8080/get.php?username=U&password=P&type=m3u_plus
//          http://srv.com:8080/U/P/123   (link de canal/lista no formato Xtream)
// ============================================================
function lp_parse_m3u($link) {
    $link = trim((string)$link);
    if ($link === '') return null;
    if (!preg_match('#^https?://#i', $link)) $link = 'http://' . $link;
    $u = parse_url($link);
    if (!$u || empty($u['host'])) return null;
    $scheme = strtolower($u['scheme']);
    if ($scheme !== 'http' && $scheme !== 'https') return null;
    $base = $scheme . '://' . strtolower($u['host']) . (isset($u['port']) ? ':' . (int)$u['port'] : '');

    $user = ''; $pass = '';
    if (!empty($u['query'])) {
        parse_str($u['query'], $q);
        if (isset($q['username']) && is_string($q['username'])) $user = $q['username'];
        if (isset($q['password']) && is_string($q['password'])) $pass = $q['password'];
    }
    if ($user === '' && !empty($u['path'])) {
        // formato /live/U/P/123.ts, /movie/U/P/1.mp4 ou /U/P/123
        $parts = array_values(array_filter(explode('/', $u['path']), 'strlen'));
        if (count($parts) >= 3 && in_array(strtolower($parts[0]), array('live', 'movie', 'series'), true)) array_shift($parts);
        if (count($parts) >= 2 && strpos($parts[0], '.') === false) { $user = rawurldecode($parts[0]); $pass = rawurldecode($parts[1]); }
    }
    $user = trim($user); $pass = trim($pass);
    if ($user === '' || $pass === '' || strlen($user) > 120 || strlen($pass) > 190 || strlen($base) > 255) return null;
    return array('base' => $base, 'user' => $user, 'pass' => $pass);
}

// O host aponta para a internet publica? (evita usar o servidor do painel para acessar a rede interna)
function lp_public_host($base) {
    $host = parse_url($base, PHP_URL_HOST);
    if (!$host) return false;
    $ips = filter_var($host, FILTER_VALIDATE_IP) ? array($host) : @gethostbynamel($host);
    if (!$ips) return false;
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
    }
    return true;
}

// Confere usuario/senha no servidor (player_api.php). true = ok | false = recusado | null = nao deu para conferir
function lp_check_m3u($m) {
    if (!lp_public_host($m['base'])) return null;
    $url = $m['base'] . '/player_api.php?username=' . rawurlencode($m['user']) . '&password=' . rawurlencode($m['pass']);
    $r = lp_http('GET', $url, null, array(), 7);
    if ($r['code'] !== 200 || !is_array($r['json'])) return null;
    if (isset($r['json']['user_info']['auth'])) {
        if ((int)$r['json']['user_info']['auth'] !== 1) return false;
        $st = isset($r['json']['user_info']['status']) ? strtolower((string)$r['json']['user_info']['status']) : 'active';
        return $st === 'active';
    }
    return null;
}

// ============================================================
//  HTTP (curl, com alternativa sem curl)
// ============================================================
function lp_http($method, $url, $body = null, $headers = array(), $timeout = 20) {
    $payload = $body === null ? null : (is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    if ($payload !== null) $headers[] = 'Content-Type: application/json';
    $code = 0; $raw = '';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => min(8, $timeout), CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'PainelAtivacao/1.0',
        ));
        if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($raw === false) { error_log('[painel] http: ' . curl_error($ch) . ' ' . $url); $raw = ''; }
        curl_close($ch);
    } else {
        $ctx = stream_context_create(array('http' => array(
            'method' => $method, 'header' => implode("\r\n", $headers), 'content' => $payload === null ? '' : $payload,
            'timeout' => $timeout, 'ignore_errors' => true, 'follow_location' => 0, 'user_agent' => 'PainelAtivacao/1.0',
        )));
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) $raw = '';
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0] . ' ', $mm)) $code = (int)$mm[1];
    }
    return array('code' => $code, 'raw' => $raw, 'json' => json_decode($raw, true));
}

// ============================================================
//  MERCADO PAGO
// ============================================================
function mp_request($method, $path, $body = null, $idempotency = '') {
    $h = array('Authorization: Bearer ' . trim(lp_cfg('mp_access_token')), 'Accept: application/json');
    if ($idempotency !== '') $h[] = 'X-Idempotency-Key: ' . $idempotency;
    $r = lp_http($method, MP_API . $path, $body, $h, 25);
    if ($r['code'] < 200 || $r['code'] >= 300) error_log('[painel] mercadopago ' . $method . ' ' . $path . ' -> ' . $r['code'] . ' ' . substr($r['raw'], 0, 500));
    return $r;
}
function mp_error_text($r) {
    $j = $r['json'];
    if (is_array($j)) {
        if (!empty($j['cause'][0]['description'])) return (string)$j['cause'][0]['description'];
        if (!empty($j['message'])) return (string)$j['message'];
    }
    return 'Falha ao falar com o Mercado Pago (HTTP ' . (int)$r['code'] . ').';
}
// data no formato do Mercado Pago: 2026-10-09T12:30:00.000-03:00
function mp_date($ts) { return date('Y-m-d\TH:i:s', $ts) . '.000' . date('P', $ts); }

// Pix: cria o pagamento e devolve o QR Code
function mp_create_pix($order, $retry = false) {
    $mins = max(10, min(1440, (int)lp_cfg('pix_minutes')));
    $body = array(
        'transaction_amount' => round((float)$order['amount'], 2),
        'description' => 'Ativacao ' . $order['plan_name'] . ' - MAC ' . $order['mac'],
        'payment_method_id' => 'pix',
        'external_reference' => $order['token'],
        'notification_url' => lp_base_url() . 'mp_webhook.php',
        'date_of_expiration' => mp_date(time() + $mins * 60),
        'payer' => array('email' => $order['email']),
    );
    $r = mp_request('POST', '/v1/payments', $body, 'pix-' . $order['token'] . ($retry ? '-' . time() : ''));
    if (!is_array($r['json']) || empty($r['json']['id'])) return array('ok' => false, 'error' => mp_error_text($r));
    $td = isset($r['json']['point_of_interaction']['transaction_data']) ? $r['json']['point_of_interaction']['transaction_data'] : array();
    return array(
        'ok' => true, 'id' => (string)$r['json']['id'], 'status' => (string)$r['json']['status'],
        'qr' => isset($td['qr_code']) ? (string)$td['qr_code'] : '',
        'qr_b64' => isset($td['qr_code_base64']) ? (string)$td['qr_code_base64'] : '',
        'ticket' => isset($td['ticket_url']) ? (string)$td['ticket_url'] : '',
    );
}

// Cartao (e demais meios): Checkout Pro. Devolve o link de pagamento.
function mp_create_checkout($order) {
    $back = lp_base_url() . 'ativar.php?pedido=' . rawurlencode($order['token']);
    $body = array(
        'items' => array(array(
            'id' => 'plano-' . (int)$order['plan_id'], 'title' => 'Ativacao ' . $order['plan_name'],
            'description' => 'MAC ' . $order['mac'], 'quantity' => 1, 'currency_id' => 'BRL',
            'unit_price' => round((float)$order['amount'], 2),
        )),
        'payer' => array('email' => $order['email']),
        'external_reference' => $order['token'],
        'notification_url' => lp_base_url() . 'mp_webhook.php',
        'back_urls' => array('success' => $back, 'pending' => $back, 'failure' => $back),
        'auto_return' => 'approved',
        'statement_descriptor' => 'ATIVACAO',
        'payment_methods' => array('excluded_payment_types' => array(array('id' => 'ticket')), 'installments' => 12),
    );
    $r = mp_request('POST', '/checkout/preferences', $body, 'pref-' . $order['token']);
    if (!is_array($r['json']) || empty($r['json']['init_point'])) return array('ok' => false, 'error' => mp_error_text($r));
    return array('ok' => true, 'url' => (string)$r['json']['init_point']);
}

// Confere a assinatura do webhook (x-signature). Sem segredo configurado, aceita
// (o pagamento e sempre consultado de novo no Mercado Pago, entao um aviso falso nao ativa nada).
function mp_signature_ok($dataId) {
    $secret = trim(lp_cfg('mp_webhook_secret'));
    if ($secret === '') return true;
    $sig = isset($_SERVER['HTTP_X_SIGNATURE']) ? (string)$_SERVER['HTTP_X_SIGNATURE'] : '';
    $req = isset($_SERVER['HTTP_X_REQUEST_ID']) ? (string)$_SERVER['HTTP_X_REQUEST_ID'] : '';
    $ts = ''; $v1 = '';
    foreach (explode(',', $sig) as $part) {
        $kv = explode('=', trim($part), 2);
        if (count($kv) !== 2) continue;
        if ($kv[0] === 'ts') $ts = $kv[1];
        if ($kv[0] === 'v1') $v1 = $kv[1];
    }
    if ($ts === '' || $v1 === '') return false;
    $id = ctype_alnum((string)$dataId) ? strtolower((string)$dataId) : (string)$dataId;
    $manifest = '';
    if ($id !== '') $manifest .= 'id:' . $id . ';';
    if ($req !== '') $manifest .= 'request-id:' . $req . ';';
    $manifest .= 'ts:' . $ts . ';';
    return hash_equals(hash_hmac('sha256', $manifest, $secret), $v1);
}

// Consulta um pagamento no Mercado Pago e aplica no pedido. Retorna o pedido atualizado (ou null).
function mp_sync_payment($paymentId) {
    $paymentId = preg_replace('/\D+/', '', (string)$paymentId);
    if ($paymentId === '' || !lp_mp_ready()) return null;
    $r = mp_request('GET', '/v1/payments/' . $paymentId);
    $p = $r['json'];
    if (!is_array($p) || empty($p['id']) || empty($p['external_reference'])) return null;
    $order = db_row("SELECT * FROM tbl_lp_orders WHERE token = ? LIMIT 1", array((string)$p['external_reference']));
    if (!$order) return null;
    return lp_apply_payment($order, $p);
}

// Busca o pagamento do pedido pelo external_reference (quando o webhook nao chegou)
function mp_sync_order($order) {
    if (!lp_mp_ready()) return $order;
    if ($order['mp_payment_id'] !== '') {
        $o = mp_sync_payment($order['mp_payment_id']);
        if ($o && $o['status'] === 'approved') return $o;
    }
    $r = mp_request('GET', '/v1/payments/search?sort=date_created&criteria=desc&limit=10&external_reference=' . rawurlencode($order['token']));
    if (is_array($r['json']) && !empty($r['json']['results'])) {
        foreach ($r['json']['results'] as $p) {
            $order = lp_apply_payment($order, $p);
            if ($order['status'] === 'approved') break;
        }
    }
    return db_row("SELECT * FROM tbl_lp_orders WHERE id = ?", array((int)$order['id']));
}

function lp_apply_payment($order, $p) {
    $st = isset($p['status']) ? (string)$p['status'] : '';
    $paid = isset($p['transaction_amount']) ? (float)$p['transaction_amount'] : 0;
    $cur = isset($p['currency_id']) ? (string)$p['currency_id'] : 'BRL';
    db_query("UPDATE tbl_lp_orders SET mp_status = ?, mp_payment_id = ?, checked_at = ? WHERE id = ? AND status <> 'approved'",
        array($st, (string)$p['id'], time(), (int)$order['id']));
    if ($st === 'approved') {
        if ($cur !== 'BRL' || $paid + 0.009 < (float)$order['amount']) {
            error_log('[painel] pagamento ' . $p['id'] . ' com valor diferente do pedido ' . $order['token']);
        } else {
            lp_activate($order, 'mp');
        }
    } else if (in_array($st, array('rejected', 'cancelled', 'refunded', 'charged_back'), true) && $order['status'] === 'pending') {
        // pix vencido ou cartao recusado: o cliente pode tentar de novo pelo mesmo pedido
        if ($st !== 'rejected') db_query("UPDATE tbl_lp_orders SET status = ? WHERE id = ? AND status = 'pending'", array($st === 'cancelled' ? 'expired' : $st, (int)$order['id']));
    }
    return db_row("SELECT * FROM tbl_lp_orders WHERE id = ?", array((int)$order['id']));
}

// ============================================================
//  ATIVACAO DO MAC (idempotente: so roda uma vez por pedido)
// ============================================================
function lp_activate($order, $method = '') {
    global $mysqli;
    $now = time();
    db_query("UPDATE tbl_lp_orders SET status = 'approved', paid_at = ?, method = IF(method = '', ?, method) WHERE id = ? AND status <> 'approved'",
        array($now, (string)$method, (int)$order['id']));
    if (mysqli_affected_rows($mysqli) < 1) return false;   // ja ativado antes
    return lp_activate_device(db_row("SELECT * FROM tbl_lp_orders WHERE id = ?", array((int)$order['id'])));
}

// grava a ativacao no aparelho (usado tambem pelo botao "ativar de novo" do painel)
function lp_activate_device($order) {
    if (!$order) return false;
    $now = time();
    $owner = lp_owner();
    if (!$owner) { error_log('[painel] landing sem dono configurado'); return false; }
    $ownerId = (int)$owner['id'];
    $mac = lf_norm_mac($order['mac']);

    // DNS do link M3U (reaproveita se ja existir para este dono)
    $base = rtrim($order['dns_base'], '/');
    $dns = db_row("SELECT id FROM tbl_dns WHERE owner_id = ? AND (dns_base = ? OR dns_backup = ?) ORDER BY (origin = 'site') ASC, id ASC LIMIT 1", array($ownerId, $base, $base));
    if ($dns) {
        $dnsId = (int)$dns['id'];
        db_query("UPDATE tbl_dns SET status = 1 WHERE id = ? AND origin = 'site'", array($dnsId));
    } else {
        $host = parse_url($base, PHP_URL_HOST);
        // cliente = '' : esta DNS nao entra na lista de servidores dos apps no modo direto
        db_query("INSERT INTO tbl_dns (dns_title, dns_base, dns_backup, status, cliente, owner_id, partner_code, created_at, origin) VALUES (?, ?, '', 1, '', ?, ?, ?, ?)",
            array('Site: ' . $host, $base, $ownerId, lf_new_partner_code(), $now, LP_SOURCE));
        $dnsId = db_last_id();
    }

    // vencimento: renova somando ao que ainda resta (reprocessar o mesmo pedido nao soma de novo)
    $dev = db_row("SELECT * FROM tbl_devices WHERE owner_id = ? AND (mac = ? OR device_key = ?) ORDER BY (act_user <> '') DESC, id ASC LIMIT 1", array($ownerId, $mac, $mac));
    $from = $now;
    if ($dev && $dev['act_source'] === LP_SOURCE && (int)$dev['act_expires'] > $now) $from = (int)$dev['act_expires'];
    $expires = (int)$order['days'] > 0 ? $from + (int)$order['days'] * 86400 : 0;
    if ((int)$order['expires_at'] > 0 && (int)$order['device_id'] > 0) $expires = (int)$order['expires_at'];
    $note = 'Site #' . (int)$order['id'] . ($order['email'] !== '' ? ' ' . $order['email'] : '');
    $note = substr($note, 0, 120);

    if ($dev) {
        db_query("UPDATE tbl_devices SET mac = ?, note = ?, act_dns_id = ?, dns_id = ?, act_user = ?, act_pass = ?, act_updated = ?, act_expires = ?, act_source = ?, is_auto = 0, status = 1 WHERE id = ?",
            array($mac, $note, $dnsId, $dnsId, $order['m3u_user'], $order['m3u_pass'], $now, $expires, LP_SOURCE, (int)$dev['id']));
        $devId = (int)$dev['id'];
    } else {
        db_query("INSERT INTO tbl_devices (owner_id, dns_id, device_key, mac, platform, note, act_dns_id, act_user, act_pass, act_updated, act_expires, act_source, is_auto, status, first_seen, last_seen)
                  VALUES (?, ?, ?, ?, 'outro', ?, ?, ?, ?, ?, ?, ?, 0, 1, ?, 0)",
            array($ownerId, $dnsId, $mac, $mac, $note, $dnsId, $order['m3u_user'], $order['m3u_pass'], $now, $expires, LP_SOURCE, $now));
        $devId = db_last_id();
    }
    db_query("UPDATE tbl_lp_orders SET device_id = ?, expires_at = ? WHERE id = ?", array($devId, $expires, (int)$order['id']));
    return true;
}

// situacao atual de um MAC (para a consulta publica: nunca mostra usuario/senha)
function lp_mac_status($mac) {
    $act = lf_activation_row($mac);
    if (!$act) {
        $last = db_row("SELECT act_expires FROM tbl_devices WHERE mac = ? AND act_source = ? AND act_expires > 0 ORDER BY act_expires DESC LIMIT 1", array(lf_norm_mac($mac), LP_SOURCE));
        return array('active' => false, 'expires' => $last ? (int)$last['act_expires'] : 0);
    }
    return array('active' => (int)$act['status'] === 1, 'expires' => (int)$act['act_expires'], 'blocked' => (int)$act['status'] === 0);
}
