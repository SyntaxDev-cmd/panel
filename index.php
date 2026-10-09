<?php
    // O arquivo includes/.lic e "oculto" (comeca com ponto): varios gerenciadores de arquivo e
    // programas de FTP nao enviam esse tipo de arquivo, e o painel caia no instalador.
    // So vai para o instalador se a pasta install existir de verdade; senao segue para o login.
    $license_filename = __DIR__ . "/includes/.lic";
    if(!file_exists($license_filename) && is_file(__DIR__ . "/install/index.php")){
        header("Location:install/index.php");
        exit;
    }
    
    $errors = false;
    if(phpversion() < "7.4"){
        $errors = true;
    }
    if(!extension_loaded('mysqli')){
        $errors = true; 
    }
    if(!extension_loaded('curl')){
        $errors = true; 
    }
    if(!extension_loaded('pdo')){
        $errors = true; 
    }
    if(!extension_loaded('json')){
        $errors = true; 
    }

    if(!$errors){
        include("includes/db_helper.php");
        include("language/language.php");
        
        if(isset($_SESSION['admin_name']) AND isset($_SESSION['admin_type'])) {
            header("Location:dashboard.php");
            exit;
        }
    } else {
        define("APP_NAME","Errors extension");
        define("APP_LOGO","errors");
    }
?>


 
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

	<!-- Seo Meta -->
    <meta name="description" content="Admin panel | Dashboard">
    <meta name="keywords" content="css3, html5">
    
    <!-- Website Title -->
    <title>Login | <?php echo APP_NAME;?></title>
    
    <!-- Favicon --> 
    <link href="images/<?php echo APP_LOGO;?>" rel="icon" sizes="32x32">
    <link href="images/<?php echo APP_LOGO;?>" rel="icon" sizes="192x192">

    <!-- IOS Touch Icons -->
    <link rel="apple-touch-icon" href="images/<?php echo APP_LOGO;?>">
    <link rel="apple-touch-icon" sizes="152x152" href="images/<?php echo APP_LOGO;?>">
    <link rel="apple-touch-icon" sizes="180x180" href="images/<?php echo APP_LOGO;?>">
    <link rel="apple-touch-icon" sizes="167x167" href="images/<?php echo APP_LOGO;?>">

    <!-- Google font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Vendor styles -->
    <link rel="stylesheet" href="assets/vendors/bootstrap/bootstrap.min.css" type="text/css">
    <link rel="stylesheet" href="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.css" type="text/css">
    <link rel="stylesheet" href="assets/vendors/remixicon/remixicon.min.css" type="text/css">
    <link rel="stylesheet" href="assets/vendors/quill/quill.min.css" type="text/css">
    <link rel="stylesheet" href="assets/vendors/select2/select2.min.css" type="text/css">

    <!-- Main style -->
    <link rel="stylesheet" href="assets/css/styles.css" type="text/css">
    <link rel="stylesheet" href="assets/css/modern.css?v=2" type="text/css">
    
    <!--[if lt IE 9]>
	    <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
        <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    
