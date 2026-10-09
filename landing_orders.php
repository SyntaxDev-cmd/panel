<?php
    $page_title = "Pedidos do site";
    include("includes/header.php");
    require("includes/lb_helper.php");
    require("language/language.php");
    require_once("includes/landing.php");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!lf_csrf_ok()) { lf_flash('Sessao expirada. Tente novamente.', 'error'); lf_redirect('landing_orders.php'); }
        $do = isset($_POST['do']) ? (string)$_POST['do'] : '';
        $o = db_row("SELECT * FROM tbl_lp_orders WHERE id = ?", array((int)(isset($_POST['id']) ? $_POST['id'] : 0)));
        if (!$o) { lf_flash('Pedido nao encontrado.', 'error'); lf_redirect('landing_orders.php'); }

        if ($do === 'sync') {
            $o2 = mp_sync_order($o);
            lf_flash('Pedido #' . (int)$o['id'] . ': ' . ($o2 && $o2['status'] === 'approved' ? 'pago e ativado.' : 'situacao no Mercado Pago: ' . ($o2 && $o2['mp_status'] !== '' ? $o2['mp_status'] : 'sem pagamento') . '.'));
        } else if ($do === 'approve') {
            // liberacao manual (ex.: cliente pagou por fora)
            if ($o['status'] === 'approved') lp_activate_device($o); else lp_activate($o, 'manual');
            lf_flash('Pedido #' . (int)$o['id'] . ' ativado.');
        }
        lf_redirect('landing_orders.php' . (isset($_POST['qs']) ? '?' . preg_replace('/[^a-zA-Z0-9=&%_.:-]/', '', (string)$_POST['qs']) : ''));
    }

    $limit = 30;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $start = ($page - 1) * $limit;
    $f_status = isset($_GET['status']) ? (string)$_GET['status'] : '';
    $keyword = isset($_GET['keyword']) ? trim((string)$_GET['keyword']) : '';
    $statuses = array('approved' => 'Pago / ativado', 'pending' => 'Aguardando', 'expired' => 'Vencido', 'rejected' => 'Recusado', 'refunded' => 'Estornado', 'charged_back' => 'Contestado', 'error' => 'Erro');

    $where = '1=1'; $params = array();
    if (isset($statuses[$f_status])) { $where .= ' AND status = ?'; $params[] = $f_status; }
    if ($keyword !== '') {
        $mk = lf_norm_mac($keyword);
        $like = '%' . $keyword . '%';
        $where .= ' AND (mac LIKE ? OR mac = ? OR email LIKE ? OR m3u_user LIKE ? OR dns_base LIKE ? OR mp_payment_id = ? OR id = ?)';
        array_push($params, $like, $mk, $like, $like, $like, $keyword, (int)$keyword);
    }
    $total = (int)db_val("SELECT COUNT(*) FROM tbl_lp_orders WHERE $where", $params);
    $rows = db_all("SELECT id, token, mac, dns_base, m3u_user, email, plan_name, days, amount, status, method, mp_payment_id, mp_status, expires_at, created_at, paid_at FROM tbl_lp_orders WHERE $where ORDER BY id DESC LIMIT $start, $limit", $params);

    $month = mktime(0, 0, 0, (int)date('n'), 1);
    $sum = db_row("SELECT COUNT(*) AS n, COALESCE(SUM(amount),0) AS total FROM tbl_lp_orders WHERE status = 'approved' AND paid_at >= ?", array($month));
    $sumAll = db_row("SELECT COUNT(*) AS n, COALESCE(SUM(amount),0) AS total FROM tbl_lp_orders WHERE status = 'approved'");
    $active = (int)db_val("SELECT COUNT(*) FROM tbl_devices WHERE act_source = ? AND act_user <> '' AND (act_expires = 0 OR act_expires > ?)", array(LP_SOURCE, time()));
    $pending = (int)db_val("SELECT COUNT(*) FROM tbl_lp_orders WHERE status = 'pending'");

    $qs = $_GET; unset($qs['page']);
    $qs_str = http_build_query($qs);
    $base_url = 'landing_orders.php' . ($qs ? '?' . $qs_str : '');
?>

