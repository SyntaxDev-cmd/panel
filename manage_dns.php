<?php
$page_title = "DNS e Codigos de Parceria";
include("includes/header.php");
require("includes/lb_helper.php");
require("language/language.php");

$limit = 24;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$start = ($page - 1) * $limit;
$keyword = isset($_GET['keyword']) ? trim((string)$_GET['keyword']) : '';

$where = lf_scope_sql('d.owner_id');
$params = array();
if ($keyword !== '') {
    $where .= " AND (d.dns_title LIKE ? OR d.dns_base LIKE ? OR d.partner_code LIKE ? OR a.username LIKE ?)";
    $like = '%' . $keyword . '%';
    $params = array($like, $like, $like, $like);
}

$total = (int)db_val("SELECT COUNT(*) FROM tbl_dns d LEFT JOIN tbl_admin a ON a.id = d.owner_id WHERE $where", $params);
$onlineSince = time() - LF_ONLINE_SECONDS;
$rows = db_all("SELECT d.*, a.username AS owner_name, a.admin_type AS owner_type,
        (SELECT COUNT(*) FROM tbl_devices v WHERE v.dns_id = d.id) AS dev_total,
        (SELECT COUNT(*) FROM tbl_devices v WHERE v.dns_id = d.id AND v.last_seen >= $onlineSince) AS dev_online
    FROM tbl_dns d LEFT JOIN tbl_admin a ON a.id = d.owner_id
    WHERE $where ORDER BY d.id DESC LIMIT $start, $limit", $params);

$login_mode = lf_login_mode();
$my_dns = (int)db_val("SELECT COUNT(*) FROM tbl_dns WHERE owner_id = ?", array((int)$lf_me['id']));
$my_max = (int)$lf_me['max_dns'];
$can_create = lf_is_admin() || $my_max === 0 || $my_dns < $my_max;
?>

<main id="nsofts_main">
    <div class="nsofts-container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb align-items-center">
                <li class="breadcrumb-item d-inline-flex"><a href="dashboard.php"><i class="ri-home-4-fill"></i></a></li>
                <li class="breadcrumb-item d-inline-flex active" aria-current="page"><?php echo e($page_title); ?></li>
            </ol>
        </nav>

        <?php if ($login_mode !== 'code') { ?>
            <div class="lf-note mb-3">
                <i class="ri-information-line"></i>
                <div>O app esta no modo <b>login direto</b> (usuario e senha). Os codigos abaixo ja existem, mas so serao exigidos quando o ADMIN ativar o <b>login por codigo de parceria</b><?php if (lf_is_admin()) { ?> em <a href="settings_login.php">Configuracoes &rsaquo; Modo de login</a><?php } ?>.</div>
            </div>
        <?php } ?>

        <div class="card h-100">
            <div class="card-header d-md-flex align-items-center justify-content-between py-3 px-4">
                <div>
                    <h5 class="mb-0"><?php echo e($page_title); ?></h5>
                    <small class="text-muted"><?php echo $total; ?> DNS<?php if (!lf_is_admin() && $my_max > 0) { echo ' &middot; suas: ' . $my_dns . ' de ' . $my_max; } ?></small>
                </div>
                <div class="d-flex mt-2 mt-md-0">
                    <form method="get" action="" class="me-2">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Buscar nome, URL, codigo..." name="keyword" value="<?php echo e($keyword); ?>">
                            <button class="btn btn-outline-default d-inline-flex align-items-center" type="submit"><i class="ri-search-2-line"></i></button>
                        </div>
                    </form>
                    <?php if ($can_create) { ?>
                        <a href="create_dns.php?add=yes" class="btn btn-primary d-inline-flex align-items-center justify-content-center">
                            <i class="ri-add-line"></i><span class="ps-1 text-nowrap d-none d-sm-block">Nova DNS</span>
                        </a>
                    <?php } else { ?>
                        <span class="btn btn-secondary disabled" title="Limite de DNS atingido">Limite atingido</span>
                    <?php } ?>
                </div>
            </div>

            <div class="card-body p-4">
                <?php if ($rows) { ?>
                    <div class="row g-4 mb-3">
                        <?php foreach ($rows as $i => $row) { ?>
                            <div class="col-xl-4 col-md-6">
                                <div class="lf-dns <?php echo (int)$row['status'] === 1 ? '' : 'is-off'; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="lf-dns__title">
                                            <h5 class="mb-1"><?php echo e($row['dns_title']); ?></h5>
                                            <div class="lf-dns__url"><?php echo e(html_entity_decode($row['dns_base'], ENT_QUOTES, 'UTF-8')); ?></div>
                                            <?php if (!empty($row['dns_backup'])) { ?><div class="lf-dns__url" title="URL reserva: usada pelos apps quando a principal nao responde"><i class="ri-shield-check-line"></i> Reserva: <?php echo e(html_entity_decode($row['dns_backup'], ENT_QUOTES, 'UTF-8')); ?></div><?php } ?>
                                        </div>
                                        <div class="nsofts-switch d-flex align-items-center enable_disable" title="Ativar / Desativar">
                                            <input type="checkbox" id="enable_disable_check_<?php echo $i; ?>" data-id="<?php echo (int)$row['id']; ?>" data-table="tbl_dns" data-column="status" class="cbx hidden btn_enable_disable" <?php if ((int)$row['status'] === 1) { echo 'checked'; } ?>>
                                            <label for="enable_disable_check_<?php echo $i; ?>" class="nsofts-switch__label"></label>
                                        </div>
                                    </div>

                                    <div class="lf-code">
                                        <div>
                                            <span class="lf-code__label">Codigo de parceria</span>
                                            <span class="lf-code__value" id="code_<?php echo (int)$row['id']; ?>"><?php echo e($row['partner_code']); ?></span>
                                        </div>
                                        <div class="d-flex">
                                            <a href="javascript:void(0)" class="btn btn-icon btn-outline-primary rounded-pill me-1 btn_copy_code" data-target="code_<?php echo (int)$row['id']; ?>" title="Copiar codigo"><i class="ri-file-copy-line"></i></a>
                                            <a href="javascript:void(0)" class="btn btn-icon btn-outline-primary rounded-pill btn_new_code" data-id="<?php echo (int)$row['id']; ?>" title="Gerar novo codigo"><i class="ri-refresh-line"></i></a>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <div class="lf-dns__meta">
                                            <a href="manage_devices.php?dns_id=<?php echo (int)$row['id']; ?>" title="Ver dispositivos">
                                                <span class="lf-dot <?php echo (int)$row['dev_online'] > 0 ? 'on' : ''; ?>"></span>
                                                <b><?php echo (int)$row['dev_online']; ?></b> online &middot; <?php echo (int)$row['dev_total']; ?> dispositivos
                                            </a>
                                            <?php if (lf_can_manage_users()) { ?>
                                                <div class="mt-1"><i class="ri-user-line"></i> <?php echo e($row['owner_name'] ? $row['owner_name'] : $row['cliente']); ?> <?php echo $row['owner_name'] ? lf_role_badge($row['owner_type']) : ''; ?></div>
                                            <?php } ?>
                                        </div>
                                        <div class="d-flex">
                                            <a href="create_dns.php?dns_id=<?php echo (int)$row['id']; ?>" class="btn btn-outline-primary rounded-pill me-2 btn-icon" title="Editar"><i class="ri-pencil-line"></i></a>
                                            <a href="javascript:void(0)" class="btn btn-outline-danger rounded-pill btn-icon btn_delete" data-id="<?php echo (int)$row['id']; ?>" data-table="tbl_dns" title="Excluir"><i class="ri-delete-bin-line"></i></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                    <?php echo lf_pagination($total, $limit, $page, 'manage_dns.php' . ($keyword !== '' ? '?keyword=' . urlencode($keyword) : '')); ?>
                <?php } else { ?>
                    <div class="lf-empty">
                        <i class="ri-server-line"></i>
                        <h5>Nenhuma DNS cadastrada</h5>
                        <p>Crie uma DNS para receber automaticamente o codigo de parceria.</p>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</main>

<?php include("includes/footer.php");?>

<script type="text/javascript">
    $(document).on("click", ".btn_copy_code", function () {
        var el = document.getElementById($(this).data("target"));
        var ok = copyToClipboard(el);
        $.notify(ok ? 'Codigo copiado: ' + el.innerText : 'Nao foi possivel copiar.', { position: "top right", className: ok ? 'success' : 'error' });
    });
    $(document).on("click", ".btn_new_code", function () {
        var id = $(this).data("id");
        swal({
            title: "Gerar novo codigo?",
            text: "O codigo atual deixa de funcionar. Os clientes precisarao digitar o novo codigo.",
            type: "warning", showCancelButton: true, buttonsStyling: false,
            confirmButtonClass: 'btn btn-primary m-2', cancelButtonClass: 'btn btn-danger m-2',
            confirmButtonText: "Gerar novo", cancelButtonText: "Cancelar"
        }).then(function (result) {
            if (!result.value) return;
            $.ajax({
                type: 'post', url: 'processData.php', dataType: 'json',
                data: { id: id, table: 'tbl_dns', action: 'new_code' },
                success: function (res) {
                    if (res.status == 1) $('#code_' + id).text(res.code);
                    $.notify(res.msg, { position: "top right", className: res.class });
                }
            });
        });
    });
</script>
