<?php
include("db_helper.php");
include("session_check.php");

$currentFile = basename($_SERVER["SCRIPT_NAME"]);

// ---------- permissoes por pagina ----------
$lf_me = lf_me();
if (!$lf_me || (int)$lf_me['status'] !== 1) {
    session_destroy();
    header("Location:index.php");
    exit;
}
// mantem a sessao igual ao banco (troca de nivel/nome vale na hora)
$_SESSION['admin_type'] = (int)$lf_me['admin_type'];
$_SESSION['admin_name'] = $lf_me['username'];

$lf_only_admin  = array('settings.php', 'settings_app.php', 'settings_login.php', 'settings_landing.php', 'landing_orders.php');
$lf_users_pages = array('manage_admin.php', 'auth_profile.php');
if ((in_array($currentFile, $lf_only_admin) && !lf_is_admin()) || (in_array($currentFile, $lf_users_pages) && !lf_can_manage_users())) {
    lf_flash('Voce nao tem permissao para acessar esta pagina.', 'error');
    header("Location:dashboard.php");
    exit;
}

function lf_nav_active($files) { global $currentFile; return in_array($currentFile, (array)$files) ? 'active' : ''; }
$lf_settings_pages = array('settings.php', 'settings_app.php', 'settings_login.php', 'settings_landing.php');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?php echo e(lf_csrf()); ?>">

    <title><?php echo (isset($page_title)) ? e($page_title).' | '.e(APP_NAME) : e(APP_NAME); ?></title>

    <link href="images/<?php echo e(APP_LOGO);?>" rel="icon" sizes="32x32">
    <link href="images/<?php echo e(APP_LOGO);?>" rel="icon" sizes="192x192">
    <link rel="apple-touch-icon" href="images/<?php echo e(APP_LOGO);?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Vendor styles -->
    <link rel="stylesheet" href="assets/vendors/bootstrap/bootstrap.min.css" type="text/css">
    <link rel="stylesheet" href="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.css" type="text/css">
    <link rel="stylesheet" href="assets/vendors/remixicon/remixicon.min.css" type="text/css">
    <link rel="stylesheet" href="assets/vendors/quill/quill.min.css" type="text/css">
    <link rel="stylesheet" href="assets/vendors/select2/select2.min.css" type="text/css">

    <!-- Main style + tema moderno -->
    <link rel="stylesheet" href="assets/css/styles.css" type="text/css">
    <link rel="stylesheet" href="assets/css/modern.css?v=2" type="text/css">
