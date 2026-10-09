<?php
// ============================================================
//  NUCLEO DO PAINEL (v2)
//  - consultas seguras (sem SQL injection)
//  - migracao automatica do banco (colunas/tabelas novas)
//  - hierarquia: ADMIN > MASTER > REVENDA
//  - codigo de parceria (numerico) por DNS
//  - registro de dispositivos (LG / Samsung / Roku / Android)
//  Compativel com PHP 7.4+
// ============================================================
if (!isset($mysqli)) { exit('No direct script access allowed'); }

define('ROLE_ADMIN', 3);     // dono do painel: ve e gerencia tudo
define('ROLE_MASTER', 1);    // revenda master: gerencia a propria arvore (suas revendas)
define('ROLE_REVENDA', 0);   // revenda comum: gerencia apenas as proprias DNS

define('LF_ONLINE_SECONDS', 300);   // "conectado agora" = sinal nos ultimos 5 minutos
define('LF_ACTIVE_DAYS', 30);       // "ativo" = usado nos ultimos 30 dias (conta no limite)
define('LF_SCHEMA_VERSION', 6);

// ---------- saida segura em HTML ----------
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---------- banco: consultas com parametros (?) ----------
function db_build($sql, $params = array()) {
    global $mysqli;
    if (empty($params)) return $sql;
    $parts = explode('?', $sql);
    if (count($parts) - 1 !== count($params)) {
        error_log('[painel] numero de parametros nao confere: ' . $sql);
        return false;
    }
    $out = $parts[0];
    $i = 1;
    foreach ($params as $p) {
        if ($p === null) $v = 'NULL';
        elseif (is_bool($p)) $v = $p ? '1' : '0';
        elseif (is_int($p) || is_float($p)) $v = (string)$p;
        else $v = "'" . $mysqli->real_escape_string((string)$p) . "'";
        $out .= $v . $parts[$i++];
    }
    return $out;
}
function db_query($sql, $params = array()) {
    global $mysqli;
    $q = db_build($sql, $params);
    if ($q === false) return false;
    $r = mysqli_query($mysqli, $q);
    if ($r === false) error_log('[painel] SQL: ' . mysqli_error($mysqli) . ' | ' . $q);
    return $r;
}
function db_all($sql, $params = array()) {
    $r = db_query($sql, $params);
    $rows = array();
    if ($r instanceof mysqli_result) { while ($row = mysqli_fetch_assoc($r)) $rows[] = $row; mysqli_free_result($r); }
    return $rows;
}
function db_row($sql, $params = array()) { $rows = db_all($sql, $params); return $rows ? $rows[0] : null; }
function db_val($sql, $params = array()) { $row = db_row($sql, $params); return $row ? reset($row) : null; }
function db_exec($sql, $params = array()) { global $mysqli; return db_query($sql, $params) === false ? false : mysqli_affected_rows($mysqli); }
function db_last_id() { global $mysqli; return (int)mysqli_insert_id($mysqli); }
function lf_in($ids) { $ids = array_map('intval', (array)$ids); return $ids ? implode(',', $ids) : '0'; }

// ============================================================
//  MIGRACAO AUTOMATICA (roda uma vez; pode rodar de novo sem risco)
// ============================================================
function lf_col_exists($table, $col) {
    return (int)db_val("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?", array($table, $col)) > 0;
}
function lf_index_exists($table, $index) {
    return (int)db_val("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?", array($table, $index)) > 0;
}
function lf_table_exists($table) {
    return (int)db_val("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?", array($table)) > 0;
}
function lf_add_col($table, $col, $ddl) {
    if (!lf_col_exists($table, $col)) db_query("ALTER TABLE `$table` ADD COLUMN `$col` $ddl");
}

function lf_schema_marker() { return __DIR__ . '/.schema_v' . LF_SCHEMA_VERSION . '_' . substr(md5(DB_HOST . '|' . DB_NAME), 0, 10); }
// a coluna de vencimento existe? (se a migracao falhar por falta de permissao, a ativacao antiga continua funcionando)
function lf_has_expiry() {
    static $ok = null;
    if ($ok === null) $ok = is_file(lf_schema_marker()) || lf_col_exists('tbl_devices', 'act_expires');
    return $ok;
}

