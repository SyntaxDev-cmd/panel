<?php
    $dns_id = isset($_GET['dns_id']) ? (int)$_GET['dns_id'] : 0;
    $page_title = $dns_id ? 'Editar DNS' : 'Nova DNS';
    include("includes/header.php");
    require("includes/lb_helper.php");
    require("language/language.php");

    $row = null;
    if ($dns_id) {
        $row = db_row("SELECT * FROM tbl_dns WHERE id = ?", array($dns_id));
        if (!$row || !lf_in_scope($row['owner_id'])) {
            lf_flash('DNS nao encontrada ou sem permissao.', 'error');
            lf_redirect('manage_dns.php');
        }
    }

    // quem pode ser dono: o proprio usuario e (para ADMIN/MASTER) quem esta abaixo na arvore
    $owners = array();
    if (lf_can_manage_users()) {
        $owners = db_all("SELECT id, username, admin_type, max_dns FROM tbl_admin WHERE status = 1 AND " . lf_scope_sql('id') . " ORDER BY admin_type DESC, username ASC");
    }

    function dns_clean_url($url) {
        $url = trim((string)$url);
        $url = rtrim($url, "/ \t");
        if ($url !== '' && !preg_match('#^https?://#i', $url)) $url = 'http://' . $url;
        return $url;
    }

    if (isset($_POST['submit'])) {
        if (!lf_csrf_ok()) { lf_flash('Sessao expirada. Tente novamente.', 'error'); lf_redirect('manage_dns.php'); }

        $title = trim((string)(isset($_POST['dns_title']) ? $_POST['dns_title'] : ''));
        $base  = dns_clean_url(isset($_POST['dns_base']) ? $_POST['dns_base'] : '');
        $back  = $dns_id ? 'create_dns.php?dns_id=' . $dns_id : 'create_dns.php?add=yes';
        $backup = dns_clean_url(isset($_POST['dns_backup']) ? $_POST['dns_backup'] : '');

        if ($title === '' || $base === '' || !filter_var($base, FILTER_VALIDATE_URL)) {
            lf_flash('Informe um nome e uma URL valida (ex.: http://servidor.com:8080).', 'error');
            lf_redirect($back);
        }

        if ($backup !== '' && (!filter_var($backup, FILTER_VALIDATE_URL) || strlen($backup) > 255)) {
            lf_flash('A URL reserva nao e valida (ex.: http://reserva.com:8080). Deixe vazio se nao tiver.', 'error');
            lf_redirect($back);
        }
        if ($backup !== '' && strtolower($backup) === strtolower($base)) $backup = '';

        // dono da DNS
        $owner_id = $row ? (int)$row['owner_id'] : (int)$lf_me['id'];
        if (lf_can_manage_users() && isset($_POST['owner_id'])) {
            $cand = (int)$_POST['owner_id'];
            if ($cand > 0 && lf_in_scope($cand)) $owner_id = $cand;
        }
        $owner = db_row("SELECT id, username, max_dns, admin_type FROM tbl_admin WHERE id = ?", array($owner_id));
        if (!$owner) { lf_flash('Dono da DNS invalido.', 'error'); lf_redirect($back); }

        // limite de DNS do dono (0 = ilimitado)
        $changing_owner = !$row || (int)$row['owner_id'] !== $owner_id;
        if ($changing_owner && (int)$owner['admin_type'] !== ROLE_ADMIN && (int)$owner['max_dns'] > 0) {
            $has = (int)db_val("SELECT COUNT(*) FROM tbl_dns WHERE owner_id = ?", array($owner_id));
            if ($has >= (int)$owner['max_dns']) {
                lf_flash('Limite de DNS atingido para ' . $owner['username'] . ' (' . (int)$owner['max_dns'] . ').', 'error');
                lf_redirect($back);
            }
        }

        if ($row) {
            db_query("UPDATE tbl_dns SET dns_title = ?, dns_base = ?, dns_backup = ?, owner_id = ?, cliente = ? WHERE id = ?",
                array($title, $base, $backup, $owner_id, $owner['username'], $dns_id));
            lf_flash('DNS atualizada.');
            lf_redirect('manage_dns.php');
        } else {
            // cada DNS nova recebe automaticamente um codigo de parceria numerico e unico
            $code = lf_new_partner_code();
            db_query("INSERT INTO tbl_dns (dns_title, dns_base, dns_backup, status, cliente, owner_id, partner_code, created_at) VALUES (?, ?, ?, 1, ?, ?, ?, ?)",
                array($title, $base, $backup, $owner['username'], $owner_id, $code, time()));
            lf_flash('DNS criada. Codigo de parceria: ' . $code);
            lf_redirect('manage_dns.php');
        }
    }
