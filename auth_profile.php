<?php
    $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    $page_title = $user_id ? 'Editar usuario' : (isset($_GET['add']) ? 'Novo usuario' : 'Usuario');
    include("includes/header.php");
    require("includes/lb_helper.php");
    require("language/language.php");

    $row = null;
    if ($user_id) {
        $row = db_row("SELECT * FROM tbl_admin WHERE id = ?", array($user_id));
        if (!$row || !lf_can_manage_user($row)) {
            lf_flash('Usuario nao encontrado ou sem permissao.', 'error');
            lf_redirect('manage_admin.php');
        }
    }

    // niveis que o usuario logado pode criar
    $roles_allowed = lf_is_admin() ? array(ROLE_MASTER, ROLE_REVENDA) : array(ROLE_REVENDA);
    if ($row && (int)$row['admin_type'] === ROLE_ADMIN) $roles_allowed = array(ROLE_ADMIN);

    // possiveis "pais" na arvore: ADMIN escolhe (ele mesmo ou um MASTER); MASTER e sempre ele mesmo
    $parents = array();
    if (lf_is_admin()) {
        $parents = db_all("SELECT id, username, admin_type FROM tbl_admin WHERE admin_type IN (?, ?) AND status = 1 ORDER BY admin_type DESC, username ASC", array(ROLE_ADMIN, ROLE_MASTER));
    }

    if (isset($_POST['submit'])) {
        $back = $row ? 'auth_profile.php?user_id=' . $user_id : 'auth_profile.php?add=yes';
        if (!lf_csrf_ok()) { lf_flash('Sessao expirada. Tente novamente.', 'error'); lf_redirect($back); }

        $username = trim((string)(isset($_POST['username']) ? $_POST['username'] : ''));
        $email    = trim((string)(isset($_POST['email']) ? $_POST['email'] : ''));
        $password = (string)(isset($_POST['password']) ? $_POST['password'] : '');
        $type     = isset($_POST['admin_type']) ? (int)$_POST['admin_type'] : ROLE_REVENDA;
        $max_dns  = max(0, (int)(isset($_POST['max_dns']) ? $_POST['max_dns'] : 0));
        $max_dev  = max(0, (int)(isset($_POST['max_devices']) ? $_POST['max_devices'] : 0));
        $status   = isset($_POST['status']) && (int)$_POST['status'] === 0 ? 0 : 1;

        if (!preg_match('/^[A-Za-z0-9_.\-]{3,40}$/', $username)) { lf_flash('Usuario invalido: use de 3 a 40 letras, numeros, ponto, traco ou _ (sem espacos).', 'error'); lf_redirect($back); }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { lf_flash('Email invalido.', 'error'); lf_redirect($back); }
        if (!in_array($type, $roles_allowed, true)) { lf_flash('Voce nao pode criar este nivel de usuario.', 'error'); lf_redirect($back); }
        if (!$row && strlen($password) < 6) { lf_flash('A senha precisa ter pelo menos 6 caracteres.', 'error'); lf_redirect($back); }
        if ($row && $password !== '' && strlen($password) < 6) { lf_flash('A senha precisa ter pelo menos 6 caracteres.', 'error'); lf_redirect($back); }

        $dup = db_val("SELECT id FROM tbl_admin WHERE username = ? AND id <> ? LIMIT 1", array($username, $row ? $user_id : 0));
        if ($dup) { lf_flash('Ja existe um usuario com este nome.', 'error'); lf_redirect($back); }

        // pai na arvore
        $parent_id = (int)$lf_me['id'];
        if (lf_is_admin() && isset($_POST['parent_id'])) {
            $cand = db_row("SELECT id, admin_type FROM tbl_admin WHERE id = ?", array((int)$_POST['parent_id']));
            if ($cand && in_array((int)$cand['admin_type'], array(ROLE_ADMIN, ROLE_MASTER), true) && (!$row || (int)$cand['id'] !== $user_id)) {
                // um MASTER so pode ficar abaixo de um ADMIN
                if (!($type === ROLE_MASTER && (int)$cand['admin_type'] !== ROLE_ADMIN)) $parent_id = (int)$cand['id'];
            }
        }
        if ($type === ROLE_ADMIN) { $parent_id = 0; $status = 1; }

        if ($row) {
            // um MASTER rebaixado nao pode continuar com revendas abaixo dele
            if ((int)$row['admin_type'] === ROLE_MASTER && $type === ROLE_REVENDA) {
                db_query("UPDATE tbl_admin SET parent_id = ? WHERE parent_id = ?", array((int)$row['parent_id'], $user_id));
            }
            db_query("UPDATE tbl_admin SET username = ?, email = ?, admin_type = ?, parent_id = ?, max_dns = ?, max_devices = ?, status = ? WHERE id = ?",
                array($username, $email, $type, $parent_id, $max_dns, $max_dev, $status, $user_id));
            if ($password !== '') db_query("UPDATE tbl_admin SET password = ? WHERE id = ?", array(lf_password_hash($password), $user_id));
            // o nome do usuario e a chave "cliente" usada pelo app: mantem tudo ligado se o nome mudar
            if ($username !== $row['username']) {
                db_query("UPDATE tbl_dns SET cliente = ? WHERE owner_id = ? OR cliente = ?", array($username, $user_id, $row['username']));
                db_query("UPDATE tbl_app SET cliente = ? WHERE cliente = ?", array($username, $row['username']));
            }
            lf_flash('Usuario atualizado.');
        } else {
            db_query("INSERT INTO tbl_admin (username, password, email, image, status, admin_type, parent_id, max_dns, max_devices, created_at) VALUES (?, ?, ?, '', ?, ?, ?, ?, ?, ?)",
                array($username, lf_password_hash($password), $email, $status, $type, $parent_id, $max_dns, $max_dev, time()));
            lf_flash('Usuario criado com sucesso.');
        }
        lf_redirect('manage_admin.php');
    }

    $cur_type = $row ? (int)$row['admin_type'] : $roles_allowed[count($roles_allowed) - 1];
    $cur_parent = $row ? (int)$row['parent_id'] : (int)$lf_me['id'];
