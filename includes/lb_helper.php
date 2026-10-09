<?php 
if(count(get_included_files()) == 1) exit("No direct script access allowed");

# Insert Data
function Insert($table, $data){
    global $mysqli;
    //print_r($data);

    $fields = array_keys($data);
    $values = array_map(array($mysqli, 'real_escape_string'), array_values($data));

    //echo "INSERT INTO $table(".implode(",",$fields).") VALUES ('".implode("','", $values )."');";
    //exit;  
    $fields = array_map(function ($f) { return '`' . str_replace('`', '', $f) . '`'; }, $fields);
    $values = array_map(array($mysqli, 'real_escape_string'), array_map('strval', array_values($data)));
    $ok = mysqli_query($mysqli, "INSERT INTO $table(" . implode(",", $fields) . ") VALUES ('" . implode("','", $values) . "');");
    if (!$ok) error_log('[painel] Insert ' . $table . ': ' . mysqli_error($mysqli));
    return $ok;
}

// Update Data, Where clause is left optional
function Update($table_name, $form_data, $where_clause = ''){
    global $mysqli;
    // check for optional where clause
    $whereSQL = '';
    if (!empty($where_clause)) {
        // check to see if the 'where' keyword exists
        if (substr(strtoupper(trim($where_clause)), 0, 5) != 'WHERE') {
            // not found, add key word
            $whereSQL = " WHERE " . $where_clause;
        } else {
            $whereSQL = " " . trim($where_clause);
        }
    }
    // start the actual SQL statement
    $sql = "UPDATE " . $table_name . " SET ";

    // loop and build the column /
    $sets = array();
    foreach ($form_data as $column => $value) {
        // valores sempre escapados (antes um apostrofo no nome do app quebrava o UPDATE)
        $sets[] = "`" . str_replace('`', '', $column) . "` = '" . $mysqli->real_escape_string((string)$value) . "'";
    }
    $sql .= implode(', ', $sets);

    // append the where statement
    $sql .= $whereSQL;

    // run and return the query result
    return mysqli_query($mysqli, $sql);
}

//Delete Data, the where clause is left optional incase the user wants to delete every row!
function Delete($table_name, $where_clause = ''){
    global $mysqli;
    // check for optional where clause
    $whereSQL = '';
    if (!empty($where_clause)) {
        // check to see if the 'where' keyword exists
        if (substr(strtoupper(trim($where_clause)), 0, 5) != 'WHERE') {
            // not found, add keyword
            $whereSQL = " WHERE " . $where_clause;
        } else {
            $whereSQL = " " . trim($where_clause);
        }
    }
    // build the query
    $sql = "DELETE FROM " . $table_name . $whereSQL;

    // run and return the query result resource
    return mysqli_query($mysqli, $sql);
}

function compress_image($source_url, $destination_url, $quality){

    $info = getimagesize($source_url);

    if ($info['mime'] == 'image/jpeg'){
        $image = imagecreatefromjpeg($source_url);
    } else if ($info['mime'] == 'image/gif'){
        $image = imagecreatefromgif($source_url);
    } else if ($info['mime'] == 'image/png'){
        $image = imagecreatefrompng($source_url);
    } else {
        $image = imagecreatefromjpeg($source_url);
    }
    
    imagejpeg($image, $destination_url, $quality);
    return $destination_url;
}

