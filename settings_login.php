<?php
    $page_title = "Modo de login do app";
    include("includes/header.php");
    require("includes/lb_helper.php");
    require("language/language.php");

    if (isset($_POST['submit'])) {
        if (!lf_csrf_ok()) { lf_flash('Sessao expirada. Tente novamente.', 'error'); lf_redirect('settings_login.php'); }
        $mode = (isset($_POST['login_mode']) && $_POST['login_mode'] === 'code') ? 'code' : 'direct';
        db_query("UPDATE tbl_settings SET login_mode = ? WHERE id = 1", array($mode));
        lf_flash($mode === 'code' ? 'Login por codigo de parceria ATIVADO.' : 'Login direto (usuario e senha) ativado.');
        lf_redirect('settings_login.php');
    }
    $mode = lf_login_mode();
?>

<main id="nsofts_main">
    <div class="nsofts-container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb align-items-center">
                <li class="breadcrumb-item d-inline-flex"><a href="dashboard.php"><i class="ri-home-4-fill"></i></a></li>
                <li class="breadcrumb-item d-inline-flex active" aria-current="page"><?php echo e($page_title); ?></li>
            </ol>
        </nav>

        <div class="card">
            <div class="card-body p-4">
                <h5 class="mb-1"><?php echo e($page_title); ?></h5>
                <p class="text-muted">Escolha como o cliente entra no aplicativo. A mudanca vale para todos os apps na proxima abertura.</p>

                <form action="" method="POST">
                    <?php echo lf_csrf_field(); ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="lf-choice <?php echo $mode === 'code' ? 'is-on' : ''; ?>">
                                <input type="radio" name="login_mode" value="code" <?php if ($mode === 'code') echo 'checked'; ?>>
                                <div class="lf-choice__icon"><i class="ri-key-2-line"></i></div>
                                <div>
                                    <b>Codigo de parceria + usuario e senha</b>
                                    <p>O cliente digita o <b>codigo numerico</b> da DNS, o usuario e a senha. O app conecta somente na DNS daquele codigo. As DNS nao ficam expostas no app.</p>
                                </div>
                            </label>
                        </div>
                        <div class="col-md-6">
                            <label class="lf-choice <?php echo $mode === 'direct' ? 'is-on' : ''; ?>">
                                <input type="radio" name="login_mode" value="direct" <?php if ($mode === 'direct') echo 'checked'; ?>>
                                <div class="lf-choice__icon"><i class="ri-login-box-line"></i></div>
                                <div>
                                    <b>Direto: somente usuario e senha</b>
                                    <p>O app recebe as DNS do <b>cliente</b> configurado nele e testa o usuario e a senha em cada uma (funcionamento antigo).</p>
                                </div>
                            </label>
                        </div>
                    </div>
                    <button type="submit" name="submit" class="btn btn-primary mt-4" style="min-width: 140px;">Salvar</button>
                </form>
            </div>
        </div>

        <div class="lf-note mt-3">
            <i class="ri-information-line"></i>
            <div>Apps antigos (sem campo de codigo) so funcionam no modo <b>direto</b>. Antes de ativar o codigo, atualize os apps dos clientes para a versao com o campo "Codigo de parceria".</div>
        </div>
    </div>
</main>

<?php include("includes/footer.php");?>

<script type="text/javascript">
    $(document).on('change', 'input[name="login_mode"]', function () {
        $('.lf-choice').removeClass('is-on');
        $(this).closest('.lf-choice').addClass('is-on');
    });
</script>
