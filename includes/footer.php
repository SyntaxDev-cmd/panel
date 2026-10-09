    <!-- Start: footer -->
    <footer id="nsofts_footer">
        <div class="nsofts-container d-sm-flex justify-content-between text-center">
            <span>Copyright &copy; <?php echo date('Y'); ?> <span class="fw-semibold"><?php echo e(APP_NAME); ?></span> - Todos os direitos reservados.</span>
            <div class="d-flex justify-content-center mt-2 mt-sm-0">
               
                
            </div>
        </div>
    </footer>
    <!-- End: footer -->
    

    <!-- Vendor scripts -->
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/vendors/bootstrap/bootstrap.min.js"></script>
    <script src="assets/vendors/notify/notify.min.js"></script>
    <script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="assets/vendors/quill/quill.min.js"></script>
    <script src="assets/vendors/select2/select2.min.js"></script>
    <script src="assets/vendors/sweetalerts2/sweetalert2.min.js"></script>
    <script src="assets/vendors/chartjs/chart.min.js"></script>

    <!-- Main script -->
    <script src="assets/js/main.js"></script>
    
    <script type="text/javascript">
    
        $.ajaxSetup({ headers: { "X-CSRF-Token": $('meta[name="csrf-token"]').attr("content") } });

        $(document).ready(function(event) {
            $(document).on("click", ".btn_enable_disable", function(e) {
                var _action;
                
                var _currentElement = $(this);
                var _id = $(this).data("id");
                var _table = $(this).data("table");
                var _column = $(this).data("column");
                
                var _for = $(this).prop("checked");
                if (_for == false) {
                    _action = "disable";
                } else {
                    _action = "enable";
                }
                
                $.ajax({
                    type: 'post',
                    url: 'processData.php',
                    dataType: 'json',
                    data: {id: _id, for_action: _action, table: _table, column: _column,'action':'toggle_status'},
                    success: function(res) {
                        $.notify(res.msg, { position:"top right",className: res.class} );
                        if (res.status != 1) { _currentElement.prop('checked', !_for); }
                    }
                });
            });
        });
      
        $(document).on("click", ".btn_delete", function(e){
            e.preventDefault();
            
            var _ids=$(this).data("id");
            var _table=$(this).data("table");
            
            swal({
                title: "Tem certeza que deseja excluir?",
                type: "warning",
                confirmButtonClass: 'btn btn-primary m-2',
                cancelButtonClass: 'btn btn-danger m-2',
                buttonsStyling: false,
                showCancelButton: true,
                confirmButtonText: "Sim, excluir",
                cancelButtonText: "Cancelar",
                closeOnConfirm: false,
                closeOnCancel: false,
                showLoaderOnConfirm: true
                
            }).then(function(result) {
                if (result.value) {
                    $.ajax({
                        type:'post',
                        url:'processData.php',
                        dataType:'json',
                        data:{'id':_ids,'table':_table,'for_action':'delete','action':'multi_action'},
                        success:function(res){
                            if (res && res.status != 1 && res.msg) { swal.close(); $.notify(res.msg, { position:"top right",className: 'error'} ); return; }
                            location.reload();
                        }
                    });
                } else {
                    swal.close();
                }
            });
        });
        
        function fileValidation(fileInput,image){
            var filePath = fileInput.value;
            var allowedExtensions = /(\.png|.jpg|.jpeg|.PNG|.JPG|.JPEG)$/i;
            if(!allowedExtensions.exec(filePath)){
                if(filePath!='')
                fileInput.value = '';
                $.notify('Please upload file having extension .png, .jpg, .jpeg .PNG, .JPG, .JPEG only!', { position:"top right",className: 'error'} ); 
                return false;
             }else {
                if (fileInput.files && fileInput.files[0]) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        $(image).find("img").attr("src", e.target.result);
                    };
                    reader.readAsDataURL(fileInput.files[0]);
                }
            }
        }
        
        function copyToClipboard(el) {
            var text = el.innerText;
            
            if (window.clipboardData && window.clipboardData.setData) {
                return window.clipboardData.setData("Text", text);
                
            } else if (document.queryCommandSupported && document.queryCommandSupported("copy")) {
                var textarea = document.createElement("textarea");
                textarea.value = text;
                textarea.style.position = "fixed";  // Prevent scrolling to bottom of page in Microsoft Edge.
                el.appendChild(textarea);
                textarea.select();
                try {
                    return document.execCommand('copy');
                } catch (ex) {
                    console.warn("Copy to clipboard failed.", ex);
                    return prompt("Copy to clipboard: Ctrl+C, Enter", text);
                } finally {
                    el.removeChild(textarea);
                }
            }
        }
		
        function generatorFunction(length,sets) {
			if (sets.indexOf('l'))
		        var chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
			else if (sets.indexOf('d')) 
				var chars = "0123456789";
			else  
				var chars = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
		    var token = "";
		    for (var i = 0; i <= length; i++) {
		        var randomNumber = Math.floor(Math.random() * chars.length);
                token += chars.substring(randomNumber, randomNumber +1);
		    }
		    return token;
        }

        function generateToken(el) {
		    var token = generatorFunction(8,"")+"-"+generatorFunction(4,"")+"-"+generatorFunction(4,"d")+"-"+generatorFunction(4,"")+"-"+generatorFunction(12,"");
		    el.value = token;
        }
	
    </script>  
    
    <?php if (isset($_SESSION['lf_flash'])) { ?>
        <script type="text/javascript">
            $('.notifyjs-corner').empty();
            $.notify(<?php echo json_encode($_SESSION['lf_flash']['msg']); ?>, {position: "top right", className: <?php echo json_encode($_SESSION['lf_flash']['class']); ?>});
        </script>
        <?php unset($_SESSION['lf_flash']); ?>
    <?php } ?>

    <?php if (isset($_SESSION['msg'])) { ?>
        <script type="text/javascript">
            var _class=<?php echo json_encode(!empty($_SESSION["class"]) ? $_SESSION["class"] : "success"); ?>;
            var _msg=<?php echo json_encode(isset($client_lang[$_SESSION["msg"]]) ? $client_lang[$_SESSION["msg"]] : ''); ?>;
            _msg=_msg.replace(/(<([^>]+)>)/ig,"");
            $('.notifyjs-corner').empty();
            if (_msg) $.notify(_msg,{position: "top right",className: _class});
        </script>
        <?php unset($_SESSION['msg']); ?>
        <?php unset($_SESSION['class']);?>
    <?php } ?>

</body>
</html>