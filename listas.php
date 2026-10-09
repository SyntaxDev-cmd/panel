<?php
// ============================================================
//  MINHAS LISTAS (area do cliente na landing page)
//  O cliente entra com o MAC do aparelho (+ senha, se criou uma) e:
//   - adiciona, edita, ve o link e remove listas (ate LP_MAX_LISTS)
//   - escolhe qual lista fica "em uso" no aparelho (o app entra sozinho com ela)
//   - cria/troca/remove a senha; marca listas como privadas (link oculto)
//  Nada muda nos apps: a lista em uso vai pela ativacao por MAC de sempre.
// ============================================================
include("includes/db_helper.php");
require_once("includes/landing.php");
require_once("includes/policies.php");
require_once("includes/lp_layout.php");

$cfg = lp_cfg();
$ip = lf_client_ip();
$now = time();
lp_rl_gc();
if ($cfg['enabled'] !== '1') { header('Location: ativar.php'); exit; }

function ls_flash($msg, $type = 'ok') { $_SESSION['ls_flash'] = array($msg, $type); }
function ls_go($anchor = '') { header('Location: listas.php' . ($anchor !== '' ? '#' . $anchor : '')); exit; }
function ls_json($a) { header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); echo json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
function ls_str($k, $max) { return isset($_POST[$k]) && is_string($_POST[$k]) ? substr(trim($_POST[$k]), 0, $max) : ''; }
function ls_mask($s) { $s = (string)$s; $n = strlen($s); return $n <= 2 ? str_repeat('•', max(3, $n)) : substr($s, 0, 1) . str_repeat('•', min(8, $n - 2)) . substr($s, -1); }

// ---------- sessao do cliente (30 min sem uso = sai) ----------
$sess = isset($_SESSION['lp_mgr']) && is_array($_SESSION['lp_mgr']) ? $_SESSION['lp_mgr'] : null;
if ($sess && ($now - (int)$sess['t'] > 1800 || lf_norm_mac($sess['mac']) === '')) { $sess = null; unset($_SESSION['lp_mgr']); }
if ($sess) { $_SESSION['lp_mgr']['t'] = $now; }
$mac = $sess ? lf_norm_mac($sess['mac']) : '';
$action = isset($_REQUEST['action']) ? (string)$_REQUEST['action'] : '';

// ---------- entrar pelo link do pedido (ate 24 h depois do pagamento) ----------
if (!$sess && isset($_GET['pedido'])) {
    $tk = preg_replace('/[^a-f0-9]/', '', (string)$_GET['pedido']);
    $o = $tk !== '' ? db_row("SELECT mac, status, paid_at FROM tbl_lp_orders WHERE token = ? LIMIT 1", array($tk)) : null;
    if ($o && $o['status'] === 'approved' && $now - (int)$o['paid_at'] < 86400) {
        session_regenerate_id(true);
        $_SESSION['lp_mgr'] = array('mac' => $o['mac'], 't' => $now);
        ls_go();
    }
    ls_flash('Este link expirou. Entre com o MAC do aparelho.', 'err'); ls_go();
}

// ---------- entrar ----------
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $m = lf_norm_mac(ls_str('mac', 40));
    $pw = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    $_SESSION['ls_mac'] = $m;
    if (!lf_csrf_ok()) { ls_flash('Sessao expirada. Tente de novo.', 'err'); ls_go(); }
    if (lp_rl_count('ip:' . $ip, 600) >= 20) { ls_flash('Muitas tentativas. Aguarde alguns minutos.', 'err'); ls_go(); }
    if ($m === '') { ls_flash('MAC invalido. Ex.: A1:B2:C3:D4:E5:F6', 'err'); ls_go(); }
    if (!lp_mac_known($m)) { lp_rl_hit('ip:' . $ip); usleep(400000); ls_flash('Aparelho nao encontrado. Abra o app no aparelho uma vez (com internet) ou faca a ativacao.', 'err'); ls_go(); }
    if (lp_access_hash($m) !== '') {
        if (lp_rl_count('mac:' . $m, 900) >= 5) { ls_flash('Muitas senhas erradas para este aparelho. Aguarde 15 minutos.', 'err'); ls_go(); }
        if (!lp_access_check($m, $pw)) {
            lp_rl_hit('ip:' . $ip); lp_rl_hit('mac:' . $m); usleep(500000);
            $_SESSION['ls_need_pass'] = 1;
            ls_flash($pw === '' ? 'Este aparelho tem senha. Digite a senha para entrar.' : 'Senha incorreta.', 'err'); ls_go();
        }
        lp_rl_clear('mac:' . $m);
    }
    unset($_SESSION['ls_need_pass']);
    session_regenerate_id(true);
    $_SESSION['lp_mgr'] = array('mac' => $m, 't' => $now);
    ls_go();
}
if ($action === 'logout') { unset($_SESSION['lp_mgr']); ls_go(); }