</head>
<body>
    
    <!-- Loader -->
    <div id="nsofts_loader">
        <div class="text-center">
            <i class="ri-3x ri-donut-chart-line nsofts-loader-icon"></i>
            <span class="d-block">Carregando</span>
        </div>
    </div>

    <!-- Start: header -->
    <header id="nsofts_header">
        <!-- Header options -->
        <ul class="nsofts-header-nav ms-auto">
            <li class="nsofts-header-nav__item">
                <a href="javascript:void(0)" id="nsofts_theme_toggler" class="nsofts-header-nav__link">
                    <i class="ri-moon-fill nsofts-theme-dark"></i>
                    <i class="ri-sun-fill nsofts-theme-light"></i>
                </a>
            </li>
        </ul>
    </header>
    <!-- End: header -->

    <!-- Start: 404 -->
    <main class="lf-login d-flex justify-content-center align-items-center py-5 min-vh-100">
        <div class="container">
            <div class="col-xl-4 col-lg-5 col-md-7 col-sm-9 mx-auto">
                <div class="nsofts-auth position-relative">
                    <img src="assets/images/pattern-1.svg" class="nsofts-auth__pattern-1 position-absolute" alt="">
                    <img src="assets/images/pattern-2.svg" class="nsofts-auth__pattern-2 position-absolute" alt="">
                    <div class="card position-relative">
                        <div class="card-body px-4 py-4">
                            <?php if($errors==true){?>
                                    
                                <?php
                                    if(phpversion() < "7.4"){
                                        echo "<div class='alert alert-danger'>
                                        <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='24' height='24'>
                                        <path fill='none' d='M0 0h24v24H0z'/>
                                        <path fill='currentColor' d='M12 10.586l4.95-4.95 1.414 1.414-4.95 4.95 4.95 4.95-1.414 1.414-4.95-4.95-4.95 4.95-1.414-1.414 4.95-4.95-4.95-4.95L7.05 5.636z'/>
                                        </svg>
                                        <span class='notify__text'>Current PHP version is ".phpversion()."! minimum PHP 7.4 or higher required.</span>
                                        </div>";
                                    } else {
                                        echo "<div class='alert alert-success'>
                                        <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='24' height='24'>
                                        <path fill='none' d='M0 0h24v24H0z'/>
                                        <path fill='currentColor' d='M10 15.172l9.192-9.193 1.415 1.414L10 18l-6.364-6.364 1.414-1.414z'/>
                                        </svg> 
                                        <span class='notify__text'>You are running PHP version ".phpversion()."</span>
                                        </div>";
                                    }
                                    if(!extension_loaded('mysqli')){
                                        echo "<div class='alert alert-danger'>
                                        <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='24' height='24'>
                                        <path fill='none' d='M0 0h24v24H0z'/>
                                        <path fill='currentColor' d='M12 10.586l4.95-4.95 1.414 1.414-4.95 4.95 4.95 4.95-1.414 1.414-4.95-4.95-4.95 4.95-1.414-1.414 4.95-4.95-4.95-4.95L7.05 5.636z'/>
                                        </svg>
                                        <span class='notify__text'>MySQLi PHP extension missing!</span>
                                        </div>";
                                    } else {
                                        echo "<div class='alert alert-success'>
                                        <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='24' height='24'>
                                        <path fill='none' d='M0 0h24v24H0z'/>
                                        <path fill='currentColor' d='M10 15.172l9.192-9.193 1.415 1.414L10 18l-6.364-6.364 1.414-1.414z'/>
                                        </svg>
                                        <span class='notify__text'>MySQLi PHP extension available</span>
                                        </div>";
                                    } 
                                    if(!extension_loaded('curl')){
                                        echo "<div class='alert alert-danger'>
                                        <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='24' height='24'>
                                        <path fill='none' d='M0 0h24v24H0z'/>
                                        <path fill='currentColor' d='M12 10.586l4.95-4.95 1.414 1.414-4.95 4.95 4.95 4.95-1.414 1.414-4.95-4.95-4.95 4.95-1.414-1.414 4.95-4.95-4.95-4.95L7.05 5.636z'/>
                                        </svg>
                                        <span class='notify__text'>Curl PHP extension missing!</span>
                                        </div>";
                                    } else {
                                        echo "<div class='alert alert-success'>
                                        <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='24' height='24'>
                                        <path fill='none' d='M0 0h24v24H0z'/>
                                        <path fill='currentColor' d='M10 15.172l9.192-9.193 1.415 1.414L10 18l-6.364-6.364 1.414-1.414z'/>
                                        </svg>
                                        <span class='notify__text'>Curl PHP extension available</span>
                                        </div>";
                                    }
                                    if(!extension_loaded('pdo')){
                                        echo "<div class='alert alert-danger'>
                                        <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='24' height='24'>
                                        <path fill='none' d='M0 0h24v24H0z'/>
                                        <path fill='currentColor' d='M12 10.586l4.95-4.95 1.414 1.414-4.95 4.95 4.95 4.95-1.414 1.414-4.95-4.95-4.95 4.95-1.414-1.414 4.95-4.95-4.95-4.95L7.05 5.636z'/>
                                        </svg>
                                        <span class='notify__text'>PDO PHP extension missing!</span>
                                        </div>";
                                    } else {
                                        echo "<div class='alert alert-success'>
                                        <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='24' height='24'>
                                        <path fill='none' d='M0 0h24v24H0z'/>
                                        <path fill='currentColor' d='M10 15.172l9.192-9.193 1.415 1.414L10 18l-6.364-6.364 1.414-1.414z'/>
                                        </svg>
                                        <span class='notify__text'>PDO PHP extension available</span>
                                        </div>";
                                    }
                                    if(!extension_loaded('json')){
                                        echo "<div class='alert alert-danger'>
                                        <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='24' height='24'>
                                        <path fill='none' d='M0 0h24v24H0z'/>
                                        <path fill='currentColor' d='M12 10.586l4.95-4.95 1.414 1.414-4.95 4.95 4.95 4.95-1.414 1.414-4.95-4.95-4.95 4.95-1.414-1.414 4.95-4.95-4.95-4.95L7.05 5.636z'/>
                                        </svg>
                                        <span class='notify__text'>JSON PHP extension missing!</span>
                                        </div>";
                                    } else {
                                        echo "<div class='alert alert-success'>
                                        <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='24' height='24'>
                                        <path fill='none' d='M0 0h24v24H0z'/>
                                        <path fill='currentColor' d='M10 15.172l9.192-9.193 1.415 1.414L10 18l-6.364-6.364 1.414-1.414z'/>
                                        </svg>
                                        <span class='notify__text'>JSON PHP extension available</span>
                                        </div>";
                                    }
                                ?>
                                
                            <?php } else { ?>
                                <form action="login_db.php" method="post">
                                    <div class="text-center mb-3">
                                        <a href="index.php" class="fs-4 fw-semibold text-decoration-none"><?php echo APP_NAME;?></a>
                                    </div>
                                    <div class="mb-4">
                                        <h5>Bem-vindo ao <?php echo APP_NAME;?></h5>
                                        <p>Entre com seu usuario e senha para gerenciar suas DNS, codigos e dispositivos.</p>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="user_login" class="form-label fw-semibold">Usuario ou email</label>
                                        <div class="nsofts-input-icon nsofts-input-icon--left">
                                            <label for="email" class="nsofts-input-icon__left">
                                                <i class="ri-user-line"></i>
                                            </label>
                                            <input type="text" name="user_login" id="user_login"  class="form-control" autocomplete="off" placeholder="Seu usuario ou email" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <label for="nsofts_password_input" class="form-label fw-semibold mb-0">Senha</label>
                                        </div>
                                        <div class="nsofts-input-icon nsofts-input-icon--both">
                                            <label for="nsofts_password_input" class="nsofts-input-icon__left">
                                                <i class="ri-door-lock-line"></i>
                                            </label>
                                            <input type="password" name="nsofts_password_input" id="nsofts_password_input" class="form-control" autocomplete="off" placeholder="Sua senha" required>
                                            <button type="button" id="nsofts_password_toggler" class="nsofts-input-icon__right btn p-0 border-0 lh-1">
                                                <i class="ri-eye-line nsofts-eye-open"></i>
                                                <i class="ri-eye-off-line nsofts-eye-close d-none"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <button  type="submit" class="btn btn-primary btn-lg w-100">Entrar</button>
                                    </div>
                                </form>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <!-- End: 404 -->
    

    <!-- Vendor scripts -->
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/vendors/bootstrap/bootstrap.min.js"></script>
    <script src="assets/vendors/notify/notify.min.js"></script>
    <script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="assets/vendors/quill/quill.min.js"></script>
    <script src="assets/vendors/select2/select2.min.js"></script>

    <!-- Main script -->
    <script src="assets/js/main.js"></script>
    
    <?php if (isset($_SESSION['msg'])) { ?>
        <script type="text/javascript">
            $('.notifyjs-corner').empty();
            $.notify(
            <?php echo json_encode(isset($client_lang[$_SESSION["msg"]]) ? $client_lang[$_SESSION["msg"]] : ''); ?>, {
                position: "top right",
                className: <?php echo json_encode(isset($_SESSION["class"]) ? $_SESSION["class"] : 'success'); ?>
            }
            );
        </script>
        <?php
        unset($_SESSION['msg']);
        unset($_SESSION['class']);
    }?>

</body>
</html>