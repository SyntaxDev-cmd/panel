<?php
    $page_title = "Landing page e Mercado Pago";
    include("includes/header.php");
    require("includes/lb_helper.php");
    require("language/language.php");
    require_once("includes/landing.php");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!lf_csrf_ok()) { lf_flash('Sessao expirada. Tente novamente.', 'error'); lf_redirect('settings_landing.php'); }
        $do = isset($_POST['do']) ? (string)$_POST['do'] : '';

        if ($do === 'config') {
            $p = function ($k, $max = 255) { return isset($_POST[$k]) ? substr(trim((string)$_POST[$k]), 0, $max) : ''; };
            $vals = array(
                'enabled' => isset($_POST['enabled']) ? '1' : '0',
                'owner_id' => (string)(int)$p('owner_id'),
                'title' => $p('title', 120),
                'subtitle' => $p('subtitle', 400),
                'whatsapp' => lf_only_digits($p('whatsapp', 30)),
                'color' => preg_match('/^#[0-9a-fA-F]{6}$/', $p('color')) ? $p('color') : '#7c3aed',
                'pay_pix' => isset($_POST['pay_pix']) ? '1' : '0',
                'pay_card' => isset($_POST['pay_card']) ? '1' : '0',
                'pix_minutes' => (string)max(10, min(1440, (int)$p('pix_minutes'))),
                'check_m3u' => isset($_POST['check_m3u']) ? '1' : '0',
                'mp_public_key' => $p('mp_public_key', 200),
            );
            if ($vals['pay_pix'] === '0' && $vals['pay_card'] === '0') $vals['pay_pix'] = '1';
            // campos secretos: em branco = mantem o que ja esta salvo
            foreach (array('mp_access_token', 'mp_webhook_secret') as $k) {
                $v = $p($k, 300);
                if ($v !== '') $vals[$k] = $v;
                if (isset($_POST['clear_' . $k])) $vals[$k] = '';
            }
            if ($vals['title'] === '') $vals['title'] = 'Ative seu aplicativo';
            lp_cfg_save($vals);

            if (!empty($vals['mp_access_token'])) {
                // confere o token na hora
                $r = lp_http('GET', MP_API . '/users/me', null, array('Authorization: Bearer ' . $vals['mp_access_token']), 15);
                if ($r['code'] === 200 && is_array($r['json'])) lf_flash('Configuracao salva. Mercado Pago conectado: ' . (isset($r['json']['nickname']) ? $r['json']['nickname'] : 'ok') . '.');
                else lf_flash('Configuracao salva, mas o Access Token foi recusado pelo Mercado Pago (HTTP ' . (int)$r['code'] . '). Confira o token.', 'error');
            } else {
                lf_flash('Configuracao salva.');
            }
            lf_redirect('settings_landing.php');
        }

        if ($do === 'plan_save') {
            $id = (int)(isset($_POST['plan_id']) ? $_POST['plan_id'] : 0);
            $name = substr(trim((string)(isset($_POST['name']) ? $_POST['name'] : '')), 0, 80);
            $days = max(0, min(3650, (int)(isset($_POST['days']) ? $_POST['days'] : 0)));
            $price = (float)str_replace(',', '.', (string)(isset($_POST['price']) ? $_POST['price'] : '0'));
            $price = max(0, min(99999, round($price, 2)));
            $sort = (int)(isset($_POST['sort']) ? $_POST['sort'] : 0);
            if ($name === '') { lf_flash('Informe o nome do plano.', 'error'); lf_redirect('settings_landing.php#planos'); }
            if ($price > 0 && $price < 1) { lf_flash('O Mercado Pago exige valor minimo de R$ 1,00 (use 0 para teste gratis).', 'error'); lf_redirect('settings_landing.php#planos'); }
            if ($id > 0) db_query("UPDATE tbl_lp_plans SET name = ?, days = ?, price = ?, sort = ? WHERE id = ?", array($name, $days, number_format($price, 2, '.', ''), $sort, $id));
            else db_query("INSERT INTO tbl_lp_plans (name, days, price, status, sort) VALUES (?, ?, ?, 1, ?)", array($name, $days, number_format($price, 2, '.', ''), $sort));
            lf_flash('Plano salvo.');
            lf_redirect('settings_landing.php#planos');
        }
        if ($do === 'plan_toggle' || $do === 'plan_delete') {
            $id = (int)(isset($_POST['plan_id']) ? $_POST['plan_id'] : 0);
            if ($do === 'plan_delete') db_query("DELETE FROM tbl_lp_plans WHERE id = ?", array($id));
            else db_query("UPDATE tbl_lp_plans SET status = 1 - status WHERE id = ?", array($id));
            lf_flash($do === 'plan_delete' ? 'Plano excluido.' : 'Plano atualizado.');
            lf_redirect('settings_landing.php#planos');
        }
    }

    $cfg = lp_cfg();
    $plans = db_all("SELECT * FROM tbl_lp_plans ORDER BY sort ASC, price ASC, id ASC");
    $admins = db_all("SELECT id, username, admin_type FROM tbl_admin WHERE status = 1 ORDER BY admin_type DESC, username ASC");
    $owner = lp_owner();
    $edit = isset($_GET['plan']) ? db_row("SELECT * FROM tbl_lp_plans WHERE id = ?", array((int)$_GET['plan'])) : null;
    $base = lp_base_url();
    $landing_url = $base . 'ativar.php';
    $webhook_url = $base . 'mp_webhook.php';
    $mask = function ($v) { $v = (string)$v; return $v === '' ? '' : substr($v, 0, 8) . str_repeat('*', 12) . substr($v, -4); };
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
            <div>Pagina publica onde o cliente ativa o proprio aparelho: informa o <b>MAC</b> e o <b>link M3U</b>, paga pelo <b>Mercado Pago</b> e o MAC e ativado na hora (igual a "Ativar aparelho por MAC"). <b>Os apps nao precisam de nenhuma alteracao</b>: eles ja consultam a ativacao pelo MAC ao abrir.</div>
        </div>

        <div class="card mb-4">
            <div class="card-body p-4">
                <h5 class="mb-3">Enderecos</h5>
                <div class="pb-clipboard mb-2"><span class="pb-clipboard__url"><b style="color: var(--ns-primary);">Landing page (envie para os clientes): </b><span id="clip_lp"><?php echo e($landing_url); ?></span></span>
                    <a class="pb-clipboard__link btn_clip" href="javascript:void(0);" data-target="clip_lp" title="Copiar"><i class="ri-file-copy-line"></i></a></div>
                <div class="pb-clipboard mb-2"><span class="pb-clipboard__url"><b style="color: var(--ns-primary);">Webhook do Mercado Pago: </b><span id="clip_wh"><?php echo e($webhook_url); ?></span></span>
                    <a class="pb-clipboard__link btn_clip" href="javascript:void(0);" data-target="clip_wh" title="Copiar"><i class="ri-file-copy-line"></i></a></div>
                <a href="ativar.php" target="_blank" class="btn btn-outline-primary mt-2"><i class="ri-external-link-line"></i> Abrir landing page</a>
                <a href="landing_orders.php" class="btn btn-outline-primary mt-2 ms-1"><i class="ri-shopping-bag-3-line"></i> Ver pedidos</a>
            </div>
        </div>

        <form action="" method="POST" autocomplete="off">
            <?php echo lf_csrf_field(); ?>
            <input type="hidden" name="do" value="config">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card h-100"><div class="card-body p-4">
                        <h5 class="mb-3">Landing page</h5>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="enabled" id="lp_enabled" <?php if ($cfg['enabled'] === '1') echo 'checked'; ?>>
                            <label class="form-check-label fw-semibold" for="lp_enabled">Landing page ligada</label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Titulo</label>
                            <input type="text" name="title" class="form-control" maxlength="120" value="<?php echo e($cfg['title']); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Texto de apresentacao</label>
                            <textarea name="subtitle" class="form-control" rows="3" maxlength="400"><?php echo e($cfg['subtitle']); ?></textarea>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-sm-7">
                                <label class="form-label fw-semibold">WhatsApp do suporte</label>
                                <input type="text" name="whatsapp" class="form-control" placeholder="5511999999999" value="<?php echo e($cfg['whatsapp']); ?>">
                            </div>
                            <div class="col-sm-5">
                                <label class="form-label fw-semibold">Cor</label>
                                <input type="color" name="color" class="form-control form-control-color w-100" value="<?php echo e($cfg['color']); ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Dono das ativacoes do site</label>
                            <select name="owner_id" class="form-control">
                                <?php foreach ($admins as $a) { ?>
                                    <option value="<?php echo (int)$a['id']; ?>" <?php if ($owner && (int)$owner['id'] === (int)$a['id']) echo 'selected'; ?>><?php echo e($a['username']); ?> (<?php echo lf_role_name($a['admin_type']); ?>)</option>
                                <?php } ?>
                            </select>
                            <small class="text-muted">Os aparelhos ativados pelo site aparecem em Dispositivos deste usuario.</small>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="check_m3u" id="check_m3u" <?php if ($cfg['check_m3u'] === '1') echo 'checked'; ?>>
                            <label class="form-check-label" for="check_m3u">Conferir usuario/senha do link M3U no servidor antes de cobrar</label>
                        </div>
                    </div></div>
                </div>

                <div class="col-lg-6">
                    <div class="card h-100"><div class="card-body p-4">
                        <h5 class="mb-1">Mercado Pago</h5>
                        <p class="text-muted"><small>Pegue as credenciais de <b>producao</b> em <a href="https://www.mercadopago.com.br/developers/panel/app" target="_blank" rel="noopener">mercadopago.com.br/developers</a> &rsaquo; Suas integracoes &rsaquo; Credenciais.</small></p>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Access Token</label>
                            <input type="password" name="mp_access_token" class="form-control lf-mono" placeholder="<?php echo $cfg['mp_access_token'] !== '' ? e($mask($cfg['mp_access_token'])) . ' (salvo - deixe em branco para manter)' : 'APP_USR-...'; ?>">
                            <?php if ($cfg['mp_access_token'] !== '') { ?><div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="clear_mp_access_token" id="c1"><label class="form-check-label small" for="c1">Remover token</label></div><?php } ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Public Key <span class="text-muted fw-normal">- opcional</span></label>
                            <input type="text" name="mp_public_key" class="form-control lf-mono" placeholder="APP_USR-..." value="<?php echo e($cfg['mp_public_key']); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Assinatura secreta do webhook <span class="text-muted fw-normal">- opcional</span></label>
                            <input type="password" name="mp_webhook_secret" class="form-control lf-mono" placeholder="<?php echo $cfg['mp_webhook_secret'] !== '' ? 'salva - deixe em branco para manter' : 'Webhooks > Assinatura secreta'; ?>">
                            <?php if ($cfg['mp_webhook_secret'] !== '') { ?><div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="clear_mp_webhook_secret" id="c2"><label class="form-check-label small" for="c2">Remover assinatura</label></div><?php } ?>
                        </div>
                        <label class="form-label fw-semibold">Formas de pagamento</label>
                        <div class="d-flex flex-wrap mb-3" style="gap:18px">
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="pay_pix" id="pp" <?php if ($cfg['pay_pix'] === '1') echo 'checked'; ?>><label class="form-check-label" for="pp">Pix (QR Code na pagina)</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="pay_card" id="pc" <?php if ($cfg['pay_card'] === '1') echo 'checked'; ?>><label class="form-check-label" for="pc">Cartao (Checkout Mercado Pago)</label></div>
                        </div>
                        <div class="mb-3" style="max-width:220px">
                            <label class="form-label fw-semibold">Validade do Pix (minutos)</label>
                            <input type="number" name="pix_minutes" class="form-control" min="10" max="1440" value="<?php echo (int)$cfg['pix_minutes']; ?>">
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="lf-dot <?php echo lp_mp_ready() ? 'on' : ''; ?>"></span>
                            <span class="ms-1"><?php echo lp_mp_ready() ? 'Token configurado' : 'Sem token: so planos gratis funcionam'; ?></span>
                        </div>
                    </div></div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-4" style="min-width: 160px;">Salvar configuracao</button>
        </form>

        <div class="card mt-4" id="planos">
            <div class="card-body p-4">
                <h5 class="mb-1">Planos</h5>
                <p class="text-muted"><small>Dias = tempo de ativacao do MAC (0 = sem vencimento). Preco 0 = teste gratis (1 vez por aparelho). Quando vence, o app volta para a tela de login normal.</small></p>

                <?php if ($plans) { ?>
                <div class="table-responsive mb-4">
                    <table class="table lf-table mb-0">
                        <thead><tr><th>Plano</th><th>Dias</th><th>Preco</th><th>Ordem</th><th class="text-center">Ativo</th><th class="text-center">Acoes</th></tr></thead>
                        <tbody>
                        <?php foreach ($plans as $p) { ?>
                            <tr>
                                <td><b><?php echo e($p['name']); ?></b></td>
                                <td><?php echo (int)$p['days'] > 0 ? (int)$p['days'] : 'sem vencimento'; ?></td>
                                <td><?php echo (float)$p['price'] > 0 ? lp_money($p['price']) : '<span class="lf-badge lf-badge--ok">GRATIS</span>'; ?></td>
                                <td><?php echo (int)$p['sort']; ?></td>
                                <td class="text-center">
                                    <form method="post" class="d-inline"><?php echo lf_csrf_field(); ?><input type="hidden" name="do" value="plan_toggle"><input type="hidden" name="plan_id" value="<?php echo (int)$p['id']; ?>">
                                        <button class="btn btn-sm <?php echo (int)$p['status'] === 1 ? 'btn-success' : 'btn-outline-default'; ?>"><?php echo (int)$p['status'] === 1 ? 'Ativo' : 'Desligado'; ?></button></form>
                                </td>
                                <td class="text-center text-nowrap">
                                    <a href="settings_landing.php?plan=<?php echo (int)$p['id']; ?>#planos" class="btn btn-outline-primary rounded-pill btn-icon" title="Editar"><i class="ri-pencil-line"></i></a>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Excluir este plano?');"><?php echo lf_csrf_field(); ?><input type="hidden" name="do" value="plan_delete"><input type="hidden" name="plan_id" value="<?php echo (int)$p['id']; ?>">
                                        <button class="btn btn-outline-danger rounded-pill btn-icon" title="Excluir"><i class="ri-delete-bin-line"></i></button></form>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php } ?>

                <h6 class="mb-2"><?php echo $edit ? 'Editar plano' : 'Novo plano'; ?></h6>
                <form method="post" class="row g-2 align-items-end">
                    <?php echo lf_csrf_field(); ?>
                    <input type="hidden" name="do" value="plan_save">
                    <input type="hidden" name="plan_id" value="<?php echo $edit ? (int)$edit['id'] : 0; ?>">
                    <div class="col-md-4"><label class="form-label small">Nome</label><input type="text" name="name" class="form-control" maxlength="80" placeholder="Ex.: Mensal" value="<?php echo $edit ? e($edit['name']) : ''; ?>" required></div>
                    <div class="col-4 col-md-2"><label class="form-label small">Dias</label><input type="number" name="days" class="form-control" min="0" max="3650" value="<?php echo $edit ? (int)$edit['days'] : 30; ?>"></div>
                    <div class="col-4 col-md-2"><label class="form-label small">Preco (R$)</label><input type="text" name="price" class="form-control" placeholder="25,00" value="<?php echo $edit ? e(number_format((float)$edit['price'], 2, ',', '')) : ''; ?>"></div>
                    <div class="col-4 col-md-2"><label class="form-label small">Ordem</label><input type="number" name="sort" class="form-control" value="<?php echo $edit ? (int)$edit['sort'] : 0; ?>"></div>
                    <div class="col-md-2"><button class="btn btn-primary w-100"><?php echo $edit ? 'Salvar' : 'Adicionar'; ?></button></div>
                </form>
                <?php if ($edit) { ?><a href="settings_landing.php#planos" class="small">Cancelar edicao</a><?php } ?>
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
