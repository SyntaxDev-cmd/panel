<?php
// ============================================================
//  AREA DO REVENDEDOR (na landing page)
//  Login com o mesmo usuario e senha do painel. Ativa e gerencia aparelhos
//  pelo MAC seguindo as permissoes do painel (arvore, limites, nada de tomar
//  MAC de outra revenda). O painel completo continua em dashboard.php.
// ============================================================
include("includes/db_helper.php");
require_once("includes/landing.php");
require_once("includes/policies.php");
require_once("includes/lp_layout.php");
require_once("includes/reseller.php");

$cfg = lp_cfg();
if ($cfg['enabled'] !== '1' || $cfg['show_reseller'] !== '1') { header('Location: ativar.php'); exit; }
$ip = lf_client_ip();
$now = time();
lp_rl_gc();

function rv_flash($msg, $type = 'ok') { $_SESSION['rv_flash'] = array($msg, $type); }
function rv_go($q = '') { header('Location: revenda.php' . $q); exit; }
function rv_str($k, $max) { return isset($_POST[$k]) && is_string($_POST[$k]) ? substr(trim($_POST[$k]), 0, $max) : ''; }

$me = lf_me();
if ($me && (int)$me['status'] !== 1) { $me = null; unset($_SESSION['id'], $_SESSION['admin_name'], $_SESSION['admin_type']); }
$action = isset($_REQUEST['action']) ? (string)$_REQUEST['action'] : '';

// ---------- entrar (mesmas regras do login do painel) ----------
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = rv_str('user', 190); $p = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    if (!lf_csrf_ok()) { rv_flash('Sessao expirada. Tente de novo.', 'err'); rv_go(); }
    if (lp_rl_count('rv:' . $ip, 600) >= 8) { rv_flash('Muitas tentativas. Aguarde alguns minutos.', 'err'); rv_go(); }
    $row = $u !== '' ? db_row("SELECT * FROM tbl_admin WHERE username = ? OR email = ? LIMIT 1", array($u, $u)) : null;
    if (!$row || !lf_password_verify($p, $row['password'])) { lp_rl_hit('rv:' . $ip); usleep(500000); rv_flash('Usuario ou senha incorretos.', 'err'); rv_go(); }
    if ((int)$row['status'] !== 1) { rv_flash('Sua conta esta desativada. Fale com o seu fornecedor.', 'err'); rv_go(); }
    if (lf_password_is_legacy($row['password'])) db_query("UPDATE tbl_admin SET password = ? WHERE id = ?", array(lf_password_hash($p), (int)$row['id']));
    session_regenerate_id(true);
    lp_rl_clear('rv:' . $ip);
    $_SESSION['id'] = (int)$row['id'];
    $_SESSION['admin_name'] = $row['username'];
    $_SESSION['admin_type'] = (int)$row['admin_type'];
    rv_go();
}
if ($action === 'logout') { unset($_SESSION['id'], $_SESSION['admin_name'], $_SESSION['admin_type']); rv_go(); }

