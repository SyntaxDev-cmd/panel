<?php
    // Ativacao por MAC: a revenda define a DNS, o usuario e a senha de um aparelho.
    // O MAC aparece na tela de login de cada app. Ao abrir, o app consulta o painel
    // (api2.php?action=device_login) e entra sozinho com os dados cadastrados aqui.
    $dev_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $page_title = $dev_id ? 'Editar aparelho' : 'Ativar aparelho por MAC';
    include("includes/header.php");
    require("includes/lb_helper.php");
    require("language/language.php");

    $row = null;
    if ($dev_id) {
        $row = db_row("SELECT * FROM tbl_devices WHERE id = ?", array($dev_id));
        if (!$row || !lf_in_scope($row['owner_id'])) {
            lf_flash('Aparelho nao encontrado ou sem permissao.', 'error');
            lf_redirect('manage_devices.php');
        }
    }

    // DNS que o usuario logado pode usar (as dele e, para ADMIN/MASTER, as da arvore)
    $dns_list = db_all("SELECT d.id, d.dns_title, d.owner_id, d.status, a.username AS owner_name
                        FROM tbl_dns d LEFT JOIN tbl_admin a ON a.id = d.owner_id
                        WHERE " . lf_scope_sql('d.owner_id') . " ORDER BY d.dns_title ASC");

    if (isset($_POST['submit'])) {
        if (!lf_csrf_ok()) { lf_flash('Sessao expirada. Tente novamente.', 'error'); lf_redirect('manage_devices.php'); }
        $back = $dev_id ? 'device_edit.php?id=' . $dev_id : 'device_edit.php';

        $mac  = lf_norm_mac(isset($_POST['mac']) ? $_POST['mac'] : '');
        $user = substr(trim((string)(isset($_POST['act_user']) ? $_POST['act_user'] : '')), 0, 120);
        $pass = substr(trim((string)(isset($_POST['act_pass']) ? $_POST['act_pass'] : '')), 0, 190);
        $note = substr(trim((string)(isset($_POST['note']) ? $_POST['note'] : '')), 0, 120);
        $dns_id = isset($_POST['act_dns_id']) ? (int)$_POST['act_dns_id'] : 0;
        $clear = isset($_POST['clear_activation']) && $_POST['clear_activation'] === '1';
        // vencimento (opcional): vale ate o fim do dia escolhido. Em branco = sem vencimento
        $expires = 0;
        $exp_in = isset($_POST['act_expires']) ? trim((string)$_POST['act_expires']) : '';
        if ($exp_in !== '' && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $exp_in, $em) && checkdate((int)$em[2], (int)$em[3], (int)$em[1])) {
            $expires = mktime(23, 59, 59, (int)$em[2], (int)$em[3], (int)$em[1]);
        }

        // aparelho ja existente sem MAC valido (ex.: Roku antigo): mantem o que esta gravado
        if ($row && $mac === '') $mac = lf_norm_mac($row['mac']);
        if ($mac === '') { lf_flash('Informe o MAC do aparelho com 12 caracteres (ex.: A1:B2:C3:D4:E5:F6).', 'error'); lf_redirect($back); }

        $dns = null;
        foreach ($dns_list as $d) { if ((int)$d['id'] === $dns_id) { $dns = $d; break; } }

        if (!$clear) {
            if (!$dns) { lf_flash('Escolha a DNS (servidor) do cliente.', 'error'); lf_redirect($back); }
            if ($user === '' || $pass === '') { lf_flash('Informe o usuario e a senha do cliente.', 'error'); lf_redirect($back); }
        }

        // o MAC ja esta ativado por alguem fora da arvore deste usuario?
        $other = db_row("SELECT id, owner_id FROM tbl_devices WHERE mac = ? AND act_user <> '' AND id <> ?" . (lf_has_expiry() ? " AND (act_expires = 0 OR act_expires > " . time() . ")" : "") . " LIMIT 1", array($mac, $row ? (int)$row['id'] : 0));
        if ($other && !lf_in_scope($other['owner_id'])) {
            lf_flash('Este MAC ja esta ativado por outro revendedor. Peca para ele remover a ativacao primeiro.', 'error');
            lf_redirect($back);
        }

        // dono do registro = dono da DNS escolhida (assim o aparelho conta no limite da revenda certa)
        $owner_id = $row ? (int)$row['owner_id'] : (int)$lf_me['id'];
        if ($dns) $owner_id = (int)$dns['owner_id'];

        // limite de aparelhos do dono (0 = ilimitado) - so para ativacao nova
        if (!$clear && (!$row || $row['act_user'] === '')) {
            $own = db_row("SELECT id, username, max_devices, admin_type FROM tbl_admin WHERE id = ?", array($owner_id));
            if ($own && (int)$own['admin_type'] !== ROLE_ADMIN && (int)$own['max_devices'] > 0) {
                $has = (int)db_val("SELECT COUNT(*) FROM tbl_devices WHERE owner_id = ? AND status = 1 AND is_auto = 0 AND (act_user <> '' OR last_seen >= ?) AND id <> ?",
                    array($owner_id, lf_active_since(), $row ? (int)$row['id'] : 0));
                if ($has >= (int)$own['max_devices']) {
                    lf_flash('Limite de aparelhos atingido para ' . $own['username'] . ' (' . (int)$own['max_devices'] . ').', 'error');
                    lf_redirect($back);
                }
            }
        }

        if ($clear) { $user = ''; $pass = ''; $dns_id = 0; $expires = 0; }
        $now = time();

        // ja existe um registro deste MAC para este dono? (o aparelho pode ter aberto o app antes)
        $target = $row;
        if (!$target || (int)$target['owner_id'] !== $owner_id) {
            $same = db_row("SELECT * FROM tbl_devices WHERE owner_id = ? AND (mac = ? OR device_key = ?) AND id <> ? ORDER BY (act_user <> '') DESC, id ASC LIMIT 1",
                array($owner_id, $mac, $mac, $row ? (int)$row['id'] : 0));
            if ($same) {
                // junta os dois: fica o registro do dono certo e o antigo e removido
                if ($row) db_query("DELETE FROM tbl_devices WHERE id = ?", array((int)$row['id']));
                $target = $same;
            }
        }

        if ($target) {
            db_query("UPDATE tbl_devices SET owner_id = ?, mac = ?, note = ?, act_dns_id = ?, act_user = ?, act_pass = ?, act_updated = ?, is_auto = 0 WHERE id = ?",
                array($owner_id, $mac, $note, $dns_id, $user, $pass, $now, (int)$target['id']));
            if ($dns_id > 0) db_query("UPDATE tbl_devices SET dns_id = ? WHERE id = ?", array($dns_id, (int)$target['id']));
            if (lf_has_expiry()) db_query("UPDATE tbl_devices SET act_expires = ?" . ($clear ? ", act_source = ''" : "") . " WHERE id = ?", array($expires, (int)$target['id']));
        } else {
            db_query("INSERT INTO tbl_devices (owner_id, dns_id, device_key, mac, platform, note, act_dns_id, act_user, act_pass, act_updated, is_auto, status, first_seen, last_seen)
                      VALUES (?, ?, ?, ?, 'outro', ?, ?, ?, ?, ?, 0, 1, ?, 0)",
                array($owner_id, $dns_id, $mac, $mac, $note, $dns_id, $user, $pass, $now, $now));
            if (lf_has_expiry() && $expires > 0) db_query("UPDATE tbl_devices SET act_expires = ? WHERE id = ?", array($expires, db_last_id()));
        }

        // o mesmo MAC ativado em outro registro da arvore deixaria duas ativacoes: vale so a mais nova
        if (!$clear) {
            db_query("UPDATE tbl_devices SET act_user = '', act_pass = '', act_dns_id = 0 WHERE mac = ? AND act_updated < ? AND act_user <> '' AND " . lf_scope_sql('owner_id'), array($mac, $now));
        }

        lf_flash($clear ? 'Ativacao removida. O aparelho volta a pedir usuario e senha.' : 'Aparelho ' . $mac . ' ativado. Abra (ou reabra) o app no aparelho para entrar.');
        lf_redirect('manage_devices.php?keyword=' . urlencode($mac));
    }

    $v_mac  = $row ? ($row['mac'] !== '' ? $row['mac'] : $row['device_key']) : (isset($_GET['mac']) ? lf_norm_mac($_GET['mac']) : '');
    $v_dns  = $row ? ((int)$row['act_dns_id'] > 0 ? (int)$row['act_dns_id'] : (int)$row['dns_id']) : 0;
    $v_user = $row ? ($row['act_user'] !== '' ? $row['act_user'] : $row['username']) : '';
    $v_pass = $row ? $row['act_pass'] : '';
    $v_note = $row ? $row['note'] : '';
    $v_exp  = ($row && isset($row['act_expires']) && (int)$row['act_expires'] > 0) ? date('Y-m-d', (int)$row['act_expires']) : '';
    $platforms = lf_platforms();