function lf_migrate() {
    $marker = lf_schema_marker();
    if (is_file($marker)) return;

    // --- usuarios do painel: arvore + limites ---
    lf_add_col('tbl_admin', 'parent_id', "INT NOT NULL DEFAULT 0");
    lf_add_col('tbl_admin', 'max_dns', "INT NOT NULL DEFAULT 0");
    lf_add_col('tbl_admin', 'max_devices', "INT NOT NULL DEFAULT 0");
    lf_add_col('tbl_admin', 'created_at', "INT NOT NULL DEFAULT 0");

    // --- DNS: dono + codigo de parceria ---
    lf_add_col('tbl_dns', 'owner_id', "INT NOT NULL DEFAULT 0");
    lf_add_col('tbl_dns', 'partner_code', "VARCHAR(12) NOT NULL DEFAULT ''");
    lf_add_col('tbl_dns', 'created_at', "INT NOT NULL DEFAULT 0");
    if (!lf_index_exists('tbl_dns', 'idx_partner_code')) db_query("ALTER TABLE `tbl_dns` ADD INDEX `idx_partner_code` (`partner_code`)");
    if (!lf_index_exists('tbl_dns', 'idx_owner')) db_query("ALTER TABLE `tbl_dns` ADD INDEX `idx_owner` (`owner_id`)");

    // --- configuracao: modo de login do app ('direct' = usuario+senha | 'code' = codigo+usuario+senha) ---
    lf_add_col('tbl_settings', 'login_mode', "VARCHAR(10) NOT NULL DEFAULT 'direct'");

    // --- dispositivos conectados ---
    db_query("CREATE TABLE IF NOT EXISTS `tbl_devices` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `owner_id` INT NOT NULL DEFAULT 0,
        `dns_id` INT NOT NULL DEFAULT 0,
        `device_key` VARCHAR(80) NOT NULL,
        `mac` VARCHAR(40) NOT NULL DEFAULT '',
        `platform` VARCHAR(20) NOT NULL DEFAULT 'outro',
        `model` VARCHAR(120) NOT NULL DEFAULT '',
        `app_version` VARCHAR(30) NOT NULL DEFAULT '',
        `username` VARCHAR(120) NOT NULL DEFAULT '',
        `ip` VARCHAR(60) NOT NULL DEFAULT '',
        `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
        `is_auto` TINYINT(1) NOT NULL DEFAULT 0,
        `status` TINYINT(1) NOT NULL DEFAULT 1,
        `first_seen` INT NOT NULL DEFAULT 0,
        `last_seen` INT NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_owner_device` (`owner_id`, `device_key`),
        KEY `idx_dns` (`dns_id`),
        KEY `idx_last_seen` (`last_seen`),
        KEY `idx_platform` (`platform`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // --- v3: ativacao por MAC (a revenda define DNS + usuario + senha do aparelho) e nome do app ---
    lf_add_col('tbl_devices', 'app_name', "VARCHAR(60) NOT NULL DEFAULT ''");
    lf_add_col('tbl_devices', 'note', "VARCHAR(120) NOT NULL DEFAULT ''");
    lf_add_col('tbl_devices', 'act_dns_id', "INT NOT NULL DEFAULT 0");
    lf_add_col('tbl_devices', 'act_user', "VARCHAR(120) NOT NULL DEFAULT ''");
    lf_add_col('tbl_devices', 'act_pass', "VARCHAR(190) NOT NULL DEFAULT ''");
    lf_add_col('tbl_devices', 'act_updated', "INT NOT NULL DEFAULT 0");
    if (!lf_index_exists('tbl_devices', 'idx_mac')) db_query("ALTER TABLE `tbl_devices` ADD INDEX `idx_mac` (`mac`)");

    // --- v4: DNS reserva (secundaria). Se a principal cair, o app entra pela reserva ---
    lf_add_col('tbl_dns', 'dns_backup', "VARCHAR(255) NOT NULL DEFAULT ''");

    // --- v5: landing page de ativacao (cliente ativa o proprio MAC com link M3U) + Mercado Pago ---
    lf_add_col('tbl_devices', 'act_expires', "INT NOT NULL DEFAULT 0");          // 0 = sem vencimento
    lf_add_col('tbl_devices', 'act_source', "VARCHAR(20) NOT NULL DEFAULT ''");  // '' = painel | 'site' = landing page
    lf_add_col('tbl_dns', 'origin', "VARCHAR(10) NOT NULL DEFAULT ''");          // '' = painel | 'site' = criada pela landing
    db_query("CREATE TABLE IF NOT EXISTS `tbl_lp_config` (
        `k` VARCHAR(60) NOT NULL,
        `v` TEXT NOT NULL,
        PRIMARY KEY (`k`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db_query("CREATE TABLE IF NOT EXISTS `tbl_lp_plans` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(80) NOT NULL,
        `days` INT NOT NULL DEFAULT 30,
        `price` DECIMAL(10,2) NOT NULL DEFAULT 0,
        `status` TINYINT(1) NOT NULL DEFAULT 1,
        `sort` INT NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db_query("CREATE TABLE IF NOT EXISTS `tbl_lp_orders` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `token` VARCHAR(40) NOT NULL,
        `mac` VARCHAR(40) NOT NULL DEFAULT '',
        `dns_base` VARCHAR(255) NOT NULL DEFAULT '',
        `m3u_user` VARCHAR(120) NOT NULL DEFAULT '',
        `m3u_pass` VARCHAR(190) NOT NULL DEFAULT '',
        `email` VARCHAR(150) NOT NULL DEFAULT '',
        `plan_id` INT NOT NULL DEFAULT 0,
        `plan_name` VARCHAR(80) NOT NULL DEFAULT '',
        `days` INT NOT NULL DEFAULT 0,
        `amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
        `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
        `method` VARCHAR(20) NOT NULL DEFAULT '',
        `mp_payment_id` VARCHAR(40) NOT NULL DEFAULT '',
        `mp_status` VARCHAR(40) NOT NULL DEFAULT '',
        `pix_qr` TEXT NULL,
        `pix_qr_b64` MEDIUMTEXT NULL,
        `pay_url` VARCHAR(500) NOT NULL DEFAULT '',
        `ip` VARCHAR(60) NOT NULL DEFAULT '',
        `device_id` INT NOT NULL DEFAULT 0,
        `expires_at` INT NOT NULL DEFAULT 0,
        `created_at` INT NOT NULL DEFAULT 0,
        `checked_at` INT NOT NULL DEFAULT 0,
        `paid_at` INT NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_token` (`token`),
        KEY `idx_mac` (`mac`),
        KEY `idx_status` (`status`),
        KEY `idx_mp_payment` (`mp_payment_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // --- v6: formato do link (ts | m3u8), pedidos de exclusao de dados (lojas) e politicas novas ---
    lf_add_col('tbl_devices', 'act_output', "VARCHAR(10) NOT NULL DEFAULT ''");
    lf_add_col('tbl_lp_orders', 'output', "VARCHAR(10) NOT NULL DEFAULT ''");
    db_query("CREATE TABLE IF NOT EXISTS `tbl_policy_deletion` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `policy_type` VARCHAR(40) NOT NULL DEFAULT '',
        `user_email` VARCHAR(190) NOT NULL DEFAULT '',
        `report_msg` TEXT NULL,
        `deletion_on` INT NOT NULL DEFAULT 0,
        `status` TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    lf_add_col('tbl_policy_deletion', 'status', "TINYINT(1) NOT NULL DEFAULT 0");
    // troca as politicas de exemplo (texto padrao em ingles do instalador) pelos modelos aceitos nas lojas
    require_once(__DIR__ . '/policies.php');
    $pol = db_row("SELECT app_privacy_policy, app_terms FROM tbl_settings WHERE id = 1");
    if ($pol) {
        if (lf_policy_is_placeholder($pol['app_privacy_policy'])) db_query("UPDATE tbl_settings SET app_privacy_policy = ? WHERE id = 1", array(lf_policy_template('privacy')));
        if (lf_policy_is_placeholder($pol['app_terms'])) db_query("UPDATE tbl_settings SET app_terms = ? WHERE id = 1", array(lf_policy_template('terms')));
    }

    // --- dados antigos: liga cada DNS ao dono e gera o codigo que faltar ---
    db_query("UPDATE tbl_dns d INNER JOIN tbl_admin a ON a.username = d.cliente SET d.owner_id = a.id WHERE d.owner_id = 0");
    $root = (int)db_val("SELECT id FROM tbl_admin WHERE admin_type = ? ORDER BY id ASC LIMIT 1", array(ROLE_ADMIN));
    if ($root > 0) db_query("UPDATE tbl_admin SET parent_id = ? WHERE parent_id = 0 AND admin_type <> ?", array($root, ROLE_ADMIN));
    foreach (db_all("SELECT id FROM tbl_dns WHERE partner_code = ''") as $row) {
        db_query("UPDATE tbl_dns SET partner_code = ? WHERE id = ?", array(lf_new_partner_code(), (int)$row['id']));
    }

    if (lf_col_exists('tbl_dns', 'partner_code') && lf_col_exists('tbl_admin', 'parent_id') && lf_col_exists('tbl_settings', 'login_mode') && lf_col_exists('tbl_devices', 'act_user') && lf_col_exists('tbl_dns', 'dns_backup')
        && lf_col_exists('tbl_devices', 'act_expires') && lf_col_exists('tbl_dns', 'origin') && lf_table_exists('tbl_lp_orders') && lf_table_exists('tbl_lp_plans') && lf_table_exists('tbl_lp_config')
        && lf_col_exists('tbl_devices', 'act_output') && lf_col_exists('tbl_lp_orders', 'output') && lf_table_exists('tbl_policy_deletion')) {
        @file_put_contents($marker, date('c'));
    }
}

// ============================================================
//  CODIGO DE PARCERIA (somente numeros, unico)
// ============================================================
function lf_new_partner_code() {
    for ($len = 6; $len <= 9; $len++) {
        $min = (int)pow(10, $len - 1);
        $max = (int)pow(10, $len) - 1;
        for ($i = 0; $i < 30; $i++) {
            $code = (string)random_int($min, $max);
            if (!db_val("SELECT id FROM tbl_dns WHERE partner_code = ? LIMIT 1", array($code))) return $code;
        }
    }
    return (string)(time() % 1000000000);
}
function lf_only_digits($s) { return preg_replace('/\D+/', '', (string)$s); }

// ============================================================
//  USUARIO LOGADO + HIERARQUIA
// ============================================================
function lf_me() {
    static $me = false;
    if ($me !== false) return $me;
    $me = null;
    if (!empty($_SESSION['id'])) $me = db_row("SELECT * FROM tbl_admin WHERE id = ?", array((int)$_SESSION['id']));
    return $me;
}
function lf_role($user = null) { $u = $user ? $user : lf_me(); return $u ? (int)$u['admin_type'] : -1; }
function lf_is_admin() { return lf_role() === ROLE_ADMIN; }
function lf_is_master() { return lf_role() === ROLE_MASTER; }
function lf_can_manage_users() { return lf_role() === ROLE_ADMIN || lf_role() === ROLE_MASTER; }
function lf_role_name($type) {
    $type = (int)$type;
    if ($type === ROLE_ADMIN) return 'ADMIN';
    if ($type === ROLE_MASTER) return 'MASTER';
    return 'REVENDA';
}
function lf_role_badge($type) {
    $type = (int)$type;
    $cls = $type === ROLE_ADMIN ? 'lf-badge--admin' : ($type === ROLE_MASTER ? 'lf-badge--master' : 'lf-badge--revenda');
    return '<span class="lf-badge ' . $cls . '">' . lf_role_name($type) . '</span>';
}

// todos os usuarios abaixo de $id na arvore (sem incluir o proprio)
function lf_descendants($id) {
    $children = array();
    foreach (db_all("SELECT id, parent_id FROM tbl_admin") as $r) $children[(int)$r['parent_id']][] = (int)$r['id'];
    $out = array(); $queue = array((int)$id); $seen = array((int)$id => true);
    while ($queue) {
        $cur = array_shift($queue);
        if (empty($children[$cur])) continue;
        foreach ($children[$cur] as $c) { if (isset($seen[$c])) continue; $seen[$c] = true; $out[] = $c; $queue[] = $c; }
    }
    return $out;
}
// ids que o usuario logado enxerga. null = todos (ADMIN)
function lf_scope_ids() {
    static $ids = false;
    if ($ids !== false) return $ids;
    $me = lf_me();
    if (!$me) return $ids = array(0);
    if (lf_role($me) === ROLE_ADMIN) return $ids = null;
    if (lf_role($me) === ROLE_MASTER) return $ids = array_merge(array((int)$me['id']), lf_descendants((int)$me['id']));
    return $ids = array((int)$me['id']);
}
function lf_scope_sql($col) { $ids = lf_scope_ids(); return $ids === null ? '1=1' : "$col IN (" . lf_in($ids) . ")"; }
function lf_in_scope($ownerId) { $ids = lf_scope_ids(); return $ids === null ? true : in_array((int)$ownerId, $ids, true); }
// pode editar/excluir este usuario? (alguem abaixo na arvore; nunca o proprio por esta tela)
function lf_can_manage_user($target) {
    $me = lf_me();
    if (!$me || !$target) return false;
    if ((int)$target['id'] === (int)$me['id']) return false;
    if (lf_role($me) === ROLE_ADMIN) return true;
    if (lf_role($me) === ROLE_MASTER) return (int)$target['admin_type'] === ROLE_REVENDA && in_array((int)$target['id'], lf_descendants((int)$me['id']), true);
    return false;
}

// ---------- CSRF ----------
function lf_csrf() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function lf_csrf_field() { return '<input type="hidden" name="csrf" value="' . e(lf_csrf()) . '">'; }
function lf_csrf_ok() {
    $t = isset($_POST['csrf']) ? $_POST['csrf'] : (isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '');
    return is_string($t) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}
function lf_flash($msg, $class = 'success') { $_SESSION['lf_flash'] = array('msg' => $msg, 'class' => $class); }
function lf_redirect($url) { header('Location: ' . $url); exit; }

// ---------- senha (aceita o md5 antigo e migra para hash seguro) ----------
function lf_password_hash($plain) { return password_hash($plain, PASSWORD_DEFAULT); }
function lf_password_verify($plain, $stored) {
    if (strlen($stored) === 32 && ctype_xdigit($stored)) return hash_equals(strtolower($stored), md5($plain));
    return password_verify($plain, $stored);
}
function lf_password_is_legacy($stored) { return strlen($stored) === 32 && ctype_xdigit($stored); }

// ---------- configuracao ----------
function lf_login_mode() {
    $m = db_val("SELECT login_mode FROM tbl_settings WHERE id = 1");
    return $m === 'code' ? 'code' : 'direct';
}

// ============================================================
//  DISPOSITIVOS
// ============================================================
function lf_platforms() {
    return array('android' => 'Android', 'roku' => 'Roku', 'samsung' => 'Samsung', 'lg' => 'LG', 'pc' => 'PC', 'outro' => 'Outros');
}
function lf_platform_icon($p) {
    $map = array('android' => 'ri-android-line', 'roku' => 'ri-tv-2-line', 'samsung' => 'ri-tv-line', 'lg' => 'ri-computer-line', 'pc' => 'ri-macbook-line', 'outro' => 'ri-device-line');
    return isset($map[$p]) ? $map[$p] : $map['outro'];
}
// Descobre a plataforma. 1o o que o proprio app informou; so depois o User-Agent
// (o navegador de uma TV LG/Samsung tambem diz "Linux", e o de um PC pode dizer muita coisa).
function lf_platform_from($t) {
    $t = strtolower(trim((string)$t));
    if ($t === '') return '';
    if (strpos($t, 'roku') !== false) return 'roku';
    if (strpos($t, 'tizen') !== false || strpos($t, 'samsung') !== false || strpos($t, 'smart-tv') !== false) return 'samsung';
    if ($t === 'lg' || strpos($t, 'webos') !== false || strpos($t, 'web0s') !== false || strpos($t, 'netcast') !== false || strpos($t, 'lg ') === 0 || strpos($t, 'lge') !== false || strpos($t, 'lg browser') !== false) return 'lg';
    if (strpos($t, 'android') !== false || strpos($t, 'okhttp') !== false || strpos($t, 'dalvik') !== false || strpos($t, 'firetv') !== false || strpos($t, 'aft') === 0 || strpos($t, 'tvbox') !== false) return 'android';
    if ($t === 'pc' || $t === 'desktop' || $t === 'computador' || strpos($t, 'electron') !== false
        || strpos($t, 'windows') !== false || strpos($t, 'win32') !== false || strpos($t, 'win64') !== false
        || strpos($t, 'macintosh') !== false || strpos($t, 'mac os') !== false || strpos($t, 'macos') !== false || $t === 'mac' || $t === 'darwin'
        || strpos($t, 'x11') !== false || strpos($t, 'linux x86') !== false || strpos($t, 'cros') !== false || $t === 'linux') return 'pc';
    return '';
}
function lf_norm_platform($raw, $ua = '') {
    $p = lf_platform_from($raw);
    if ($p === '') $p = lf_platform_from($ua);
    return $p === '' ? 'outro' : $p;
}

// MAC no formato AA:BB:CC:DD:EE:FF (aceita com ou sem separador). '' se nao for um MAC.
function lf_norm_mac($s) {
    $h = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', (string)$s));
    if (strlen($h) !== 12) return '';
    return implode(':', str_split($h, 2));
}

// Ativacao por MAC: registro com DNS + usuario + senha definidos pela revenda para este aparelho
function lf_activation_row($mac) {
    $mac = lf_norm_mac($mac);
    if ($mac === '') return null;
    return db_row("SELECT v.*, d.dns_title, d.dns_base, d.dns_backup, d.partner_code, d.status AS dns_status, a.status AS owner_status
                   FROM tbl_devices v
                   INNER JOIN tbl_dns d ON d.id = v.act_dns_id
                   INNER JOIN tbl_admin a ON a.id = v.owner_id
                   WHERE v.mac = ? AND v.act_user <> ''" . (lf_has_expiry() ? " AND (v.act_expires = 0 OR v.act_expires > " . time() . ")" : "") . "
                   ORDER BY v.act_updated DESC LIMIT 1", array($mac));
}
// assinatura da ativacao: o app compara e, se mudar, entra de novo com os dados novos
function lf_activation_sig($act) {
    return substr(md5($act['act_dns_id'] . '|' . $act['act_user'] . '|' . $act['act_pass']), 0, 12);
}
function lf_client_ip() {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    return substr($ip, 0, 60);
}
function lf_active_since() { return time() - LF_ACTIVE_DAYS * 86400; }
function lf_active_count($ownerId) {
    return (int)db_val("SELECT COUNT(*) FROM tbl_devices WHERE owner_id = ? AND status = 1 AND is_auto = 0 AND last_seen >= ?", array((int)$ownerId, lf_active_since()));
}

// Registra/atualiza o dispositivo. Retorna array('ok'=>bool, 'error'=>'blocked'|'limit'|'')
function lf_touch_device($owner, $dnsId, $d) {
    $now = time();
    $key = substr(trim((string)$d['key']), 0, 80);
    if ($key === '' || !$owner) return array('ok' => true, 'error' => '');
    $ownerId = (int)$owner['id'];
    $isAuto = !empty($d['auto']) ? 1 : 0;

    // o mesmo aparelho pode ter sido cadastrado pela revenda so com o MAC: procura pelos dois
    $mac = isset($d['mac']) ? lf_norm_mac($d['mac']) : '';
    if ($mac !== '') {
        $row = db_row("SELECT id, status FROM tbl_devices WHERE owner_id = ? AND (device_key = ? OR mac = ?) ORDER BY (act_user <> '') DESC, id ASC LIMIT 1", array($ownerId, $key, $mac));
    } else {
        $row = db_row("SELECT id, status FROM tbl_devices WHERE owner_id = ? AND device_key = ?", array($ownerId, $key));
    }
    if ($row) {
        if ((int)$row['status'] === 0) return array('ok' => false, 'error' => 'blocked');
        $sql = "UPDATE tbl_devices SET last_seen = ?, ip = ?, user_agent = ?, platform = ?";
        $p = array($now, $d['ip'], $d['ua'], $d['platform']);
        if ($dnsId > 0) { $sql .= ", dns_id = ?"; $p[] = (int)$dnsId; }
        $sql .= ", is_auto = ?"; $p[] = $isAuto;
        foreach (array('mac' => 'mac', 'model' => 'model', 'app_version' => 'version', 'username' => 'username', 'app_name' => 'app') as $col => $k) {
            if (isset($d[$k]) && $d[$k] !== '') { $sql .= ", $col = ?"; $p[] = $d[$k]; }
        }
        $sql .= " WHERE id = ?"; $p[] = (int)$row['id'];
        db_query($sql, $p);
        return array('ok' => true, 'error' => '');
    }

    $limit = (int)$owner['max_devices'];
    if (!$isAuto && $limit > 0 && lf_active_count($ownerId) >= $limit) return array('ok' => false, 'error' => 'limit');

    db_query("INSERT INTO tbl_devices (owner_id, dns_id, device_key, mac, platform, model, app_version, username, ip, user_agent, is_auto, status, first_seen, last_seen, app_name)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)",
        array($ownerId, (int)$dnsId, $key, (string)$d['mac'], $d['platform'], (string)$d['model'], (string)$d['version'], (string)$d['username'], $d['ip'], $d['ua'], $isAuto, $now, $now, isset($d['app']) ? (string)$d['app'] : ''));
    return array('ok' => true, 'error' => '');
}

// ---------- utilidades de tela ----------
function lf_time_ago($ts) {
    $ts = (int)$ts;
    if ($ts <= 0) return 'nunca';
    $s = time() - $ts;
    if ($s < 60) return 'agora';
    if ($s < 3600) return floor($s / 60) . ' min';
    if ($s < 86400) return floor($s / 3600) . ' h';
    if ($s < 86400 * 30) return floor($s / 86400) . ' d';
    return date('d/m/Y', $ts);
}
function lf_pagination($total, $limit, $page, $baseUrl) {
    $pages = (int)ceil($total / max(1, $limit));
    if ($pages <= 1) return '';
    $sep = strpos($baseUrl, '?') === false ? '?' : '&';
    $h = '<nav class="lf-pagination">';
    $from = max(1, $page - 3); $to = min($pages, $page + 3);
    if ($page > 1) $h .= '<a href="' . e($baseUrl . $sep . 'page=' . ($page - 1)) . '">&laquo;</a>';
    for ($i = $from; $i <= $to; $i++) {
        $h .= $i === $page ? '<span class="active">' . $i . '</span>' : '<a href="' . e($baseUrl . $sep . 'page=' . $i) . '">' . $i . '</a>';
    }
    if ($page < $pages) $h .= '<a href="' . e($baseUrl . $sep . 'page=' . ($page + 1)) . '">&raquo;</a>';
    return $h . '</nav>';
}
