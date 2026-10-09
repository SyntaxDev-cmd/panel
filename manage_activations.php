<?php
// ============================================================
//  GESTAO DE ATIVACOES (aparelhos ativados por MAC - pelo painel ou pelo site)
//  Renovar, trocar formato, bloquear, remover. Cada usuario ve so a propria arvore.
// ============================================================
$page_title = "Ativacoes";
include("includes/header.php");
require("includes/lb_helper.php");
require("language/language.php");
require_once("includes/landing.php");

$now = time();
$hasExp = lf_has_expiry();
$soon = $now + 7 * 86400;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = isset($_POST['do']) ? (string)$_POST['do'] : '';
    $ids = isset($_POST['ids']) ? (array)$_POST['ids'] : (isset($_POST['id']) ? array($_POST['id']) : array());
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    $back = 'manage_activations.php' . (isset($_POST['qs']) && $_POST['qs'] !== '' ? '?' . preg_replace('/[^a-zA-Z0-9=&%_.:+-]/', '', (string)$_POST['qs']) : '');
    if (!$ids) { lf_flash('Nenhuma ativacao selecionada.', 'error'); lf_redirect($back); }

    $done = 0;
    foreach ($ids as $id) {
        $r = db_row("SELECT * FROM tbl_devices WHERE id = ? AND act_user <> ''", array($id));
        if (!$r || !lf_in_scope($r['owner_id'])) continue;
        if ($do === 'renew' && $hasExp) {
            $days = max(1, min(3650, (int)(isset($_POST['days']) ? $_POST['days'] : 30)));
            if ((int)$r['act_expires'] === 0) continue;            // sem vencimento: nada a renovar
            $from = max($now, (int)$r['act_expires']);
            db_query("UPDATE tbl_devices SET act_expires = ? WHERE id = ?", array($from + $days * 86400, $id));
        } else if ($do === 'unlimited' && $hasExp) {
            db_query("UPDATE tbl_devices SET act_expires = 0 WHERE id = ?", array($id));
        } else if ($do === 'output') {
            db_query("UPDATE tbl_devices SET act_output = ? WHERE id = ?", array(lp_norm_output(isset($_POST['output']) ? $_POST['output'] : ''), $id));
        } else if ($do === 'block' || $do === 'unblock') {
            db_query("UPDATE tbl_devices SET status = ? WHERE id = ?", array($do === 'unblock' ? 1 : 0, $id));
        } else if ($do === 'reset_pass') {
            // cliente esqueceu a senha de "Minhas listas": remove a senha (listas privadas voltam a ficar visiveis)
            $m = lf_norm_mac($r['mac']);
            if ($m === '') continue;
            db_query("DELETE FROM tbl_lp_access WHERE mac = ?", array($m));
            db_query("UPDATE tbl_lp_lists SET is_private = 0 WHERE mac = ?", array($m));
        } else if ($do === 'remove') {
            db_query("UPDATE tbl_devices SET act_user = '', act_pass = '', act_dns_id = 0" . ($hasExp ? ", act_expires = 0, act_source = ''" : "") . " WHERE id = ?", array($id));
        } else {
            continue;
        }
        $done++;
    }
    $msgs = array('renew' => 'renovada(s)', 'unlimited' => 'sem vencimento', 'output' => 'com formato atualizado', 'block' => 'bloqueada(s)', 'reset_pass' => 'com a senha do cliente removida', 'unblock' => 'liberada(s)', 'remove' => 'removida(s)');
    lf_flash($done ? $done . ' ativacao(oes) ' . (isset($msgs[$do]) ? $msgs[$do] : 'atualizada(s)') . '.' : 'Nada foi alterado.', $done ? 'success' : 'error');
    lf_redirect($back);
}

// ---------- filtros ----------
$limit = 30;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$start = ($page - 1) * $limit;
$f_state  = isset($_GET['state']) ? (string)$_GET['state'] : '';
$f_source = isset($_GET['source']) ? (string)$_GET['source'] : '';
$keyword  = isset($_GET['keyword']) ? trim((string)$_GET['keyword']) : '';