?>

<main id="nsofts_main">
    <div class="nsofts-container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb align-items-center">
                <li class="breadcrumb-item d-inline-flex"><a href="dashboard.php"><i class="ri-home-4-fill"></i></a></li>
                <li class="breadcrumb-item d-inline-flex"><a href="manage_devices.php">Dispositivos</a></li>
                <li class="breadcrumb-item d-inline-flex active" aria-current="page"><?php echo e($page_title); ?></li>
            </ol>
        </nav>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <h5 class="mb-3"><?php echo e($page_title); ?></h5>
                        <?php if (!$dns_list) { ?>
                            <div class="alert alert-warning">Voce ainda nao tem nenhuma DNS. <a href="create_dns.php?add=yes">Crie uma DNS</a> antes de ativar aparelhos.</div>
                        <?php } ?>
                        <form action="" method="POST" autocomplete="off">
                            <?php echo lf_csrf_field(); ?>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">MAC do aparelho</label>
                                <input type="text" name="mac" class="form-control lf-mono" maxlength="17" placeholder="A1:B2:C3:D4:E5:F6" value="<?php echo e($v_mac); ?>" <?php echo ($row && lf_norm_mac($v_mac) !== '') ? 'readonly' : 'required'; ?>>
                                <small class="text-muted">E o codigo que aparece na tela de login do app (canto de baixo). Pode digitar com ou sem os dois-pontos.</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">DNS (servidor) do cliente</label>
                                <select name="act_dns_id" class="form-control">
                                    <option value="0">Escolha...</option>
                                    <?php foreach ($dns_list as $d) { ?>
                                        <option value="<?php echo (int)$d['id']; ?>" <?php if ((int)$d['id'] === $v_dns) echo 'selected'; ?>><?php echo e($d['dns_title']); ?><?php if (lf_can_manage_users()) echo ' (' . e($d['owner_name']) . ')'; ?><?php if ((int)$d['status'] !== 1) echo ' - desativada'; ?></option>
                                    <?php } ?>
                                </select>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Usuario do cliente</label>
                                    <input type="text" name="act_user" class="form-control" maxlength="120" value="<?php echo e($v_user); ?>" autocomplete="off">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Senha do cliente</label>
                                    <input type="text" name="act_pass" class="form-control" maxlength="190" value="<?php echo e($v_pass); ?>" autocomplete="off">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Observacao (opcional)</label>
                                <input type="text" name="note" class="form-control" maxlength="120" placeholder="Nome do cliente, telefone..." value="<?php echo e($v_note); ?>">
                            </div>

                            <?php if (lf_has_expiry()) { ?>
                            <div class="mb-3" style="max-width: 260px;">
                                <label class="form-label fw-semibold">Vencimento <span class="text-muted fw-normal">- opcional</span></label>
                                <input type="date" name="act_expires" class="form-control" value="<?php echo e($v_exp); ?>">
                                <small class="text-muted">Em branco = sem vencimento. Depois da data o app volta para a tela de login.</small>
                            </div>
                            <?php } ?>

                            <?php if ($row && $row['act_user'] !== '') { ?>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="clear_activation" value="1" id="clear_activation">
                                <label class="form-check-label" for="clear_activation">Remover a ativacao por MAC (o aparelho volta a pedir usuario e senha)</label>
                            </div>
                            <?php } ?>

                            <button type="submit" name="submit" class="btn btn-primary" style="min-width: 140px;"><?php echo $row ? 'Salvar' : 'Ativar aparelho'; ?></button>
                            <a href="manage_devices.php" class="btn btn-outline-default ms-1">Cancelar</a>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <?php if ($row) { $plat = isset($platforms[$row['platform']]) ? $row['platform'] : 'outro'; ?>
                            <h5 class="mb-3">Este aparelho</h5>
                            <p class="mb-2"><span class="lf-plat lf-plat--<?php echo e($plat); ?>"><i class="<?php echo lf_platform_icon($plat); ?>"></i> <?php echo e($platforms[$plat]); ?></span></p>
                            <p class="mb-1"><b>App:</b> <?php echo $row['app_name'] !== '' ? e($row['app_name']) : '-'; ?><?php if ($row['app_version'] !== '') echo ' v' . e($row['app_version']); ?></p>
                            <p class="mb-1"><b>Modelo:</b> <?php echo $row['model'] !== '' ? e($row['model']) : '-'; ?></p>
                            <p class="mb-1"><b>IP:</b> <span class="lf-mono"><?php echo $row['ip'] !== '' ? e($row['ip']) : '-'; ?></span></p>
                            <p class="mb-1"><b>Ultimo acesso:</b> <?php echo e(lf_time_ago($row['last_seen'])); ?></p>
                            <p class="mb-0"><b>Usuario em uso:</b> <?php echo $row['username'] !== '' ? e($row['username']) : '-'; ?></p>
                        <?php } else { ?>
                            <h5 class="mb-3">Como funciona</h5>
                            <ol class="text-muted ps-3 mb-0">
                                <li class="mb-2">O cliente abre o app. Na tela de login aparece o <b>MAC</b> do aparelho.</li>
                                <li class="mb-2">Ele informa esse MAC para voce.</li>
                                <li class="mb-2">Voce cadastra aqui o MAC com a DNS, o usuario e a senha.</li>
                                <li>O cliente toca em <b>Ja ativei</b> (ou reabre o app) e entra sozinho. Para trocar o usuario depois, basta editar aqui.</li>
                            </ol>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include("includes/footer.php");?>
