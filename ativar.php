<?php
// ============================================================
//  LANDING PAGE PUBLICA DE ATIVACAO
//  O cliente informa o MAC do aparelho + o link (M3U / M3U8 HLS / MPEG-TS / Xtream)
//  ou servidor + usuario + senha, escolhe o plano e paga pelo Mercado Pago
//  (Pix ou cartao). Aprovado o pagamento, o MAC e ativado e o app entra sozinho
//  (api2.php?action=device_login) - sem nenhuma mudanca nos apps.
// ============================================================
include("includes/db_helper.php");
require_once("includes/landing.php");
require_once("includes/policies.php");

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$cfg = lp_cfg();
$brand = lp_brand();
$enabled = $cfg['enabled'] === '1';
$action = isset($_REQUEST['action']) ? (string)$_REQUEST['action'] : '';
$token = isset($_REQUEST['pedido']) ? preg_replace('/[^a-f0-9]/', '', (string)$_REQUEST['pedido']) : '';

function lp_json($a) { header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
function lp_back($msg, $old = array()) { $_SESSION['lp_err'] = $msg; $_SESSION['lp_old'] = $old; header('Location: ativar.php#ativar'); exit; }
function lp_order($token) { return $token === '' ? null : db_row("SELECT * FROM tbl_lp_orders WHERE token = ? LIMIT 1", array($token)); }
function lp_host($base) { $h = parse_url($base, PHP_URL_HOST); return $h ? $h : $base; }

// ---------- consulta publica do MAC (nunca mostra usuario/senha) ----------
if ($action === 'consulta') {
    $mac = lf_norm_mac(isset($_GET['mac']) ? $_GET['mac'] : '');
    if ($mac === '') lp_json(array('ok' => false, 'msg' => 'MAC invalido. Ex.: A1:B2:C3:D4:E5:F6'));
    usleep(300000);
    $s = lp_mac_status($mac);
    $exp = $s['expires'] > 0 ? date('d/m/Y H:i', $s['expires']) : '';
    if (!empty($s['blocked'])) lp_json(array('ok' => true, 'active' => false, 'msg' => 'Este aparelho esta bloqueado. Fale com o suporte.'));
    if ($s['active']) lp_json(array('ok' => true, 'active' => true, 'msg' => $exp ? 'Ativo ate ' . $exp . '.' : 'Ativo, sem vencimento.'));
    lp_json(array('ok' => true, 'active' => false, 'msg' => $exp ? 'Ativacao vencida em ' . $exp . '. Renove abaixo.' : 'Este aparelho ainda nao esta ativado.'));
}

// ---------- situacao do pedido (a pagina consulta a cada poucos segundos) ----------
if ($action === 'status') {
    $o = lp_order($token);
    if (!$o) lp_json(array('ok' => false));
    if ($o['status'] === 'pending' && (float)$o['amount'] > 0 && time() - (int)$o['checked_at'] >= 8) {
        db_query("UPDATE tbl_lp_orders SET checked_at = ? WHERE id = ?", array(time(), (int)$o['id']));
        $o = mp_sync_order($o);
    }
    lp_json(array('ok' => true, 'status' => $o['status'], 'expires' => (int)$o['expires_at'] > 0 ? date('d/m/Y H:i', (int)$o['expires_at']) : ''));
}

// ---------- criar pedido ----------
if ($action === 'criar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $str = function ($k, $max) { return isset($_POST[$k]) && is_string($_POST[$k]) ? substr(trim($_POST[$k]), 0, $max) : ''; };
    $old = array(
        'mac' => $str('mac', 40), 'mode' => $str('mode', 10) === 'manual' ? 'manual' : 'link',
        'm3u' => $str('m3u', 800), 'server' => $str('server', 255), 'user' => $str('user', 120), 'pass' => $str('pass', 190),
        'output' => lp_norm_output($str('output', 10)), 'email' => $str('email', 150),
        'plan' => isset($_POST['plan']) ? (int)$_POST['plan'] : 0,
        'method' => $str('method', 10) === 'card' ? 'card' : 'pix',
    );
    if (!$enabled) lp_back('A ativacao pelo site esta desligada no momento.', $old);
    if (!lf_csrf_ok()) lp_back('Sua sessao expirou. Confira os dados e tente de novo.', $old);
    if (!empty($_POST['website'])) lp_back('Nao foi possivel continuar.', $old);    // armadilha para robos
    if (empty($_POST['rights'])) lp_back('Confirme que voce tem direito de acesso ao conteudo da sua lista.', $old);

    $mac = lf_norm_mac($old['mac']);
    if ($mac === '') lp_back('MAC invalido. Ele tem 12 letras/numeros e aparece na tela do app (ex.: A1:B2:C3:D4:E5:F6).', $old);
    if ($old['mode'] === 'manual' && $cfg['allow_manual'] === '1') {
        $m = lp_parse_manual($old['server'], $old['user'], $old['pass']);
        if (!$m) lp_back('Confira o servidor (ex.: http://servidor.com:8080), o usuario e a senha.', $old);
    } else {
        $m = lp_parse_m3u($old['m3u']);
        if (!$m) lp_back('Link invalido. Use o link completo da sua lista (M3U, M3U8/HLS ou TS) com usuario e senha, ex.: http://servidor.com:8080/get.php?username=USUARIO&password=SENHA&type=m3u_plus', $old);
    }
    $output = $old['output'] !== '' ? $old['output'] : ($m['output'] !== '' ? $m['output'] : lp_norm_output($cfg['default_output']));
    $plan = db_row("SELECT * FROM tbl_lp_plans WHERE id = ? AND status = 1", array($old['plan']));
    if (!$plan) lp_back('Escolha um plano.', $old);
    $free = (float)$plan['price'] <= 0;
    if (!$free && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) lp_back('Informe um e-mail valido (o comprovante chega nele).', $old);
    if (!lp_owner()) lp_back('Ativacao indisponivel no momento. Fale com o suporte.', $old);

    // limite de pedidos por IP (anti abuso)
    $ip = lf_client_ip();
    if ((int)db_val("SELECT COUNT(*) FROM tbl_lp_orders WHERE ip = ? AND created_at >= ?", array($ip, time() - 3600)) >= 15) lp_back('Muitas tentativas. Aguarde alguns minutos.', $old);

    // aparelho bloqueado no painel nao pode ser ativado pelo site
    if (db_val("SELECT id FROM tbl_devices WHERE mac = ? AND status = 0 LIMIT 1", array($mac))) lp_back('Este aparelho esta bloqueado. Fale com o suporte.', $old);
    if ($free && db_val("SELECT id FROM tbl_lp_orders WHERE mac = ? AND amount = 0 AND status = 'approved' LIMIT 1", array($mac))) {
        lp_back('O teste gratis ja foi usado neste aparelho. Escolha um plano.', $old);
    }
    if ($cfg['check_m3u'] === '1' && lp_check_m3u($m) === false) {
        lp_back('O servidor recusou este usuario/senha (ou a conta esta vencida). Confira os dados da sua lista.', $old);
    }
    if (!$free) {
        if (!lp_mp_ready()) lp_back('Pagamento indisponivel no momento. Fale com o suporte.', $old);
        if ($old['method'] === 'pix' && $cfg['pay_pix'] !== '1') $old['method'] = 'card';
        if ($old['method'] === 'card' && $cfg['pay_card'] !== '1') $old['method'] = 'pix';
    }

    $tk = bin2hex(random_bytes(16));
    db_query("INSERT INTO tbl_lp_orders (token, mac, dns_base, m3u_user, m3u_pass, output, email, plan_id, plan_name, days, amount, status, method, ip, created_at)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)",
        array($tk, $mac, $m['base'], $m['user'], $m['pass'], $output, $old['email'], (int)$plan['id'], $plan['name'], (int)$plan['days'], number_format((float)$plan['price'], 2, '.', ''), $free ? 'free' : $old['method'], $ip, time()));
    $order = lp_order($tk);
    if (!$order) lp_back('Nao foi possivel registrar o pedido. Tente de novo.', $old);

    if ($free) {
        lp_activate($order, 'free');
        header('Location: ativar.php?pedido=' . $tk); exit;
    }
    if ($order['method'] === 'card') {
        $r = mp_create_checkout($order);
        if (!$r['ok']) { db_query("UPDATE tbl_lp_orders SET status = 'error', mp_status = ? WHERE id = ?", array(substr($r['error'], 0, 40), (int)$order['id'])); lp_back('Mercado Pago: ' . $r['error'], $old); }
        db_query("UPDATE tbl_lp_orders SET pay_url = ? WHERE id = ?", array($r['url'], (int)$order['id']));
        header('Location: ' . $r['url']); exit;
    }
    $r = mp_create_pix($order);
    if (!$r['ok']) { db_query("UPDATE tbl_lp_orders SET status = 'error', mp_status = ? WHERE id = ?", array(substr($r['error'], 0, 40), (int)$order['id'])); lp_back('Mercado Pago: ' . $r['error'], $old); }
    db_query("UPDATE tbl_lp_orders SET mp_payment_id = ?, mp_status = ?, pix_qr = ?, pix_qr_b64 = ?, pay_url = ? WHERE id = ?",
        array($r['id'], $r['status'], $r['qr'], $r['qr_b64'], $r['ticket'], (int)$order['id']));
    header('Location: ativar.php?pedido=' . $tk); exit;
}

// ---------- gerar outro Pix (o anterior venceu ou deu erro) ----------
if ($action === 'novopix' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $o = lp_order($token);
    if (!$o || !lf_csrf_ok() || !in_array($o['status'], array('pending', 'expired', 'error'), true) || (float)$o['amount'] <= 0 || !lp_mp_ready()) { header('Location: ativar.php'); exit; }
    $r = mp_create_pix($o, true);
    if ($r['ok']) {
        db_query("UPDATE tbl_lp_orders SET status = 'pending', method = 'pix', mp_payment_id = ?, mp_status = ?, pix_qr = ?, pix_qr_b64 = ?, pay_url = ?, created_at = ? WHERE id = ?",
            array($r['id'], $r['status'], $r['qr'], $r['qr_b64'], $r['ticket'], time(), (int)$o['id']));
    } else {
        $_SESSION['lp_err'] = 'Mercado Pago: ' . $r['error'];
    }
    header('Location: ativar.php?pedido=' . $token); exit;
}

// ---------- dados para a tela ----------
$order = lp_order($token);
if ($order && $order['status'] === 'pending' && (float)$order['amount'] > 0 && isset($_GET['payment_id'])) {
    $order = mp_sync_order($order);        // voltou do Checkout Pro
}
$plans = db_all("SELECT * FROM tbl_lp_plans WHERE status = 1 ORDER BY sort ASC, price ASC, id ASC");
$err = isset($_SESSION['lp_err']) ? $_SESSION['lp_err'] : ''; unset($_SESSION['lp_err']);
$old = isset($_SESSION['lp_old']) && is_array($_SESSION['lp_old']) ? $_SESSION['lp_old'] : array(); unset($_SESSION['lp_old']);
$o = function ($k, $d = '') use ($old) { return isset($old[$k]) ? $old[$k] : $d; };
$prefillMac = isset($_GET['mac']) ? lf_norm_mac($_GET['mac']) : '';
$wa = lf_only_digits($cfg['whatsapp']);
$tg = preg_replace('/[^A-Za-z0-9_]/', '', ltrim($cfg['telegram'], '@'));
$mail = filter_var($cfg['support_email'], FILTER_VALIDATE_EMAIL) ? $cfg['support_email'] : '';
$payPix = $cfg['pay_pix'] === '1'; $payCard = $cfg['pay_card'] === '1';
$mpReady = lp_mp_ready();
$allowManual = $cfg['allow_manual'] === '1';
$mode = $allowManual && $o('mode') === 'manual' ? 'manual' : 'link';
$outSel = $o('output', '');
$days = function ($d) { $d = (int)$d; return $d <= 0 ? 'Sem vencimento' : ($d === 1 ? '1 dia' : $d . ' dias'); };
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="<?php echo e($brand['theme']); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo e($cfg['title']); ?> | <?php echo e($brand['name']); ?></title>
<meta name="description" content="<?php echo e($cfg['subtitle']); ?>">
<meta name="theme-color" content="<?php echo e($brand['color']); ?>">
<?php if ($brand['logo']) { ?><link rel="icon" href="<?php echo e($brand['logo']); ?>"><link rel="apple-touch-icon" href="<?php echo e($brand['logo']); ?>"><?php } ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/vendors/remixicon/remixicon.min.css">
<style>
:root{--c:<?php echo $brand['color']; ?>;--bg:#f7f7f9;--card:#ffffff;--soft:#f1f1f5;--line:#e6e6ec;--txt:#16161d;--mut:#6c6c7a;--ok:#16a34a;--err:#dc2626;--sh:0 1px 2px rgba(16,16,24,.04),0 12px 40px rgba(16,16,24,.06)}
html[data-theme=dark]{--bg:#0a0a0f;--card:#121219;--soft:#191922;--line:#25252f;--txt:#ededf3;--mut:#9898a8;--sh:0 1px 2px rgba(0,0,0,.3),0 20px 50px rgba(0,0,0,.35)}
@media (prefers-color-scheme:dark){html[data-theme=auto]{--bg:#0a0a0f;--card:#121219;--soft:#191922;--line:#25252f;--txt:#ededf3;--mut:#9898a8;--sh:0 1px 2px rgba(0,0,0,.3),0 20px 50px rgba(0,0,0,.35)}}
*{box-sizing:border-box}html{scroll-behavior:smooth}
body{margin:0;font:15px/1.55 Inter,system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:var(--bg);color:var(--txt);-webkit-font-smoothing:antialiased}
body:before{content:"";position:fixed;inset:0 0 auto 0;height:420px;z-index:-1;background:radial-gradient(600px 300px at 50% -80px,color-mix(in srgb,var(--c) 22%,transparent),transparent 75%)}
a{color:inherit}
.w{max-width:560px;margin:0 auto;padding:0 16px}
.nav{display:flex;align-items:center;justify-content:space-between;padding:20px 0}
.brand{display:flex;align-items:center;gap:10px;font-weight:700;font-size:16px;text-decoration:none;min-width:0}
.brand img{height:36px;width:auto;max-width:140px;object-fit:contain;border-radius:8px}
.brand span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.link{font-size:14px;color:var(--mut);text-decoration:none;display:inline-flex;gap:6px;align-items:center}.link:hover{color:var(--txt)}
.hero{text-align:center;padding:26px 0 22px}
.pill{display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:600;color:var(--c);background:color-mix(in srgb,var(--c) 12%,transparent);padding:5px 11px;border-radius:99px}
.hero h1{font-size:clamp(28px,6vw,40px);line-height:1.12;letter-spacing:-.025em;margin:14px 0 10px}
.hero p{color:var(--mut);margin:0 auto;max-width:460px;font-size:16px}
.card{background:var(--card);border:1px solid var(--line);border-radius:20px;padding:24px;box-shadow:var(--sh)}
.lbl{display:flex;justify-content:space-between;align-items:baseline;gap:8px;font-weight:600;font-size:13.5px;margin:18px 0 7px}
.lbl small{font-weight:400;color:var(--mut);text-align:right}
form > .lbl:first-of-type{margin-top:0}
.in{width:100%;background:var(--soft);border:1px solid transparent;color:var(--txt);border-radius:12px;padding:12px 14px;font:15px Inter,sans-serif;outline:none;transition:.15s}
.in:focus{border-color:var(--c);background:var(--card);box-shadow:0 0 0 4px color-mix(in srgb,var(--c) 15%,transparent)}
textarea.in{min-height:78px;resize:vertical;word-break:break-all}
.mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;letter-spacing:.03em}
.hint{font-size:12.5px;color:var(--mut);margin-top:6px}
.seg{display:flex;background:var(--soft);border-radius:12px;padding:4px;gap:4px}
.seg label{position:relative;flex:1;text-align:center;padding:8px 4px;border-radius:9px;cursor:pointer;font-size:13px;font-weight:600;color:var(--mut);transition:.15s}
.seg input{position:absolute;opacity:0;pointer-events:none}
.seg label:has(input:checked){background:var(--card);color:var(--txt);box-shadow:0 1px 3px rgba(0,0,0,.12)}
.plans{display:grid;gap:8px}
.plan{display:flex;align-items:center;gap:12px;border:1px solid var(--line);border-radius:14px;padding:13px 14px;cursor:pointer;transition:.15s}
.plan input{accent-color:var(--c);width:17px;height:17px;margin:0;flex:none}
.plan b{display:block;font-size:14.5px}.plan small{color:var(--mut)}
.plan .pr{margin-left:auto;font-weight:700;font-size:16px;white-space:nowrap}
.plan:has(input:checked){border-color:var(--c);background:color-mix(in srgb,var(--c) 7%,var(--card))}
.chk{display:flex;gap:10px;align-items:flex-start;font-size:13px;color:var(--mut);margin-top:18px;cursor:pointer}
.chk input{accent-color:var(--c);margin-top:3px;flex:none}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:0;border-radius:12px;padding:14px 18px;font:600 15px Inter,sans-serif;cursor:pointer;text-decoration:none;transition:.15s;background:var(--soft);color:var(--txt)}
.btn-p{background:var(--c);color:#fff;width:100%}.btn-p:hover{filter:brightness(1.08)}
.btn:disabled{opacity:.6;cursor:wait}
.alert{border-radius:12px;padding:11px 13px;margin-bottom:16px;font-size:13.5px;display:flex;gap:9px;align-items:flex-start}
.a-err{background:color-mix(in srgb,var(--err) 10%,transparent);color:var(--err)}
.a-ok{background:color-mix(in srgb,var(--ok) 12%,transparent);color:var(--ok)}
.a-info{background:var(--soft);color:var(--mut)}
.secure{text-align:center;font-size:12.5px;color:var(--mut);margin:12px 0 0}
.steps{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:26px 0 0}
.step{text-align:center;font-size:12.5px;color:var(--mut)}
.step i{display:flex;align-items:center;justify-content:center;width:38px;height:38px;margin:0 auto 6px;border-radius:12px;background:color-mix(in srgb,var(--c) 12%,transparent);color:var(--c);font-size:18px}
.step b{display:block;color:var(--txt);font-size:13px}
.sec{margin-top:16px}
.row{display:flex;gap:8px}.row .in{flex:1;min-width:0}
.faq details{border-top:1px solid var(--line);padding:13px 0}.faq details:first-of-type{border-top:0;padding-top:4px}
.faq summary{cursor:pointer;font-weight:600;font-size:14px;list-style:none;display:flex;justify-content:space-between;gap:10px}.faq summary::-webkit-details-marker{display:none}
.faq summary:after{content:"+";color:var(--mut)}.faq details[open] summary:after{content:"\2013"}
.faq p{color:var(--mut);margin:7px 0 0;font-size:13.5px}
h2{font-size:17px;margin:0 0 4px}
.qr{background:#fff;border-radius:16px;padding:10px;width:220px;max-width:100%;margin:14px auto;display:block;border:1px solid var(--line)}
.amount{font-size:28px;font-weight:800;letter-spacing:-.02em}
.status{display:flex;align-items:center;gap:9px;justify-content:center;margin-top:14px;color:var(--mut);font-size:13.5px}
.spin{width:16px;height:16px;border:2px solid var(--line);border-top-color:var(--c);border-radius:50%;animation:s 1s linear infinite}@keyframes s{to{transform:rotate(360deg)}}
.big-ok{width:62px;height:62px;border-radius:50%;margin:0 auto 10px;display:flex;align-items:center;justify-content:center;background:color-mix(in srgb,var(--ok) 14%,transparent);color:var(--ok);font-size:32px}
.kv{display:grid;grid-template-columns:auto 1fr;gap:7px 16px;font-size:13.5px;margin:18px 0 6px;text-align:left;border-top:1px solid var(--line);padding-top:16px}.kv span{color:var(--mut)}.kv b{word-break:break-all}
.disc{font-size:12px;color:var(--mut);text-align:center;margin:28px auto 0;max-width:520px;line-height:1.6}
footer{color:var(--mut);text-align:center;font-size:12.5px;padding:16px 0 40px}
footer a{color:var(--mut)}
.float{position:fixed;right:16px;bottom:16px;display:flex;flex-direction:column;gap:10px;z-index:5}
@media(max-width:640px){.float{flex-direction:row;right:12px;bottom:12px}.float a{width:44px!important;height:44px!important;font-size:21px!important}body.has-float footer{padding-bottom:80px}}
.float a{width:50px;height:50px;border-radius:50%;color:#fff;display:flex;align-items:center;justify-content:center;font-size:24px;box-shadow:0 8px 24px rgba(0,0,0,.25);text-decoration:none}
.hp{position:absolute;left:-9999px;width:1px;height:1px}
[hidden]{display:none!important}
</style>
</head>
<body<?php if ($wa || $tg) echo ' class="has-float"'; ?>>
<div class="w">
    <header class="nav">
        <a class="brand" href="ativar.php"><?php if ($brand['logo']) { ?><img src="<?php echo e($brand['logo']); ?>" alt="<?php echo e($brand['name']); ?>"><?php } ?><span><?php echo e($brand['name']); ?></span></a>
        <?php if ($order) { ?><a class="link" href="ativar.php"><i class="ri-arrow-left-line"></i> Inicio</a><?php } else if ($enabled) { ?><a class="link" href="#consulta"><i class="ri-search-line"></i> Consultar</a><?php } ?>
    </header>

<?php if ($order) {
    // ===================== TELA DO PEDIDO =====================
    $st = $order['status']; ?>
    <section style="margin:18px 0 10px">
        <?php if ($err) { ?><div class="alert a-err"><i class="ri-error-warning-line"></i><div><?php echo e($err); ?></div></div><?php } ?>
        <div class="card" style="text-align:center">
            <div id="box-ok" <?php if ($st !== 'approved') echo 'hidden'; ?>>
                <div class="big-ok"><i class="ri-check-line"></i></div>
                <h1 style="font-size:24px;margin:0 0 6px">Aparelho ativado</h1>
                <p style="color:var(--mut);margin:0">Feche e abra o aplicativo no aparelho. Ele entra sozinho.</p>
                <p id="exp-txt" style="margin:12px 0 0"><?php if ((int)$order['expires_at'] > 0) { ?>Valido ate <b><?php echo date('d/m/Y H:i', (int)$order['expires_at']); ?></b><?php } ?></p>
            </div>

            <?php if ($st === 'pending' && $order['method'] === 'pix' && $order['pix_qr'] !== '') { ?>
            <div id="box-pay">
                <h1 style="font-size:22px;margin:0 0 4px">Pague com Pix</h1>
                <p style="color:var(--mut);margin:0">Escaneie o QR Code ou use o Pix Copia e Cola.</p>
                <?php if ($order['pix_qr_b64'] !== '') { ?><img class="qr" alt="QR Code Pix" src="data:image/png;base64,<?php echo e($order['pix_qr_b64']); ?>"><?php } ?>
                <div class="amount"><?php echo lp_money($order['amount']); ?></div>
                <div class="row" style="margin-top:14px"><input class="in mono" id="pixcode" readonly value="<?php echo e($order['pix_qr']); ?>"><button class="btn" type="button" id="btn-copy" style="background:var(--c);color:#fff" aria-label="Copiar"><i class="ri-file-copy-line"></i></button></div>
                <div class="status"><span class="spin"></span> Aguardando pagamento &middot; ativacao automatica</div>
            </div>
            <?php } else if ($st === 'pending' && $order['method'] === 'card') { ?>
            <div id="box-pay">
                <h1 style="font-size:22px;margin:0 0 4px">Pagamento em analise</h1>
                <p style="color:var(--mut)">Assim que o Mercado Pago aprovar, o aparelho e ativado automaticamente.</p>
                <?php if ($order['pay_url'] !== '') { ?><a class="btn btn-p" href="<?php echo e($order['pay_url']); ?>"><i class="ri-bank-card-line"></i> Ir para o pagamento</a><?php } ?>
                <div class="status"><span class="spin"></span> Verificando...</div>
            </div>
            <?php } else if ($st !== 'approved') { ?>
            <div id="box-pay">
                <h1 style="font-size:22px;margin:0 0 4px"><?php echo $st === 'expired' ? 'Pix vencido' : 'Pagamento nao concluido'; ?></h1>
                <p style="color:var(--mut)">Nenhum valor foi cobrado. Gere um novo pagamento para ativar.</p>
                <?php if ((float)$order['amount'] > 0 && $mpReady && $payPix) { ?>
                <form method="post" action="ativar.php?action=novopix&amp;pedido=<?php echo e($order['token']); ?>"><?php echo lf_csrf_field(); ?><button class="btn btn-p"><i class="ri-qr-code-line"></i> Gerar novo Pix</button></form>
                <?php } ?>
                <p style="margin:14px 0 0"><a href="ativar.php" class="link">Fazer um novo pedido</a></p>
            </div>
            <?php } ?>

            <div class="kv">
                <span>Aparelho</span><b class="mono"><?php echo e($order['mac']); ?></b>
                <span>Plano</span><b><?php echo e($order['plan_name']); ?> &middot; <?php echo e($days($order['days'])); ?></b>
                <span>Servidor</span><b><?php echo e(lp_host($order['dns_base'])); ?></b>
                <span>Usuario</span><b><?php echo e($order['m3u_user']); ?></b>
                <span>Formato</span><b><?php echo e(lp_output_label(isset($order['output']) ? $order['output'] : '')); ?></b>
                <span>Pedido</span><b>#<?php echo (int)$order['id']; ?></b>
            </div>
            <p style="font-size:12px;color:var(--mut);margin:8px 0 0">Guarde este link para acompanhar o pedido.</p>
        </div>
    </section>
    <script>
    (function(){
        var st = <?php echo json_encode($st); ?>, tk = <?php echo json_encode($order['token']); ?>;
        var b = document.getElementById('btn-copy');
        if (b) b.onclick = function(){ var i = document.getElementById('pixcode'); i.select(); i.setSelectionRange(0, 99999);
            (navigator.clipboard ? navigator.clipboard.writeText(i.value) : Promise.reject()).catch(function(){ document.execCommand('copy'); });
            b.innerHTML = '<i class="ri-check-line"></i>'; setTimeout(function(){ b.innerHTML = '<i class="ri-file-copy-line"></i>'; }, 2000); };
        if (st !== 'pending') return;
        var n = 0;
        function poll(){
            n++;
            fetch('ativar.php?action=status&pedido=' + tk, {cache:'no-store'}).then(function(r){ return r.json(); }).then(function(j){
                if (j && j.ok && j.status === 'approved') {
                    var p = document.getElementById('box-pay'); if (p) p.hidden = true;
                    document.getElementById('box-ok').hidden = false;
                    if (j.expires) document.getElementById('exp-txt').innerHTML = 'Valido ate <b>' + j.expires + '</b>';
                    return;
                }
                if (j && j.ok && j.status !== 'pending') { location.reload(); return; }
                setTimeout(poll, n < 60 ? 5000 : 15000);
            }).catch(function(){ setTimeout(poll, 10000); });
        }
        setTimeout(poll, 4000);
    })();
    </script>

<?php } else if (!$enabled) { ?>
    <section class="hero">
        <h1>Ativacao indisponivel</h1>
        <p>Tente novamente mais tarde<?php if ($wa || $tg || $mail) { ?> ou fale com o suporte<?php } ?>.</p>
    </section>

<?php } else {
    // ===================== LANDING =====================
    $selPlan = (int)$o('plan', $plans ? (int)$plans[0]['id'] : 0); ?>
    <section class="hero">
        <span class="pill"><i class="ri-flashlight-fill"></i> Ativacao automatica</span>
        <h1><?php echo e($cfg['title']); ?></h1>
        <p><?php echo e($cfg['subtitle']); ?></p>
    </section>

    <section class="card" id="ativar">
        <?php if ($err) { ?><div class="alert a-err"><i class="ri-error-warning-line"></i><div><?php echo e($err); ?></div></div><?php } ?>
        <?php if (!$plans) { ?>
            <div class="alert a-info" style="margin:0"><i class="ri-information-line"></i><div>Nenhum plano disponivel no momento.</div></div>
        <?php } else { ?>
        <form method="post" action="ativar.php?action=criar" id="f" autocomplete="off" novalidate>
            <?php echo lf_csrf_field(); ?>
            <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">

            <label class="lbl" for="mac">MAC do aparelho <small>aparece na tela do app</small></label>
            <input class="in mono" id="mac" name="mac" maxlength="17" placeholder="A1:B2:C3:D4:E5:F6" required autocapitalize="characters" value="<?php echo e($o('mac', $prefillMac)); ?>">

            <div class="lbl">Sua lista</div>
            <?php if ($allowManual) { ?>
            <div class="seg" style="margin-bottom:10px">
                <label><input type="radio" name="mode" value="link" <?php if ($mode === 'link') echo 'checked'; ?>> Link da lista</label>
                <label><input type="radio" name="mode" value="manual" <?php if ($mode === 'manual') echo 'checked'; ?>> Servidor e usuario</label>
            </div>
            <?php } else { ?><input type="hidden" name="mode" value="link"><?php } ?>
            <div id="mode-link" <?php if ($mode !== 'link') echo 'hidden'; ?>>
                <textarea class="in mono" id="m3u" name="m3u" placeholder="http://servidor.com:8080/get.php?username=USUARIO&password=SENHA&type=m3u_plus"><?php echo e($o('m3u')); ?></textarea>
                <div class="hint" id="m3u-info">Aceita M3U, M3U8 (HLS), MPEG-TS (.ts) e Xtream Codes.</div>
            </div>
            <?php if ($allowManual) { ?>
            <div id="mode-manual" <?php if ($mode !== 'manual') echo 'hidden'; ?>>
                <input class="in mono" name="server" placeholder="http://servidor.com:8080" value="<?php echo e($o('server')); ?>" style="margin-bottom:8px">
                <div class="row"><input class="in" name="user" placeholder="Usuario" value="<?php echo e($o('user')); ?>"><input class="in" name="pass" placeholder="Senha" value="<?php echo e($o('pass')); ?>"></div>
            </div>
            <?php } ?>

            <div class="lbl">Formato <small>na duvida, deixe Automatico</small></div>
            <div class="seg">
                <label><input type="radio" name="output" value="" <?php if ($outSel === '') echo 'checked'; ?>> Automatico</label>
                <label><input type="radio" name="output" value="ts" <?php if ($outSel === 'ts') echo 'checked'; ?>> MPEG-TS</label>
                <label><input type="radio" name="output" value="m3u8" <?php if ($outSel === 'm3u8') echo 'checked'; ?>> HLS</label>
            </div>

            <div class="lbl">Plano</div>
            <div class="plans">
                <?php foreach ($plans as $p) { ?>
                <label class="plan">
                    <input type="radio" name="plan" value="<?php echo (int)$p['id']; ?>" data-price="<?php echo (float)$p['price']; ?>" <?php if ((int)$p['id'] === $selPlan) echo 'checked'; ?>>
                    <div><b><?php echo e($p['name']); ?></b><small><?php echo e($days($p['days'])); ?></small></div>
                    <span class="pr"><?php echo (float)$p['price'] > 0 ? lp_money($p['price']) : 'Gratis'; ?></span>
                </label>
                <?php } ?>
            </div>

            <div id="paid-fields">
                <label class="lbl" for="email">E-mail <small>para o comprovante</small></label>
                <input class="in" type="email" id="email" name="email" maxlength="150" placeholder="voce@email.com" value="<?php echo e($o('email')); ?>">
                <?php if ($payPix && $payCard) { ?>
                <div class="lbl">Pagamento</div>
                <div class="seg">
                    <label><input type="radio" name="method" value="pix" <?php if ($o('method', 'pix') === 'pix') echo 'checked'; ?>> <i class="ri-qr-code-line"></i> Pix</label>
                    <label><input type="radio" name="method" value="card" <?php if ($o('method') === 'card') echo 'checked'; ?>> <i class="ri-bank-card-line"></i> Cartao</label>
                </div>
                <?php } else { ?><input type="hidden" name="method" value="<?php echo $payCard ? 'card' : 'pix'; ?>"><?php } ?>
            </div>

            <label class="chk"><input type="checkbox" name="rights" value="1" required> <span>Declaro que a lista informada e minha ou que tenho direito legal de acessa-la, e concordo com os <a href="terms.php" target="_blank">Termos</a> e a <a href="privacy_policy.php" target="_blank">Privacidade</a>.</span></label>

            <button class="btn btn-p" type="submit" id="btn-go" style="margin-top:18px"><span><?php echo e($cfg['cta_text'] !== '' ? $cfg['cta_text'] : 'Ativar agora'); ?></span> <i class="ri-arrow-right-line"></i></button>
            <p class="secure"><i class="ri-lock-2-line"></i> Pagamento seguro pelo Mercado Pago<?php if (!$mpReady) { ?> &middot; <b>indisponivel</b><?php } ?></p>
        </form>
        <?php } ?>
    </section>

    <?php if ($cfg['show_steps'] === '1') { ?>
    <div class="steps">
        <div class="step"><i class="ri-tv-2-line"></i><b>Abra o app</b>copie o MAC</div>
        <div class="step"><i class="ri-links-line"></i><b>Informe a lista</b>e escolha o plano</div>
        <div class="step"><i class="ri-checkbox-circle-line"></i><b>Pronto</b>reabra o app</div>
    </div>
    <?php } ?>

    <section class="card sec" id="consulta">
        <h2>Consultar ativacao</h2>
        <p style="color:var(--mut);font-size:13.5px;margin:0 0 12px">Veja se o aparelho esta ativo e ate quando.</p>
        <div class="row"><input class="in mono" id="q-mac" maxlength="17" placeholder="A1:B2:C3:D4:E5:F6"><button class="btn" type="button" id="q-btn" aria-label="Consultar"><i class="ri-search-line"></i></button></div>
        <div id="q-res" style="margin-top:12px"></div>
    </section>

    <?php if ($cfg['show_faq'] === '1') { ?>
    <section class="card sec faq">
        <h2 style="margin-bottom:8px">Perguntas frequentes</h2>
        <details><summary>Voces vendem canais ou listas?</summary><p>Nao. Somos apenas um reprodutor de midia. Voce usa a sua propria lista, de um fornecedor da sua escolha.</p></details>
        <details><summary>Preciso instalar outro app?</summary><p>Nao. Depois da ativacao, feche e abra o mesmo aplicativo: ele entra sozinho.</p></details>
        <details><summary>Onde encontro o MAC?</summary><p>Na tela de login do aplicativo, normalmente na parte de baixo.</p></details>
        <details><summary>Quais formatos funcionam?</summary><p>Links M3U/M3U Plus, M3U8 (HLS), MPEG-TS (.ts) e Xtream Codes. Se a imagem travar, refaca a ativacao trocando o formato entre MPEG-TS e HLS.</p></details>
        <details><summary>Como renovo ou troco de lista?</summary><p>Faca uma nova ativacao com o mesmo MAC. Os dias novos sao somados ao que resta e a lista e atualizada.</p></details>
    </section>
    <?php } ?>
    <script>
    (function(){
        var f = document.getElementById('f');
        function fmtMac(v){ var h = v.replace(/[^0-9a-fA-F]/g,'').toUpperCase().slice(0,12); return h.replace(/(.{2})(?=.)/g,'$1:'); }
        ['mac','q-mac'].forEach(function(id){ var el = document.getElementById(id); if (el) el.addEventListener('input', function(){ var end = el.selectionStart === el.value.length; el.value = fmtMac(el.value); if (end) el.selectionStart = el.selectionEnd = el.value.length; }); });
        if (f) {
            var paid = document.getElementById('paid-fields'), go = document.getElementById('btn-go'), label = go.querySelector('span').textContent;
            function upd(){
                var c = f.querySelector('input[name=plan]:checked'), free = c && parseFloat(c.getAttribute('data-price')) <= 0;
                paid.hidden = !!free; go.querySelector('span').textContent = free ? 'Ativar teste gratis' : label;
                var m = f.querySelector('input[name=mode]:checked'), man = !!(m && m.value === 'manual');
                document.getElementById('mode-link').hidden = man; var mm = document.getElementById('mode-manual'); if (mm) mm.hidden = !man;
            }
            f.addEventListener('change', upd); upd();
            var m3u = document.getElementById('m3u'), info = document.getElementById('m3u-info'), def = info.innerHTML;
            function esc(s){ return String(s).replace(/[&<>"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
            m3u.addEventListener('input', function(){
                try { var v = m3u.value.replace(/\s+/g,''); if (!v) { info.innerHTML = def; return; } if (!/^https?:\/\//i.test(v)) v = 'http://' + v; var u = new URL(v);
                    var us = u.searchParams.get('username') || u.searchParams.get('user'), pw = u.searchParams.get('password') || u.searchParams.get('pass');
                    if (!us) { var p = u.pathname.split('/').filter(Boolean); if (['live','movie','series','timeshift','hls'].indexOf((p[0]||'').toLowerCase()) >= 0 && p.length >= 3) p.shift(); if (p.length >= 2 && p[0].indexOf('.') < 0 && p[1].indexOf('.') < 0) { us = decodeURIComponent(p[0]); pw = p[1]; } }
                    var out = (u.searchParams.get('output') || '').toLowerCase(), fmt = (/\.m3u8$/i.test(u.pathname) || out === 'hls' || out === 'm3u8') ? 'HLS' : ((/\.ts$/i.test(u.pathname) || out === 'ts' || out === 'mpegts') ? 'MPEG-TS' : '');
                    info.innerHTML = us && pw ? '<span style="color:var(--ok)"><i class="ri-check-line"></i></span> ' + esc(u.host) + ' &middot; usuario <b>' + esc(us) + '</b>' + (fmt ? ' &middot; ' + fmt : '') : '<span style="color:var(--err)">Nao encontramos usuario e senha neste link.</span>';
                } catch (e) { info.innerHTML = '<span style="color:var(--err)">Link invalido.</span>'; }
            });
            if (m3u.value) m3u.dispatchEvent(new Event('input'));
            f.addEventListener('submit', function(e){
                var mac = document.getElementById('mac');
                if (mac.value.replace(/[^0-9a-fA-F]/g,'').length !== 12) { e.preventDefault(); mac.focus(); mac.style.borderColor = 'var(--err)'; return; }
                var r = f.querySelector('input[name=rights]'); if (!r.checked) { e.preventDefault(); r.parentNode.style.color = 'var(--err)'; return; }
                go.disabled = true; go.querySelector('span').textContent = 'Aguarde...';
            });
        }
        var qb = document.getElementById('q-btn');
        if (qb) qb.onclick = function(){
            var r = document.getElementById('q-res'), m = document.getElementById('q-mac').value;
            r.innerHTML = '<div class="status" style="margin:0"><span class="spin"></span></div>';
            fetch('ativar.php?action=consulta&mac=' + encodeURIComponent(m), {cache:'no-store'}).then(function(x){ return x.json(); }).then(function(j){
                var d = document.createElement('div'); d.className = 'alert ' + (j.active ? 'a-ok' : (j.ok ? 'a-info' : 'a-err')); d.style.margin = '0'; d.textContent = j.msg || 'Erro.'; r.innerHTML = ''; r.appendChild(d);
                if (j.ok && !j.active) { var mi = document.getElementById('mac'); if (mi && !mi.value) mi.value = fmtMac(m); }
            }).catch(function(){ r.innerHTML = '<div class="alert a-err" style="margin:0">Falha na consulta.</div>'; });
        };
    })();
    </script>
<?php } ?>

    <p class="disc"><?php echo e(lf_disclaimer_text()); ?></p>
    <footer>
        <?php if (trim($cfg['footer_text']) !== '') { ?><div style="margin-bottom:6px"><?php echo e($cfg['footer_text']); ?></div><?php } ?>
        &copy; <?php echo date('Y'); ?> <?php echo e($brand['name']); ?> &middot; <a href="terms.php">Termos</a> &middot; <a href="privacy_policy.php">Privacidade</a> &middot; <a href="policy/account_delete_request.php">Excluir dados</a>
        <?php if ($mail) { ?> &middot; <a href="mailto:<?php echo e($mail); ?>">Suporte</a><?php } ?>
    </footer>
</div>
<?php if ($wa || $tg) { ?><div class="float">
    <?php if ($tg) { ?><a href="https://t.me/<?php echo e($tg); ?>" target="_blank" rel="noopener" aria-label="Telegram" style="background:#229ed9"><i class="ri-telegram-fill"></i></a><?php } ?>
    <?php if ($wa) { ?><a href="https://wa.me/<?php echo e($wa); ?>" target="_blank" rel="noopener" aria-label="WhatsApp" style="background:#22c55e"><i class="ri-whatsapp-line"></i></a><?php } ?>
</div><?php } ?>
</body>
</html>
