<?php
// Pagina publica simples para Politica de Privacidade e Termos (link exigido pelas lojas).
if (!isset($mysqli)) { exit('No direct script access allowed'); }
require_once(__DIR__ . '/policies.php');
require_once(__DIR__ . '/landing.php');

function lf_policy_page($title, $html) {
    $brand = lp_brand();
    $body = lf_policy_is_placeholder($html) ? lf_policy_template($title === 'Termos de Uso' ? 'terms' : 'privacy') : stripslashes((string)$html);
    header('X-Content-Type-Options: nosniff');
    ?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo e($title); ?> | <?php echo e($brand['name']); ?></title>
<?php if ($brand['logo']) { ?><link rel="icon" href="<?php echo e($brand['logo']); ?>"><?php } ?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{--c:<?php echo $brand['color']; ?>;--bg:#fafafa;--card:#fff;--txt:#18181b;--mut:#6b6b76;--line:#e7e7ea}
@media (prefers-color-scheme:dark){:root{--bg:#0b0b0f;--card:#131318;--txt:#ececf1;--mut:#9b9ba8;--line:#24242c}}
body{margin:0;background:var(--bg);color:var(--txt);font:16px/1.65 Inter,system-ui,sans-serif}
.w{max-width:760px;margin:0 auto;padding:28px 16px 60px}
.top{display:flex;align-items:center;gap:10px;font-weight:700;margin-bottom:22px;text-decoration:none;color:inherit}
.top img{height:34px;width:auto;border-radius:8px}
.card{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:28px}
h2{margin-top:0;font-size:28px}h3{margin:26px 0 6px;font-size:18px}
p,li{color:var(--mut)}b{color:var(--txt)}a{color:var(--c)}
.note{border-left:3px solid var(--c);padding:10px 14px;background:color-mix(in srgb,var(--c) 8%,transparent);border-radius:8px;margin-bottom:18px;font-size:14px}
footer{color:var(--mut);font-size:13px;text-align:center;margin-top:26px}
</style>
</head>
<body><div class="w">
<a class="top" href="ativar.php"><?php if ($brand['logo']) { ?><img src="<?php echo e($brand['logo']); ?>" alt=""><?php } ?><span><?php echo e($brand['name']); ?></span></a>
<div class="card">
<div class="note"><?php echo e(lf_disclaimer_text()); ?></div>
<?php echo lf_policy_render($body); ?>
</div>
<footer><a href="privacy_policy.php">Privacidade</a> &middot; <a href="terms.php">Termos</a> &middot; <a href="policy/account_delete_request.php">Excluir meus dados</a></footer>
</div></body></html><?php
}
