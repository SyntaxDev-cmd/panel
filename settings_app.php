<?php $page_title="Settings App";
    include("includes/header.php");
    require("includes/lb_helper.php");
    require("language/language.php");
    
    if(!isset($_SESSION['admin_type'])){
        if($_SESSION['admin_type'] == 0){
            session_destroy();
            header( "Location:index.php");
            exit;
        }
    }
    
    $privacy_policy_file_path = getBaseUrl().'privacy_policy.php';
    $terms_file_path = getBaseUrl().'terms.php';
    
    $qry="SELECT * FROM tbl_settings where id='1'";
    $result=mysqli_query($mysqli,$qry);
    $settings_data=mysqli_fetch_assoc($result);
    
    if(isset($_POST['submit_general'])){
        
        $data = array(
            'app_email'  =>  $_POST['app_email'],
            'app_author'  =>  $_POST['app_author'],
            'app_contact'  =>  $_POST['app_contact'],
            'app_website'  =>  $_POST['app_website'],
            'app_developed_by'  =>  $_POST['app_developed_by'],
            'app_description'  =>  $_POST['app_description']
        );
        $settings_edit=Update('tbl_settings', $data, "WHERE id = '1'");
        
        $_SESSION['msg']="11";
        $_SESSION['class'] = "success";
        header( "Location:settings_app.php");
        exit;
       
    } else if(isset($_POST['policy_submit'])){
        
        $data = array('app_privacy_policy'  =>  $_POST['app_privacy_policy']);
        $settings_edit=Update('tbl_settings', $data, "WHERE id = '1'");
        
        $_SESSION['msg']="11";
        $_SESSION['class'] = "success";
        header( "Location:settings_app.php");
        exit;
        
    } else if(isset($_POST['terms_submit'])){
        
        $data = array('app_terms'  =>  $_POST['app_terms']);
        $settings_edit=Update('tbl_settings', $data, "WHERE id = '1'");
        
        $_SESSION['msg']="11";
        $_SESSION['class'] = "success";
        header( "Location:settings_app.php");
        exit;
        
    } else if(isset($_POST['policy_template'])) {

        // modelos prontos (reprodutor de midia, LGPD/GDPR, Google Play, Amazon, Roku, LG, Samsung, Apple)
        require_once("includes/policies.php");
        Update('tbl_settings', array('app_privacy_policy' => lf_policy_template('privacy'), 'app_terms' => lf_policy_template('terms')), "WHERE id = '1'");
        lf_flash('Politica de privacidade e termos trocados pelos modelos recomendados. Revise o e-mail e o site em "Geral".');
        header("Location:settings_app.php");
        exit;

    } else if(isset($_POST['notification_submit'])) {
        
        $data = array(
          'onesignal_app_id' => trim($_POST['onesignal_app_id']),
          'onesignal_rest_key' => trim($_POST['onesignal_rest_key']),
        );
        
        $settings_edit = Update('tbl_settings', $data, "WHERE id = '1'");
        
        $_SESSION['msg'] = "11";
        $_SESSION['class'] = "success";
        header("Location:settings_app.php");
        exit;
        
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

                            <button class="nav-link" id="nsofts_setting_4" data-bs-toggle="pill" data-bs-target="#nsofts_setting_content_4" type="button" role="tab" aria-controls="nsofts_setting_4" aria-selected="false">
                                <i class="ri-survey-line"></i>
                                <span>Privacy Policy</span>
                            </button>
                            
                            <button class="nav-link" id="nsofts_setting_5" data-bs-toggle="pill" data-bs-target="#nsofts_setting_content_5" type="button" role="tab" aria-controls="nsofts_setting_5" aria-selected="false">
                                <i class="ri-survey-line"></i>
                                <span>Terms & Conditions</span>
                            </button>
                            
                            <button class="nav-link" id="nsofts_setting_6" data-bs-toggle="pill" data-bs-target="#nsofts_setting_content_6" type="button" role="tab" aria-controls="nsofts_setting_6" aria-selected="false">
                                <i class="ri-notification-3-line"></i>
                                <span>Notification</span>
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
                                        <label for="" class="col-sm-2 col-form-label">Email</label>
                                        <div class="col-sm-10">
                                            <input type="text" class="form-control" name="app_email" id="app_email" value="<?php echo e($settings_data['app_email']); ?>" >
                                        </div>
                                    </div>
                                    <div class="mb-3 row">
                                        <label for="" class="col-sm-2 col-form-label">Author</label>
                                        <div class="col-sm-10">
                                            <input type="text" class="form-control" name="app_author" id="app_author" value="<?php echo e($settings_data['app_author']); ?>" >
                                        </div>
                                    </div>
                                    <div class="mb-3 row">
                                        <label for="" class="col-sm-2 col-form-label">Contact</label>
                                        <div class="col-sm-10">
                                            <input type="text" class="form-control" name="app_contact" id="app_contact" value="<?php echo e($settings_data['app_contact']); ?>" >
                                        </div>
                                    </div>
                                    <div class="mb-3 row">
                                        <label for="" class="col-sm-2 col-form-label">Website</label>
                                        <div class="col-sm-10">
                                            <input type="text" class="form-control" name="app_website" id="app_website" value="<?php echo e($settings_data['app_website']); ?>" >
                                        </div>
                                    </div>
                                    <div class="mb-3 row">
                                        <label for="" class="col-sm-2 col-form-label">Developed By</label>
                                        <div class="col-sm-10">
                                            <input type="text" class="form-control" name="app_developed_by" id="app_developed_by" value="<?php echo e($settings_data['app_developed_by']); ?>" >
                                        </div>
                                    </div>
                                    <div class="mb-3 row">
                                        <label for="" class="col-sm-2 col-form-label">Description</label>
                                        <div class="col-sm-10">
                                            <textarea name="app_description" id="app_description" class="form-control" ><?php echo htmlspecialchars(stripslashes($settings_data['app_description']), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                        </div>
                                    </div>
                                    <button type="submit" name="submit_general" class="btn btn-primary" style="min-width: 120px;">Save</button>
                                </form>
                            </div>

                            <!--Privacy Policy-->
                            <div class="tab-pane fade" id="nsofts_setting_content_4" role="tabpanel" aria-labelledby="nsofts_setting_4" tabindex="0">
                                <form action="" name="settings_policy" method="POST" enctype="multipart/form-data">
                                    <h4 class="mb-2">Politica de Privacidade</h4>
                                    <div class="lf-note mb-3"><i class="ri-shield-check-line"></i><div>Use os <b>modelos recomendados</b> (reprodutor de midia, sem conteudo, LGPD/GDPR, Google Play, Amazon, Roku, LG, Samsung e Apple). Os textos usam o nome do app, o e-mail e o site de "Geral" automaticamente.
                                        <button type="submit" name="policy_template" class="btn btn-sm btn-outline-primary ms-1" onclick="return confirm('Substituir a politica e os termos atuais pelos modelos recomendados?');">Usar modelos recomendados</button></div></div>
                                    <div class="pb-clipboard mb-2">
                                        <span class="pb-clipboard__url"><span id="clipboard_policy"><?=$privacy_policy_file_path ?></span></span>
                                        <a class="pb-clipboard__link btn_policy" href="javascript:void(0);" data-clipboard-action="copy" data-clipboard-target="#clipboard_base_url" data-bs-toggle="tooltip" data-bs-placement="top" title="Copy">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                                            </svg>
                                        </a>
                                    </div>
                                    <div>
                                        <textarea name="app_privacy_policy" id="app_privacy_policy" rows="5" class="nsofts-editor mb-4">
                                            <?php echo htmlspecialchars(stripslashes($settings_data['app_privacy_policy']), ENT_QUOTES, 'UTF-8'); ?>
                                            
                                        </textarea>
                                    </div>
                                    <button type="submit" name="policy_submit" class="btn btn-primary" style="min-width: 120px;">Save</button>
                                </form>
                            </div>
                            
                            <!--Terms & Conditions-->
                            <div class="tab-pane fade" id="nsofts_setting_content_5" role="tabpanel" aria-labelledby="nsofts_setting_5" tabindex="0">
                                <form action="" name="settings_terms" method="POST" enctype="multipart/form-data">
                                    <h4 class="mb-4">Terms & Conditions</h4>
                                    <div class="pb-clipboard mb-2">
                                        <span class="pb-clipboard__url"><span id="clipboard_terms"><?=$terms_file_path ?></span></span>
                                        <a class="pb-clipboard__link btn_terms" href="javascript:void(0);" data-clipboard-action="copy" data-clipboard-target="#clipboard_base_url" data-bs-toggle="tooltip" data-bs-placement="top" title="Copy">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                                            </svg>
                                        </a>
                                    </div>
                                    <div>
                                        <textarea name="app_terms" id="app_terms" rows="5" class="nsofts-editor mb-4">
                                           <?php echo htmlspecialchars(stripslashes($settings_data['app_terms']), ENT_QUOTES, 'UTF-8'); ?>
                                        </textarea>
                                    </div>
                                    <button type="submit" name="terms_submit" class="btn btn-primary" style="min-width: 120px;">Save</button>
                                </form>
                            </div>
                            
                            <!--Notification-->
                            <div class="tab-pane fade" id="nsofts_setting_content_6" role="tabpanel" aria-labelledby="nsofts_setting_6" tabindex="0">
                                <form action="" name="settings_notification" method="POST" enctype="multipart/form-data">
                                    <h4 class="mb-4">Notification</h4>
                                    <div class="mb-3 row">
                                        <label for="" class="col-sm-2 col-form-label">OneSignal App ID</label>
                                        <div class="col-sm-10">
                                            <input type="text" name="onesignal_app_id" id="onesignal_app_id" value="<?php echo e($settings_data['onesignal_app_id']); ?>"  class="form-control">
                                        </div>
                                    </div>
                                    <div class="mb-3 row">
                                        <label for="" class="col-sm-2 col-form-label">OneSignal Rest Key</label>
                                        <div class="col-sm-10">
                                            <input type="text" name="onesignal_rest_key" id="onesignal_rest_key" value="<?php echo e($settings_data['onesignal_rest_key']); ?>"   class="form-control">
                                        </div>
                                    </div>
                                    <button type="submit" name="notification_submit" class="btn btn-primary" style="min-width: 120px;">Save</button>
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
        
        $(document).on("click", ".btn_policy", function(e) {
            var el = document.getElementById('clipboard_policy');
            var successful = copyToClipboard(el);
            if (successful) {
                $.notify('Copied!', { position:"top right",className: 'success'} );
            } else {
                $.notify('Whoops, not copied!', { position:"top right",className: 'error'} );
            }
        });
        
        $(document).on("click", ".btn_terms", function(e) {
            var el = document.getElementById('clipboard_terms');
            var successful = copyToClipboard(el);
            if (successful) {
                $.notify('Copied!', { position:"top right",className: 'success'} );
            } else {
                $.notify('Whoops, not copied!', { position:"top right",className: 'error'} );
            }
        });

    });
</script>