<main id="nsofts_main">
    <div class="nsofts-container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb align-items-center">
                <li class="breadcrumb-item d-inline-flex"><a href="dashboard.php"><i class="ri-home-4-fill"></i></a></li>
                <li class="breadcrumb-item d-inline-flex"><a href="settings_landing.php">Landing page</a></li>
                <li class="breadcrumb-item d-inline-flex active" aria-current="page"><?php echo e($page_title); ?></li>
            </ol>
        </nav>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3"><div class="lf-stat"><div class="lf-stat__icon lf-c-green"><i class="ri-money-dollar-circle-line"></i></div><div><div class="lf-stat__num"><?php echo lp_money($sum['total']); ?></div><div class="lf-stat__lbl">Recebido no mes (<?php echo (int)$sum['n']; ?>)</div></div></div></div>
            <div class="col-6 col-lg-3"><div class="lf-stat"><div class="lf-stat__icon lf-c-blue"><i class="ri-wallet-3-line"></i></div><div><div class="lf-stat__num"><?php echo lp_money($sumAll['total']); ?></div><div class="lf-stat__lbl">Recebido total (<?php echo (int)$sumAll['n']; ?>)</div></div></div></div>
            <div class="col-6 col-lg-3"><div class="lf-stat"><div class="lf-stat__icon lf-c-purple"><i class="ri-tv-2-line"></i></div><div><div class="lf-stat__num"><?php echo $active; ?></div><div class="lf-stat__lbl">Aparelhos ativos pelo site</div></div></div></div>
            <div class="col-6 col-lg-3"><div class="lf-stat"><div class="lf-stat__icon lf-c-red"><i class="ri-time-line"></i></div><div><div class="lf-stat__num"><?php echo $pending; ?></div><div class="lf-stat__lbl">Aguardando pagamento</div></div></div></div>
        </div>

        <div class="card h-100">
            <div class="card-header py-3 px-4">
                <form method="get" action="" class="row g-2 align-items-center">
                    <div class="col-md-5"><input type="text" class="form-control" name="keyword" placeholder="MAC, e-mail, usuario, n. do pedido ou pagamento..." value="<?php echo e($keyword); ?>"></div>
                    <div class="col-8 col-md-3">
                        <select name="status" class="form-control">
                            <option value="">Todos</option>
                            <?php foreach ($statuses as $k => $l) { ?><option value="<?php echo e($k); ?>" <?php if ($f_status === $k) echo 'selected'; ?>><?php echo e($l); ?></option><?php } ?>
                        </select>
                    </div>
                    <div class="col-4 col-md-1"><button class="btn btn-primary w-100" type="submit"><i class="ri-filter-3-line"></i></button></div>
                    <div class="col-md-3 text-md-end"><a href="settings_landing.php" class="btn btn-outline-default"><i class="ri-settings-3-line"></i> Configurar</a></div>
                </form>
            </div>
            <div class="card-body p-0">
                <?php if ($rows) { ?>
                <div class="table-responsive">
                    <table class="table lf-table mb-0">
                        <thead><tr><th>#</th><th>MAC</th><th>Lista (M3U)</th><th>Plano</th><th>Valor</th><th>Situacao</th><th>Vence</th><th>Data</th><th class="text-center">Acoes</th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $r) {
                            $cls = $r['status'] === 'approved' ? 'lf-badge--ok' : ($r['status'] === 'pending' ? 'lf-badge--master' : 'lf-badge--admin'); ?>
                            <tr>
                                <td><?php echo (int)$r['id']; ?></td>
                                <td><a class="lf-mono" href="manage_devices.php?keyword=<?php echo urlencode($r['mac']); ?>"><?php echo e($r['mac']); ?></a><?php if ($r['email'] !== '') { ?><div class="lf-sub"><?php echo e($r['email']); ?></div><?php } ?></td>
                                <td><?php echo e(parse_url($r['dns_base'], PHP_URL_HOST)); ?><div class="lf-sub">usuario: <?php echo e($r['m3u_user']); ?></div></td>
                                <td><?php echo e($r['plan_name']); ?><div class="lf-sub"><?php echo (int)$r['days'] > 0 ? (int)$r['days'] . ' dias' : 'sem vencimento'; ?></div></td>
                                <td><?php echo (float)$r['amount'] > 0 ? lp_money($r['amount']) : 'Gratis'; ?><div class="lf-sub"><?php echo e($r['method']); ?><?php if ($r['mp_payment_id'] !== '') echo ' &middot; MP ' . e($r['mp_payment_id']); ?></div></td>
                                <td><span class="lf-badge <?php echo $cls; ?>"><?php echo e(isset($statuses[$r['status']]) ? $statuses[$r['status']] : $r['status']); ?></span><?php if ($r['mp_status'] !== '' && $r['status'] !== 'approved') { ?><div class="lf-sub"><?php echo e($r['mp_status']); ?></div><?php } ?></td>
                                <td><?php if ((int)$r['expires_at'] > 0) { echo date('d/m/Y', (int)$r['expires_at']); if ((int)$r['expires_at'] < time()) echo '<div class="lf-sub">vencido</div>'; } else { echo $r['status'] === 'approved' ? 'nunca' : '-'; } ?></td>
                                <td><?php echo date('d/m/Y H:i', (int)$r['created_at']); ?><?php if ((int)$r['paid_at'] > 0) { ?><div class="lf-sub">pago <?php echo date('d/m H:i', (int)$r['paid_at']); ?></div><?php } ?></td>
                                <td class="text-center text-nowrap">
                                    <a href="ativar.php?pedido=<?php echo e($r['token']); ?>" target="_blank" class="btn btn-outline-primary rounded-pill btn-icon" title="Pagina do pedido (link do cliente)"><i class="ri-external-link-line"></i></a>
                                    <?php if ($r['status'] !== 'approved' && (float)$r['amount'] > 0) { ?>
                                    <form method="post" class="d-inline"><?php echo lf_csrf_field(); ?><input type="hidden" name="do" value="sync"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>"><input type="hidden" name="qs" value="<?php echo e($qs_str); ?>">
                                        <button class="btn btn-outline-primary rounded-pill btn-icon" title="Consultar no Mercado Pago"><i class="ri-refresh-line"></i></button></form>
                                    <?php } ?>
                                    <form method="post" class="d-inline" onsubmit="return confirm('<?php echo $r['status'] === 'approved' ? 'Gravar a ativacao deste pedido de novo no aparelho?' : 'Liberar este pedido manualmente (sem pagamento confirmado)?'; ?>');"><?php echo lf_csrf_field(); ?><input type="hidden" name="do" value="approve"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>"><input type="hidden" name="qs" value="<?php echo e($qs_str); ?>">
                                        <button class="btn btn-outline-success rounded-pill btn-icon" title="<?php echo $r['status'] === 'approved' ? 'Ativar de novo' : 'Liberar manualmente'; ?>"><i class="ri-check-double-line"></i></button></form>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <div class="p-3"><?php echo lf_pagination($total, $limit, $page, $base_url); ?></div>
                <?php } else { ?>
                    <div class="lf-empty">
                        <i class="ri-shopping-bag-3-line"></i>
                        <h5>Nenhum pedido ainda</h5>
                        <p>Os pedidos feitos na <a href="ativar.php" target="_blank">landing page</a> aparecem aqui.</p>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</main>

<?php include("includes/footer.php");?>