</head>
<body class="lf-body">

    <!-- Loader -->
    <div id="nsofts_loader">
        <div class="text-center">
            <i class="ri-3x ri-donut-chart-line nsofts-loader-icon"></i>
            <span class="d-block">Carregando</span>
        </div>
    </div>

    <!-- Start: header -->
    <header id="nsofts_header">
        <a href="javascript:void(0)" id="nsofts_hamburger">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 18" width="18" height="18" class="nsofts-hamburger">
                <line x1="0" y1="50%" x2="100%" y2="50%" class="nsofts-hamburger__bar-1" />
                <line x1="0" y1="50%" x2="100%" y2="50%" class="nsofts-hamburger__bar-2" />
                <line x1="0" y1="50%" x2="100%" y2="50%" class="nsofts-hamburger__bar-3" />
            </svg>
        </a>

        <a href="dashboard.php" id="nsofts_brand">
            <?php if (APP_LOGO !='' AND file_exists('images/'.APP_LOGO)) { ?>
                <img src="images/<?php echo e(APP_LOGO);?>" alt="" style="width: 30px; border-radius: 6px; margin-right: 10px;">
            <?php } else { ?>
                <img src="assets/images/300x300.jpg" alt="" style="width: 30px; border-radius: 6px; margin-right: 10px;">
            <?php } ?>
            <span><?php echo e(APP_NAME);?></span>
        </a>

        <!-- Header options -->
        <ul class="nsofts-header-nav ms-auto">
            <li class="nsofts-header-nav__item d-none d-sm-flex align-items-center me-2">
                <?php echo lf_role_badge($lf_me['admin_type']); ?>
            </li>
            <li class="nsofts-header-nav__item">
                <a href="javascript:void(0)" id="nsofts_theme_toggler" class="nsofts-header-nav__link" title="Tema claro/escuro">
                    <i class="ri-moon-fill nsofts-theme-dark"></i>
                    <i class="ri-sun-fill nsofts-theme-light"></i>
                </a>
            </li>
            <li class="nsofts-header-nav__item dropdown">
                <a href="javascript:void(0)" class="nsofts-header-nav__link" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="ri-user-fill"></i>
                </a>
                <div class="dropdown-menu mt-3">
                    <div class="px-3 py-2">
                        <div class="nsofts-avatar">
                            <div class="nsofts-avatar__image">
                                <?php if (defined('PROFILE_IMG') AND PROFILE_IMG !='' AND file_exists('images/'.PROFILE_IMG)) { ?>
                                    <img src="images/<?php echo e(PROFILE_IMG); ?>" alt="">
                                <?php } else { ?>
                                    <img src="assets/images/user_photo.png" alt="">
                                <?php } ?>
                            </div>
                            <div class="ps-2">
                                <span class="d-block fw-semibold"><?php echo e($lf_me['username']); ?></span>
                                <small class="text-muted"><?php echo lf_role_name($lf_me['admin_type']); ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item dropdown-item--group" href="auth_profile_edit.php">
                        <i class="ri-user-line"></i>
                        <span>Meu perfil</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item dropdown-item--group" href="logout.php">
                        <i class="ri-shut-down-line"></i>
                        <span>Sair</span>
                    </a>
                </div>
            </li>
        </ul>
    </header>
    <!-- End: header -->

    <!-- Start: sidebar -->
    <aside id="nsofts_sidebar">
        <nav class="nsofts-sidebar-nav" data-scroll="true">
            <ul>
                <li class="nsofts-sidebar-nav__item">
                    <a href="dashboard.php" class="nsofts-sidebar-nav__link <?php echo lf_nav_active('dashboard.php'); ?>">
                        <i class="ri-dashboard-3-line nsofts-sidebar-nav__icon"></i>
                        <span class="nsofts-sidebar-nav__text">Painel</span>
                    </a>
                </li>

                <li class="nsofts-sidebar-nav__item">
                    <a href="manage_dns.php" class="nsofts-sidebar-nav__link <?php echo lf_nav_active(array('create_dns.php', 'manage_dns.php')); ?>">
                        <i class="ri-server-line nsofts-sidebar-nav__icon"></i>
                        <span class="nsofts-sidebar-nav__text">DNS e Codigos</span>
                    </a>
                </li>

                <li class="nsofts-sidebar-nav__item">
                    <a href="manage_devices.php" class="nsofts-sidebar-nav__link <?php echo lf_nav_active('manage_devices.php'); ?>">
                        <i class="ri-device-line nsofts-sidebar-nav__icon"></i>
                        <span class="nsofts-sidebar-nav__text">Dispositivos</span>
                    </a>
                </li>

                <?php if (lf_can_manage_users()) { ?>
                <li class="nsofts-sidebar-nav__item">
                    <a href="manage_admin.php" class="nsofts-sidebar-nav__link <?php echo lf_nav_active(array('manage_admin.php', 'auth_profile.php')); ?>">
                        <i class="ri-group-line nsofts-sidebar-nav__icon"></i>
                        <span class="nsofts-sidebar-nav__text"><?php echo lf_is_admin() ? 'Usuarios e Revendas' : 'Minhas Revendas'; ?></span>
                    </a>
                </li>
                <?php } ?>

                <li class="nsofts-sidebar-nav__item">
                    <a href="settings_api.php" class="nsofts-sidebar-nav__link <?php echo lf_nav_active('settings_api.php'); ?>">
                        <i class="ri-palette-line nsofts-sidebar-nav__icon"></i>
                        <span class="nsofts-sidebar-nav__text">Aparencia do App</span>
                    </a>
                </li>

                <?php if (lf_is_admin()) { ?>
                <li class="nsofts-sidebar-nav__item">
                    <a href="landing_orders.php" class="nsofts-sidebar-nav__link <?php echo lf_nav_active('landing_orders.php'); ?>">
                        <i class="ri-shopping-bag-3-line nsofts-sidebar-nav__icon"></i>
                        <span class="nsofts-sidebar-nav__text">Ativacoes pelo site</span>
                    </a>
                </li>

                <li class="nsofts-sidebar-nav__item nsofts-has-menu">
                    <a href="javascript:void(0)" class="nsofts-sidebar-nav__link <?php if (in_array($currentFile, $lf_settings_pages)) { ?>open active<?php } ?>">
                        <i class="ri-settings-3-line nsofts-sidebar-nav__icon"></i>
                        <span class="nsofts-sidebar-nav__text">Configuracoes</span>
                    </a>
                    <ul class="nsofts-submenu <?php if (in_array($currentFile, $lf_settings_pages)) { ?>show<?php } ?>">
                        <li><a href="settings_login.php" class="nsofts-submenu__link <?php echo lf_nav_active('settings_login.php'); ?>">Modo de login do app</a></li>
                        <li><a href="settings_landing.php" class="nsofts-submenu__link <?php echo lf_nav_active('settings_landing.php'); ?>">Landing page e Mercado Pago</a></li>
                        <li><a href="settings.php" class="nsofts-submenu__link <?php echo lf_nav_active('settings.php'); ?>">Painel</a></li>
                        <li><a href="settings_app.php" class="nsofts-submenu__link <?php echo lf_nav_active('settings_app.php'); ?>">App (geral)</a></li>
                    </ul>
                </li>
                <?php } ?>

                <li class="nsofts-sidebar-nav__item">
                    <a href="api_urls.php" class="nsofts-sidebar-nav__link <?php echo lf_nav_active('api_urls.php'); ?>">
                        <i class="ri-links-line nsofts-sidebar-nav__icon"></i>
                        <span class="nsofts-sidebar-nav__text">URLs e API</span>
                    </a>
                </li>
            </ul>
        </nav>
    </aside>
    <!-- End: sidebar -->
