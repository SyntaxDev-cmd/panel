<?php
// ============================================================
//  PEDIDO DE EXCLUSAO DE DADOS / CONTA (link exigido pelo Google Play e demais lojas)
//  Os pedidos aparecem no painel em Configuracoes > Pedidos de exclusao.
// ============================================================
include("../includes/db_helper.php");
require_once("../includes/landing.php");
require_once("../includes/policies.php");

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');

$msg = ''; $ok = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $who  = isset($_POST['user_email']) && is_string($_POST['user_email']) ? substr(trim($_POST['user_email']), 0, 190) : '';
    $type = (isset($_POST['type']) && $_POST['type'] === 'clear') ? 'Clear Data' : 'Delete Account';
    $note = isset($_POST['note']) && is_string($_POST['note']) ? substr(trim($_POST['note']), 0, 1000) : '';
    if (!lf_csrf_ok()) {
        $msg = 'Sessao expirada. Tente novamente.';
    } else if (!empty($_POST['website'])) {
        $msg = 'Nao foi possivel enviar.';
    } else if ($who === '') {
        $msg = 'Informe o e-mail, o usuario ou o MAC do aparelho.';
    } else if (!empty($_SESSION['del_sent']) && time() - (int)$_SESSION['del_sent'] < 60) {
        $msg = 'Aguarde um minuto para enviar outro pedido.';
    } else {
        $mac = lf_norm_mac($who);
        db_query("INSERT INTO tbl_policy_deletion (policy_type, user_email, report_msg, deletion_on, status) VALUES (?, ?, ?, ?, 0)",
            array($type, $mac !== '' ? $mac : $who, $note, time()));
        $_SESSION['del_sent'] = time();
        $ok = true;
        $msg = 'Pedido recebido. Seus dados serao removidos em ate 30 dias (exceto o que a lei obrigar a manter).';
    }
}
$brand = lp_brand();
$logo = $brand['logo'] ? '../' . $brand['logo'] : '';
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="<?php echo e($brand['theme']); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Excluir meus dados | <?php echo e($brand['name']); ?></title>
<?php if ($logo) { ?><link rel="icon" href="<?php echo e($logo); ?>"><?php } ?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{--c:<?php echo $brand['color']; ?>;--bg:#f7f7f9;--card:#fff;--soft:#f1f1f5;--txt:#16161d;--mut:#6c6c7a;--line:#e6e6ec}
html[data-theme=dark]{--bg:#0a0a0f;--card:#121219;--soft:#191922;--txt:#ededf3;--mut:#9898a8;--line:#25252f}
@media (prefers-color-scheme:dark){html[data-theme=auto]{--bg:#0a0a0f;--card:#121219;--soft:#191922;--txt:#ededf3;--mut:#9898a8;--line:#25252f}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--txt);font:15px/1.6 Inter,system-ui,sans-serif}
.w{max-width:480px;margin:0 auto;padding:28px 16px 50px}
.top{display:flex;align-items:center;gap:10px;font-weight:700;margin-bottom:20px;text-decoration:none;color:inherit}.top img{height:34px;border-radius:8px}
.card{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:24px}
h1{font-size:22px;margin:0 0 6px}p{color:var(--mut);margin:0 0 14px}
label{display:block;font-weight:600;font-size:13.5px;margin:14px 0 6px}
.in{width:100%;background:var(--soft);border:1px solid transparent;color:var(--txt);border-radius:12px;padding:12px 14px;font:15px Inter,sans-serif;outline:none}.in:focus{border-color:var(--c)}
.opt{display:flex;gap:10px;align-items:flex-start;border:1px solid var(--line);border-radius:12px;padding:12px;margin-top:8px;cursor:pointer;font-weight:400;color:var(--mut)}.opt b{color:var(--txt)}
.opt input{accent-color:var(--c);margin-top:4px}
button{width:100%;margin-top:18px;border:0;border-radius:12px;padding:14px;background:var(--c);color:#fff;font:600 15px Inter,sans-serif;cursor:pointer}
.alert{border-radius:12px;padding:11px 13px;margin-bottom:14px;font-size:13.5px}
.ok{background:rgba(22,163,74,.12);color:#16a34a}.err{background:rgba(220,38,38,.1);color:#dc2626}
small{color:var(--mut)}footer{text-align:center;font-size:12.5px;color:var(--mut);margin-top:22px}footer a{color:var(--mut)}
.hp{position:absolute;left:-9999px}
</style>
</head>
<body><div class="w">
<a class="top" href="../ativar.php"><?php if ($logo) { ?><img src="<?php echo e($logo); ?>" alt=""><?php } ?><span><?php echo e($brand['name']); ?></span></a>
<div class="card">
    <h1>Excluir meus dados</h1>
    <p>Peca a exclusao dos dados do seu aparelho e da sua ativacao no <?php echo e($brand['name']); ?>.</p>
    <?php if ($msg) { ?><div class="alert <?php echo $ok ? 'ok' : 'err'; ?>"><?php echo e($msg); ?></div><?php } ?>
    <?php if (!$ok) { ?>
    <form method="post" autocomplete="off">
        <?php echo lf_csrf_field(); ?>
        <input type="text" name="website" class="hp" tabindex="-1" aria-hidden="true">
        <label for="u">E-mail, usuario ou MAC do aparelho</label>
        <input class="in" id="u" name="user_email" maxlength="190" required placeholder="voce@email.com ou A1:B2:C3:D4:E5:F6">
        <label>O que deseja fazer</label>
        <label class="opt"><input type="radio" name="type" value="delete" checked> <span><b>Excluir conta e todos os dados</b><br>Remove a ativacao do aparelho, os dados de acesso e o historico.</span></label>
        <label class="opt"><input type="radio" name="type" value="clear"> <span><b>Apagar somente os dados coletados</b><br>Remove IP, modelo e registros de acesso.</span></label>
        <label for="n">Observacao <small>(opcional)</small></label>
        <textarea class="in" id="n" name="note" rows="3" maxlength="1000"></textarea>
        <button type="submit">Enviar pedido</button>
        <p style="margin:12px 0 0;font-size:12.5px">O pedido e analisado e concluido em ate 30 dias. Depois de excluidos, os dados nao podem ser recuperados.</p>
    </form>
    <?php } ?>
</div>
<footer><?php echo e(lf_disclaimer_text()); ?><br><br><a href="../privacy_policy.php">Privacidade</a> &middot; <a href="../terms.php">Termos</a></footer>
</div></body></html>
