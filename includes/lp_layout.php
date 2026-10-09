<?php
// Layout comum das paginas publicas (ativar.php e listas.php): marca, tema, abas e rodape
if (!isset($mysqli)) { exit('No direct script access allowed'); }

function lp_layout_start($title, $tab = 'ativar') {
    $cfg = lp_cfg(); $brand = lp_brand();
    $wa = lf_only_digits($cfg['whatsapp']);
    $tg = preg_replace('/[^A-Za-z0-9_]/', '', ltrim($cfg['telegram'], '@'));
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="<?php echo e($brand['theme']); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo e($title); ?> | <?php echo e($brand['name']); ?></title>
<meta name="description" content="<?php echo e($cfg['subtitle']); ?>">
<meta name="robots" content="<?php echo $tab === 'listas' ? 'noindex' : 'index'; ?>">
<meta name="theme-color" content="<?php echo e($brand['color']); ?>">
<?php if ($brand['logo']) { ?><link rel="icon" href="<?php echo e($brand['logo']); ?>"><link rel="apple-touch-icon" href="<?php echo e($brand['logo']); ?>"><?php } ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/vendors/remixicon/remixicon.min.css">
<style>
:root{--c:<?php echo $brand['color']; ?>;--bg:#f7f7f9;--card:#ffffff;--soft:#f1f1f5;--line:#e6e6ec;--txt:#16161d;--mut:#6c6c7a;--ok:#16a34a;--err:#dc2626;--sh:0 1px 2px rgba(16,16,24,.04),0 12px 40px rgba(16,16,24,.06)}
html[data-theme=dark]{--bg:#0a0a0f;--card:#121219;--soft:#191922;--line:#25252f;--txt:#ededf3;--mut:#9898a8;--sh:0 1px 2px rgba(0,0,0,.3),0 20px 50px rgba(0,0,0,.35)}
@media (prefers-color-scheme:dark){html[data-theme=auto]{--bg:#0a0a0f;--card:#121219;--soft:#191922;--line:#25252f;--txt:#ededf3;--mut:#9898a8;--sh:0 1px 2px rgba(0,0,0,.3),0 20px 50px rgba(0,0,0,.35)}}
*{box-sizing:border-box}html{scroll-behavior:smooth}
body{margin:0;font:15px/1.55 Inter,system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:var(--bg);color:var(--txt);-webkit-font-smoothing:antialiased}
body:before{content:"";position:fixed;inset:0 0 auto 0;height:420px;z-index:-1;background:radial-gradient(600px 300px at 50% -80px,color-mix(in srgb,var(--c) 22%,transparent),transparent 75%)}
a{color:inherit}
.w{max-width:560px;margin:0 auto;padding:0 16px}
.nav{display:flex;align-items:center;justify-content:space-between;padding:20px 0}
.brand{display:flex;align-items:center;gap:10px;font-weight:700;font-size:16px;text-decoration:none;min-width:0}
.brand img{height:36px;width:auto;max-width:140px;object-fit:contain;border-radius:8px}
.brand span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.link{font-size:14px;color:var(--mut);text-decoration:none;display:inline-flex;gap:6px;align-items:center}.link:hover{color:var(--txt)}
.hero{text-align:center;padding:26px 0 22px}
.pill{display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:600;color:var(--c);background:color-mix(in srgb,var(--c) 12%,transparent);padding:5px 11px;border-radius:99px}
.hero h1{font-size:clamp(28px,6vw,40px);line-height:1.12;letter-spacing:-.025em;margin:14px 0 10px}
.hero p{color:var(--mut);margin:0 auto;max-width:460px;font-size:16px}
.card{background:var(--card);border:1px solid var(--line);border-radius:20px;padding:24px;box-shadow:var(--sh)}
.lbl{display:flex;justify-content:space-between;align-items:baseline;gap:8px;font-weight:600;font-size:13.5px;margin:18px 0 7px}
.lbl small{font-weight:400;color:var(--mut);text-align:right}
form > .lbl:first-of-type{margin-top:0}
.in{width:100%;background:var(--soft);border:1px solid transparent;color:var(--txt);border-radius:12px;padding:12px 14px;font:15px Inter,sans-serif;outline:none;transition:.15s}
.in:focus{border-color:var(--c);background:var(--card);box-shadow:0 0 0 4px color-mix(in srgb,var(--c) 15%,transparent)}
textarea.in{min-height:78px;resize:vertical;word-break:break-all}
.mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;letter-spacing:.03em}
.hint{font-size:12.5px;color:var(--mut);margin-top:6px}
.seg{display:flex;background:var(--soft);border-radius:12px;padding:4px;gap:4px}
.seg label{position:relative;flex:1;text-align:center;padding:8px 4px;border-radius:9px;cursor:pointer;font-size:13px;font-weight:600;color:var(--mut);transition:.15s}
.seg input{position:absolute;opacity:0;pointer-events:none}
.seg label:has(input:checked){background:var(--card);color:var(--txt);box-shadow:0 1px 3px rgba(0,0,0,.12)}
.plans{display:grid;gap:8px}
.plan{display:flex;align-items:center;gap:12px;border:1px solid var(--line);border-radius:14px;padding:13px 14px;cursor:pointer;transition:.15s}
.plan input{accent-color:var(--c);width:17px;height:17px;margin:0;flex:none}
.plan b{display:block;font-size:14.5px}.plan small{color:var(--mut)}
.plan .pr{margin-left:auto;font-weight:700;font-size:16px;white-space:nowrap}
.plan:has(input:checked){border-color:var(--c);background:color-mix(in srgb,var(--c) 7%,var(--card))}
.chk{display:flex;gap:10px;align-items:flex-start;font-size:13px;color:var(--mut);margin-top:18px;cursor:pointer}
.chk input{accent-color:var(--c);margin-top:3px;flex:none}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:0;border-radius:12px;padding:14px 18px;font:600 15px Inter,sans-serif;cursor:pointer;text-decoration:none;transition:.15s;background:var(--soft);color:var(--txt)}
.btn-p{background:var(--c);color:#fff;width:100%}.btn-p:hover{filter:brightness(1.08)}
.btn:disabled{opacity:.6;cursor:wait}
.alert{border-radius:12px;padding:11px 13px;margin-bottom:16px;font-size:13.5px;display:flex;gap:9px;align-items:flex-start}
.a-err{background:color-mix(in srgb,var(--err) 10%,transparent);color:var(--err)}
.a-ok{background:color-mix(in srgb,var(--ok) 12%,transparent);color:var(--ok)}
.a-info{background:var(--soft);color:var(--mut)}
.secure{text-align:center;font-size:12.5px;color:var(--mut);margin:12px 0 0}
.steps{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:26px 0 0}
.step{text-align:center;font-size:12.5px;color:var(--mut)}
.step i{display:flex;align-items:center;justify-content:center;width:38px;height:38px;margin:0 auto 6px;border-radius:12px;background:color-mix(in srgb,var(--c) 12%,transparent);color:var(--c);font-size:18px}
.step b{display:block;color:var(--txt);font-size:13px}
.sec{margin-top:16px}
.row{display:flex;gap:8px}.row .in{flex:1;min-width:0}
.faq details{border-top:1px solid var(--line);padding:13px 0}.faq details:first-of-type{border-top:0;padding-top:4px}
.faq summary{cursor:pointer;font-weight:600;font-size:14px;list-style:none;display:flex;justify-content:space-between;gap:10px}.faq summary::-webkit-details-marker{display:none}
.faq summary:after{content:"+";color:var(--mut)}.faq details[open] summary:after{content:"\2013"}
.faq p{color:var(--mut);margin:7px 0 0;font-size:13.5px}
h2{font-size:17px;margin:0 0 4px}
.qr{background:#fff;border-radius:16px;padding:10px;width:220px;max-width:100%;margin:14px auto;display:block;border:1px solid var(--line)}
.amount{font-size:28px;font-weight:800;letter-spacing:-.02em}
.status{display:flex;align-items:center;gap:9px;justify-content:center;margin-top:14px;color:var(--mut);font-size:13.5px}
.spin{width:16px;height:16px;border:2px solid var(--line);border-top-color:var(--c);border-radius:50%;animation:s 1s linear infinite}@keyframes s{to{transform:rotate(360deg)}}
.big-ok{width:62px;height:62px;border-radius:50%;margin:0 auto 10px;display:flex;align-items:center;justify-content:center;background:color-mix(in srgb,var(--ok) 14%,transparent);color:var(--ok);font-size:32px}
.kv{display:grid;grid-template-columns:auto 1fr;gap:7px 16px;font-size:13.5px;margin:18px 0 6px;text-align:left;border-top:1px solid var(--line);padding-top:16px}.kv span{color:var(--mut)}.kv b{word-break:break-all}
.disc{font-size:12px;color:var(--mut);text-align:center;margin:28px auto 0;max-width:520px;line-height:1.6}
footer{color:var(--mut);text-align:center;font-size:12.5px;padding:16px 0 40px}
footer a{color:var(--mut)}
.float{position:fixed;right:16px;bottom:16px;display:flex;flex-direction:column;gap:10px;z-index:5}
@media(max-width:640px){.float{flex-direction:row;right:12px;bottom:12px}.float a{width:44px!important;height:44px!important;font-size:21px!important}body.has-float footer{padding-bottom:80px}}
.float a{width:50px;height:50px;border-radius:50%;color:#fff;display:flex;align-items:center;justify-content:center;font-size:24px;box-shadow:0 8px 24px rgba(0,0,0,.25);text-decoration:none}
.hp{position:absolute;left:-9999px;width:1px;height:1px}
[hidden]{display:none!important}
.tabs{display:flex;gap:4px;background:var(--soft);border-radius:12px;padding:4px;flex:none}
.tabs a{font-size:13px;font-weight:600;color:var(--mut);text-decoration:none;padding:7px 11px;border-radius:9px;white-space:nowrap}
.tabs a.on{background:var(--card);color:var(--txt);box-shadow:0 1px 3px rgba(0,0,0,.12)}
.list{border:1px solid var(--line);border-radius:16px;padding:16px}
.list + .list{margin-top:10px}
.list.on{border-color:var(--c);box-shadow:0 0 0 1px var(--c) inset}
.list h3{margin:0 0 6px;font-size:15.5px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.meta{display:flex;flex-wrap:wrap;gap:4px 14px;font-size:13px;color:var(--mut)}
.meta span{display:inline-flex;align-items:center;gap:5px;min-width:0;word-break:break-all}
.tag{font-size:11px;font-weight:700;letter-spacing:.03em;padding:3px 8px;border-radius:99px;background:var(--soft);color:var(--mut);white-space:nowrap}
.tag.c{background:color-mix(in srgb,var(--c) 14%,transparent);color:var(--c)}
.tag.ok{background:color-mix(in srgb,var(--ok) 14%,transparent);color:var(--ok)}
.tag.err{background:color-mix(in srgb,var(--err) 12%,transparent);color:var(--err)}
.acts{display:flex;flex-wrap:wrap;gap:6px;margin-top:12px}
.btn-s{padding:8px 12px;font-size:13px;border-radius:10px}
.btn-o{background:transparent;border:1px solid var(--line)}
.btn-o:hover{border-color:var(--c);color:var(--c)}
.btn-d:hover{border-color:var(--err);color:var(--err)}
.btn-c{background:var(--c);color:#fff}
.head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.muted{color:var(--mut)}
.linkbox{margin-top:10px;background:var(--soft);border-radius:10px;padding:10px 12px;font-size:12.5px;word-break:break-all;display:flex;gap:8px;align-items:flex-start;justify-content:space-between}
.linkbox code{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
.drawer{border-top:1px solid var(--line);margin-top:14px;padding-top:4px}
.sw{display:flex;align-items:flex-start;gap:10px;font-size:13.5px;cursor:pointer;color:var(--mut);margin-top:14px}
.sw input{accent-color:var(--c);width:16px;height:16px;margin-top:2px;flex:none}
.sw b{color:var(--txt);font-weight:600}
@media(max-width:420px){.brand span{display:none}}
</style>
</head>
<body<?php if ($wa || $tg) echo ' class="has-float"'; ?>>
<div class="w">
    <header class="nav">
        <a class="brand" href="ativar.php"><?php if ($brand['logo']) { ?><img src="<?php echo e($brand['logo']); ?>" alt="<?php echo e($brand['name']); ?>"><?php } ?><span><?php echo e($brand['name']); ?></span></a>
        <?php if ($cfg['enabled'] === '1') { ?><nav class="tabs"><a href="ativar.php" class="<?php echo $tab === 'ativar' ? 'on' : ''; ?>">Ativar</a><a href="listas.php" class="<?php echo $tab === 'listas' ? 'on' : ''; ?>">Minhas listas</a></nav><?php } ?>
    </header>
<?php
}

function lp_layout_end() {
    $cfg = lp_cfg(); $brand = lp_brand();
    $wa = lf_only_digits($cfg['whatsapp']);
    $tg = preg_replace('/[^A-Za-z0-9_]/', '', ltrim($cfg['telegram'], '@'));
    $mail = filter_var($cfg['support_email'], FILTER_VALIDATE_EMAIL) ? $cfg['support_email'] : '';
?>
    <p class="disc"><?php echo e(lf_disclaimer_text()); ?></p>
    <footer>
        <?php if (trim($cfg['footer_text']) !== '') { ?><div style="margin-bottom:6px"><?php echo e($cfg['footer_text']); ?></div><?php } ?>
        &copy; <?php echo date('Y'); ?> <?php echo e($brand['name']); ?> &middot; <a href="terms.php">Termos</a> &middot; <a href="privacy_policy.php">Privacidade</a> &middot; <a href="policy/account_delete_request.php">Excluir dados</a>
        <?php if ($mail) { ?> &middot; <a href="mailto:<?php echo e($mail); ?>">Suporte</a><?php } ?>
    </footer>
</div>
<?php if ($wa || $tg) { ?><div class="float">
    <?php if ($tg) { ?><a href="https://t.me/<?php echo e($tg); ?>" target="_blank" rel="noopener" aria-label="Telegram" style="background:#229ed9"><i class="ri-telegram-fill"></i></a><?php } ?>
    <?php if ($wa) { ?><a href="https://wa.me/<?php echo e($wa); ?>" target="_blank" rel="noopener" aria-label="WhatsApp" style="background:#22c55e"><i class="ri-whatsapp-line"></i></a><?php } ?>
</div><?php } ?>
</body>
</html>
<?php
}
