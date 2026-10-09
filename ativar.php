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
require_once("includes/lp_layout.php");

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
        'mpass' => '',
    );
    $mpass = isset($_POST['mpass']) && is_string($_POST['mpass']) ? (string)$_POST['mpass'] : '';
    if ($mpass !== '' && (strlen($mpass) < 4 || strlen($mpass) > 64)) { $old['mpass'] = '1'; lp_back('A senha das listas precisa ter de 4 a 64 caracteres.', $old); }
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
    db_query("INSERT INTO tbl_lp_orders (token, mac, dns_base, m3u_user, m3u_pass, output, email, plan_id, plan_name, days, amount, status, method, ip, created_at, manage_pass)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?)",
        array($tk, $mac, $m['base'], $m['user'], $m['pass'], $output, $old['email'], (int)$plan['id'], $plan['name'], (int)$plan['days'], number_format((float)$plan['price'], 2, '.', ''), $free ? 'free' : $old['method'], $ip, time(), $mpass !== '' ? password_hash($mpass, PASSWORD_DEFAULT) : ''));
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
<?php lp_layout_start($order ? 'Pedido #' . (int)$order['id'] : $cfg['title'], 'ativar'); ?>

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
                <a class="btn btn-o btn-s" style="margin-top:14px" href="listas.php?pedido=<?php echo e($order['token']); ?>"><i class="ri-play-list-2-line"></i> Gerenciar minhas listas</a>
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

            <label class="sw"><input type="checkbox" id="want-pass" <?php if ($o('mpass') !== '') echo 'checked'; ?>> <span><b>Proteger minhas listas com senha</b> <span style="font-size:12.5px">(opcional)</span></span></label>
            <div id="pass-box" hidden style="margin-top:8px">
                <input class="in" type="password" name="mpass" minlength="4" maxlength="64" autocomplete="new-password" placeholder="Crie uma senha (minimo 4 caracteres)">
                <div class="hint">Usada em <b>Minhas listas</b> para trocar, ver ou remover listas deste aparelho.</div>
            </div>

            <label class="chk"><input type="checkbox" name="rights" value="1" required> <span>Declaro que a lista informada e minha ou que tenho direito legal de acessa-la, e concordo com os <a href="terms.php" target="_blank">Termos</a> e a <a href="privacy_policy.php" target="_blank">Privacidade</a>.</span></label>

            <button class="btn btn-p" type="submit" id="btn-go" style="margin-top:18px"><span><?php echo e($cfg['cta_text'] !== '' ? $cfg['cta_text'] : 'Ativar agora'); ?></span> <i class="ri-arrow-right-line"></i></button>
            <p class="secure"><i class="ri-lock-2-line"></i> Pagamento seguro pelo Mercado Pago<?php if (!$mpReady) { ?> &middot; <b>indisponivel</b><?php } ?></p>
        </form>
        <?php } ?>
    </section>

    <p style="text-align:center;margin:16px 0 0;font-size:13.5px" class="muted">Ja ativou? <a href="listas.php" style="color:var(--c);font-weight:600;text-decoration:none">Gerencie suas listas &rarr;</a></p>

    <?php if ($cfg['show_steps'] === '1') { ?>
    <div class="steps">
        <div class="step"><i class="ri-tv-2-line"></i><b>Abra o app</b>copie o MAC</div>
        <div class="step"><i class="ri-links-line"></i><b>Informe a lista</b>e escolha o plano</div>
        <div class="step"><i class="ri-checkbox-circle-line"></i><b>Pronto</b>reabra o app</div>
    </div>
    <?php } ?>


    <?php if ($cfg['show_faq'] === '1') { ?>
    <section class="card sec faq">
        <h2 style="margin-bottom:8px">Perguntas frequentes</h2>
        <details><summary>Voces vendem canais ou listas?</summary><p>Nao. Somos apenas um reprodutor de midia. Voce usa a sua propria lista, de um fornecedor da sua escolha.</p></details>
        <details><summary>Preciso instalar outro app?</summary><p>Nao. Depois da ativacao, feche e abra o mesmo aplicativo: ele entra sozinho.</p></details>
        <details><summary>Onde encontro o MAC?</summary><p>Na tela de login do aplicativo, normalmente na parte de baixo.</p></details>
        <details><summary>Quais formatos funcionam?</summary><p>Links M3U/M3U Plus, M3U8 (HLS), MPEG-TS (.ts) e Xtream Codes. Se a imagem travar, refaca a ativacao trocando o formato entre MPEG-TS e HLS.</p></details>
        <details><summary>Como troco ou adiciono listas?</summary><p>Em <b>Minhas listas</b>, entre com o MAC do aparelho. La voce adiciona, troca, ve ou remove listas e pode proteger tudo com senha.</p></details>
        <details><summary>Como renovo?</summary><p>Faca uma nova ativacao com o mesmo MAC. Os dias novos sao somados ao que ainda resta.</p></details>
    </section>
    <?php } ?>
    <script>
    (function(){
        var f = document.getElementById('f');
        function fmtMac(v){ var h = v.replace(/[^0-9a-fA-F]/g,'').toUpperCase().slice(0,12); return h.replace(/(.{2})(?=.)/g,'$1:'); }
        ['mac'].forEach(function(id){ var el = document.getElementById(id); if (el) el.addEventListener('input', function(){ var end = el.selectionStart === el.value.length; el.value = fmtMac(el.value); if (end) el.selectionStart = el.selectionEnd = el.value.length; }); });
        if (f) {
            var paid = document.getElementById('paid-fields'), go = document.getElementById('btn-go'), label = go.querySelector('span').textContent;
            function upd(){
                var c = f.querySelector('input[name=plan]:checked'), free = c && parseFloat(c.getAttribute('data-price')) <= 0;
                paid.hidden = !!free; go.querySelector('span').textContent = free ? 'Ativar teste gratis' : label;
                var wp = document.getElementById('want-pass'); document.getElementById('pass-box').hidden = !wp.checked;
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
    })();
    </script>
<?php } ?>

<?php lp_layout_end();
