<?php
include("includes/db_helper.php");

$username = isset($_POST['user_login']) ? trim((string)$_POST['user_login']) : '';
$password = isset($_POST['nsofts_password_input']) ? (string)$_POST['nsofts_password_input'] : '';

function login_fail($msg) {
    $_SESSION['class'] = "error";
    $_SESSION['msg'] = $msg;
    header("Location:index.php");
    exit;
}

if ($username == "") login_fail("1");
if ($password == "") login_fail("2");

// freio simples contra tentativa de adivinhar senha (por sessao)
$now = time();
if (!isset($_SESSION['login_tries']) || $now - $_SESSION['login_tries']['t'] > 600) $_SESSION['login_tries'] = array('n' => 0, 't' => $now);
if ($_SESSION['login_tries']['n'] >= 8) { sleep(2); login_fail("4"); }

$row = db_row("SELECT * FROM tbl_admin WHERE username = ? OR email = ? LIMIT 1", array($username, $username));

if (!$row || !lf_password_verify($password, $row['password'])) {
    $_SESSION['login_tries']['n']++;
    usleep(400000);
    login_fail("4");
}
if ((int)$row['status'] === 0) login_fail("approve_admin");

// senha antiga em md5: converte para hash seguro no primeiro login
if (lf_password_is_legacy($row['password'])) {
    db_query("UPDATE tbl_admin SET password = ? WHERE id = ?", array(lf_password_hash($password), (int)$row['id']));
}

session_regenerate_id(true);
unset($_SESSION['login_tries']);
$_SESSION['id'] = (int)$row['id'];
$_SESSION['admin_name'] = $row['username'];
$_SESSION['admin_type'] = (int)$row['admin_type'];
$_SESSION['class'] = "success";
$_SESSION['msg'] = "17";

header("Location:dashboard.php");
exit;
