<?php
// Acoes AJAX do painel (ativar/desativar, excluir, novo codigo).
// Exige login, token CSRF e respeita a arvore de permissoes.
require("includes/db_helper.php");
require("includes/lb_helper.php");

header('Content-Type: application/json; charset=utf-8');

function pd_out($status, $msg = '', $extra = array()) {
    echo json_encode(array_merge(array('status' => $status, 'msg' => $msg, 'class' => $status == 1 ? 'success' : 'error'), $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

$me = lf_me();
if (!$me || (int)$me['status'] !== 1) pd_out(0, 'Sessao expirada. Entre novamente.');
if (!lf_csrf_ok()) pd_out(0, 'Sessao invalida. Recarregue a pagina.');

$action = isset($_POST['action']) ? (string)$_POST['action'] : '';
$table  = isset($_POST['table']) ? (string)$_POST['table'] : '';
$ids    = isset($_POST['id']) ? $_POST['id'] : array();
if (!is_array($ids)) $ids = explode(',', (string)$ids);
$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

// Retorna o registro apenas se o usuario logado pode mexer nele
function pd_allowed($table, $id) {
    if ($table === 'tbl_dns') {
        $r = db_row("SELECT * FROM tbl_dns WHERE id = ?", array($id));
        return ($r && lf_in_scope($r['owner_id'])) ? $r : null;
    }
    if ($table === 'tbl_devices') {
        $r = db_row("SELECT * FROM tbl_devices WHERE id = ?", array($id));
        return ($r && lf_in_scope($r['owner_id'])) ? $r : null;
    }
    if ($table === 'tbl_admin') {
        $r = db_row("SELECT * FROM tbl_admin WHERE id = ?", array($id));
        return ($r && lf_can_manage_user($r)) ? $r : null;
    }
    return null;
}

if (!in_array($table, array('tbl_dns', 'tbl_devices', 'tbl_admin'), true)) pd_out(0, 'Operacao nao permitida.');
if (!$ids) pd_out(0, 'Nenhum item selecionado.');

switch ($action) {

    case 'toggle_status': {
        $enable = (isset($_POST['for_action']) && $_POST['for_action'] === 'enable') ? 1 : 0;
        $row = pd_allowed($table, $ids[0]);
        if (!$row) pd_out(0, 'Voce nao tem permissao para alterar este item.');
        db_query("UPDATE `$table` SET status = ? WHERE id = ?", array($enable, $ids[0]));
        pd_out(1, $enable ? 'Ativado com sucesso.' : 'Desativado com sucesso.', array('action' => $enable ? 'enable' : 'disable'));
    }

    case 'multi_action': {
        $for = isset($_POST['for_action']) ? (string)$_POST['for_action'] : '';
        $done = 0;
        foreach ($ids as $id) {
            $row = pd_allowed($table, $id);
            if (!$row) continue;
            if ($for === 'enable' || $for === 'disable') {
                db_query("UPDATE `$table` SET status = ? WHERE id = ?", array($for === 'enable' ? 1 : 0, $id));
            } else if ($for === 'delete') {
                if ($table === 'tbl_dns') {
                    db_query("DELETE FROM tbl_devices WHERE dns_id = ?", array($id));
                    db_query("DELETE FROM tbl_dns WHERE id = ?", array($id));
                } else if ($table === 'tbl_devices') {
                    db_query("DELETE FROM tbl_devices WHERE id = ?", array($id));
                } else if ($table === 'tbl_admin') {
                    if ((int)$row['admin_type'] === ROLE_ADMIN) continue; // nunca exclui um ADMIN por aqui
                    // as revendas abaixo sobem um nivel; DNS, dispositivos e aparencia do usuario sao removidos
                    db_query("UPDATE tbl_admin SET parent_id = ? WHERE parent_id = ?", array((int)$row['parent_id'], $id));
                    db_query("DELETE FROM tbl_devices WHERE owner_id = ?", array($id));
                    db_query("DELETE FROM tbl_dns WHERE owner_id = ?", array($id));
                    db_query("DELETE FROM tbl_app WHERE cliente = ?", array($row['username']));
                    db_query("DELETE FROM tbl_admin WHERE id = ?", array($id));
                }
            } else {
                pd_out(0, 'Operacao desconhecida.');
            }
            $done++;
        }
        if (!$done) pd_out(0, 'Voce nao tem permissao para alterar este item.');
        lf_flash($for === 'delete' ? 'Excluido com sucesso.' : 'Atualizado com sucesso.');
        pd_out(1, 'OK');
    }

    // gera um novo codigo de parceria para a DNS (o antigo para de funcionar)
    case 'new_code': {
        if ($table !== 'tbl_dns') pd_out(0, 'Operacao nao permitida.');
        $row = pd_allowed('tbl_dns', $ids[0]);
        if (!$row) pd_out(0, 'Voce nao tem permissao para alterar esta DNS.');
        $code = lf_new_partner_code();
        db_query("UPDATE tbl_dns SET partner_code = ? WHERE id = ?", array($code, $ids[0]));
        pd_out(1, 'Novo codigo gerado: ' . $code, array('code' => $code));
    }

    default:
        pd_out(0, 'Operacao desconhecida.');
}