function imageResize($source_url, $destination_url, $targetWidth, $targetHeight){
	
	list($orig_width, $orig_height, $type) = getimagesize($source_url);

	switch($type){
		case IMAGETYPE_GIF:
			$image = imagecreatefromgif($source_url);
	        $targetLayer=imagecreatetruecolor($targetWidth,$targetHeight);
	       	$negro = imagecolorallocate($targetLayer, 0, 0, 0);
	        imagecolortransparent($targetLayer, $negro);
	        imagecopyresampled($targetLayer,$image,0,0,0,0,$targetWidth,$targetHeight, $orig_width, $orig_height);
			imagegif($targetLayer, $destination_url);
			imagedestroy($targetLayer);
			break;
		case IMAGETYPE_JPEG:
			$image = imagecreatefromjpeg($source_url);
	        $targetLayer=imagecreatetruecolor($targetWidth,$targetHeight);
	       	$negro = imagecolorallocate($targetLayer, 0, 0, 0);
	        imagecolortransparent($targetLayer, $negro);
	        imagecopyresampled($targetLayer,$image,0,0,0,0,$targetWidth,$targetHeight, $orig_width, $orig_height);
            imagejpeg($targetLayer, $destination_url, 95);
			imagedestroy($targetLayer);
			break;
		case IMAGETYPE_PNG:
			$image = imagecreatefrompng($source_url);
	        $targetLayer=imagecreatetruecolor($targetWidth,$targetHeight);
	       	$negro = imagecolorallocate($targetLayer, 0, 0, 0);
	        imagecolortransparent($targetLayer, $negro);
	        imagecopyresampled($targetLayer,$image,0,0,0,0,$targetWidth,$targetHeight, $orig_width, $orig_height);
            imagepng($targetLayer, $destination_url);
			imagedestroy($targetLayer);
			break;
	}

    return $destination_url;
}

function cleanInput($inputText){
    return addslashes(trim($inputText));
}

function thousandsNumberFormat($num){
    if ($num > 1000) {
        $x = round($num);
        $x_number_format = number_format($x);
        $x_array = explode(',', $x_number_format);
        $x_parts = array(' K', ' M', ' B', ' T');
        $x_count_parts = count($x_array) - 1;
        $x_display = $x;
        $x_display = $x_array[0] . ((int) $x_array[1][0] !== 0 ? '.' . $x_array[1][0] : '');
        $x_display .= $x_parts[$x_count_parts - 1];
        return $x_display;
    }
    return $num;
}

function calculate_time_span($post_time, $flag = false){
    if ($post_time != '') {
        $seconds = time() - $post_time;
        $year = floor($seconds / 31556926);
        $months = floor($seconds / 2629743);
        $week = floor($seconds / 604800);
        $day = floor($seconds / 86400);
        $hours = floor($seconds / 3600);
        $mins = floor(($seconds - ($hours * 3600)) / 60);
        $secs = floor($seconds % 60);

        if ($seconds < 60) $time = $secs . " sec ago";
        else if ($seconds < 3600) $time = ($mins == 1) ? $mins . " min ago" : $mins . " mins ago";
        else if ($seconds < 86400) $time = ($hours == 1) ? $hours . " hour ago" : $hours . " hours ago";
        else if ($seconds < 604800) $time = ($day == 1) ? $day . " day ago" : $day . " days ago";
        else if ($seconds < 2629743) $time = ($week == 1) ? $week . " week ago" : $week . " weeks ago";
        else if ($seconds < 31556926) $time = ($months == 1) ? $months . " month ago" : $months . " months ago";
        else $time = ($year == 1) ? $year . " year ago" : $year . " years ago";

        if ($flag) {
            if ($day > 1) {
                $time = date('d-m-Y', $post_time);
            }
        }
        return $time;
    } else {
        return 'not available';
    }
}

function calculate_end_days($days, $endDay){
    $date_plus_days = new DateTime($days);
    $date_plus_days->modify("+$endDay days");
    return $date_plus_days->format("Y-m-d");
}

function LastID($table_name){   
    global $mysqli;
    return mysqli_insert_id($mysqli);
}

function call_api($url){
	$auth_token = ""; // token removido do codigo por seguranca
	$headers = array(
	    "User-Agent: php-curl",
	    "Accept: application/vnd.github+json",
	    "Authorization: Bearer " . $auth_token,
	    "X-GitHub-Api-Version: 2022-11-28"
	);
    $curl = curl_init();
    $params = array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => $headers
    );
	curl_setopt_array($curl, $params);
	$result = curl_exec($curl);
	$err = curl_error($curl);
	$response_code = intval(curl_getinfo($curl, CURLINFO_HTTP_CODE));
	curl_close($curl);
	return $result;
}