?>

<main id="nsofts_main">
    <div class="nsofts-container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb align-items-center">
                <li class="breadcrumb-item d-inline-flex"><a href="dashboard.php"><i class="ri-home-4-fill"></i></a></li>
                <li class="breadcrumb-item d-inline-flex"><a href="manage_dns.php">DNS</a></li>
                <li class="breadcrumb-item d-inline-flex active" aria-current="page"><?php echo e($page_title); ?></li>
            </ol>
        </nav>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <h5 class="mb-3"><?php echo e($page_title); ?></h5>
                        <form action="" method="POST">
                            <?php echo lf_csrf_field(); ?>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Nome</label>
                                <input type="text" name="dns_title" class="form-control" maxlength="120" placeholder="Ex.: Servidor principal" value="<?php echo $row ? e($row['dns_title']) : ''; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">URL do servidor (DNS)</label>
                                <input type="text" name="dns_base" class="form-control" placeholder="http://servidor.com:8080" value="<?php echo $row ? e(html_entity_decode($row['dns_base'], ENT_QUOTES, 'UTF-8')) : ''; ?>" required>
                                <small class="text-muted">Endereco Xtream Codes / XUI, com porta, sem barra no final.</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">URL reserva (DNS secundaria) <span class="text-muted fw-normal">- opcional</span></label>
                                <input type="text" name="dns_backup" class="form-control" maxlength="255" placeholder="http://reserva.com:8080" value="<?php echo ($row && isset($row['dns_backup'])) ? e(html_entity_decode($row['dns_backup'], ENT_QUOTES, 'UTF-8')) : ''; ?>">
                                <small class="text-muted">Outro endereco do MESMO servidor (mesmos usuarios e senhas). Se a URL principal cair, os apps entram pela reserva sozinhos, com o mesmo codigo de parceria.</small>
                            </div>

                            <?php if ($owners) { ?>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Dono da DNS</label>
                                <select name="owner_id" class="form-control">
                                    <?php $sel = $row ? (int)$row['owner_id'] : (int)$lf_me['id']; foreach ($owners as $o) { ?>
                                        <option value="<?php echo (int)$o['id']; ?>" <?php if ((int)$o['id'] === $sel) echo 'selected'; ?>><?php echo e($o['username']); ?> (<?php echo lf_role_name($o['admin_type']); ?>)</option>
                                    <?php } ?>
                                </select>
                            </div>
                            <?php } ?>

                            <button type="submit" name="submit" class="btn btn-primary" style="min-width: 140px;"><?php echo $row ? 'Salvar' : 'Criar DNS'; ?></button>
                            <a href="manage_dns.php" class="btn btn-outline-default ms-1">Cancelar</a>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <h5 class="mb-3">Codigo de parceria</h5>
                        <?php if ($row) { ?>
                            <div class="lf-code lf-code--big"><div><span class="lf-code__label">Codigo desta DNS</span><span class="lf-code__value"><?php echo e($row['partner_code']); ?></span></div></div>
                            <p class="text-muted mt-3 mb-0">Para trocar o codigo use o botao <i class="ri-refresh-line"></i> na lista de DNS.</p>
                        <?php } else { ?>
                            <p class="text-muted mb-0">Ao criar a DNS o painel gera automaticamente um <b>codigo numerico unico</b>. O cliente digita esse codigo no app junto com usuario e senha, e o app conecta direto nesta DNS.</p>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include("includes/footer.php");?>
