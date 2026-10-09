<?php 
$page_title = "Settings API";
include("includes/header.php");
require("includes/lb_helper.php");
require("language/language.php");

if (!isset($_SESSION['admin_type'])) {
    if ($_SESSION['admin_type'] == 0) {
        session_destroy();
        header("Location:index.php");
        exit;
    }
}

// Mostrar solo la información del cliente actual
$cliente = mysqli_real_escape_string($mysqli, isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : "");

$qry = "SELECT * FROM tbl_app WHERE cliente = '$cliente' LIMIT 1";
$result = mysqli_query($mysqli, $qry);
$settings_data = $result ? mysqli_fetch_assoc($result) : null;
// usuario que ainda nao configurou a aparencia: campos vazios (evita avisos do PHP)
$lf_api_has_row = is_array($settings_data);
if (!$lf_api_has_row) $settings_data = array('app_name' => '', 'app_titulo' => '', 'app_logo' => '', 'bg_main' => '', 'bg_login' => '', 'bg_banner' => '');

if (isset($_POST['submit_general'])) {

    // Función para redimensionar imágenes y manejar transparencia en PNG
    function resizeImage($file, $filename, $width, $height) {
        $info = @getimagesize($file['tmp_name']);
        $image_type = $info ? $info[2] : 0;
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);

        // Cargar imagen según tipo
        switch ($image_type) {
            case IMAGETYPE_JPEG:
                $src_image = imagecreatefromjpeg($file['tmp_name']);
                break;
            case IMAGETYPE_PNG:
                // Cargar imagen PNG y manejar transparencia
                $src_image = imagecreatefrompng($file['tmp_name']);
                break;
            case IMAGETYPE_GIF:
                $src_image = imagecreatefromgif($file['tmp_name']);
                break;
            default:
                return false;
        }

        // extensao sempre definida pelo tipo real da imagem (nunca pelo nome enviado)
        $safe_ext = ($image_type == IMAGETYPE_PNG) ? 'png' : (($image_type == IMAGETYPE_GIF) ? 'gif' : 'jpg');
        $filename = preg_replace('/\.[^.]*$/', '', $filename) . '.' . $safe_ext;

        // Obtener las dimensiones originales
        list($orig_width, $orig_height) = getimagesize($file['tmp_name']);

        // Crear una nueva imagen con el tamaño redimensionado
        $new_image = imagecreatetruecolor($width, $height);

        // Si es PNG, mantenemos la transparencia
        if ($image_type == IMAGETYPE_PNG) {
            // Mantener la transparencia para PNG
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
            $transparent = imagecolorallocatealpha($new_image, 0, 0, 0, 127);
            imagefill($new_image, 0, 0, $transparent);
        }

        // Redimensionar la imagen
        imagecopyresampled($new_image, $src_image, 0, 0, 0, 0, $width, $height, $orig_width, $orig_height);

        // Guardar la imagen en el archivo final
        switch ($image_type) {
            case IMAGETYPE_JPEG:
                imagejpeg($new_image, 'images/' . $filename);
                break;
            case IMAGETYPE_PNG:
                imagepng($new_image, 'images/' . $filename);
                break;
            case IMAGETYPE_GIF:
                imagegif($new_image, 'images/' . $filename);
                break;
        }

        // Liberar memoria
        imagedestroy($src_image);
        imagedestroy($new_image);

        return $filename;
    }

    // envia a imagem nova e so entao apaga a antiga (antes um arquivo invalido apagava a imagem atual)
    function lf_api_upload($field, $w, $h, $current) {
        if (empty($_FILES[$field]['name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) return (string)$current;
        $name = rand(0, 99999) . '_' . $field . '.jpg';
        $saved = resizeImage($_FILES[$field], $name, $w, $h);
        if (!$saved) { lf_flash('Imagem invalida (use PNG, JPG ou GIF).', 'error'); return (string)$current; }
        if ($current != '' && $current !== $saved && is_file('images/' . basename($current))) @unlink('images/' . basename($current));
        return $saved;
    }

    // BG Login
    $bg_login = lf_api_upload('bg_login', 500, 500, $settings_data ? $settings_data['bg_login'] : '');

    // BG Main
    $bg_main = lf_api_upload('bg_main', 1920, 1080, $settings_data ? $settings_data['bg_main'] : '');

    // BG Banner (Portada de Facebook)
    $bg_banner = lf_api_upload('bg_banner', 500, 300, $settings_data ? $settings_data['bg_banner'] : '');

    // App Logo (redimensionar a 215x75)
    $app_logo = lf_api_upload('app_logo', 800, 500, $settings_data ? $settings_data['app_logo'] : '');

    // App Name, Titulo, and Client
    $app_name = substr(trim((string)(isset($_POST['app_name']) ? $_POST['app_name'] : $settings_data['app_name'])), 0, 255);
    $app_titulo = substr(trim((string)(isset($_POST['app_titulo']) ? $_POST['app_titulo'] : $settings_data['app_titulo'])), 0, 255);

    // Comprobamos si el cliente ya está registrado
    $qry_check = "SELECT * FROM tbl_app WHERE cliente = '$cliente'";
    $result_check = mysqli_query($mysqli, $qry_check);

    $data = array(
        'bg_login'     => $bg_login,
        'bg_main'      => $bg_main,
        'bg_banner'    => $bg_banner,
        'app_name'     => $app_name,
        'app_logo'     => $app_logo,
        'app_titulo'   => $app_titulo,
        'cliente'      => isset($_SESSION['admin_name']) ? (string)$_SESSION['admin_name'] : '',
    );

    if (mysqli_num_rows($result_check) > 0) {
        // El cliente ya existe, actualizamos los datos
        $settings_edit = Update('tbl_app', $data, "WHERE cliente = '$cliente'");

        $_SESSION['msg'] = "11";
        $_SESSION['class'] = "success";
        header("Location:settings_api.php");
        exit;
    } else {
        // El cliente no existe, insertamos una nueva fila
        $settings_insert = Insert('tbl_app', $data);

        $_SESSION['msg'] = "11";
        $_SESSION['class'] = "success";
        header("Location:settings_api.php");
        exit;
    }
}
?>




<!-- Start: main -->
<main id="nsofts_main">
    <div class="nsofts-container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb align-items-center">
                <li class="breadcrumb-item d-inline-flex"><a href="dashboard.php"><i class="ri-home-4-fill"></i></a></li>
                <li class="breadcrumb-item d-inline-flex active" aria-current="page"><?php echo (isset($page_title)) ? $page_title : "" ?></li>
            </ol>
        </nav>
            
        <div class="card">
            <div class="card-body p-0">                    
                <div class="nsofts-setting">
                    <div class="nsofts-setting__sidebar">
                        <a class="d-inline-flex align-items-center text-decoration-none fw-semibold mb-4">
                            <span class="ps-2 lh-1"><?php echo (isset($page_title)) ? $page_title : "" ?></span>
                        </a>
                        <div class="nav flex-column nav-pills" id="nsofts_setting" role="tablist" aria-orientation="vertical">
                            <button class="nav-link active" id="nsofts_setting_1" data-bs-toggle="pill" data-bs-target="#nsofts_setting_content_1" type="button" role="tab" aria-controls="nsofts_setting_1" aria-selected="true">
                                <i class="ri-list-settings-line"></i>
                                <span>General</span>
                            </button>

                        </div>
                    </div>
                    <div class="nsofts-setting__content">
                        <div class="tab-content">
                        
                            <!--General Settings-->
                            <div class="tab-pane fade show active" id="nsofts_setting_content_1" role="tabpanel" aria-labelledby="nsofts_setting_1" tabindex="0">
                                <form action="" name="settings_general" method="POST" enctype="multipart/form-data">
                                    <h4 class="mb-4">General Settings</h4>
                                    
                                    <div class="mb-3 row">
                                        <label for="" class="col-sm-2 col-form-label">Banner Login</label>
                                        <div class="col-sm-10">
                                            <div class="row">
                                                <div class="col-md-5">
                                                    <input type="file" class="form-control-file" name="bg_login" value="fileupload" accept=".png, .jpg, .jpeg, .PNG, .JPG, .JPEG" onchange="fileValidation(this,'#loginPreview')" id="fileupload">
                                                    <span class="badge text-bg-dark">(Recommended resolution: 1920x1080px)</span>
                                                </div>
                                                <div class="col-md-3">
                                                    <?php if($settings_data['bg_login']!='' AND file_exists('images/'.$settings_data['bg_login'])) { ?>
                                                        <div class="fileupload_img" id="loginPreview">
                                                            <img  type="image" src="images/<?=$settings_data['bg_login']?>" style="width: 50px;height: 50px"   alt="image" />
                                                        </div>
                                                    <?php }else{ ?>
                                                        <div class="fileupload_img" id="loginPreview">
                                                            <img type="image" src="assets/images/300x300.jpg" style="width: 50px; height: 50px"  alt="image" />
                                                        </div>
                                                    <?php } ?>
                                                </div>
                                               <div class="col-md-3">
                                                   <span class="badge rounded-pill text-bg-warning fw-bold" data-bs-toggle="tooltip" data-bs-placement="top"
        data-bs-custom-class="custom-tooltip"
        data-bs-title="Implemented very soon..">Implemented very soon.. </span>
                                               </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3 row">
                                        <label for="" class="col-sm-2 col-form-label">Fondo </label>
                                        <div class="col-sm-10">
                                            <div class="row">
                                                <div class="col-md-5">
                                                    <input type="file" class="form-control-file" name="bg_main" value="fileupload" accept=".png, .jpg, .jpeg, .PNG, .JPG, .JPEG" onchange="fileValidation(this,'#mainPreview')" id="fileupload">
                                                    <span class="badge text-bg-dark">(Recommended resolution: 1920x1080px)</span>
                                                </div>
                                                <div class="col-md-3">
                                                    <?php if($settings_data['bg_main']!='' AND file_exists('images/'.$settings_data['bg_main'])) { ?>
                                                        <div class="fileupload_img" id="mainPreview">
                                                            <img  type="image" src="images/<?=$settings_data['bg_main']?>" style="width: 50px;height: 50px"   alt="image" />
                                                        </div>
                                                    <?php }else{ ?>
                                                        <div class="fileupload_img" id="mainPreview">
                                                            <img type="image" src="assets/images/300x300.jpg" style="width: 50px; height: 50px"  alt="image" />
                                                        </div>
                                                    <?php } ?>
                                                </div>
                                               
                                            </div>
                                        </div>
                                    </div>
                                    
                <div class="mb-3 row">
    <label for="" class="col-sm-2 col-form-label">Logo o Banner</label>
    <div class="col-sm-10">
        <div class="row">
            <div class="col-md-5">
                <input type="file" class="form-control-file" name="bg_banner" value="fileupload" accept=".png, .jpg, .jpeg, .PNG, .JPG, .JPEG" onchange="fileValidation(this,'#bannerPreview')" id="fileupload">
                <span class="badge text-bg-dark">(Recommended resolution: 500x300px)</span>
            </div>
            <div class="col-md-3">
                <?php if($settings_data['bg_banner']!='' AND file_exists('images/'.$settings_data['bg_banner'])) { ?>
                    <div class="fileupload_img" id="bannerPreview">
                        <img type="image" src="images/<?=$settings_data['bg_banner']?>" style="width: 50px; height: 50px" alt="image" />
                    </div>
                <?php } else { ?>
                    <div class="fileupload_img" id="bannerPreview">
                        <img type="image" src="assets/images/300x300.jpg" style="width: 50px; height: 50px" alt="image" />
                    </div>
                <?php } ?>
            </div>
            
        </div>
    </div>
</div>
                    
                               
                             
       <!-- App Name -->
                                    <div class="mb-3 row">
                                        <label for="app_name" class="col-sm-2 col-form-label">App Name</label>
                                        <div class="col-sm-10">
                                            <input type="text" class="form-control" name="app_name" id="app_name" value="<?php echo isset($settings_data['app_name']) ? $settings_data['app_name'] : ''; ?>">
                                        </div>
                                    </div>

                                    <!-- App Logo -->
                                    <div class="mb-3 row">
                                        <label for="app_logo" class="col-sm-2 col-form-label">App Logo</label>
                                        <div class="col-sm-10">
                                            <input type="file" class="form-control-file" name="app_logo" accept=".png, .jpg, .jpeg" onchange="fileValidation(this,'#logoPreview')" id="fileupload">
                                            <div id="logoPreview">
                                                <?php if($settings_data['app_logo'] != '' && file_exists('images/'.$settings_data['app_logo'])) { ?>
                                                    <img src="images/<?=$settings_data['app_logo']?>" style="width: 50px; height: 50px" alt="App Logo" />
                                                <?php } else { ?>
                                                    <img src="assets/images/300x300.jpg" style="width: 50px; height: 50px" alt="App Logo" />
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- App Titulo -->
                                    <div class="mb-3 row">
                                        <label for="app_titulo" class="col-sm-2 col-form-label">App Titulo</label>
                                        <div class="col-sm-10">
                                            <input type="text" class="form-control" name="app_titulo" id="app_titulo" value="<?php echo isset($settings_data['app_titulo']) ? $settings_data['app_titulo'] : ''; ?>">
                                        </div>
                                    </div>
 
                                    <button type="submit" name="submit_general" class="btn btn-primary" style="min-width: 120px;">Save</button>
                                </form>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<!-- End: main -->
    
<?php include("includes/footer.php");?>
<script type="text/javascript">

    $(document).ready(function(event) {
        
        $(document).on("click", ".btn_token", function(e) {
            var el = document.getElementById('access_token');
			el.select();
			el.setSelectionRange(0, 99999); // For mobile devices
            var successful = copyToClipboard(el);
            if (successful) {
                $.notify('Copied!', { position:"top right",className: 'success'} );
            } else {
                $.notify('Whoops, not copied!', { position:"top right",className: 'error'} );
            }
        });
        
        $(document).on("click", ".btn_generate", function(e) {
            var el = document.getElementById('access_token');
            var successful = generateToken(el);
            $.notify('Generate Token!', { position:"top right",className: 'success'} );
        });
		
        const colorPicker = document.getElementById('colorPicker');
        const hexCodeInput = document.getElementById('bg_color');
        function updateHexCode() {
                const hexCode = colorPicker.value;
                hexCodeInput.value = hexCode.toUpperCase();
        }
        colorPicker.addEventListener('input', updateHexCode);
        updateHexCode();


	
    });
</script>