<?php 
    $page_title=(isset($_GET['user_id'])) ? 'Edit User' : 'Create User';
    include("includes/header.php");
    require("includes/lb_helper.php");
    require("language/language.php");
	require_once("includes/thumbnail_images.class.php");
	
	$page_save=(isset($_GET['user_id'])) ? 'Save' : 'Create';
	
	if(isset($_POST['submit']) and isset($_GET['add'])){
	    
	    $base_url = htmlentities(trim($_POST['dns_base']));
        $data = array(
            'user_type'=>'xui',											 
            'user_name'  => cleanInput($_POST['user_name']),
            'user_password'  =>  cleanInput($_POST['user_password']),
            'dns_base'  =>  $base_url,
            'device_id'  =>  cleanInput($_POST['device_id']),
            'registered_on'  =>  strtotime(date('d-m-Y h:i:s A')),
            'status'  =>  '1'
        );
        
        $qry = Insert('tbl_users',$data);
        
        $_SESSION['msg']="10";
        $_SESSION['class']='success'; 
        header("location:manage_users.php");	 
        exit;
    }
	
	if(isset($_GET['user_id'])){
        $user_qry="SELECT * FROM tbl_users where id='".$_GET['user_id']."'";
        $user_result=mysqli_query($mysqli,$user_qry);
        $user_row=mysqli_fetch_assoc($user_result);
    }
    
    if(isset($_POST['submit']) and isset($_POST['user_id'])){

        $base_url = htmlentities(trim($_POST['dns_base']));
        $data = array(
            'user_type'=>'xui',											 
            'user_name'  => cleanInput($_POST['user_name']),
            'user_password'  =>  cleanInput($_POST['user_password']),
            'dns_base'  =>  $base_url,
            'device_id'  =>  cleanInput($_POST['device_id']),
        );
        
        $user_edit=Update('tbl_users', $data, "WHERE id = '".$_POST['user_id']."'");
        
        $_SESSION['msg']="11";
        $_SESSION['class']='success'; 
        header("Location:create_user.php?user_id=".$_POST['user_id']);
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
            
        <div class="row g-4">
            <div class="col-12">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <h5 class="mb-3"><?=$page_title ?></h5>
                        <form action="" name="addedituser" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="user_id" value="<?=(isset($_GET['user_id'])) ? $_GET['user_id'] : ''?>" />
                            
                            <div class="mb-3 row">
                                <label class="col-sm-2 col-form-label">User Type</label>
                                <div class="col-sm-10">
                                        <select name="user_type" id="user_type" class="nsofts-select" required>
                                            <?php if(isset($_GET['live_id'])){ ?>
                                                <option value="xui" <?php if($row['user_type']=='xui'){?>selected<?php }?>>Xtream Codes or XUI</option> 
                                                <option value="stream" <?php if($row['user_type']=='stream'){?>selected<?php }?>>1-Stream</option> 
                                            <?php } else { ?>
                                                <option value="xui">Xtream Codes or XUI</option> 
                                                <option value="stream">1-Stream</option>
                                            <?php } ?> 
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3 row">
                                <label class="col-sm-2 col-form-label">User name</label>
                                <div class="col-sm-10">
                                    <input type="text" name="user_name" value="<?php if(isset($_GET['user_id'])){echo $user_row['user_name'];}?>"  class="form-control" required>
                                </div>
                            </div>
                            
                            <div class="mb-3 row">
                                <label class="col-sm-2 col-form-label">Password</label>
                                <div class="col-sm-10">
                                    <input type="text" name="user_password" value="<?php if(isset($_GET['user_id'])){echo $user_row['user_password'];}?>" class="form-control" required>
                                </div>
                            </div>
                            
                            <div class="mb-3 row">
                                <label class="col-sm-2 col-form-label">Dns base</label>
                                <div class="col-sm-10">
                                    <input type="text" name="dns_base" value="<?php if(isset($_GET['user_id'])){echo $user_row['dns_base'];}?>" class="form-control" required>
                                </div>
                            </div>
                            
                            <div class="mb-3 row">
                                <label class="col-sm-2 col-form-label">Device ID</label>
                                <div class="col-sm-10">
                                    <input type="text" name="device_id" value="<?php if(isset($_GET['user_id'])){echo $user_row['device_id'];}?>" class="form-control" required>
                                </div>
                            </div>
                            
                            <div class="mb-3 row">
                                <label class="col-sm-2 col-form-label">&nbsp;</label>
                                <div class="col-sm-10">
                                    <button type="submit" name="submit" class="btn btn-primary" style="min-width: 120px;"><?=$page_save?></button>
                                </div>
                            </div>
                            
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<!-- End: main -->
    
<?php include("includes/footer.php");?> 