// ---------- acoes do cliente logado ----------
if ($sess && $_SERVER['REQUEST_METHOD'] === 'POST' && $action !== '') {
    $ajax = $action === 'reveal';
    if (!lf_csrf_ok()) { if ($ajax) ls_json(array('ok' => false, 'msg' => 'Sessao expirada. Recarregue a pagina.')); ls_flash('Sessao expirada. Tente de novo.', 'err'); ls_go(); }
    $hasPass = lp_access_hash($mac) !== '';
    $st = lp_mac_state($mac);
    $panel = $st['source'] === 'panel';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $list = $id ? db_row("SELECT * FROM tbl_lp_lists WHERE id = ? AND mac = ?", array($id, $mac)) : null;

    switch ($action) {
        // ver o link de uma lista privada: pede a senha de novo
        case 'reveal': {
            if (!$list) ls_json(array('ok' => false, 'msg' => 'Lista nao encontrada.'));
            if ((int)$list['is_private'] === 1) {
                if (lp_rl_count('mac:' . $mac, 900) >= 5) ls_json(array('ok' => false, 'msg' => 'Muitas tentativas. Aguarde 15 minutos.'));
                if (!lp_access_check($mac, isset($_POST['password']) ? $_POST['password'] : '')) { lp_rl_hit('mac:' . $mac); usleep(400000); ls_json(array('ok' => false, 'msg' => 'Senha incorreta.')); }
            }
            ls_json(array('ok' => true, 'url' => lp_m3u_url($list), 'user' => $list['m3u_user'], 'pass' => $list['m3u_pass']));
        }

        case 'add':
        case 'edit': {
            if ($panel) { ls_flash('Este aparelho e gerenciado pelo seu revendedor.', 'err'); ls_go(); }
            if ($action === 'edit' && !$list) { ls_flash('Lista nao encontrada.', 'err'); ls_go(); }
            if ($action === 'add' && (int)db_val("SELECT COUNT(*) FROM tbl_lp_lists WHERE mac = ?", array($mac)) >= LP_MAX_LISTS) { ls_flash('Limite de ' . LP_MAX_LISTS . ' listas por aparelho.', 'err'); ls_go(); }
            $name = ls_str('name', 60);
            $mode = in_array(ls_str('mode', 10), array('manual', 'file'), true) ? ls_str('mode', 10) : 'link';
            $hasNew = $mode === 'file' ? !empty($_FILES['m3u_file']['name']) : ($mode === 'manual' ? (ls_str('server', 255) !== '' || ls_str('user', 120) !== '') : ls_str('m3u', 800) !== '');
            $parsed = null;
            if ($hasNew || $action === 'add') {
                if ($mode === 'file') {
                    $fr = lp_parse_m3u_file('m3u_file');
                    if (!$fr['ok']) { ls_flash($fr['error'], 'err'); ls_go($action === 'edit' ? 'l' . $id : 'add'); }
                    $parsed = $fr['list'];
                } else {
                    $parsed = $mode === 'manual' ? lp_parse_manual(ls_str('server', 255), ls_str('user', 120), ls_str('pass', 190)) : lp_parse_m3u(ls_str('m3u', 800));
                }
                if (!$parsed) { ls_flash($mode === 'manual' ? 'Confira o servidor, o usuario e a senha da lista.' : 'Link invalido. Use o link completo com usuario e senha (M3U, M3U8/HLS ou TS).', 'err'); ls_go($action === 'edit' ? 'l' . $id : 'add'); }
                if ($cfg['check_m3u'] === '1' && lp_check_m3u($parsed) === false) { ls_flash('O servidor recusou este usuario/senha (ou a conta esta vencida).', 'err'); ls_go($action === 'edit' ? 'l' . $id : 'add'); }
            }
            $out = lp_norm_output(ls_str('output', 10));
            if ($out === '' && $parsed && $parsed['output'] !== '') $out = $parsed['output'];
            $priv = isset($_POST['private']) ? 1 : 0;
            if ($priv && !$hasPass) { ls_flash('Crie uma senha antes de deixar listas privadas.', 'err'); ls_go('conta'); }
            // tirar o "privada" de uma lista exige a senha
            if ($list && (int)$list['is_private'] === 1 && !$priv && !lp_access_check($mac, isset($_POST['password']) ? $_POST['password'] : '')) {
                lp_rl_hit('mac:' . $mac); ls_flash('Para deixar a lista visivel de novo, digite a senha.', 'err'); ls_go('l' . $id);
            }
            if ($action === 'add') {
                if ($name === '') $name = substr(lp_list_name_default($parsed['base']), 0, 60);
                db_query("INSERT INTO tbl_lp_lists (mac, name, dns_base, m3u_user, m3u_pass, output, is_private, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?)",
                    array($mac, $name, $parsed['base'], $parsed['user'], $parsed['pass'], $out, $priv, $now, $now));
                $newId = db_last_id();
                $first = (int)db_val("SELECT COUNT(*) FROM tbl_lp_lists WHERE mac = ?", array($mac)) === 1;
                if (isset($_POST['use_now']) || $first) {
                    $err = lp_list_apply($mac, $newId);
                    ls_flash($err === '' ? 'Lista adicionada e colocada no aparelho. Reabra o app.' : 'Lista salva. ' . $err, $err === '' ? 'ok' : 'err');
                } else {
                    ls_flash('Lista adicionada.');
                }
                ls_go('l' . $newId);
            }
            $sql = "UPDATE tbl_lp_lists SET name = ?, output = ?, is_private = ?, updated_at = ?"; $p = array($name !== '' ? $name : $list['name'], $out, $priv, $now);
            if ($parsed) { $sql .= ", dns_base = ?, m3u_user = ?, m3u_pass = ?"; array_push($p, $parsed['base'], $parsed['user'], $parsed['pass']); }
            $p[] = $id;
            db_query($sql . " WHERE id = ?", $p);
            $msg = 'Lista atualizada.';
            if ((int)$list['is_active'] === 1 && ($parsed || $out !== $list['output'])) {
                $err = lp_list_apply($mac, $id);
                $msg = $err === '' ? 'Lista atualizada no aparelho. Reabra o app.' : 'Lista salva. ' . $err;
            }
            ls_flash($msg); ls_go('l' . $id);
        }

        case 'use': {
            if (!$list) { ls_flash('Lista nao encontrada.', 'err'); ls_go(); }
            $err = lp_list_apply($mac, $id);
            ls_flash($err === '' ? '"' . $list['name'] . '" agora esta no aparelho. Feche e abra o app.' : $err, $err === '' ? 'ok' : 'err');
            ls_go('l' . $id);
        }

        case 'remove': {
            if (!$list) { ls_flash('Lista nao encontrada.', 'err'); ls_go(); }
            if ((int)$list['is_active'] === 1 && !$panel) { ls_flash('Esta lista esta em uso no aparelho. Coloque outra lista no aparelho antes de remover.', 'err'); ls_go('l' . $id); }
            db_query("DELETE FROM tbl_lp_lists WHERE id = ? AND mac = ?", array($id, $mac));
            ls_flash('Lista removida.'); ls_go();
        }

        case 'password': {
            $cur = isset($_POST['current']) && is_string($_POST['current']) ? $_POST['current'] : '';
            $new = isset($_POST['new']) && is_string($_POST['new']) ? $_POST['new'] : '';
            $rem = isset($_POST['remove_pass']);
            if ($hasPass) {
                if (lp_rl_count('mac:' . $mac, 900) >= 5) { ls_flash('Muitas tentativas. Aguarde 15 minutos.', 'err'); ls_go('conta'); }
                if (!lp_access_check($mac, $cur)) { lp_rl_hit('mac:' . $mac); ls_flash('Senha atual incorreta.', 'err'); ls_go('conta'); }
            }
            if ($rem) {
                if ((int)db_val("SELECT COUNT(*) FROM tbl_lp_lists WHERE mac = ? AND is_private = 1", array($mac)) > 0) { ls_flash('Deixe as listas privadas visiveis antes de remover a senha.', 'err'); ls_go('conta'); }
                lp_access_set($mac, ''); ls_flash('Senha removida. Qualquer pessoa com o MAC pode gerenciar as listas.'); ls_go('conta');
            }
            if (strlen($new) < 4 || strlen($new) > 64) { ls_flash('A nova senha precisa ter de 4 a 64 caracteres.', 'err'); ls_go('conta'); }
            if ($new !== (isset($_POST['confirm']) ? (string)$_POST['confirm'] : '')) { ls_flash('As senhas nao conferem.', 'err'); ls_go('conta'); }
            lp_access_set($mac, $new);
            ls_flash($hasPass ? 'Senha alterada.' : 'Senha criada. Agora ela sera pedida para entrar.'); ls_go('conta');
        }
    }
    ls_go();
}