// ---------- acoes ----------
if ($me && $_SERVER['REQUEST_METHOD'] === 'POST' && $action !== '') {
    if (!lf_csrf_ok()) { rv_flash('Sessao expirada. Tente de novo.', 'err'); rv_go(); }

    if ($action === 'activate') {
        $mac = lf_norm_mac(rv_str('mac', 40));
        $_SESSION['rv_old'] = array('mac' => rv_str('mac', 40), 'note' => rv_str('note', 120), 'days' => rv_str('days', 5), 'src' => rv_str('src', 10));
        if ($mac === '') { rv_flash('MAC invalido. Ex.: A1:B2:C3:D4:E5:F6', 'err'); rv_go('#ativar'); }
        $src = rv_str('src', 10);
        if ($src === 'file') { $r = lp_parse_m3u_file('m3u_file'); if (!$r['ok']) { rv_flash($r['error'], 'err'); rv_go('#ativar'); } $list = $r['list']; }
        else if ($src === 'manual') { $list = lp_parse_manual(rv_str('server', 255), rv_str('user', 120), rv_str('pass', 190)); }
        else { $list = lp_parse_m3u(rv_str('m3u', 800)); }
        if (!$list) { rv_flash($src === 'manual' ? 'Confira o servidor, o usuario e a senha.' : 'Link invalido. Use o link completo da lista com usuario e senha.', 'err'); rv_go('#ativar'); }
        if ($cfg['check_m3u'] === '1' && lp_check_m3u($list) === false) { rv_flash('O servidor recusou este usuario/senha (ou a conta esta vencida).', 'err'); rv_go('#ativar'); }
        $out = lp_norm_output(rv_str('output', 10));
        if ($out === '' && $list['output'] !== '') $out = $list['output'];
        $days = (int)rv_str('days', 5);
        $expires = 0;
        if ($days > 0) {
            $cur = (int)db_val("SELECT act_expires FROM tbl_devices WHERE mac = ? AND act_user <> '' AND " . lf_scope_sql('owner_id') . " ORDER BY act_updated DESC LIMIT 1", array($mac));
            $expires = max($now, $cur) + min(3650, $days) * 86400;      // ativo: soma ao que resta
        }
        $res = rs_activate($me, $mac, $list, $out, $expires, rv_str('note', 120));
        if (!$res['ok']) { rv_flash($res['error'], 'err'); rv_go('#ativar'); }
        unset($_SESSION['rv_old']);
        rv_flash(($res['renewed'] ? 'Aparelho atualizado: ' : 'Aparelho ativado: ') . $mac . ($expires ? ' ate ' . date('d/m/Y', $expires) : ' sem vencimento') . '. Reabra o app.');
        rv_go('?q=' . urlencode($mac) . '#aparelhos');
    }

    // acoes rapidas na lista de aparelhos
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $d = $id ? db_row("SELECT * FROM tbl_devices WHERE id = ? AND act_user <> ''", array($id)) : null;
    if (!$d || !lf_in_scope($d['owner_id'])) { rv_flash('Aparelho nao encontrado.', 'err'); rv_go('#aparelhos'); }
    if ($action === 'renew') {
        $days = max(1, min(365, (int)(isset($_POST['days']) ? $_POST['days'] : 30)));
        if ((int)$d['act_expires'] === 0) { rv_flash('Este aparelho nao tem vencimento.', 'err'); rv_go('#aparelhos'); }
        $exp = max($now, (int)$d['act_expires']) + $days * 86400;
        db_query("UPDATE tbl_devices SET act_expires = ? WHERE id = ?", array($exp, $id));
        rv_flash($d['mac'] . ' renovado ate ' . date('d/m/Y', $exp) . '.');
    } else if ($action === 'block' || $action === 'unblock') {
        db_query("UPDATE tbl_devices SET status = ? WHERE id = ?", array($action === 'unblock' ? 1 : 0, $id));
        rv_flash($d['mac'] . ($action === 'unblock' ? ' liberado.' : ' bloqueado.'));
    } else if ($action === 'remove') {
        db_query("UPDATE tbl_devices SET act_user = '', act_pass = '', act_dns_id = 0, act_expires = 0, act_source = '' WHERE id = ?", array($id));
        rv_flash('Ativacao de ' . $d['mac'] . ' removida.');
    }
    rv_go('#aparelhos');
}

// ---------- tela ----------
$flash = isset($_SESSION['rv_flash']) ? $_SESSION['rv_flash'] : null; unset($_SESSION['rv_flash']);
$old = isset($_SESSION['rv_old']) ? $_SESSION['rv_old'] : array(); unset($_SESSION['rv_old']);
$ov = function ($k, $d = '') use ($old) { return isset($old[$k]) ? $old[$k] : $d; };
$allowManual = $cfg['allow_manual'] === '1';

lp_layout_start('Area do revendedor', 'revenda');

