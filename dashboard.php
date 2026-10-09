<?php
$page_title = "Painel";
include("includes/header.php");
require("includes/lb_helper.php");
require("language/language.php");

$now = time();
$onlineSince = $now - LF_ONLINE_SECONDS;
$activeSince = lf_active_since();
$scopeDev = lf_scope_sql('v.owner_id');
$scopeDns = lf_scope_sql('d.owner_id');

$dev = db_row("SELECT COUNT(*) AS total,
        SUM(CASE WHEN v.status = 1 AND v.last_seen >= $onlineSince THEN 1 ELSE 0 END) AS online,
        SUM(CASE WHEN v.status = 1 AND v.last_seen >= $activeSince THEN 1 ELSE 0 END) AS active,
        SUM(CASE WHEN v.status = 0 THEN 1 ELSE 0 END) AS blocked,
        COUNT(DISTINCT CASE WHEN v.status = 1 AND v.last_seen >= $onlineSince AND v.username <> '' THEN v.username END) AS clients_online,
        COUNT(DISTINCT CASE WHEN v.username <> '' THEN v.username END) AS clients_total
    FROM tbl_devices v WHERE $scopeDev");

$dns_total  = (int)db_val("SELECT COUNT(*) FROM tbl_dns d WHERE $scopeDns");
$dns_active = (int)db_val("SELECT COUNT(*) FROM tbl_dns d WHERE $scopeDns AND d.status = 1");
$users_total = lf_can_manage_users() ? (int)db_val("SELECT COUNT(*) FROM tbl_admin WHERE " . lf_scope_sql('id') . " AND id <> ?", array((int)$lf_me['id'])) : 0;

// por plataforma
$platforms = lf_platforms();
$by = array();
foreach ($platforms as $k => $label) $by[$k] = array('total' => 0, 'online' => 0);
foreach (db_all("SELECT v.platform, COUNT(*) AS total, SUM(CASE WHEN v.status = 1 AND v.last_seen >= $onlineSince THEN 1 ELSE 0 END) AS online
                 FROM tbl_devices v WHERE $scopeDev GROUP BY v.platform") as $r) {
    $k = isset($by[$r['platform']]) ? $r['platform'] : 'outro';
    $by[$k]['total'] += (int)$r['total'];
    $by[$k]['online'] += (int)$r['online'];
}
$max_plat = 1;
foreach ($by as $b) $max_plat = max($max_plat, $b['total']);

// DNS com mais aparelhos conectados
$top_dns = db_all("SELECT d.id, d.dns_title, d.partner_code, d.status, a.username AS owner_name,
        (SELECT COUNT(*) FROM tbl_devices v WHERE v.dns_id = d.id AND v.status = 1 AND v.last_seen >= $onlineSince) AS online,
        (SELECT COUNT(*) FROM tbl_devices v WHERE v.dns_id = d.id) AS total
    FROM tbl_dns d LEFT JOIN tbl_admin a ON a.id = d.owner_id
    WHERE $scopeDns ORDER BY online DESC, total DESC, d.id DESC LIMIT 6");

// ultimos acessos
$recent = db_all("SELECT v.*, d.dns_title FROM tbl_devices v LEFT JOIN tbl_dns d ON d.id = v.dns_id WHERE $scopeDev ORDER BY v.last_seen DESC LIMIT 8");

$login_mode = lf_login_mode();
$my_limit = (int)$lf_me['max_devices'];
$my_active = lf_active_count((int)$lf_me['id']);
?>

<main id="nsofts_main">
    <div class="nsofts-container">

        <div class="lf-hero mb-4">
            <div>
                <h4 class="mb-1">Ola, <?php echo e($lf_me['username']); ?> <?php echo lf_role_badge($lf_me['admin_type']); ?></h4>
                <p class="mb-0">Modo de login do app: <b><?php echo $login_mode === 'code' ? 'Codigo de parceria + usuario e senha' : 'Direto (usuario e senha)'; ?></b>
                <?php if (lf_is_admin()) { ?> &middot; <a href="settings_login.php">alterar</a><?php } ?></p>
            </div>
            <div class="lf-hero__actions">
                <a href="create_dns.php?add=yes" class="btn btn-light"><i class="ri-add-line"></i> Nova DNS</a>
                <a href="manage_devices.php?state=online" class="btn btn-outline-light"><i class="ri-wifi-line"></i> Ver conectados</a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3"><a class="lf-stat" href="manage_devices.php?state=online"><div class="lf-stat__icon lf-c-green"><i class="ri-wifi-line"></i></div><div><div class="lf-stat__num"><?php echo (int)$dev['online']; ?></div><div class="lf-stat__lbl">Dispositivos conectados agora</div></div></a></div>
            <div class="col-6 col-xl-3"><a class="lf-stat" href="manage_devices.php"><div class="lf-stat__icon lf-c-blue"><i class="ri-device-line"></i></div><div><div class="lf-stat__num"><?php echo (int)$dev['active']; ?><?php if (!lf_is_admin() && $my_limit > 0) echo '<small> / ' . $my_limit . '</small>'; ?></div><div class="lf-stat__lbl">Dispositivos ativos (<?php echo LF_ACTIVE_DAYS; ?> dias)</div></div></a></div>
            <div class="col-6 col-xl-3"><a class="lf-stat" href="manage_devices.php?state=online"><div class="lf-stat__icon lf-c-purple"><i class="ri-user-3-line"></i></div><div><div class="lf-stat__num"><?php echo (int)$dev['clients_online']; ?><small> / <?php echo (int)$dev['clients_total']; ?></small></div><div class="lf-stat__lbl">Clientes conectados / total</div></div></a></div>
            <div class="col-6 col-xl-3"><a class="lf-stat" href="manage_dns.php"><div class="lf-stat__icon lf-c-orange"><i class="ri-server-line"></i></div><div><div class="lf-stat__num"><?php echo $dns_active; ?><small> / <?php echo $dns_total; ?></small></div><div class="lf-stat__lbl">DNS ativas / total</div></div></a></div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <h5 class="mb-3">Dispositivos por plataforma</h5>
                        <?php foreach ($platforms as $k => $label) { $b = $by[$k]; ?>
                            <a class="lf-bar" href="manage_devices.php?platform=<?php echo e($k); ?>">
                                <div class="lf-bar__head">
                                    <span class="lf-plat lf-plat--<?php echo e($k); ?>"><i class="<?php echo lf_platform_icon($k); ?>"></i> <?php echo e($label); ?></span>
                                    <span><b><?php echo $b['online']; ?></b> online &middot; <?php echo $b['total']; ?> total</span>
                                </div>
                                <div class="lf-bar__track"><div class="lf-bar__fill lf-bg-<?php echo e($k); ?>" style="width: <?php echo round($b['total'] / $max_plat * 100); ?>%"></div></div>
                            </a>
                        <?php } ?>
                        <?php if (lf_can_manage_users()) { ?>
                            <div class="d-flex justify-content-between mt-3 pt-3 border-top">
                                <span class="text-muted"><?php echo lf_is_admin() ? 'Usuarios / revendas' : 'Minhas revendas'; ?></span>
                                <a href="manage_admin.php"><b><?php echo $users_total; ?></b></a>
                            </div>
                        <?php } ?>
                        <?php if ((int)$dev['blocked'] > 0) { ?>
                            <div class="d-flex justify-content-between mt-2">
                                <span class="text-muted">Dispositivos bloqueados</span>
                                <a href="manage_devices.php?state=blocked"><b><?php echo (int)$dev['blocked']; ?></b></a>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">DNS e codigos de parceria</h5>
                            <a href="manage_dns.php">ver todas</a>
                        </div>
                        <?php if ($top_dns) { ?>
                            <div class="table-responsive">
                                <table class="table lf-table mb-0">
                                    <thead><tr><th>DNS</th><th>Codigo</th><?php if (lf_can_manage_users()) { ?><th>Dono</th><?php } ?><th class="text-center">Online</th><th class="text-center">Total</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($top_dns as $d) { ?>
                                            <tr>
                                                <td><a href="manage_devices.php?dns_id=<?php echo (int)$d['id']; ?>"><?php echo e($d['dns_title']); ?></a><?php if ((int)$d['status'] !== 1) echo ' <span class="lf-badge lf-badge--off">desativada</span>'; ?></td>
                                                <td class="lf-mono"><?php echo e($d['partner_code']); ?></td>
                                                <?php if (lf_can_manage_users()) { ?><td><?php echo e($d['owner_name']); ?></td><?php } ?>
                                                <td class="text-center"><span class="lf-dot <?php echo (int)$d['online'] > 0 ? 'on' : ''; ?>"></span> <?php echo (int)$d['online']; ?></td>
                                                <td class="text-center"><?php echo (int)$d['total']; ?></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div class="lf-empty lf-empty--sm"><i class="ri-server-line"></i><p>Nenhuma DNS ainda. <a href="create_dns.php?add=yes">Criar a primeira</a>.</p></div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Ultimos acessos</h5>
                    <a href="manage_devices.php">ver todos</a>
                </div>
                <?php if ($recent) { ?>
                    <div class="table-responsive">
                        <table class="table lf-table mb-0">
                            <thead><tr><th>Plataforma</th><th>MAC / ID</th><th>Cliente</th><th>DNS</th><th>IP</th><th>Ultimo acesso</th></tr></thead>
                            <tbody>
                                <?php foreach ($recent as $r) {
                                    $plat = isset($platforms[$r['platform']]) ? $r['platform'] : 'outro';
                                    $on = (int)$r['status'] === 1 && (int)$r['last_seen'] >= $onlineSince; ?>
                                    <tr>
                                        <td><span class="lf-plat lf-plat--<?php echo e($plat); ?>"><i class="<?php echo lf_platform_icon($plat); ?>"></i> <?php echo e($platforms[$plat]); ?></span></td>
                                        <td class="lf-mono"><?php echo (int)$r['is_auto'] === 1 ? '<span class="text-muted">sem ID</span>' : e($r['mac'] !== '' ? $r['mac'] : $r['device_key']); ?></td>
                                        <td><?php echo $r['username'] !== '' ? e($r['username']) : '-'; ?></td>
                                        <td><?php echo $r['dns_title'] ? e($r['dns_title']) : '-'; ?></td>
                                        <td class="lf-mono"><?php echo e($r['ip']); ?></td>
                                        <td><span class="lf-dot <?php echo $on ? 'on' : ''; ?>"></span> <?php echo $on ? '<b>online</b>' : e(lf_time_ago($r['last_seen'])); ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } else { ?>
                    <div class="lf-empty lf-empty--sm"><i class="ri-device-line"></i><p>Nenhum aparelho conectou ainda.</p></div>
                <?php } ?>
            </div>
        </div>
    </div>
</main>

<?php include("includes/footer.php"); ?>
