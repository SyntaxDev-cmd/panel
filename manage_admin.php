<?php
    $page_title = "Usuarios e Revendas";
    include("includes/header.php");
    require("includes/lb_helper.php");
    require("language/language.php");

    $limit = 25;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $start = ($page - 1) * $limit;
    $keyword = isset($_GET['keyword']) ? trim((string)$_GET['keyword']) : '';

    $where = lf_scope_sql('a.id');
    $params = array();
    if ($keyword !== '') {
        $where .= " AND (a.username LIKE ? OR a.email LIKE ?)";
        $params = array('%' . $keyword . '%', '%' . $keyword . '%');
    }

    $total = (int)db_val("SELECT COUNT(*) FROM tbl_admin a WHERE $where", $params);
    $onlineSince = time() - LF_ONLINE_SECONDS;
    $activeSince = lf_active_since();
    $rows = db_all("SELECT a.*, p.username AS parent_name,
            (SELECT COUNT(*) FROM tbl_dns d WHERE d.owner_id = a.id) AS dns_total,
            (SELECT COUNT(*) FROM tbl_admin c WHERE c.parent_id = a.id) AS sub_total,
            (SELECT COUNT(*) FROM tbl_devices v WHERE v.owner_id = a.id AND v.status = 1 AND v.is_auto = 0 AND v.last_seen >= $activeSince) AS dev_active,
            (SELECT COUNT(*) FROM tbl_devices v WHERE v.owner_id = a.id AND v.status = 1 AND v.last_seen >= $onlineSince) AS dev_online
        FROM tbl_admin a LEFT JOIN tbl_admin p ON p.id = a.parent_id
        WHERE $where ORDER BY a.admin_type DESC, a.id DESC LIMIT $start, $limit", $params);
?>

<main id="nsofts_main">
    <div class="nsofts-container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb align-items-center">
                <li class="breadcrumb-item d-inline-flex"><a href="dashboard.php"><i class="ri-home-4-fill"></i></a></li>
                <li class="breadcrumb-item d-inline-flex active" aria-current="page"><?php echo e($page_title); ?></li>
            </ol>
        </nav>

        <div class="card h-100">
            <div class="card-header d-md-flex align-items-center justify-content-between py-3 px-4">
                <div>
                    <h5 class="mb-0"><?php echo e($page_title); ?></h5>
                    <small class="text-muted"><?php echo $total; ?> usuario(s) na sua arvore</small>
                </div>
                <div class="d-flex mt-2 mt-md-0">
                    <form method="get" action="" class="me-2">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Buscar usuario ou email..." name="keyword" value="<?php echo e($keyword); ?>">
                            <button class="btn btn-outline-default d-inline-flex align-items-center" type="submit"><i class="ri-search-2-line"></i></button>
                        </div>
                    </form>
                    <a href="auth_profile.php?add=yes" class="btn btn-primary d-inline-flex align-items-center justify-content-center">
                        <i class="ri-user-add-line"></i><span class="ps-1 text-nowrap d-none d-sm-block"><?php echo lf_is_admin() ? 'Novo usuario' : 'Nova revenda'; ?></span>
                    </a>
                </div>
            </div>

            <div class="card-body p-0">
                <?php if ($rows) { ?>
                    <div class="table-responsive">
                        <table class="table lf-table mb-0">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Nivel</th>
                                    <th>Criado por</th>
                                    <th class="text-center">DNS</th>
                                    <th class="text-center">Dispositivos ativos</th>
                                    <th class="text-center">Online</th>
                                    <th class="text-center">Revendas</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center" style="width: 130px;">Acoes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $i => $row) {
                                    $can = lf_can_manage_user($row);
                                    $isSelf = (int)$row['id'] === (int)$lf_me['id']; ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="lf-avatar"><?php echo e(strtoupper(substr($row['username'], 0, 1))); ?></span>
                                                <div class="ps-2">
                                                    <b><?php echo e($row['username']); ?></b><?php if ($isSelf) echo ' <small class="text-muted">(voce)</small>'; ?>
                                                    <div class="lf-sub"><?php echo e($row['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo lf_role_badge($row['admin_type']); ?></td>
                                        <td><?php echo $row['parent_name'] ? e($row['parent_name']) : '<span class="text-muted">-</span>'; ?></td>
                                        <td class="text-center"><?php echo (int)$row['dns_total']; ?><span class="text-muted"> / <?php echo (int)$row['max_dns'] > 0 ? (int)$row['max_dns'] : '&infin;'; ?></span></td>
                                        <td class="text-center"><a href="manage_devices.php?owner_id=<?php echo (int)$row['id']; ?>"><?php echo (int)$row['dev_active']; ?></a><span class="text-muted"> / <?php echo (int)$row['max_devices'] > 0 ? (int)$row['max_devices'] : '&infin;'; ?></span></td>
                                        <td class="text-center"><span class="lf-dot <?php echo (int)$row['dev_online'] > 0 ? 'on' : ''; ?>"></span> <?php echo (int)$row['dev_online']; ?></td>
                                        <td class="text-center"><?php echo (int)$row['sub_total']; ?></td>
                                        <td class="text-center">
                                            <?php if ($can && (int)$row['admin_type'] !== ROLE_ADMIN) { ?>
                                                <div class="nsofts-switch enable_disable d-inline-flex" title="Ativar / Desativar">
                                                    <input type="checkbox" id="enable_disable_check_<?php echo $i; ?>" data-id="<?php echo (int)$row['id']; ?>" data-table="tbl_admin" data-column="status" class="cbx hidden btn_enable_disable" <?php if ((int)$row['status'] === 1) { echo 'checked'; } ?>>
                                                    <label for="enable_disable_check_<?php echo $i; ?>" class="nsofts-switch__label"></label>
                                                </div>
                                            <?php } else { ?>
                                                <span class="lf-badge <?php echo (int)$row['status'] === 1 ? 'lf-badge--ok' : 'lf-badge--off'; ?>"><?php echo (int)$row['status'] === 1 ? 'Ativo' : 'Inativo'; ?></span>
                                            <?php } ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($isSelf) { ?>
                                                <a href="auth_profile_edit.php" class="btn btn-outline-primary rounded-pill btn-icon" title="Meu perfil"><i class="ri-user-settings-line"></i></a>
                                            <?php } else if ($can) { ?>
                                                <a href="auth_profile.php?user_id=<?php echo (int)$row['id']; ?>" class="btn btn-outline-primary rounded-pill btn-icon me-1" title="Editar"><i class="ri-pencil-line"></i></a>
                                                <?php if ((int)$row['admin_type'] !== ROLE_ADMIN) { ?>
                                                    <a href="javascript:void(0)" class="btn btn-outline-danger rounded-pill btn-icon btn_delete" data-id="<?php echo (int)$row['id']; ?>" data-table="tbl_admin" title="Excluir (remove tambem as DNS e dispositivos deste usuario)"><i class="ri-delete-bin-line"></i></a>
                                                <?php } ?>
                                            <?php } else { ?>
                                                <span class="text-muted">-</span>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3"><?php echo lf_pagination($total, $limit, $page, 'manage_admin.php' . ($keyword !== '' ? '?keyword=' . urlencode($keyword) : '')); ?></div>
                <?php } else { ?>
                    <div class="lf-empty"><i class="ri-group-line"></i><h5>Nenhum usuario encontrado</h5></div>
                <?php } ?>
            </div>
        </div>

        <div class="lf-note mt-3">
            <i class="ri-shield-user-line"></i>
            <div><b>Niveis:</b> <b>ADMIN</b> gerencia tudo &middot; <b>MASTER</b> cria e gerencia as proprias revendas, as DNS e os dispositivos da sua arvore &middot; <b>REVENDA</b> gerencia apenas as proprias DNS e dispositivos. Limite 0 = ilimitado.</div>
        </div>
    </div>
</main>

<?php include("includes/footer.php");?>
