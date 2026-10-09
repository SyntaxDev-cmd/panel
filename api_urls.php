<?php $page_title="URLs e API";
    include("includes/header.php");
    require("includes/lb_helper.php");

    $base = getBaseUrl();
    $urls = array(
        'URL do app (configEndpoint)' => $base . 'api2.php?cliente=' . rawurlencode($lf_me['username']),
        'Login por codigo de parceria (POST)' => $base . 'api2.php?action=partner_login',
        'Sinal de vida do dispositivo (POST)' => $base . 'api2.php?action=heartbeat',
        'Ativacao por MAC (POST)' => $base . 'api2.php?action=device_login',
        'Landing page de ativacao (clientes)' => $base . 'ativar.php',
        'Politica de privacidade' => $base . 'privacy_policy.php',
        'Termos de uso' => $base . 'terms.php',
        'Exclusao de conta' => $base . 'policy/account_delete_request.php'
    );
?>

<main id="nsofts_main">
    <div class="nsofts-container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb align-items-center">
                <li class="breadcrumb-item d-inline-flex"><a href="dashboard.php"><i class="ri-home-4-fill"></i></a></li>
                <li class="breadcrumb-item d-inline-flex active" aria-current="page"><?php echo e($page_title); ?></li>
            </ol>
        </nav>

        <div class="card mb-4">
            <div class="card-body p-4">
                <h5 class="mb-3">Enderecos</h5>
                <?php $n = 0; foreach ($urls as $label => $url) { $n++; ?>
                    <div class="pb-clipboard mb-2">
                        <span class="pb-clipboard__url"><b style="color: var(--ns-primary);"><?php echo e($label); ?>: </b><span id="clip_<?php echo $n; ?>"><?php echo e($url); ?></span></span>
                        <a class="pb-clipboard__link btn_clip" href="javascript:void(0);" data-target="clip_<?php echo $n; ?>" title="Copiar">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" /></svg>
                        </a>
                    </div>
                <?php } ?>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-4">
                <h5 class="mb-3">Como os apps (Android, Roku, Samsung, LG) usam a API</h5>
                <ol class="lf-steps">
                    <li><b>Abrir o app:</b> <code>GET api2.php?cliente=USUARIO</code> devolve a aparencia, <code>login_mode</code> (<code>direct</code> ou <code>code</code>) e, no modo direto, a lista <code>servidores</code>.</li>
                    <li><b>Modo codigo:</b> o app envia <code>POST api2.php?action=partner_login</code> com <code>code</code>, <code>device_id</code>, <code>mac</code>, <code>platform</code> (android | roku | samsung | lg), <code>model</code>, <code>app_version</code> e <code>username</code>. Resposta: <code>{"status":"success","servidores":[{"title":"...","hdPosterUrl":"http://dns:porta"}]}</code>. Erros: <code>invalid_code</code>, <code>blocked</code>, <code>limit</code>.</li>
                    <li><b>Depois do login e a cada 2 minutos:</b> <code>POST api2.php?action=heartbeat</code> com <code>code</code> (ou <code>cliente</code> + <code>host</code> no modo direto), <code>device_id</code>, <code>mac</code>, <code>platform</code>, <code>username</code>. Se responder <code>{"status":"blocked"}</code> o app deve sair para a tela de login.</li>
                </ol>
                <p class="text-muted mb-0">Os dados podem ir em JSON no corpo, em formulario (POST) ou na propria URL. Apps antigos que so chamam o item 1 continuam funcionando no modo direto e aparecem em Dispositivos como "sem ID".</p>
            </div>
        </div>
    </div>
</main>

<?php include("includes/footer.php");?>

<script type="text/javascript">
    $(document).on("click", ".btn_clip", function () {
        var ok = copyToClipboard(document.getElementById($(this).data("target")));
        $.notify(ok ? 'Copiado!' : 'Nao foi possivel copiar.', { position: "top right", className: ok ? 'success' : 'error' });
    });
</script>
