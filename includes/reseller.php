<?php
// ============================================================
//  ATIVACAO POR MAC FEITA POR UM USUARIO DO PAINEL (revenda / master / admin)
//  Usada pelo portal da revenda (revenda.php). Segue as mesmas regras do painel:
//  - so mexe em aparelhos da propria arvore (lf_in_scope)
//  - nao toma MAC ativado por outra revenda nem pelo site
//  - respeita o limite de aparelhos (max_devices) e de DNS (max_dns) do usuario
// ============================================================
if (!isset($mysqli)) { exit('No direct script access allowed'); }
require_once(__DIR__ . '/landing.php');

function rs_device_limit_ok($owner, $exceptId = 0) {
    if ((int)$owner['admin_type'] === ROLE_ADMIN || (int)$owner['max_devices'] <= 0) return true;
    $has = (int)db_val("SELECT COUNT(*) FROM tbl_devices WHERE owner_id = ? AND status = 1 AND is_auto = 0 AND (act_user <> '' OR last_seen >= ?) AND id <> ?",
        array((int)$owner['id'], lf_active_since(), (int)$exceptId));
    return $has < (int)$owner['max_devices'];
}
function rs_usage($owner) {
    $used = (int)db_val("SELECT COUNT(*) FROM tbl_devices WHERE owner_id = ? AND status = 1 AND is_auto = 0 AND (act_user <> '' OR last_seen >= ?)", array((int)$owner['id'], lf_active_since()));
    return array('used' => $used, 'max' => (int)$owner['admin_type'] === ROLE_ADMIN ? 0 : (int)$owner['max_devices']);
}

// $list = array('base','user','pass','output'). $expires: timestamp (0 = sem vencimento)
// retorna array('ok'=>bool, 'error'=>'', 'id'=>device_id, 'renewed'=>bool)
function rs_activate($me, $mac, $list, $output, $expires, $note) {
    $mac = lf_norm_mac($mac);
    if ($mac === '') return array('ok' => false, 'error' => 'MAC invalido. Ex.: A1:B2:C3:D4:E5:F6');
    $now = time();
    $meId = (int)$me['id'];

    // MAC ativo de outra revenda (ou pelo site, que e do dono do painel)?
    $other = db_row("SELECT id, owner_id, act_source FROM tbl_devices WHERE mac = ? AND act_user <> '' AND (act_expires = 0 OR act_expires > ?) ORDER BY act_updated DESC LIMIT 1", array($mac, $now));
    if ($other && $other['act_source'] === LP_SOURCE && (int)lf_role($me) !== ROLE_ADMIN) return array('ok' => false, 'error' => 'Este MAC foi ativado pelo proprio cliente no site.');
    if ($other && !lf_in_scope($other['owner_id'])) return array('ok' => false, 'error' => 'Este MAC ja esta ativo com outro revendedor.');

    // registro do aparelho para este usuario (o app pode ja ter aberto e registrado o MAC)
    $dev = db_row("SELECT * FROM tbl_devices WHERE owner_id = ? AND (mac = ? OR device_key = ?) ORDER BY (act_user <> '') DESC, id ASC LIMIT 1", array($meId, $mac, $mac));
    if (!$dev && $other && lf_in_scope($other['owner_id'])) $dev = db_row("SELECT * FROM tbl_devices WHERE id = ?", array((int)$other['id']));
    $isNew = !$dev || $dev['act_user'] === '';
    $owner = $dev ? db_row("SELECT * FROM tbl_admin WHERE id = ?", array((int)$dev['owner_id'])) : $me;
    if (!$owner) $owner = $me;
    if ($dev && (int)$dev['status'] === 0) return array('ok' => false, 'error' => 'Este aparelho esta bloqueado. Libere em Ativacoes antes.');
    if ($isNew && !rs_device_limit_ok($owner, $dev ? (int)$dev['id'] : 0)) return array('ok' => false, 'error' => 'Limite de aparelhos atingido (' . (int)$owner['max_devices'] . '). Fale com o seu fornecedor.');

    // DNS do servidor da lista (reaproveita; nova respeita o limite de DNS)
    $dnsId = lp_dns_find((int)$owner['id'], $list['base']);
    if (!$dnsId) {
        if ((int)$owner['admin_type'] !== ROLE_ADMIN && (int)$owner['max_dns'] > 0 && (int)db_val("SELECT COUNT(*) FROM tbl_dns WHERE owner_id = ?", array((int)$owner['id'])) >= (int)$owner['max_dns']) {
            return array('ok' => false, 'error' => 'Limite de DNS atingido (' . (int)$owner['max_dns'] . '). Use um servidor ja cadastrado no painel.');
        }
        $dnsId = lp_dns_for((int)$owner['id'], $list['base'], '');
    }
    $note = substr(trim((string)$note), 0, 120);
    $out = lp_norm_output($output);
    if ($out === '' && $dev && $dev['act_user'] !== '' && $dev['act_user'] === $list['user']) $out = (string)$dev['act_output'];   // renovacao: mantem o formato
    if ($dev) {
        db_query("UPDATE tbl_devices SET mac = ?, note = IF(? = '', note, ?), act_dns_id = ?, dns_id = ?, act_user = ?, act_pass = ?, act_output = ?, act_expires = ?, act_updated = ?, act_source = '', is_auto = 0 WHERE id = ?",
            array($mac, $note, $note, $dnsId, $dnsId, $list['user'], $list['pass'], $out, (int)$expires, $now, (int)$dev['id']));
        $id = (int)$dev['id'];
    } else {
        db_query("INSERT INTO tbl_devices (owner_id, dns_id, device_key, mac, platform, note, act_dns_id, act_user, act_pass, act_output, act_expires, act_updated, act_source, is_auto, status, first_seen, last_seen)
                  VALUES (?, ?, ?, ?, 'outro', ?, ?, ?, ?, ?, ?, ?, '', 0, 1, ?, 0)",
            array($meId, $dnsId, $mac, $mac, $note, $dnsId, $list['user'], $list['pass'], $out, (int)$expires, $now, $now));
        $id = db_last_id();
    }
    // o mesmo MAC ativado em outro registro da arvore: vale so este
    db_query("UPDATE tbl_devices SET act_user = '', act_pass = '', act_dns_id = 0 WHERE mac = ? AND id <> ? AND act_user <> '' AND " . lf_scope_sql('owner_id'), array($mac, $id));
    return array('ok' => true, 'error' => '', 'id' => $id, 'renewed' => !$isNew);
}