if (!$me) { ?>
    <section class="hero" style="padding-bottom:18px">
        <h1 style="font-size:clamp(26px,5vw,34px)">Area do revendedor</h1>
        <p>Ative e gerencie os aparelhos dos seus clientes pelo MAC.</p>
    </section>
    <section class="card" style="max-width:440px;margin:0 auto">
        <?php if ($flash) { ?><div class="alert <?php echo $flash[1] === 'err' ? 'a-err' : 'a-ok'; ?>"><?php echo e($flash[0]); ?></div><?php } ?>
        <form method="post" action="revenda.php?action=login">
            <?php echo lf_csrf_field(); ?>
            <label class="lbl" for="u">Usuario ou e-mail</label>
            <input class="in" id="u" name="user" maxlength="190" required autocomplete="username" autofocus>
            <label class="lbl" for="p">Senha</label>
            <input class="in" id="p" type="password" name="password" maxlength="200" required autocomplete="current-password">
            <button class="btn btn-p" type="submit" style="margin-top:18px">Entrar <i class="ri-arrow-right-line"></i></button>
        </form>
        <p class="secure" style="margin-top:14px">Use o mesmo acesso do painel. Nao tem acesso? Fale com o seu fornecedor.</p>
    </section>
<?php } else {
    $usage = rs_usage($me);
    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $where = lf_scope_sql('v.owner_id') . " AND v.act_user <> ''"; $params = array();
    if ($q !== '') { $like = '%' . $q . '%'; $mk = lf_norm_mac($q); $where .= " AND (v.mac LIKE ? OR v.mac = ? OR v.note LIKE ? OR v.act_user LIKE ?)"; array_push($params, $like, $mk, $like, $like); }
    $rows = db_all("SELECT v.*, d.dns_base, a.username AS owner_name FROM tbl_devices v LEFT JOIN tbl_dns d ON d.id = v.act_dns_id LEFT JOIN tbl_admin a ON a.id = v.owner_id
                    WHERE $where ORDER BY (v.act_expires = 0) ASC, v.act_expires ASC, v.act_updated DESC LIMIT 50", $params);
    $sum = db_row("SELECT SUM(CASE WHEN status = 1 AND (act_expires = 0 OR act_expires > $now) THEN 1 ELSE 0 END) AS active,
                          SUM(CASE WHEN status = 1 AND act_expires > $now AND act_expires <= " . ($now + 7 * 86400) . " THEN 1 ELSE 0 END) AS soon,
                          SUM(CASE WHEN act_expires > 0 AND act_expires <= $now THEN 1 ELSE 0 END) AS expired
                   FROM tbl_devices WHERE " . lf_scope_sql('owner_id') . " AND act_user <> ''");
    $src = $ov('src', 'link'); ?>

    <section class="card" style="margin-top:8px">
        <div class="head">
            <div style="min-width:0">
                <div class="muted" style="font-size:12.5px">Revendedor</div>
                <div style="font-size:18px;font-weight:700"><?php echo e($me['username']); ?> <span class="tag c" style="vertical-align:middle"><?php echo lf_role_name($me['admin_type']); ?></span></div>
                <div class="muted" style="font-size:13px;margin-top:4px"><?php echo $usage['max'] > 0 ? 'Aparelhos: <b style="color:var(--txt)">' . $usage['used'] . ' de ' . $usage['max'] . '</b>' : 'Aparelhos: <b style="color:var(--txt)">' . $usage['used'] . '</b> &middot; sem limite'; ?></div>
            </div>
            <div class="acts" style="margin:0">
                <a class="btn btn-o btn-s" href="dashboard.php"><i class="ri-dashboard-3-line"></i> Painel completo</a>
                <a class="btn btn-o btn-s" href="revenda.php?action=logout"><i class="ri-logout-box-r-line"></i> Sair</a>
            </div>
        </div>
        <div class="steps" style="margin-top:16px">
            <div class="step"><b style="font-size:20px"><?php echo (int)$sum['active']; ?></b>ativos</div>
            <div class="step"><b style="font-size:20px;color:<?php echo (int)$sum['soon'] ? 'var(--c)' : 'var(--txt)'; ?>"><?php echo (int)$sum['soon']; ?></b>vencem em 7 dias</div>
            <div class="step"><b style="font-size:20px"><?php echo (int)$sum['expired']; ?></b>vencidos</div>
        </div>
    </section>

    <?php if ($flash) { ?><div class="alert <?php echo $flash[1] === 'err' ? 'a-err' : 'a-ok'; ?>" style="margin:14px 0 0"><i class="<?php echo $flash[1] === 'err' ? 'ri-error-warning-line' : 'ri-check-line'; ?>"></i><div><?php echo e($flash[0]); ?></div></div><?php } ?>

    <section class="card sec" id="ativar">
        <h2>Ativar aparelho</h2>
        <p class="muted" style="font-size:13.5px;margin:0 0 4px">Se o MAC ja estiver ativo com voce, a lista e trocada e os dias sao somados.</p>
        <form method="post" action="revenda.php?action=activate" enctype="multipart/form-data" autocomplete="off" id="fa">
            <?php echo lf_csrf_field(); ?>
            <label class="lbl" for="mac">MAC do aparelho <small>aparece na tela do app</small></label>
            <input class="in mono" id="mac" name="mac" maxlength="17" placeholder="A1:B2:C3:D4:E5:F6" required value="<?php echo e($ov('mac')); ?>">

            <div class="lbl">Lista do cliente</div>
            <div class="seg">
                <label><input type="radio" name="src" value="link" <?php if ($src === 'link') echo 'checked'; ?>> Link</label>
                <label><input type="radio" name="src" value="file" <?php if ($src === 'file') echo 'checked'; ?>> Arquivo</label>
                <?php if ($allowManual) { ?><label><input type="radio" name="src" value="manual" <?php if ($src === 'manual') echo 'checked'; ?>> Servidor</label><?php } ?>
            </div>
            <div data-src="link" style="margin-top:10px"><textarea class="in mono" name="m3u" placeholder="http://servidor.com:8080/get.php?username=USUARIO&password=SENHA&type=m3u_plus"></textarea></div>
            <div data-src="file" hidden style="margin-top:10px"><input class="in" type="file" name="m3u_file" accept=".m3u,.m3u8,.txt"><div class="hint">Arquivo .m3u / .m3u8 da lista (formato Xtream, ate 15 MB).</div></div>
            <?php if ($allowManual) { ?>
            <div data-src="manual" hidden style="margin-top:10px">
                <input class="in mono" name="server" placeholder="http://servidor.com:8080" style="margin-bottom:8px">
                <div class="row"><input class="in" name="user" placeholder="Usuario"><input class="in" name="pass" placeholder="Senha"></div>
            </div>
            <?php } ?>

            <div class="row" style="margin-top:0;flex-wrap:wrap">
                <div style="flex:1;min-width:150px">
                    <div class="lbl">Validade</div>
                    <select class="in" name="days">
                        <?php foreach (array(1 => '1 dia (teste)', 7 => '7 dias', 30 => '30 dias', 60 => '60 dias', 90 => '90 dias', 180 => '180 dias', 365 => '1 ano', 0 => 'Sem vencimento') as $k => $l) { ?><option value="<?php echo $k; ?>" <?php if ((string)$k === $ov('days', '30')) echo 'selected'; ?>><?php echo $l; ?></option><?php } ?>
                    </select>
                </div>
                <div style="flex:1;min-width:150px">
                    <div class="lbl">Formato</div>
                    <select class="in" name="output"><option value="">Automatico</option><option value="ts">MPEG-TS</option><option value="m3u8">HLS (m3u8)</option></select>
                </div>
            </div>
            <label class="lbl" for="note">Cliente <small>opcional</small></label>
            <input class="in" id="note" name="note" maxlength="120" placeholder="Nome ou telefone do cliente" value="<?php echo e($ov('note')); ?>">
            <button class="btn btn-p" type="submit" style="margin-top:18px"><i class="ri-flashlight-line"></i> Ativar aparelho</button>
        </form>
    </section>

    <section class="card sec" id="aparelhos">
        <div class="head" style="margin-bottom:12px">
            <h2 style="margin:0">Meus aparelhos</h2>
            <form method="get" action="revenda.php#aparelhos" class="row" style="flex:1;max-width:280px"><input class="in" name="q" placeholder="Buscar MAC ou cliente" value="<?php echo e($q); ?>" style="padding:9px 12px"><button class="btn btn-o btn-s" type="submit" style="flex:none"><i class="ri-search-line"></i></button></form>
        </div>
        <?php if (!$rows) { ?>
            <div class="muted" style="text-align:center;padding:18px 0;font-size:14px"><?php echo $q !== '' ? 'Nenhum aparelho encontrado.' : 'Nenhum aparelho ativado ainda.'; ?></div>
        <?php } ?>
        <?php foreach ($rows as $r) {
            $exp = (int)$r['act_expires']; $blocked = (int)$r['status'] === 0; $expired = $exp > 0 && $exp <= $now;
            $tag = $blocked ? '<span class="tag err">BLOQUEADO</span>' : ($expired ? '<span class="tag err">VENCIDO</span>' : ($exp > 0 && $exp <= $now + 7 * 86400 ? '<span class="tag c">VENCE EM ' . max(1, (int)ceil(($exp - $now) / 86400)) . 'D</span>' : '<span class="tag ok">ATIVO</span>'));
            $online = !$blocked && (int)$r['last_seen'] >= $now - LF_ONLINE_SECONDS; ?>
        <div class="list">
            <h3><span class="mono"><?php echo e($r['mac']); ?></span> <?php echo $tag; ?><?php if ($online) { ?> <span class="tag ok">ONLINE</span><?php } ?><?php if ($r['act_source'] === LP_SOURCE) { ?> <span class="tag">SITE</span><?php } ?></h3>
            <div class="meta">
                <?php if ($r['note'] !== '') { ?><span><i class="ri-user-line"></i> <?php echo e($r['note']); ?></span><?php } ?>
                <span><i class="ri-server-line"></i> <?php echo e($r['dns_base'] ? parse_url(html_entity_decode($r['dns_base'], ENT_QUOTES, 'UTF-8'), PHP_URL_HOST) : '-'); ?> &middot; <?php echo e($r['act_user']); ?></span>
                <span><i class="ri-calendar-line"></i> <?php echo $exp > 0 ? date('d/m/Y', $exp) : 'sem vencimento'; ?></span>
                <?php if ((int)$r['owner_id'] !== (int)$me['id']) { ?><span><i class="ri-team-line"></i> <?php echo e($r['owner_name']); ?></span><?php } ?>
            </div>
            <div class="acts">
                <?php if ($exp > 0) { ?><form method="post" action="revenda.php?action=renew" style="display:inline"><?php echo lf_csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>"><input type="hidden" name="days" value="30"><button class="btn btn-c btn-s"><i class="ri-calendar-check-line"></i> +30 dias</button></form><?php } ?>
                <button class="btn btn-o btn-s" type="button" data-fill="<?php echo e($r['mac']); ?>" data-note="<?php echo e($r['note']); ?>"><i class="ri-repeat-line"></i> Trocar lista</button>
                <form method="post" action="revenda.php?action=<?php echo $blocked ? 'unblock' : 'block'; ?>" style="display:inline"><?php echo lf_csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>"><button class="btn btn-o btn-s"><i class="<?php echo $blocked ? 'ri-lock-unlock-line' : 'ri-forbid-line'; ?>"></i> <?php echo $blocked ? 'Liberar' : 'Bloquear'; ?></button></form>
                <form method="post" action="revenda.php?action=remove" style="display:inline" onsubmit="return confirm('Remover a ativacao de <?php echo e($r['mac']); ?>?');"><?php echo lf_csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>"><button class="btn btn-o btn-d btn-s"><i class="ri-delete-bin-line"></i> Remover</button></form>
            </div>
        </div>
        <?php } ?>
        <?php if (count($rows) >= 50) { ?><p class="secure" style="margin-top:12px">Mostrando 50. Use a busca ou <a href="manage_activations.php" style="color:var(--c)">veja todos no painel</a>.</p><?php } ?>
    </section>

    <script>
    (function(){
        var f = document.getElementById('fa'), mac = document.getElementById('mac');
        mac.addEventListener('input', function(){ var h = mac.value.replace(/[^0-9a-fA-F]/g,'').toUpperCase().slice(0,12); mac.value = h.replace(/(.{2})(?=.)/g,'$1:'); });
        function upd(){ var s = f.querySelector('input[name=src]:checked').value; f.querySelectorAll('[data-src]').forEach(function(x){ x.hidden = x.getAttribute('data-src') !== s; }); }
        f.addEventListener('change', upd); upd();
        document.querySelectorAll('[data-fill]').forEach(function(b){ b.addEventListener('click', function(){
            mac.value = b.getAttribute('data-fill'); document.getElementById('note').value = b.getAttribute('data-note');
            document.getElementById('ativar').scrollIntoView({behavior:'smooth'}); setTimeout(function(){ f.querySelector('textarea[name=m3u]').focus(); }, 400);
        }); });
    })();
    </script>
<?php }
lp_layout_end();
