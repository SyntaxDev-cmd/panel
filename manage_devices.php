<?php
$page_title = "Dispositivos Conectados";
include("includes/header.php");
require("includes/lb_helper.php");
require("language/language.php");

$limit = 30;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$start = ($page - 1) * $limit;
$now = time();
$onlineSince = $now - LF_ONLINE_SECONDS;

$f_platform = isset($_GET['platform']) ? (string)$_GET['platform'] : '';
$f_state    = isset($_GET['state']) ? (string)$_GET['state'] : '';
$f_dns      = isset($_GET['dns_id']) ? (int)$_GET['dns_id'] : 0;
$f_owner    = isset($_GET['owner_id']) ? (int)$_GET['owner_id'] : 0;
$keyword    = isset($_GET['keyword']) ? trim((string)$_GET['keyword']) : '';

$scope = lf_scope_sql('v.owner_id');
$where = $scope;
$params = array();
$platforms = lf_platforms();
if (isset($platforms[$f_platform])) { $where .= " AND v.platform = ?"; $params[] = $f_platform; }
if ($f_state === 'online')  { $where .= " AND v.status = 1 AND v.last_seen >= $onlineSince"; }
if ($f_state === 'blocked') { $where .= " AND v.status = 0"; }
if ($f_dns > 0)   { $where .= " AND v.dns_id = ?"; $params[] = $f_dns; }
if ($f_owner > 0) { $where .= " AND v.owner_id = ?"; $params[] = $f_owner; }
if ($keyword !== '') {
    $like = '%' . $keyword . '%';
    // aceita o MAC digitado com ou sem os dois-pontos
    $macKey = lf_norm_mac($keyword);
    $likeMac = $macKey !== '' ? $macKey : $like;
    $where .= " AND (v.mac LIKE ? OR v.mac LIKE ? OR v.device_key LIKE ? OR v.username LIKE ? OR v.act_user LIKE ? OR v.note LIKE ? OR v.ip LIKE ? OR v.model LIKE ?)";
    array_push($params, $like, $likeMac, $like, $like, $like, $like, $like, $like);
}