// ---------- tela ----------
$flash = isset($_SESSION['ls_flash']) ? $_SESSION['ls_flash'] : null; unset($_SESSION['ls_flash']);
$lastMac = isset($_SESSION['ls_mac']) ? $_SESSION['ls_mac'] : '';
$needPass = !empty($_SESSION['ls_need_pass']);
if ($mac !== '') lp_lists_seed($mac);
$st = $mac !== '' ? lp_mac_state($mac) : null;
$lists = $mac !== '' ? lp_lists($mac) : array();
$hasPass = $mac !== '' && lp_access_hash($mac) !== '';
$panel = $st && $st['source'] === 'panel';
$allowManual = $cfg['allow_manual'] === '1';
$outLabel = function ($o) { return $o === 'm3u8' ? 'HLS' : ($o === 'ts' ? 'MPEG-TS' : 'Auto'); };

lp_layout_start('Minhas listas', 'listas');
?>
<?php if (!$mac) { ?>
    <section class="hero" style="padding-bottom:18px">
        <h1 style="font-size:clamp(26px,5vw,34px)">Minhas listas</h1>
        <p>Envie, troque ou remova listas usando so o MAC do aparelho.</p>
    </section>
    <section class="card" style="max-width:440px;margin:0 auto">
        <?php if ($flash) { ?><div class="alert <?php echo $flash[1] === 'err' ? 'a-err' : 'a-ok'; ?>"><?php echo e($flash[0]); ?></div><?php } ?>
        <form method="post" action="listas.php?action=login" autocomplete="off">
            <?php echo lf_csrf_field(); ?>
            <label class="lbl" for="mac">MAC do aparelho <small>aparece na tela do app</small></label>
            <input class="in mono" id="mac" name="mac" maxlength="17" placeholder="A1:B2:C3:D4:E5:F6" required autocapitalize="characters" value="<?php echo e($lastMac); ?>" <?php if (!$needPass) echo 'autofocus'; ?>>
            <label class="lbl" for="pw">Senha <small>somente se voce criou uma</small></label>
            <input class="in" id="pw" type="password" name="password" maxlength="64" autocomplete="current-password" placeholder="••••••" <?php if ($needPass) echo 'autofocus'; ?>>
            <button class="btn btn-p" type="submit" style="margin-top:18px">Entrar <i class="ri-arrow-right-line"></i></button>
        </form>
        <p class="secure" style="margin-top:14px">Ainda nao ativou? <a href="ativar.php" style="color:var(--c);font-weight:600;text-decoration:none">Ative seu aparelho</a></p>
    </section>
    <script>
    (function(){ var el = document.getElementById('mac'); el.addEventListener('input', function(){ var h = el.value.replace(/[^0-9a-fA-F]/g,'').toUpperCase().slice(0,12); el.value = h.replace(/(.{2})(?=.)/g,'$1:'); }); })();
    </script>

<?php } else {
    // status do aparelho
    if ($st['blocked']) { $tag = '<span class="tag err">BLOQUEADO</span>'; $line = 'Fale com o suporte.'; }
    else if ($panel) { $tag = '<span class="tag">REVENDEDOR</span>'; $line = 'Este aparelho e gerenciado pelo seu revendedor.'; }
    else if ($st['active']) { $tag = '<span class="tag ok">ATIVO</span>'; $line = $st['expires'] > 0 ? 'Valido ate ' . date('d/m/Y', $st['expires']) : 'Sem vencimento'; }
    else if ($st['expires'] > 0) { $tag = '<span class="tag err">VENCIDO</span>'; $line = 'Venceu em ' . date('d/m/Y', $st['expires']); }
    else { $tag = '<span class="tag">SEM ATIVACAO</span>'; $line = 'Ative o aparelho para usar as listas.'; } ?>

    <section class="card" style="margin-top:8px">
        <div class="head">
            <div style="min-width:0">
                <div class="muted" style="font-size:12.5px">Aparelho</div>
                <div class="mono" style="font-size:18px;font-weight:700"><?php echo e($mac); ?></div>
                <div style="margin-top:6px;display:flex;gap:8px;align-items:center;flex-wrap:wrap"><?php echo $tag; ?> <span class="muted" style="font-size:13px"><?php echo e($line); ?></span></div>
            </div>
            <div class="acts" style="margin:0">
                <?php if (!$panel && !$st['blocked']) { ?><a class="btn btn-c btn-s" href="ativar.php?mac=<?php echo urlencode($mac); ?>#ativar"><i class="ri-refresh-line"></i> <?php echo $st['active'] ? 'Renovar' : 'Ativar'; ?></a><?php } ?>
                <a class="btn btn-o btn-s" href="listas.php?action=logout"><i class="ri-logout-box-r-line"></i> Sair</a>
            </div>
        </div>
    </section>

    <?php if ($flash) { ?><div class="alert <?php echo $flash[1] === 'err' ? 'a-err' : 'a-ok'; ?>" style="margin:14px 0 0"><i class="<?php echo $flash[1] === 'err' ? 'ri-error-warning-line' : 'ri-check-line'; ?>"></i><div><?php echo e($flash[0]); ?></div></div><?php } ?>

    <?php if (!$hasPass && !$panel) { ?>
    <div class="alert a-info" style="margin:14px 0 0"><i class="ri-lock-unlock-line"></i><div>Sem senha, qualquer pessoa com o MAC pode ver e alterar suas listas. <a href="#conta" style="color:var(--c);font-weight:600">Criar senha</a></div></div>
    <?php } ?>

    <section class="card sec">
        <div class="head" style="margin-bottom:14px">
            <h2 style="margin:0">Suas listas <span class="muted" style="font-weight:500;font-size:13px"><?php echo count($lists); ?>/<?php echo LP_MAX_LISTS; ?></span></h2>
            <?php if (!$panel && count($lists) < LP_MAX_LISTS) { ?><button class="btn btn-o btn-s" type="button" data-open="add"><i class="ri-add-line"></i> Adicionar</button><?php } ?>
        </div>

        <?php if (!$panel) { ?>
        <form method="post" action="listas.php?action=add" id="add" class="drawer" hidden autocomplete="off" enctype="multipart/form-data" style="border-top:0;margin:0 0 14px;padding:0">
            <?php echo lf_csrf_field(); ?>
            <div class="list" style="background:var(--soft);border-color:transparent">
                <h3>Nova lista</h3>
                <input class="in" name="name" maxlength="60" placeholder="Nome (ex.: Lista da sala)" style="background:var(--card)">
                <div class="seg" style="margin-top:10px;background:var(--card)">
                    <label><input type="radio" name="mode" value="link" checked> Link</label>
                    <label><input type="radio" name="mode" value="file"> Arquivo</label>
                    <?php if ($allowManual) { ?><label><input type="radio" name="mode" value="manual"> Servidor</label><?php } ?>
                </div>
                <div data-mode="file" hidden style="margin-top:10px"><input class="in" type="file" name="m3u_file" accept=".m3u,.m3u8,.txt" style="background:var(--card)"><div class="hint">Arquivo .m3u / .m3u8 da lista (ate 15 MB).</div></div>
                <div data-mode="link" style="margin-top:10px"><textarea class="in mono" name="m3u" placeholder="http://servidor.com:8080/get.php?username=USUARIO&password=SENHA&type=m3u_plus" style="background:var(--card)"></textarea></div>
                <?php if ($allowManual) { ?>
                <div data-mode="manual" hidden style="margin-top:10px">
                    <input class="in mono" name="server" placeholder="http://servidor.com:8080" style="margin-bottom:8px;background:var(--card)">
                    <div class="row"><input class="in" name="user" placeholder="Usuario" style="background:var(--card)"><input class="in" name="pass" placeholder="Senha" style="background:var(--card)"></div>
                </div>
                <?php } ?>
                <div class="seg" style="margin-top:10px;background:var(--card)">
                    <label><input type="radio" name="output" value="" checked> Auto</label>
                    <label><input type="radio" name="output" value="ts"> MPEG-TS</label>
                    <label><input type="radio" name="output" value="m3u8"> HLS</label>
                </div>
                <label class="sw"><input type="checkbox" name="use_now" value="1" <?php if (!$lists) echo 'checked'; ?>> <span><b>Colocar no aparelho agora</b></span></label>
                <label class="sw"><input type="checkbox" name="private" value="1" <?php if (!$hasPass) echo 'disabled'; ?>> <span><b>Lista privada</b> &mdash; o link fica oculto e so aparece com a senha<?php if (!$hasPass) echo ' (crie uma senha para usar)'; ?></span></label>
                <div class="acts"><button class="btn btn-c btn-s" type="submit">Salvar lista</button><button class="btn btn-o btn-s" type="button" data-close="add">Cancelar</button></div>
            </div>
        </form>
        <?php } ?>

        <?php if (!$lists) { ?>
            <div class="muted" style="text-align:center;padding:18px 0;font-size:14px"><i class="ri-play-list-2-line" style="font-size:28px;display:block;margin-bottom:6px"></i><?php echo $panel ? 'As listas deste aparelho sao definidas pelo seu revendedor.' : 'Nenhuma lista ainda. Adicione a primeira.'; ?></div>
        <?php } ?>

        <?php foreach ($lists as $l) {
            $priv = (int)$l['is_private'] === 1; $on = (int)$l['is_active'] === 1;
            $host = parse_url($l['dns_base'], PHP_URL_HOST); ?>
        <div class="list <?php echo $on ? 'on' : ''; ?>" id="l<?php echo (int)$l['id']; ?>">
            <h3><?php echo e($l['name']); ?>
                <?php if ($on) { ?><span class="tag c">EM USO</span><?php } ?>
                <?php if ($priv) { ?><span class="tag"><i class="ri-lock-line"></i> PRIVADA</span><?php } ?>
            </h3>
            <div class="meta">
                <span><i class="ri-server-line"></i> <?php echo $priv ? e(ls_mask($host)) : e($host); ?></span>
                <span><i class="ri-user-line"></i> <?php echo $priv ? e(ls_mask($l['m3u_user'])) : e($l['m3u_user']); ?></span>
                <span><i class="ri-film-line"></i> <?php echo $outLabel($l['output']); ?></span>
            </div>

            <div class="linkbox" data-box="<?php echo (int)$l['id']; ?>" hidden><code></code><button class="btn btn-o btn-s" type="button" data-copy style="padding:5px 9px;flex:none"><i class="ri-file-copy-line"></i></button></div>
            <?php if ($priv) { ?>
            <form class="row" data-ask="<?php echo (int)$l['id']; ?>" hidden style="margin-top:10px"><input class="in" type="password" placeholder="Senha para ver o link" maxlength="64" autocomplete="current-password"><button class="btn btn-c btn-s" type="submit" style="flex:none">Ver</button></form>
            <?php } ?>

            <div class="acts">
                <?php if (!$on && !$panel) { ?>
                <form method="post" action="listas.php?action=use" style="display:inline"><?php echo lf_csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int)$l['id']; ?>"><button class="btn btn-c btn-s"><i class="ri-tv-2-line"></i> Usar no aparelho</button></form>
                <?php } ?>
                <button class="btn btn-o btn-s" type="button" data-reveal="<?php echo (int)$l['id']; ?>" data-private="<?php echo $priv ? 1 : 0; ?>"><i class="ri-eye-line"></i> Ver link</button>
                <?php if (!$panel) { ?>
                <button class="btn btn-o btn-s" type="button" data-open="e<?php echo (int)$l['id']; ?>"><i class="ri-pencil-line"></i> Editar</button>
                <?php if (!$on) { ?><form method="post" action="listas.php?action=remove" style="display:inline" onsubmit="return confirm('Remover a lista &quot;<?php echo e(addslashes($l['name'])); ?>&quot;?');"><?php echo lf_csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int)$l['id']; ?>"><button class="btn btn-o btn-d btn-s"><i class="ri-delete-bin-line"></i> Remover</button></form><?php } ?>
                <?php } ?>
            </div>

            <?php if (!$panel) { ?>
            <form method="post" action="listas.php?action=edit" class="drawer" id="e<?php echo (int)$l['id']; ?>" hidden autocomplete="off">
                <?php echo lf_csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int)$l['id']; ?>">
                <label class="lbl">Nome</label>
                <input class="in" name="name" maxlength="60" value="<?php echo e($l['name']); ?>">
                <label class="lbl">Trocar o link <small>deixe vazio para manter o atual</small></label>
                <input type="hidden" name="mode" value="link">
                <textarea class="in mono" name="m3u" placeholder="Cole aqui o novo link (M3U, M3U8 ou TS)"></textarea>
                <label class="lbl">Formato</label>
                <div class="seg">
                    <label><input type="radio" name="output" value="" <?php if ($l['output'] === '') echo 'checked'; ?>> Auto</label>
                    <label><input type="radio" name="output" value="ts" <?php if ($l['output'] === 'ts') echo 'checked'; ?>> MPEG-TS</label>
                    <label><input type="radio" name="output" value="m3u8" <?php if ($l['output'] === 'm3u8') echo 'checked'; ?>> HLS</label>
                </div>
                <label class="sw"><input type="checkbox" name="private" value="1" <?php if ($priv) echo 'checked'; ?> <?php if (!$hasPass && !$priv) echo 'disabled'; ?> data-priv="<?php echo (int)$l['id']; ?>"> <span><b>Lista privada</b> &mdash; o link fica oculto e so aparece com a senha<?php if (!$hasPass) echo ' (crie uma senha para usar)'; ?></span></label>
                <?php if ($priv) { ?><div data-unpriv="<?php echo (int)$l['id']; ?>" hidden style="margin-top:8px"><input class="in" type="password" name="password" maxlength="64" placeholder="Senha para deixar a lista visivel" autocomplete="current-password"></div><?php } ?>
                <div class="acts"><button class="btn btn-c btn-s" type="submit">Salvar</button><button class="btn btn-o btn-s" type="button" data-close="e<?php echo (int)$l['id']; ?>">Cancelar</button></div>
            </form>
            <?php } ?>
        </div>
        <?php } ?>

        <?php if ($lists && !$panel) { ?><p class="secure" style="margin-top:14px"><i class="ri-information-line"></i> Depois de trocar a lista, feche e abra o app no aparelho.</p><?php } ?>
    </section>

    <?php if (!$panel) { ?>
    <section class="card sec" id="conta">
        <h2><?php echo $hasPass ? 'Senha' : 'Proteger com senha'; ?></h2>
        <p class="muted" style="font-size:13.5px;margin:0 0 6px"><?php echo $hasPass ? 'A senha e pedida para entrar e para ver listas privadas.' : 'Crie uma senha para que so voce consiga ver e alterar as listas deste aparelho.'; ?></p>
        <form method="post" action="listas.php?action=password" autocomplete="off" <?php if ($hasPass) echo 'hidden id="pwform"'; ?>>
            <?php echo lf_csrf_field(); ?>
            <?php if ($hasPass) { ?><label class="lbl">Senha atual</label><input class="in" type="password" name="current" maxlength="64" autocomplete="current-password"><?php } ?>
            <div class="row" style="margin-top:12px"><input class="in" type="password" name="new" minlength="4" maxlength="64" placeholder="Nova senha" autocomplete="new-password"><input class="in" type="password" name="confirm" maxlength="64" placeholder="Repita a senha" autocomplete="new-password"></div>
            <div class="acts">
                <button class="btn btn-c btn-s" type="submit"><?php echo $hasPass ? 'Alterar senha' : 'Criar senha'; ?></button>
                <?php if ($hasPass) { ?><button class="btn btn-o btn-d btn-s" type="submit" name="remove_pass" value="1" formnovalidate onclick="return confirm('Remover a senha deste aparelho?');">Remover senha</button><?php } ?>
            </div>
        </form>
        <?php if ($hasPass) { ?><button class="btn btn-o btn-s" type="button" data-open="pwform" style="margin-top:8px"><i class="ri-key-2-line"></i> Alterar ou remover senha</button><?php } ?>
    </section>
    <?php } ?>

    <script>
    (function(){
        var csrf = <?php echo json_encode(lf_csrf()); ?>;
        function $(s, r){ return (r || document).querySelector(s); }
        document.addEventListener('click', function(ev){
            var t = ev.target.closest('[data-open],[data-close],[data-reveal],[data-copy]'); if (!t) return;
            if (t.hasAttribute('data-open')) { var d = document.getElementById(t.getAttribute('data-open')); d.hidden = !d.hidden; if (!d.hidden) { var i = d.querySelector('input:not([type=hidden]),textarea'); if (i) i.focus(); } if (t.getAttribute('data-open') === 'pwform') t.hidden = true; }
            if (t.hasAttribute('data-close')) document.getElementById(t.getAttribute('data-close')).hidden = true;
            if (t.hasAttribute('data-copy')) { var c = t.parentNode.querySelector('code').textContent; (navigator.clipboard ? navigator.clipboard.writeText(c) : Promise.reject()).then(function(){ t.innerHTML = '<i class="ri-check-line"></i>'; setTimeout(function(){ t.innerHTML = '<i class="ri-file-copy-line"></i>'; }, 1800); }).catch(function(){}); }
            if (t.hasAttribute('data-reveal')) {
                var id = t.getAttribute('data-reveal'), box = $('[data-box="' + id + '"]');
                if (!box.hidden) { box.hidden = true; return; }
                if (t.getAttribute('data-private') === '1') { var a = $('[data-ask="' + id + '"]'); a.hidden = !a.hidden; if (!a.hidden) a.querySelector('input').focus(); return; }
                reveal(id, '');
            }
        });
        function reveal(id, pw){
            var fd = new FormData(); fd.append('csrf', csrf); fd.append('id', id); fd.append('password', pw);
            fetch('listas.php?action=reveal', {method:'POST', body:fd, cache:'no-store'}).then(function(r){ return r.json(); }).then(function(j){
                var box = $('[data-box="' + id + '"]'), a = $('[data-ask="' + id + '"]');
                if (!j.ok) { alert(j.msg || 'Erro.'); return; }
                if (a) { a.hidden = true; a.querySelector('input').value = ''; }
                box.querySelector('code').textContent = j.url; box.hidden = false;
            }).catch(function(){ alert('Falha de conexao.'); });
        }
        document.querySelectorAll('[data-ask]').forEach(function(f){ f.addEventListener('submit', function(e){ e.preventDefault(); reveal(f.getAttribute('data-ask'), f.querySelector('input').value); }); });
        document.querySelectorAll('[data-priv]').forEach(function(c){ c.addEventListener('change', function(){ var u = $('[data-unpriv="' + c.getAttribute('data-priv') + '"]'); if (u) u.hidden = c.checked; }); });
        var add = document.getElementById('add');
        if (add) add.addEventListener('change', function(){ var m = add.querySelector('input[name=mode]:checked'); if (!m) return; add.querySelectorAll('[data-mode]').forEach(function(x){ x.hidden = x.getAttribute('data-mode') !== m.value; }); });
        if (location.hash === '#add' && add) add.hidden = false;
    })();
    </script>
<?php } ?>
<?php lp_layout_end();
