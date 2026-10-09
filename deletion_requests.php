<?php
// Pedidos de exclusao de dados enviados pela pagina publica (policy/account_delete_request.php)
$page_title = "Pedidos de exclusao de dados";
include("includes/header.php");
require("includes/lb_helper.php");
require("language/language.php");

// apaga (ou limpa) os dados ligados ao e-mail / usuario / MAC informado
function dr_apply($req) {
    $v = trim((string)$req['user_email']);
    if ($v === '') return 0;
    $mac = lf_norm_mac($v);
    $full = $req['policy_type'] !== 'Clear Data';
    $ids = array();
    if ($mac !== '') foreach (db_all("SELECT id FROM tbl_devices WHERE mac = ? OR device_key = ?", array($mac, $mac)) as $r) $ids[] = (int)$r['id'];
    foreach (db_all("SELECT id FROM tbl_devices WHERE (act_user = ? OR username = ?) AND act_user <> ''", array($v, $v)) as $r) $ids[] = (int)$r['id'];
    foreach (db_all("SELECT device_id FROM tbl_lp_orders WHERE (email = ? OR mac = ?) AND device_id > 0", array($v, $mac)) as $r) $ids[] = (int)$r['device_id'];
    $ids = array_values(array_unique(array_filter($ids)));
    $n = count($ids);
    if ($ids) {
        if ($full) db_query("DELETE FROM tbl_devices WHERE id IN (" . lf_in($ids) . ")");
        else db_query("UPDATE tbl_devices SET ip = '', user_agent = '', model = '', username = '' WHERE id IN (" . lf_in($ids) . ")");
    }
    if ($mac !== '' && $full) {
        $n += (int)db_exec("DELETE FROM tbl_lp_lists WHERE mac = ?", array($mac));
        db_query("DELETE FROM tbl_lp_access WHERE mac = ?", array($mac));
    }
    // pedidos do site: mantem so o registro financeiro (obrigacao fiscal), sem dados pessoais
    $n += (int)db_exec("UPDATE tbl_lp_orders SET email = '', ip = '', m3u_user = '', m3u_pass = '', pix_qr = NULL, pix_qr_b64 = NULL" . ($full ? ", dns_base = ''" : "") . " WHERE email = ? OR (mac <> '' AND mac = ?)", array($v, $mac));
    return $n;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $req = db_row("SELECT * FROM tbl_policy_deletion WHERE id = ?", array((int)(isset($_POST['id']) ? $_POST['id'] : 0)));
    $do = isset($_POST['do']) ? (string)$_POST['do'] : '';
    if ($req && $do === 'apply') {
        $n = dr_apply($req);
        db_query("UPDATE tbl_policy_deletion SET status = 1 WHERE id = ?", array((int)$req['id']));
        lf_flash('Pedido #' . (int)$req['id'] . ' concluido. ' . $n . ' registro(s) removido(s) ou anonimizado(s).');
    } else if ($req && $do === 'done') {
        db_query("UPDATE tbl_policy_deletion SET status = 1 WHERE id = ?", array((int)$req['id']));
        lf_flash('Pedido marcado como concluido.');
    } else if ($req && $do === 'delete') {
        db_query("DELETE FROM tbl_policy_deletion WHERE id = ?", array((int)$req['id']));
        lf_flash('Pedido apagado.');
    }
    lf_redirect('deletion_requests.php');
}

$rows = db_all("SELECT * FROM tbl_policy_deletion ORDER BY status ASC, id DESC LIMIT 300");
?>

<main id="nsofts_main">
    <div class="nsofts-container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb align-items-center">
                <li class="breadcrumb-item d-inline-flex"><a href="dashboard.php"><i class="ri-home-4-fill"></i></a></li>
                <li class="breadcrumb-item d-inline-flex active" aria-current="page"><?php echo e($page_title); ?></li>
            </ol>
        </nav>

        <div class="lf-note mb-3">
            <i class="ri-information-line"></i>
            <div>As lojas (Google Play, Amazon, Roku, LG, Samsung, Apple) exigem um link para o usuario pedir a exclusao dos dados. Use este link na ficha do app: <b class="lf-mono"><?php echo e(getBaseUrl()); ?>policy/account_delete_request.php</b>. Responda os pedidos em ate 30 dias.</div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <?php if ($rows) { ?>
                <div class="table-responsive">
                    <table class="table lf-table mb-0">
                        <thead><tr><th>#</th><th>E-mail / usuario / MAC</th><th>Pedido</th><th>Observacao</th><th>Data</th><th>Situacao</th><th class="text-center">Acoes</th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $r) { ?>
                            <tr>
                                <td><?php echo (int)$r['id']; ?></td>
                                <td class="lf-mono"><?php echo e($r['user_email']); ?></td>
                                <td><?php echo $r['policy_type'] === 'Clear Data' ? 'Apagar dados coletados' : 'Excluir conta e dados'; ?></td>
                                <td style="max-width:260px"><small><?php echo e($r['report_msg']); ?></small></td>
                                <td><?php echo (int)$r['deletion_on'] > 0 ? date('d/m/Y H:i', (int)$r['deletion_on']) : '-'; ?></td>
                                <td><?php echo (int)$r['status'] === 1 ? '<span class="lf-badge lf-badge--ok">CONCLUIDO</span>' : '<span class="lf-badge lf-badge--master">PENDENTE</span>'; ?></td>
                                <td class="text-center text-nowrap">
                                    <?php if ((int)$r['status'] !== 1) { ?>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Remover agora os dados ligados a este pedido? Nao da para desfazer.');"><input type="hidden" name="do" value="apply"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>"><button class="btn btn-sm btn-danger"><i class="ri-delete-bin-6-line"></i> Excluir dados</button></form>
                                    <form method="post" class="d-inline"><input type="hidden" name="do" value="done"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>"><button class="btn btn-sm btn-outline-success" title="Marcar como concluido"><i class="ri-check-line"></i></button></form>
                                    <?php } ?>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Apagar este pedido da lista?');"><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>"><button class="btn btn-sm btn-outline-secondary" title="Apagar pedido"><i class="ri-close-line"></i></button></form>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php } else { ?>
                    <div class="lf-empty"><i class="ri-shield-user-line"></i><h5>Nenhum pedido</h5><p>Os pedidos enviados pela pagina de exclusao aparecem aqui.</p></div>
                <?php } ?>
            </div>
        </div>
    </div>
</main>

<?php include("includes/footer.php");?>