$scope = lf_scope_sql('v.owner_id') . " AND v.act_user <> ''";
$where = $scope; $params = array();
if ($hasExp) {
    if ($f_state === 'active')   $where .= " AND v.status = 1 AND (v.act_expires = 0 OR v.act_expires > $now)";
    if ($f_state === 'soon')     $where .= " AND v.status = 1 AND v.act_expires > $now AND v.act_expires <= $soon";
    if ($f_state === 'expired')  $where .= " AND v.act_expires > 0 AND v.act_expires <= $now";
    if ($f_source === 'site')    $where .= " AND v.act_source = 'site'";
    if ($f_source === 'panel')   $where .= " AND v.act_source <> 'site'";
}
if ($f_state === 'blocked') $where .= " AND v.status = 0";
if ($keyword !== '') {
    $like = '%' . $keyword . '%';
    $mk = lf_norm_mac($keyword);
    $where .= " AND (v.mac LIKE ? OR v.mac = ? OR v.act_user LIKE ? OR v.note LIKE ? OR d.dns_base LIKE ? OR d.dns_title LIKE ?)";
    array_push($params, $like, $mk, $like, $like, $like, $like);
}

$total = (int)db_val("SELECT COUNT(*) FROM tbl_devices v LEFT JOIN tbl_dns d ON d.id = v.act_dns_id WHERE $where", $params);
$order = $hasExp ? "(v.act_expires = 0) ASC, v.act_expires ASC, v.act_updated DESC" : "v.act_updated DESC";
$rows = db_all("SELECT v.*, d.dns_title, d.dns_base, a.username AS owner_name
    FROM tbl_devices v LEFT JOIN tbl_dns d ON d.id = v.act_dns_id LEFT JOIN tbl_admin a ON a.id = v.owner_id
    WHERE $where ORDER BY $order LIMIT $start, $limit", $params);

$expCase = $hasExp ? "SUM(CASE WHEN v.status = 1 AND (v.act_expires = 0 OR v.act_expires > $now) THEN 1 ELSE 0 END) AS active,
        SUM(CASE WHEN v.status = 1 AND v.act_expires > $now AND v.act_expires <= $soon THEN 1 ELSE 0 END) AS soon,
        SUM(CASE WHEN v.act_expires > 0 AND v.act_expires <= $now THEN 1 ELSE 0 END) AS expired,"
    : "SUM(CASE WHEN v.status = 1 THEN 1 ELSE 0 END) AS active, 0 AS soon, 0 AS expired,";
$sum = db_row("SELECT $expCase SUM(CASE WHEN v.status = 0 THEN 1 ELSE 0 END) AS blocked FROM tbl_devices v WHERE $scope");

// listas do cliente (landing) e se o MAC tem senha
$lsInfo = array();
$macs = array();
foreach ($rows as $r) if ($r['mac'] !== '') $macs[] = $r['mac'];
if ($macs && lf_table_exists('tbl_lp_lists')) {
    $ph = implode(',', array_fill(0, count($macs), '?'));
    foreach (db_all("SELECT mac, COUNT(*) AS n FROM tbl_lp_lists WHERE mac IN ($ph) GROUP BY mac", $macs) as $x) $lsInfo[$x['mac']] = array('n' => (int)$x['n'], 'pass' => false);
    foreach (db_all("SELECT mac FROM tbl_lp_access WHERE mac IN ($ph)", $macs) as $x) { if (!isset($lsInfo[$x['mac']])) $lsInfo[$x['mac']] = array('n' => 0, 'pass' => false); $lsInfo[$x['mac']]['pass'] = true; }
}

$qs = $_GET; unset($qs['page']);
$qs_str = http_build_query($qs);
$base_url = 'manage_activations.php' . ($qs ? '?' . $qs_str : '');
$onlineSince = $now - LF_ONLINE_SECONDS;
$link = function ($state) use ($f_source, $keyword) { return 'manage_activations.php?' . http_build_query(array_filter(array('state' => $state, 'source' => $f_source, 'keyword' => $keyword))); };
?>

<main id="nsofts_main">
    <div class="nsofts-container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb align-items-center">
                <li class="breadcrumb-item d-inline-flex"><a href="dashboard.php"><i class="ri-home-4-fill"></i></a></li>
                <li class="breadcrumb-item d-inline-flex active" aria-current="page"><?php echo e($page_title); ?></li>
            </ol>
        </nav>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3"><a class="text-decoration-none" href="<?php echo e($link('active')); ?>"><div class="lf-stat"><div class="lf-stat__icon lf-c-green"><i class="ri-checkbox-circle-line"></i></div><div><div class="lf-stat__num"><?php echo (int)$sum['active']; ?></div><div class="lf-stat__lbl">Ativas</div></div></div></a></div>
            <div class="col-6 col-lg-3"><a class="text-decoration-none" href="<?php echo e($link('soon')); ?>"><div class="lf-stat"><div class="lf-stat__icon lf-c-purple"><i class="ri-alarm-warning-line"></i></div><div><div class="lf-stat__num"><?php echo (int)$sum['soon']; ?></div><div class="lf-stat__lbl">Vencem em 7 dias</div></div></div></a></div>
            <div class="col-6 col-lg-3"><a class="text-decoration-none" href="<?php echo e($link('expired')); ?>"><div class="lf-stat"><div class="lf-stat__icon lf-c-blue"><i class="ri-time-line"></i></div><div><div class="lf-stat__num"><?php echo (int)$sum['expired']; ?></div><div class="lf-stat__lbl">Vencidas</div></div></div></a></div>
            <div class="col-6 col-lg-3"><a class="text-decoration-none" href="<?php echo e($link('blocked')); ?>"><div class="lf-stat"><div class="lf-stat__icon lf-c-red"><i class="ri-forbid-line"></i></div><div><div class="lf-stat__num"><?php echo (int)$sum['blocked']; ?></div><div class="lf-stat__lbl">Bloqueadas</div></div></div></a></div>
        </div>

        <div class="card">
            <div class="card-header py-3 px-4">
                <form method="get" action="" class="row g-2 align-items-center">
                    <div class="col-md-4"><input type="text" class="form-control" name="keyword" placeholder="MAC, usuario, servidor, observacao..." value="<?php echo e($keyword); ?>"></div>
                    <div class="col-6 col-md-2">
                        <select name="state" class="form-control">
                            <option value="">Todas</option>
                            <?php foreach (array('active' => 'Ativas', 'soon' => 'Vencem em 7 dias', 'expired' => 'Vencidas', 'blocked' => 'Bloqueadas') as $k => $l) { ?><option value="<?php echo $k; ?>" <?php if ($f_state === $k) echo 'selected'; ?>><?php echo $l; ?></option><?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <select name="source" class="form-control">
                            <option value="">Qualquer origem</option>
                            <option value="site" <?php if ($f_source === 'site') echo 'selected'; ?>>Pelo site</option>
                            <option value="panel" <?php if ($f_source === 'panel') echo 'selected'; ?>>Pelo painel</option>
                        </select>
                    </div>
                    <div class="col-4 col-md-1"><button class="btn btn-primary w-100" type="submit"><i class="ri-filter-3-line"></i></button></div>
                    <div class="col-8 col-md-3 text-md-end"><a href="device_edit.php" class="btn btn-primary"><i class="ri-add-line"></i> Nova ativacao</a></div>
                </form>
            </div>

            <div class="card-body p-0">
                <?php if ($rows) { ?>
                <form method="post" id="bulk">
                    <input type="hidden" name="qs" value="<?php echo e($qs_str); ?>">
                    <input type="hidden" name="do" id="bulk_do" value="">
                    <div class="d-flex flex-wrap align-items-center px-4 py-2" style="gap:8px;border-bottom:1px solid var(--ns-border-color, rgba(128,128,128,.15))">
                        <small class="text-muted me-1">Selecionadas:</small>
                        <?php if ($hasExp) { ?>
                        <select name="days" class="form-control form-control-sm" style="width:auto">
                            <?php foreach (array(1, 7, 15, 30, 60, 90, 180, 365) as $d) { ?><option value="<?php echo $d; ?>" <?php if ($d === 30) echo 'selected'; ?>>+<?php echo $d; ?> dia<?php echo $d > 1 ? 's' : ''; ?></option><?php } ?>
                        </select>
                        <button type="button" class="btn btn-sm btn-outline-primary bulk" data-do="renew"><i class="ri-calendar-check-line"></i> Renovar</button>
                        <?php } ?>
                        <select name="output" class="form-control form-control-sm" style="width:auto">
                            <option value="">Formato automatico</option><option value="ts">MPEG-TS</option><option value="m3u8">HLS (m3u8)</option>
                        </select>
                        <button type="button" class="btn btn-sm btn-outline-primary bulk" data-do="output"><i class="ri-film-line"></i> Aplicar formato</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary bulk" data-do="block"><i class="ri-forbid-line"></i> Bloquear</button>
                        <button type="button" class="btn btn-sm btn-outline-success bulk" data-do="unblock"><i class="ri-check-line"></i> Liberar</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary bulk" data-do="reset_pass" data-confirm="Remover a senha de 'Minhas listas' dos aparelhos selecionados? Use quando o cliente esquecer a senha."><i class="ri-key-2-line"></i> Resetar senha do cliente</button>
                        <button type="button" class="btn btn-sm btn-outline-danger bulk" data-do="remove" data-confirm="Remover a ativacao dos aparelhos selecionados? Eles voltam para a tela de login."><i class="ri-delete-bin-line"></i> Remover</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table lf-table mb-0">
                            <thead><tr>
                                <th style="width:34px"><input type="checkbox" class="form-check-input" id="all"></th>
                                <th>Aparelho</th><th>Lista</th><th>Formato</th><th>Vencimento</th><th>Ultimo acesso</th>
                                <?php if (lf_can_manage_users()) { ?><th>Dono</th><?php } ?>
                                <th class="text-center">Acoes</th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($rows as $r) {
                                $exp = $hasExp ? (int)$r['act_expires'] : 0;
                                $blocked = (int)$r['status'] === 0;
                                $expired = $exp > 0 && $exp <= $now;
                                $online = !$blocked && (int)$r['last_seen'] >= $onlineSince;
                                $site = $hasExp && $r['act_source'] === 'site';
                                $out = isset($r['act_output']) ? $r['act_output'] : '';
                                if ($blocked) { $chip = '<span class="lf-badge lf-badge--admin">BLOQUEADA</span>'; }
                                else if ($expired) { $chip = '<span class="lf-badge lf-badge--admin">VENCIDA</span>'; }
                                else if ($exp > 0 && $exp <= $soon) { $chip = '<span class="lf-badge lf-badge--master">VENCE EM ' . max(1, (int)ceil(($exp - $now) / 86400)) . 'D</span>'; }
                                else { $chip = '<span class="lf-badge lf-badge--ok">ATIVA</span>'; } ?>
                                <tr>
                                    <td><input type="checkbox" class="form-check-input pick" name="ids[]" value="<?php echo (int)$r['id']; ?>"></td>
                                    <td>
                                        <span class="lf-mono"><?php echo e($r['mac'] !== '' ? $r['mac'] : $r['device_key']); ?></span>
                                        <div class="lf-sub"><?php echo $site ? '<i class="ri-global-line"></i> site' : '<i class="ri-user-settings-line"></i> painel'; ?><?php if ($r['note'] !== '') echo ' &middot; ' . e($r['note']); ?></div>
                                        <?php if ($site && isset($lsInfo[$r['mac']])) { $li = $lsInfo[$r['mac']]; ?><div class="lf-sub"><i class="ri-play-list-2-line"></i> <?php echo (int)$li['n']; ?> lista(s)<?php if ($li['pass']) echo ' &middot; <i class="ri-lock-line"></i> com senha'; ?></div><?php } ?>
                                    </td>
                                    <td><?php echo e($r['dns_base'] ? parse_url(html_entity_decode($r['dns_base'], ENT_QUOTES, 'UTF-8'), PHP_URL_HOST) : '-'); ?><div class="lf-sub">usuario: <?php echo e($r['act_user']); ?></div></td>
                                    <td><?php echo e(lp_output_label($out)); ?></td>
                                    <td><?php echo $chip; ?><div class="lf-sub"><?php echo $exp > 0 ? date('d/m/Y', $exp) : 'sem vencimento'; ?></div></td>
                                    <td><span class="lf-dot <?php echo $online ? 'on' : ''; ?>"></span> <?php echo $online ? '<b>online</b>' : e(lf_time_ago($r['last_seen'])); ?></td>
                                    <?php if (lf_can_manage_users()) { ?><td><?php echo e($r['owner_name']); ?></td><?php } ?>
                                    <td class="text-center text-nowrap">
                                        <?php if ($hasExp && $exp > 0) { ?><button type="button" class="btn btn-outline-primary rounded-pill btn-icon one" data-do="renew" data-id="<?php echo (int)$r['id']; ?>" title="Renovar pelos dias escolhidos acima"><i class="ri-calendar-check-line"></i></button><?php } ?>
                                        <a href="device_edit.php?id=<?php echo (int)$r['id']; ?>" class="btn btn-outline-primary rounded-pill btn-icon" title="Editar lista, formato e vencimento"><i class="ri-pencil-line"></i></a>
                                        <button type="button" class="btn btn-outline-secondary rounded-pill btn-icon one" data-do="<?php echo $blocked ? 'unblock' : 'block'; ?>" data-id="<?php echo (int)$r['id']; ?>" title="<?php echo $blocked ? 'Liberar' : 'Bloquear'; ?>"><i class="<?php echo $blocked ? 'ri-lock-unlock-line' : 'ri-forbid-line'; ?>"></i></button>
                                        <button type="button" class="btn btn-outline-danger rounded-pill btn-icon one" data-do="remove" data-id="<?php echo (int)$r['id']; ?>" data-confirm="Remover a ativacao deste aparelho?" title="Remover ativacao"><i class="ri-delete-bin-line"></i></button>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </form>
                <div class="p-3"><?php echo lf_pagination($total, $limit, $page, $base_url); ?></div>
                <?php } else { ?>
                    <div class="lf-empty">
                        <i class="ri-tv-2-line"></i>
                        <h5>Nenhuma ativacao encontrada</h5>
                        <p>Ative aparelhos por MAC aqui no painel ou envie a <a href="ativar.php" target="_blank">landing page</a> para os clientes.</p>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</main>

<?php include("includes/footer.php");?>

<script type="text/javascript">
    $('#all').on('change', function () { $('.pick').prop('checked', this.checked); });
    function lfSend(doIt, ids, msg) {
        if (msg && !confirm(msg)) return;
        var f = $('#bulk');
        if (ids) { f.find('.pick').prop('checked', false); f.find('.pick[value="' + ids + '"]').prop('checked', true); }
        if (!f.find('.pick:checked').length) { $.notify('Selecione pelo menos uma ativacao.', { position: "top right", className: 'error' }); return; }
        $('#bulk_do').val(doIt); f.trigger('submit');
    }
    $('.bulk').on('click', function () { lfSend($(this).data('do'), null, $(this).data('confirm')); });
    $('.one').on('click', function () { lfSend($(this).data('do'), $(this).data('id'), $(this).data('confirm')); });
</script>