$total = (int)db_val("SELECT COUNT(*) FROM tbl_devices v WHERE $where", $params);
$rows = db_all("SELECT v.*, d.dns_title, d.partner_code, a.username AS owner_name
    FROM tbl_devices v
    LEFT JOIN tbl_dns d ON d.id = v.dns_id
    LEFT JOIN tbl_admin a ON a.id = v.owner_id
    WHERE $where ORDER BY v.last_seen DESC LIMIT $start, $limit", $params);

// resumo (sempre do escopo inteiro do usuario)
$sum = db_row("SELECT COUNT(*) AS total,
        SUM(CASE WHEN v.status = 1 AND v.last_seen >= $onlineSince THEN 1 ELSE 0 END) AS online,
        SUM(CASE WHEN v.status = 0 THEN 1 ELSE 0 END) AS blocked,
        COUNT(DISTINCT CASE WHEN v.status = 1 AND v.last_seen >= $onlineSince AND v.username <> '' THEN v.username END) AS clients
    FROM tbl_devices v WHERE $scope");

$dns_list = db_all("SELECT d.id, d.dns_title FROM tbl_dns d WHERE " . lf_scope_sql('d.owner_id') . " ORDER BY d.dns_title ASC");
$owner_list = lf_can_manage_users() ? db_all("SELECT id, username FROM tbl_admin WHERE " . lf_scope_sql('id') . " ORDER BY username ASC") : array();

$qs = $_GET; unset($qs['page']);
$base_url = 'manage_devices.php' . ($qs ? '?' . http_build_query($qs) : '');
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
            <div class="col-6 col-lg-3"><div class="lf-stat"><div class="lf-stat__icon lf-c-green"><i class="ri-wifi-line"></i></div><div><div class="lf-stat__num"><?php echo (int)$sum['online']; ?></div><div class="lf-stat__lbl">Conectados agora</div></div></div></div>
            <div class="col-6 col-lg-3"><div class="lf-stat"><div class="lf-stat__icon lf-c-blue"><i class="ri-device-line"></i></div><div><div class="lf-stat__num"><?php echo (int)$sum['total']; ?></div><div class="lf-stat__lbl">Dispositivos (total)</div></div></div></div>
            <div class="col-6 col-lg-3"><div class="lf-stat"><div class="lf-stat__icon lf-c-purple"><i class="ri-user-3-line"></i></div><div><div class="lf-stat__num"><?php echo (int)$sum['clients']; ?></div><div class="lf-stat__lbl">Clientes conectados</div></div></div></div>
            <div class="col-6 col-lg-3"><div class="lf-stat"><div class="lf-stat__icon lf-c-red"><i class="ri-forbid-line"></i></div><div><div class="lf-stat__num"><?php echo (int)$sum['blocked']; ?></div><div class="lf-stat__lbl">Bloqueados</div></div></div></div>
        </div>

        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3" style="gap:10px;">
            <div class="text-muted"><small>O MAC aparece na tela de login de cada app. Com ele voce define (ou troca) a DNS, o usuario e a senha do aparelho sem o cliente digitar nada.</small></div>
            <a href="device_edit.php" class="btn btn-primary"><i class="ri-add-line"></i> Ativar aparelho por MAC</a>
        </div>

        <div class="card h-100">
            <div class="card-header py-3 px-4">
                <form method="get" action="" class="row g-2 align-items-center">
                    <div class="col-md-3"><input type="text" class="form-control" name="keyword" placeholder="MAC, usuario, IP, modelo..." value="<?php echo e($keyword); ?>"></div>
                    <div class="col-6 col-md-2">
                        <select name="platform" class="form-control">
                            <option value="">Todas as plataformas</option>
                            <?php foreach ($platforms as $k => $label) { ?><option value="<?php echo e($k); ?>" <?php if ($f_platform === $k) echo 'selected'; ?>><?php echo e($label); ?></option><?php } ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <select name="state" class="form-control">
                            <option value="">Todos</option>
                            <option value="online" <?php if ($f_state === 'online') echo 'selected'; ?>>Conectados agora</option>
                            <option value="blocked" <?php if ($f_state === 'blocked') echo 'selected'; ?>>Bloqueados</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <select name="dns_id" class="form-control">
                            <option value="0">Todas as DNS</option>
                            <?php foreach ($dns_list as $d) { ?><option value="<?php echo (int)$d['id']; ?>" <?php if ($f_dns === (int)$d['id']) echo 'selected'; ?>><?php echo e($d['dns_title']); ?></option><?php } ?>
                        </select>
                    </div>
                    <?php if ($owner_list) { ?>
                    <div class="col-6 col-md-2">
                        <select name="owner_id" class="form-control">
                            <option value="0">Todos os donos</option>
                            <?php foreach ($owner_list as $o) { ?><option value="<?php echo (int)$o['id']; ?>" <?php if ($f_owner === (int)$o['id']) echo 'selected'; ?>><?php echo e($o['username']); ?></option><?php } ?>
                        </select>
                    </div>
                    <?php } ?>
                    <div class="col-md-1"><button class="btn btn-primary w-100" type="submit"><i class="ri-filter-3-line"></i></button></div>
                </form>
            </div>

            <div class="card-body p-0">
                <?php if ($rows) { ?>
                    <div class="table-responsive">
                        <table class="table lf-table mb-0">
                            <thead>
                                <tr>
                                    <th>Plataforma</th>
                                    <th>MAC / ID do aparelho</th>
                                    <th>Cliente (usuario)</th>
                                    <th>DNS</th>
                                    <?php if (lf_can_manage_users()) { ?><th>Dono</th><?php } ?>
                                    <th>IP</th>
                                    <th>Ultimo acesso</th>
                                    <th class="text-center">Liberado</th>
                                    <th class="text-center">Acoes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $i => $row) {
                                    $online = (int)$row['status'] === 1 && (int)$row['last_seen'] >= $onlineSince;
                                    $plat = isset($platforms[$row['platform']]) ? $row['platform'] : 'outro'; ?>
                                    <tr>
                                        <td>
                                            <span class="lf-plat lf-plat--<?php echo e($plat); ?>"><i class="<?php echo lf_platform_icon($plat); ?>"></i> <?php echo e($platforms[$plat]); ?></span>
                                            <?php $info = array(); if ($row['app_name'] !== '') $info[] = e($row['app_name']); if ($row['model'] !== '') $info[] = e($row['model']); if ($row['app_version'] !== '') $info[] = 'v' . e($row['app_version']);
                                            if ($info) { ?><div class="lf-sub"><?php echo implode(' &middot; ', $info); ?></div><?php } ?>
                                        </td>
                                        <td>
                                            <?php if ((int)$row['is_auto'] === 1) { ?>
                                                <span class="text-muted">sem ID (app antigo)</span>
                                            <?php } else { ?>
                                                <span class="lf-mono"><?php echo e($row['mac'] !== '' ? $row['mac'] : $row['device_key']); ?></span>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <?php echo $row['username'] !== '' ? e($row['username']) : '<span class="text-muted">-</span>'; ?>
                                            <?php if ($row['act_user'] !== '') {
                                                $exp = isset($row['act_expires']) ? (int)$row['act_expires'] : 0;
                                                $site = isset($row['act_source']) && $row['act_source'] === 'site'; ?>
                                                <div class="lf-sub"><span class="lf-badge <?php echo ($exp > 0 && $exp <= $now) ? 'lf-badge--admin' : 'lf-badge--ok'; ?>"><?php echo $site ? 'ATIVADO PELO SITE' : 'ATIVADO POR MAC'; ?></span> <?php echo e($row['act_user']); ?></div>
                                                <?php if ($exp > 0) { ?><div class="lf-sub"><?php echo $exp > $now ? 'vence ' : 'venceu '; echo date('d/m/Y', $exp); ?></div><?php } ?>
                                            <?php } ?>
                                            <?php if ($row['note'] !== '') { ?><div class="lf-sub"><?php echo e($row['note']); ?></div><?php } ?>
                                        </td>
                                        <td>
                                            <?php if ($row['dns_title']) { ?>
                                                <?php echo e($row['dns_title']); ?><div class="lf-sub">cod. <?php echo e($row['partner_code']); ?></div>
                                            <?php } else { ?><span class="text-muted">-</span><?php } ?>
                                        </td>
                                        <?php if (lf_can_manage_users()) { ?><td><?php echo e($row['owner_name']); ?></td><?php } ?>
                                        <td class="lf-mono"><?php echo e($row['ip']); ?></td>
                                        <td>
                                            <span class="lf-dot <?php echo $online ? 'on' : ''; ?>"></span>
                                            <?php echo $online ? '<b>online</b>' : e(lf_time_ago($row['last_seen'])); ?>
                                            <div class="lf-sub">desde <?php echo date('d/m/Y', (int)$row['first_seen']); ?></div>
                                        </td>
                                        <td class="text-center">
                                            <div class="nsofts-switch enable_disable d-inline-flex" title="Liberar / Bloquear">
                                                <input type="checkbox" id="enable_disable_check_<?php echo $i; ?>" data-id="<?php echo (int)$row['id']; ?>" data-table="tbl_devices" data-column="status" class="cbx hidden btn_enable_disable" <?php if ((int)$row['status'] === 1) { echo 'checked'; } ?>>
                                                <label for="enable_disable_check_<?php echo $i; ?>" class="nsofts-switch__label"></label>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <?php if ((int)$row['is_auto'] !== 1) { ?><a href="device_edit.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-outline-primary rounded-pill btn-icon" title="Editar usuario deste aparelho"><i class="ri-edit-line"></i></a><?php } ?>
                                            <a href="javascript:void(0)" class="btn btn-outline-danger rounded-pill btn-icon btn_delete" data-id="<?php echo (int)$row['id']; ?>" data-table="tbl_devices" title="Remover"><i class="ri-delete-bin-line"></i></a>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3"><?php echo lf_pagination($total, $limit, $page, $base_url); ?></div>
                <?php } else { ?>
                    <div class="lf-empty">
                        <i class="ri-device-line"></i>
                        <h5>Nenhum dispositivo encontrado</h5>
                        <p>Os aparelhos aparecem aqui assim que abrem o app (Android, PC, Roku, Samsung ou LG).</p>
                    </div>
                <?php } ?>
            </div>
        </div>
        <p class="text-muted mt-3 mb-0"><small>"Conectado agora" = aparelho que enviou sinal nos ultimos <?php echo (int)(LF_ONLINE_SECONDS / 60); ?> minutos. Bloquear um aparelho impede o acesso dele no proximo sinal.</small></p>
    </div>
</main>

<?php include("includes/footer.php");?>
