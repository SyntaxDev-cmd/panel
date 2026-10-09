<?php
// ============================================================
//  LANDING PAGE PUBLICA DE ATIVACAO
//  O cliente informa o MAC do aparelho + o link M3U, escolhe o plano e paga
//  pelo Mercado Pago (Pix ou cartao). Aprovado o pagamento, o MAC e ativado
//  e o app entra sozinho (api2.php?action=device_login) - sem mudar os apps.
// ============================================================
include("includes/db_helper.php");
require_once("includes/landing.php");

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$cfg = lp_cfg();
$enabled = $cfg['enabled'] === '1';
$action = isset($_REQUEST['action']) ? (string)$_REQUEST['action'] : '';
$token = isset($_REQUEST['pedido']) ? preg_replace('/[^a-f0-9]/', '', (string)$_REQUEST['pedido']) : '';

function lp_json($a) { header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
function lp_back($msg, $old = array()) { $_SESSION['lp_err'] = $msg; $_SESSION['lp_old'] = $old; header('Location: ativar.php#ativar'); exit; }
function lp_order($token) { return $token === '' ? null : db_row("SELECT * FROM tbl_lp_orders WHERE token = ? LIMIT 1", array($token)); }
function lp_mask_host($base) { $h = parse_url($base, PHP_URL_HOST); return $h ? $h : $base; }

// ---------- consulta publica do MAC ----------
if ($action === 'consulta') {
    $mac = lf_norm_mac(isset($_GET['mac']) ? $_GET['mac'] : '');
    if ($mac === '') lp_json(array('ok' => false, 'msg' => 'MAC invalido. Ex.: A1:B2:C3:D4:E5:F6'));
    usleep(300000);
    $s = lp_mac_status($mac);
    $exp = $s['expires'] > 0 ? date('d/m/Y H:i', $s['expires']) : '';
    if (!empty($s['blocked'])) lp_json(array('ok' => true, 'active' => false, 'msg' => 'Este aparelho esta bloqueado. Fale com o suporte.'));
    if ($s['active']) lp_json(array('ok' => true, 'active' => true, 'msg' => $exp ? 'Ativo ate ' . $exp . '.' : 'Ativo (sem vencimento).'));
    lp_json(array('ok' => true, 'active' => false, 'msg' => $exp ? 'Ativacao vencida em ' . $exp . '. Renove abaixo.' : 'Este MAC ainda nao esta ativado.'));
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
    $old = array(
        'mac' => isset($_POST['mac']) ? substr((string)$_POST['mac'], 0, 40) : '',
        'm3u' => isset($_POST['m3u']) ? substr((string)$_POST['m3u'], 0, 600) : '',
        'email' => isset($_POST['email']) ? substr(trim((string)$_POST['email']), 0, 150) : '',
        'plan' => isset($_POST['plan']) ? (int)$_POST['plan'] : 0,
        'method' => isset($_POST['method']) && $_POST['method'] === 'card' ? 'card' : 'pix',
    );
    if (!$enabled) lp_back('A ativacao pelo site esta desligada no momento.', $old);
    if (!lf_csrf_ok()) lp_back('Sua sessao expirou. Confira os dados e tente de novo.', $old);
    if (!empty($_POST['website'])) lp_back('Nao foi possivel continuar.', $old);    // armadilha para robos

    $mac = lf_norm_mac($old['mac']);
    if ($mac === '') lp_back('MAC invalido. Ele tem 12 letras/numeros e aparece na tela do app (ex.: A1:B2:C3:D4:E5:F6).', $old);
    $m = lp_parse_m3u($old['m3u']);
    if (!$m) lp_back('Link M3U invalido. Use o link completo, ex.: http://servidor.com:8080/get.php?username=SEU_USUARIO&password=SUA_SENHA&type=m3u_plus', $old);
    $plan = db_row("SELECT * FROM tbl_lp_plans WHERE id = ? AND status = 1", array($old['plan']));
    if (!$plan) lp_back('Escolha um plano.', $old);
    $free = (float)$plan['price'] <= 0;
    if (!$free && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) lp_back('Informe um e-mail valido (o Mercado Pago envia o comprovante para ele).', $old);
    if (!lp_owner()) lp_back('Ativacao indisponivel no momento (painel sem dono configurado).', $old);

    // limite de pedidos por IP (anti abuso)
    $ip = lf_client_ip();
    if ((int)db_val("SELECT COUNT(*) FROM tbl_lp_orders WHERE ip = ? AND created_at >= ?", array($ip, time() - 3600)) >= 15) lp_back('Muitas tentativas. Aguarde alguns minutos.', $old);

    // aparelho bloqueado no painel nao pode ser ativado pelo site
    if (db_val("SELECT id FROM tbl_devices WHERE mac = ? AND status = 0 LIMIT 1", array($mac))) lp_back('Este aparelho esta bloqueado. Fale com o suporte.', $old);

    if ($free && db_val("SELECT id FROM tbl_lp_orders WHERE mac = ? AND amount = 0 AND status = 'approved' LIMIT 1", array($mac))) {
        lp_back('O teste gratis ja foi usado neste aparelho. Escolha um plano pago.', $old);
    }

    if ($cfg['check_m3u'] === '1') {
        $chk = lp_check_m3u($m);
        if ($chk === false) lp_back('O servidor recusou este usuario/senha (ou a conta esta vencida). Confira o seu link M3U.', $old);
    }
    if (!$free) {
        if (!lp_mp_ready()) lp_back('Pagamento indisponivel no momento. Fale com o suporte.', $old);
        if ($old['method'] === 'pix' && $cfg['pay_pix'] !== '1') $old['method'] = 'card';
        if ($old['method'] === 'card' && $cfg['pay_card'] !== '1') $old['method'] = 'pix';
    }

    $tk = bin2hex(random_bytes(16));
    db_query("INSERT INTO tbl_lp_orders (token, mac, dns_base, m3u_user, m3u_pass, email, plan_id, plan_name, days, amount, status, method, ip, created_at)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)",
        array($tk, $mac, $m['base'], $m['user'], $m['pass'], $old['email'], (int)$plan['id'], $plan['name'], (int)$plan['days'], number_format((float)$plan['price'], 2, '.', ''), $free ? 'free' : $old['method'], $ip, time()));
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

// ---------- gerar outro Pix (o anterior venceu) ----------
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
$old = isset($_SESSION['lp_old']) ? $_SESSION['lp_old'] : array(); unset($_SESSION['lp_old']);
$o = function ($k, $d = '') use ($old) { return isset($old[$k]) ? $old[$k] : $d; };
$prefillMac = isset($_GET['mac']) ? lf_norm_mac($_GET['mac']) : '';
$color = preg_match('/^#[0-9a-fA-F]{6}$/', $cfg['color']) ? $cfg['color'] : '#7c3aed';
$logo = (APP_LOGO != '' && file_exists('images/' . APP_LOGO)) ? 'images/' . APP_LOGO : '';
$wa = lf_only_digits($cfg['whatsapp']);
$payPix = $cfg['pay_pix'] === '1'; $payCard = $cfg['pay_card'] === '1';
$mpReady = lp_mp_ready();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo e($cfg['title']); ?> | <?php echo e(APP_NAME); ?></title>
<meta name="description" content="<?php echo e($cfg['subtitle']); ?>">
<?php if ($logo) { ?><link rel="icon" href="<?php echo e($logo); ?>"><?php } ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/vendors/remixicon/remixicon.min.css">
<style>
:root{--c:<?php echo $color; ?>;--bg:#0b0b14;--card:#141424;--line:#262640;--txt:#ececf6;--mut:#9a9ab8;--ok:#22c55e;--err:#ef4444}
*{box-sizing:border-box}html{scroll-behavior:smooth}
body{margin:0;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:var(--bg);color:var(--txt);line-height:1.5}
body:before{content:"";position:fixed;inset:0;z-index:-1;background:radial-gradient(900px 500px at 15% -10%,color-mix(in srgb,var(--c) 35%,transparent),transparent 70%),radial-gradient(700px 500px at 100% 0,color-mix(in srgb,var(--c) 18%,transparent),transparent 70%)}
a{color:inherit}
.wrap{max-width:1080px;margin:0 auto;padding:0 16px}
header.top{display:flex;align-items:center;justify-content:space-between;padding:18px 0}
.brand{display:flex;align-items:center;gap:10px;font-weight:800;font-size:18px;text-decoration:none}
.brand img{width:36px;height:36px;border-radius:9px;object-fit:cover}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:0;border-radius:12px;padding:13px 20px;font:600 15px Inter,sans-serif;cursor:pointer;text-decoration:none;transition:.15s}
.btn-p{background:var(--c);color:#fff}.btn-p:hover{filter:brightness(1.1)}
.btn-g{background:transparent;color:var(--txt);border:1px solid var(--line)}.btn-g:hover{border-color:var(--c)}
.btn:disabled{opacity:.6;cursor:wait}
.hero{padding:40px 0 30px;text-align:center}
.hero h1{font-size:clamp(30px,5vw,50px);line-height:1.1;margin:0 0 14px;letter-spacing:-.02em}
.hero p{color:var(--mut);font-size:18px;max-width:640px;margin:0 auto 26px}
.steps{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin:10px 0 40px}
.step{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:18px}
.step i{font-size:24px;color:var(--c)}.step b{display:block;margin:6px 0 2px}.step span{color:var(--mut);font-size:14px}
.card{background:var(--card);border:1px solid var(--line);border-radius:20px;padding:26px}
.grid{display:grid;grid-template-columns:1.35fr 1fr;gap:20px;align-items:start}
@media(max-width:860px){.grid{grid-template-columns:1fr}}
h2{margin:0 0 6px;font-size:22px}.mut{color:var(--mut)}.small{font-size:13px}
label.f{display:block;font-weight:600;margin:16px 0 6px;font-size:14px}
input.i,textarea.i{width:100%;background:#0e0e1a;border:1px solid var(--line);color:var(--txt);border-radius:12px;padding:13px 14px;font:15px Inter,sans-serif;outline:none}
input.i:focus,textarea.i:focus{border-color:var(--c)}
textarea.i{min-height:84px;resize:vertical;word-break:break-all}
.mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;letter-spacing:.04em}
.plans{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px}
.plan{position:relative;display:block;border:1px solid var(--line);border-radius:14px;padding:14px;cursor:pointer;background:#0e0e1a}
.plan input{position:absolute;opacity:0}
.plan b{display:block}.plan .pr{font-size:22px;font-weight:800;margin-top:4px}
.plan:has(input:checked){border-color:var(--c);box-shadow:0 0 0 1px var(--c) inset;background:color-mix(in srgb,var(--c) 12%,#0e0e1a)}
.pay{display:flex;gap:10px;flex-wrap:wrap}
.pay label{flex:1;min-width:140px;display:flex;align-items:center;gap:8px;border:1px solid var(--line);border-radius:12px;padding:12px;cursor:pointer;background:#0e0e1a}
.pay label:has(input:checked){border-color:var(--c)}
.alert{border-radius:12px;padding:12px 14px;margin-bottom:14px;font-size:14px;display:flex;gap:10px}
.alert-err{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.4)}
.alert-ok{background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.4)}
.alert-info{background:color-mix(in srgb,var(--c) 12%,transparent);border:1px solid color-mix(in srgb,var(--c) 40%,transparent)}
.qr{background:#fff;border-radius:16px;padding:12px;width:240px;max-width:100%;margin:10px auto;display:block}
.copy{display:flex;gap:8px;margin-top:10px}.copy input{flex:1;min-width:0}
.status{display:flex;align-items:center;gap:10px;justify-content:center;margin-top:14px;color:var(--mut)}
.spin{width:18px;height:18px;border:2px solid var(--line);border-top-color:var(--c);border-radius:50%;animation:s 1s linear infinite}
@keyframes s{to{transform:rotate(360deg)}}
.big-ok{font-size:64px;color:var(--ok);line-height:1}
.kv{display:grid;grid-template-columns:auto 1fr;gap:6px 14px;font-size:14px;margin:14px 0}.kv span{color:var(--mut)}
.faq details{border-bottom:1px solid var(--line);padding:12px 0}.faq summary{cursor:pointer;font-weight:600}.faq p{color:var(--mut);margin:8px 0 0}
footer{color:var(--mut);text-align:center;font-size:13px;padding:40px 0}
.wa{position:fixed;right:18px;bottom:18px;width:54px;height:54px;border-radius:50%;background:#22c55e;color:#fff;display:flex;align-items:center;justify-content:center;font-size:28px;box-shadow:0 8px 24px rgba(0,0,0,.4);text-decoration:none}
.hp{position:absolute;left:-9999px}
</style>
</head>
<body>
<div class="wrap">
    <header class="top">
        <a class="brand" href="ativar.php"><?php if ($logo) { ?><img src="<?php echo e($logo); ?>" alt=""><?php } ?><span><?php echo e(APP_NAME); ?></span></a>
        <?php if (!$order) { ?><a class="btn btn-g" href="#consulta"><i class="ri-search-line"></i> Consultar MAC</a><?php } else { ?><a class="btn btn-g" href="ativar.php"><i class="ri-arrow-left-line"></i> Inicio</a><?php } ?>
    </header>

<?php if ($order) {
    // ===================== TELA DO PEDIDO =====================
    $st = $order['status']; ?>
    <section style="max-width:560px;margin:20px auto 40px">
        <?php if ($err) { ?><div class="alert alert-err"><i class="ri-error-warning-line"></i><div><?php echo e($err); ?></div></div><?php } ?>
        <div class="card" style="text-align:center">
            <div id="box-ok" style="<?php echo $st === 'approved' ? '' : 'display:none'; ?>">
                <div class="big-ok"><i class="ri-checkbox-circle-fill"></i></div>
                <h2>Aparelho ativado!</h2>
                <p class="mut">Feche e abra o app de novo no aparelho. Ele entra sozinho com a sua lista.</p>
                <p id="exp-txt"><?php if ((int)$order['expires_at'] > 0) { ?>Valido ate <b><?php echo date('d/m/Y H:i', (int)$order['expires_at']); ?></b><?php } ?></p>
            </div>

            <?php if ($st === 'pending' && $order['method'] === 'pix' && $order['pix_qr'] !== '') { ?>
            <div id="box-pay">
                <h2>Pague com Pix</h2>
                <p class="mut">Abra o app do seu banco, escolha <b>Pix &rsaquo; QR Code</b> ou <b>Pix Copia e Cola</b>.</p>
                <?php if ($order['pix_qr_b64'] !== '') { ?><img class="qr" alt="QR Code Pix" src="data:image/png;base64,<?php echo e($order['pix_qr_b64']); ?>"><?php } ?>
                <div style="font-size:26px;font-weight:800"><?php echo lp_money($order['amount']); ?></div>
                <div class="copy"><input class="i mono" id="pixcode" readonly value="<?php echo e($order['pix_qr']); ?>"><button class="btn btn-p" type="button" id="btn-copy"><i class="ri-file-copy-line"></i> Copiar</button></div>
                <div class="status"><span class="spin"></span> Aguardando o pagamento... a ativacao e automatica.</div>
            </div>
            <?php } else if ($st === 'pending' && $order['method'] === 'card') { ?>
            <div id="box-pay">
                <h2>Pagamento em processamento</h2>
                <p class="mut">Assim que o Mercado Pago aprovar, o aparelho e ativado automaticamente.</p>
                <?php if ($order['pay_url'] !== '') { ?><a class="btn btn-p" href="<?php echo e($order['pay_url']); ?>"><i class="ri-bank-card-line"></i> Ir para o pagamento</a><?php } ?>
                <div class="status"><span class="spin"></span> Verificando...</div>
            </div>
            <?php } else if ($st !== 'approved') { ?>
            <div id="box-pay">
                <h2><?php echo $st === 'expired' ? 'Pix vencido' : 'Pagamento nao concluido'; ?></h2>
                <p class="mut">Nenhum valor foi cobrado. Gere um novo pagamento para ativar o aparelho.</p>
                <?php if ((float)$order['amount'] > 0 && $mpReady && $payPix) { ?>
                <form method="post" action="ativar.php?action=novopix&amp;pedido=<?php echo e($order['token']); ?>"><?php echo lf_csrf_field(); ?><button class="btn btn-p"><i class="ri-qr-code-line"></i> Gerar novo Pix</button></form>
                <?php } ?>
                <p><a href="ativar.php" class="mut small">Fazer um novo pedido</a></p>
            </div>
            <?php } ?>

            <div class="kv" style="text-align:left">
                <span>MAC</span><b class="mono"><?php echo e($order['mac']); ?></b>
                <span>Plano</span><b><?php echo e($order['plan_name']); ?> &middot; <?php echo (int)$order['days'] > 0 ? (int)$order['days'] . ' dias' : 'sem vencimento'; ?></b>
                <span>Servidor</span><b><?php echo e(lp_mask_host($order['dns_base'])); ?></b>
                <span>Usuario</span><b><?php echo e($order['m3u_user']); ?></b>
                <span>Pedido</span><b>#<?php echo (int)$order['id']; ?></b>
            </div>
            <p class="small mut" style="margin:0">Guarde este link para acompanhar o pedido.</p>
        </div>
    </section>
    <script>
    (function(){
        var st = <?php echo json_encode($st); ?>, tk = <?php echo json_encode($order['token']); ?>;
        var b = document.getElementById('btn-copy');
        if (b) b.onclick = function(){ var i = document.getElementById('pixcode'); i.select(); i.setSelectionRange(0, 99999);
            (navigator.clipboard ? navigator.clipboard.writeText(i.value) : Promise.reject()).catch(function(){ document.execCommand('copy'); });
            b.innerHTML = '<i class="ri-check-line"></i> Copiado'; setTimeout(function(){ b.innerHTML = '<i class="ri-file-copy-line"></i> Copiar'; }, 2000); };
        if (st !== 'pending') return;
        var n = 0;
        function poll(){
            n++;
            fetch('ativar.php?action=status&pedido=' + tk, {cache:'no-store'}).then(function(r){ return r.json(); }).then(function(j){
                if (j && j.ok && j.status === 'approved') {
                    var p = document.getElementById('box-pay'); if (p) p.style.display = 'none';
                    document.getElementById('box-ok').style.display = '';
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
        <h1>Ativacao temporariamente indisponivel</h1>
        <p>Tente novamente mais tarde<?php if ($wa) { ?> ou fale com o suporte pelo WhatsApp<?php } ?>.</p>
        <?php if ($wa) { ?><a class="btn btn-p" href="https://wa.me/<?php echo e($wa); ?>" target="_blank" rel="noopener"><i class="ri-whatsapp-line"></i> Falar com o suporte</a><?php } ?>
    </section>

<?php } else {
    // ===================== LANDING =====================
    $selPlan = (int)$o('plan', $plans ? (int)$plans[0]['id'] : 0); ?>
    <section class="hero">
        <h1><?php echo e($cfg['title']); ?></h1>
        <p><?php echo e($cfg['subtitle']); ?></p>
        <a class="btn btn-p" href="#ativar"><i class="ri-flashlight-line"></i> Ativar agora</a>
    </section>

    <div class="steps">
        <div class="step"><i class="ri-tv-2-line"></i><b>1. Abra o app</b><span>O MAC aparece na tela de login do aplicativo.</span></div>
        <div class="step"><i class="ri-links-line"></i><b>2. Cole seu link M3U</b><span>O link da sua lista (com usuario e senha).</span></div>
        <div class="step"><i class="ri-qr-code-line"></i><b>3. Pague com Pix ou cartao</b><span>Pagamento seguro pelo Mercado Pago.</span></div>
        <div class="step"><i class="ri-checkbox-circle-line"></i><b>4. Pronto!</b><span>Reabra o app: ele entra sozinho.</span></div>
    </div>

    <div class="grid" id="ativar">
        <div class="card">
            <h2>Ativar aparelho</h2>
            <p class="mut small" style="margin-top:0">Preencha os dados abaixo. A ativacao e automatica apos a confirmacao do pagamento.</p>
            <?php if ($err) { ?><div class="alert alert-err"><i class="ri-error-warning-line"></i><div><?php echo e($err); ?></div></div><?php } ?>
            <?php if (!$plans) { ?>
                <div class="alert alert-info"><i class="ri-information-line"></i><div>Nenhum plano disponivel no momento.</div></div>
            <?php } else { ?>
            <form method="post" action="ativar.php?action=criar" id="f" autocomplete="off">
                <?php echo lf_csrf_field(); ?>
                <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">

                <label class="f" for="mac">MAC do aparelho</label>
                <input class="i mono" id="mac" name="mac" maxlength="17" placeholder="A1:B2:C3:D4:E5:F6" required value="<?php echo e($o('mac', $prefillMac)); ?>">
                <div class="small mut">Aparece na tela de login do app. Pode digitar com ou sem os dois-pontos.</div>

                <label class="f" for="m3u">Link M3U</label>
                <textarea class="i mono" id="m3u" name="m3u" required placeholder="http://servidor.com:8080/get.php?username=USUARIO&password=SENHA&type=m3u_plus"><?php echo e($o('m3u')); ?></textarea>
                <div class="small mut" id="m3u-info">Seu fornecedor de lista envia esse link. Usamos o servidor, o usuario e a senha dele.</div>

                <label class="f">Plano</label>
                <div class="plans">
                    <?php foreach ($plans as $p) { ?>
                    <label class="plan">
                        <input type="radio" name="plan" value="<?php echo (int)$p['id']; ?>" data-price="<?php echo (float)$p['price']; ?>" <?php if ((int)$p['id'] === $selPlan) echo 'checked'; ?>>
                        <b><?php echo e($p['name']); ?></b>
                        <span class="mut small"><?php echo (int)$p['days'] > 1 ? (int)$p['days'] . ' dias' : ((int)$p['days'] === 1 ? '1 dia' : 'Vitalicio'); ?></span>
                        <div class="pr"><?php echo (float)$p['price'] > 0 ? lp_money($p['price']) : 'Gratis'; ?></div>
                    </label>
                    <?php } ?>
                </div>

                <div id="paid-fields">
                    <label class="f" for="email">Seu e-mail</label>
                    <input class="i" type="email" id="email" name="email" maxlength="150" placeholder="voce@email.com" value="<?php echo e($o('email')); ?>">
                    <div class="small mut">O comprovante do Mercado Pago chega nele.</div>

                    <?php if ($payPix && $payCard) { ?>
                    <label class="f">Forma de pagamento</label>
                    <div class="pay">
                        <label><input type="radio" name="method" value="pix" <?php if ($o('method', 'pix') === 'pix') echo 'checked'; ?>> <i class="ri-qr-code-line"></i> Pix (na hora)</label>
                        <label><input type="radio" name="method" value="card" <?php if ($o('method') === 'card') echo 'checked'; ?>> <i class="ri-bank-card-line"></i> Cartao</label>
                    </div>
                    <?php } else { ?><input type="hidden" name="method" value="<?php echo $payCard ? 'card' : 'pix'; ?>"><?php } ?>
                </div>

                <button class="btn btn-p" type="submit" id="btn-go" style="width:100%;margin-top:20px"><i class="ri-lock-line"></i> <span>Continuar para o pagamento</span></button>
                <p class="small mut" style="text-align:center;margin-bottom:0"><i class="ri-shield-check-line"></i> Pagamento processado pelo Mercado Pago.<?php if (!$mpReady) { ?> <b>(pagamento ainda nao configurado)</b><?php } ?></p>
            </form>
            <?php } ?>
        </div>

        <div>
            <div class="card" id="consulta">
                <h2>Consultar ativacao</h2>
                <p class="mut small" style="margin-top:0">Veja se o aparelho esta ativo e ate quando.</p>
                <div class="copy"><input class="i mono" id="q-mac" maxlength="17" placeholder="A1:B2:C3:D4:E5:F6"><button class="btn btn-p" type="button" id="q-btn"><i class="ri-search-line"></i></button></div>
                <div id="q-res" style="margin-top:12px"></div>
            </div>

            <div class="card faq" style="margin-top:20px">
                <h2>Duvidas</h2>
                <details><summary>Preciso instalar outro app?</summary><p>Nao. Use o mesmo aplicativo. Depois do pagamento, feche e abra o app: ele entra sozinho.</p></details>
                <details><summary>Onde encontro o MAC?</summary><p>Na tela de login do aplicativo, normalmente na parte de baixo.</p></details>
                <details><summary>Que link M3U eu uso?</summary><p>O link completo que o seu fornecedor enviou, no formato <span class="mono">http://servidor:porta/get.php?username=...&amp;password=...</span></p></details>
                <details><summary>Como renovo?</summary><p>Faca uma nova ativacao com o mesmo MAC. Os dias novos sao somados ao que ainda resta.</p></details>
                <details><summary>Troquei de lista. E agora?</summary><p>Faca a ativacao de novo com o link novo. O app passa a usar a lista nova.</p></details>
            </div>
        </div>
    </div>
    <script>
    (function(){
        var f = document.getElementById('f');
        function fmtMac(v){ var h = v.replace(/[^0-9a-fA-F]/g,'').toUpperCase().slice(0,12); return h.replace(/(.{2})(?=.)/g,'$1:'); }
        ['mac','q-mac'].forEach(function(id){ var el = document.getElementById(id); if (el) el.addEventListener('input', function(){ var p = el.selectionStart === el.value.length; el.value = fmtMac(el.value); if (p) el.selectionStart = el.selectionEnd = el.value.length; }); });
        if (f) {
            var paid = document.getElementById('paid-fields'), go = document.getElementById('btn-go');
            function upd(){ var c = f.querySelector('input[name=plan]:checked'); var free = c && parseFloat(c.getAttribute('data-price')) <= 0;
                paid.style.display = free ? 'none' : ''; document.getElementById('email').required = !free;
                go.querySelector('span').textContent = free ? 'Ativar teste gratis' : 'Continuar para o pagamento'; }
            f.addEventListener('change', upd); upd();
            var m3u = document.getElementById('m3u'), info = document.getElementById('m3u-info'), def = info.innerHTML;
            m3u.addEventListener('input', function(){
                try { var v = m3u.value.trim(); if (!/^https?:\/\//i.test(v)) v = 'http://' + v; var u = new URL(v);
                    var us = u.searchParams.get('username'), pw = u.searchParams.get('password');
                    if (!us) { var p = u.pathname.split('/').filter(Boolean); if (['live','movie','series'].indexOf((p[0]||'').toLowerCase()) >= 0) p.shift(); if (p.length >= 2) { us = p[0]; pw = p[1]; } }
                    info.innerHTML = us && pw ? '<i class="ri-check-line" style="color:var(--ok)"></i> Servidor <b>' + u.host.replace(/</g,'') + '</b> &middot; usuario <b>' + us.replace(/</g,'&lt;') + '</b>' : def;
                } catch (e) { info.innerHTML = def; }
            });
            m3u.dispatchEvent(new Event('input'));
            f.addEventListener('submit', function(){ go.disabled = true; go.querySelector('span').textContent = 'Aguarde...'; });
        }
        var qb = document.getElementById('q-btn');
        if (qb) qb.onclick = function(){
            var r = document.getElementById('q-res'), m = document.getElementById('q-mac').value;
            r.innerHTML = '<div class="status"><span class="spin"></span></div>';
            fetch('ativar.php?action=consulta&mac=' + encodeURIComponent(m), {cache:'no-store'}).then(function(x){ return x.json(); }).then(function(j){
                var cls = j.active ? 'alert-ok' : (j.ok ? 'alert-info' : 'alert-err');
                var d = document.createElement('div'); d.className = 'alert ' + cls; d.textContent = j.msg || 'Erro.'; r.innerHTML = ''; r.appendChild(d);
                if (j.ok && !j.active) { var mi = document.getElementById('mac'); if (mi && !mi.value) mi.value = fmtMac(m); }
            }).catch(function(){ r.innerHTML = '<div class="alert alert-err">Falha na consulta.</div>'; });
        };
    })();
    </script>
<?php } ?>

    <footer>
        &copy; <?php echo date('Y'); ?> <?php echo e(APP_NAME); ?> &middot; <a href="terms.php">Termos</a> &middot; <a href="privacy_policy.php">Privacidade</a>
    </footer>
</div>
<?php if ($wa) { ?><a class="wa" href="https://wa.me/<?php echo e($wa); ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="ri-whatsapp-line"></i></a><?php } ?>
</body>
</html>