?>

<main id="nsofts_main">
    <div class="nsofts-container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb align-items-center">
                <li class="breadcrumb-item d-inline-flex"><a href="dashboard.php"><i class="ri-home-4-fill"></i></a></li>
                <li class="breadcrumb-item d-inline-flex"><a href="manage_admin.php">Usuarios</a></li>
                <li class="breadcrumb-item d-inline-flex active" aria-current="page"><?php echo e($page_title); ?></li>
            </ol>
        </nav>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <h5 class="mb-3"><?php echo e($page_title); ?></h5>
                        <form action="" method="POST" autocomplete="off">
                            <?php echo lf_csrf_field(); ?>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Nivel</label>
                                    <select name="admin_type" class="form-control" required>
                                        <?php foreach ($roles_allowed as $r) { ?>
                                            <option value="<?php echo $r; ?>" <?php if ($cur_type === $r) echo 'selected'; ?>><?php echo lf_role_name($r); ?><?php echo $r === ROLE_MASTER ? ' (cria revendas)' : ($r === ROLE_REVENDA ? ' (comum)' : ''); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>

                                <?php if ($parents && $cur_type !== ROLE_ADMIN) { ?>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Pertence a (arvore)</label>
                                    <select name="parent_id" class="form-control">
                                        <?php foreach ($parents as $p) { if ($row && (int)$p['id'] === $user_id) continue; ?>
                                            <option value="<?php echo (int)$p['id']; ?>" <?php if ((int)$p['id'] === $cur_parent) echo 'selected'; ?>><?php echo e($p['username']); ?> (<?php echo lf_role_name($p['admin_type']); ?>)</option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <?php } ?>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Usuario</label>
                                    <input type="text" name="username" class="form-control" maxlength="40" value="<?php echo $row ? e($row['username']) : ''; ?>" required>
                                    <small class="text-muted">Tambem e o <b>cliente</b> usado na URL do app (api2.php?cliente=...).</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Email</label>
                                    <input type="email" name="email" class="form-control" maxlength="190" value="<?php echo $row ? e($row['email']) : ''; ?>">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Senha <?php if ($row) echo '<small class="text-muted">(deixe vazio para nao alterar)</small>'; ?></label>
                                    <input type="text" name="password" class="form-control" autocomplete="new-password" minlength="6" <?php if (!$row) echo 'required'; ?>>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Status</label>
                                    <select name="status" class="form-control">
                                        <option value="1" <?php if (!$row || (int)$row['status'] === 1) echo 'selected'; ?>>Ativo</option>
                                        <option value="0" <?php if ($row && (int)$row['status'] === 0) echo 'selected'; ?>>Inativo (bloqueia painel e codigos)</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Limite de DNS</label>
                                    <input type="number" name="max_dns" class="form-control" min="0" step="1" value="<?php echo $row ? (int)$row['max_dns'] : 0; ?>">
                                    <small class="text-muted">Quantas DNS este usuario pode cadastrar. 0 = ilimitado.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Limite de dispositivos ativos</label>
                                    <input type="number" name="max_devices" class="form-control" min="0" step="1" value="<?php echo $row ? (int)$row['max_devices'] : 0; ?>">
                                    <small class="text-muted">Aparelhos ativos somando todas as DNS dele. 0 = ilimitado.</small>
                                </div>
                            </div>

                            <div class="mt-4">
                                <button type="submit" name="submit" class="btn btn-primary" style="min-width: 140px;"><?php echo $row ? 'Salvar' : 'Criar usuario'; ?></button>
                                <a href="manage_admin.php" class="btn btn-outline-default ms-1">Cancelar</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <h5 class="mb-3">Como funcionam os niveis</h5>
                        <p><?php echo lf_role_badge(ROLE_ADMIN); ?><br><span class="text-muted">Dono do painel. Ve e gerencia todas as DNS, usuarios e dispositivos e define o modo de login do app.</span></p>
                        <p><?php echo lf_role_badge(ROLE_MASTER); ?><br><span class="text-muted">Cria revendas e gerencia as DNS e os dispositivos de toda a sua arvore.</span></p>
                        <p class="mb-0"><?php echo lf_role_badge(ROLE_REVENDA); ?><br><span class="text-muted">Gerencia somente as proprias DNS, codigos de parceria e dispositivos.</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include("includes/footer.php");?>