function get_latest_version($item_id){
    // $message = array($data);
    $data_array =  array(
    	'method_name' => "latest_version",
        'item_id' => $item_id
    );
    $get_data = call_api($data_array);
    $response = json_decode($get_data, true);
    return $response;
}

function activate_license($license,$create_lic = true){
    $get_data = call_api("https://api.github.com/repos/xoceunder/roku_feed/contents/key.json");
    $response = json_decode($get_data);
    $data_arr = json_decode(urldecode(base64_decode($response->content)), true);

	$current_path = realpath(__DIR__);
	$license_file = $current_path.'/.lic';
	$status = false;
	$message = "invalid_license";
	if(!empty($create_lic)){
		if(in_array($license, $data_arr)){
			$status = true;
			$message = "activate_license";
			$licfile = trim($license);
			file_put_contents($license_file, $licfile, LOCK_EX);
		} else {
			@chmod($license_file, 0777);
			if(is_writeable($license_file)){
				unlink($license_file);
			}
		}
	}
	$response =  array(
        'status' => $status,
		"lic_response"  => $license,
		"message" => "activate_license" );
	return $response;
}

function deactivate_license(){
    $current_path = realpath(__DIR__);
	$license_file = $current_path.'/.lic';
	$get_data = call_api($data_array);
	@chmod($license_file, 0777);
	if(is_writeable($license_file)){
		unlink($license_file);
	}
	return true;
}

function verify_purchase_code($license){
    $get_data = call_api("https://api.github.com/repos/xoceunder/roku_feed/contents/key.json");
    $response = json_decode($get_data);
    $data_arr = json_decode(urldecode(base64_decode($response->content)), true);

	return in_array($license, $data_arr);
}

function get_ip_from_third_party(){
	$curl = curl_init ();
	curl_setopt($curl, CURLOPT_URL, "http://ipecho.net/plain");
	curl_setopt($curl, CURLOPT_HEADER, 0);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10); 
	curl_setopt($curl, CURLOPT_TIMEOUT, 10);
	$response = curl_exec($curl);
	curl_close($curl);
	return $response;
}

function generateStrong($length = 4, $available_sets = 'ld'){
	$sets = array();
	if(strpos($available_sets, 'l') !== false)
		$sets[] = 'abcdefghijklmnopqrstuvwxyz';

	if(strpos($available_sets, 'd') !== false)
		$sets[] = '23456789';

	$all = '';
	$password = '';
	foreach($sets as $set){
		$password .= $set[array_rand(str_split($set))];
		$all .= $set;
	}

	$all = str_split($all);
	for($i = 0; $i < $length - count($sets); $i++)
		$password .= $all[array_rand($all)];

	$password = str_shuffle($password);
    return $password;
}

function generateStrongPassword(){
	$key = generateStrong(8)."-".generateStrong(4,"d")."-".generateStrong()."-".generateStrong()."-".generateStrong(12);
	return $key;
}

function getBaseUrl(){
    // Detecta HTTPS detrás de proxy y local
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
    );

    $protocol = $isHttps ? 'https://' : 'http://';

    // Host (con puerto si aplica)
    $host = $_SERVER['HTTP_HOST'];

    // Ruta base donde corre este script: p.ej. /ROKU/ROKUADMIN
    // dirname('/ROKU/ROKUADMIN/api2.php') => /ROKU/ROKUADMIN
    $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

    // Asegura slash final
    if ($basePath !== '') {
        $basePath .= '/';
    } else {
        $basePath = '/';
    }

    // Devuelve URL cruda (no escapada) para poder concatenar paths
    return $protocol . $host . $basePath;
}


function outputJson($Return=array()){
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    }
    /*Set response header*/
    header('Access-Control-Allow-Credentials: true');
    header('Content-Type: application/json');

    /*Final JSON response*/
    exit(json_encode($Return,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
?>