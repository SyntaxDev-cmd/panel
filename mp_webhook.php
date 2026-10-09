<?php
// ============================================================
//  WEBHOOK DO MERCADO PAGO
//  Configure no Mercado Pago (Suas integracoes > Webhooks) a URL:
//     https://SEU-SITE/mp_webhook.php      evento: Pagamentos
//  Os pedidos ja enviam esta URL sozinhos (notification_url), entao
//  funciona mesmo sem configurar. O pagamento e sempre consultado de novo
//  na API do Mercado Pago: um aviso falso nao ativa nada.
// ============================================================
define('LF_NO_SESSION', true);
include("includes/db_helper.php");
require_once("includes/landing.php");

header('Content-Type: application/json; charset=utf-8');

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) $body = array();

// formatos: Webhooks  {type:"payment", data:{id}}  (+ ?type=payment&data.id=ID)
//           IPN antigo ?topic=payment&id=ID  |  ?topic=merchant_order&id=ID
$type = isset($body['type']) ? (string)$body['type'] : (isset($_GET['type']) ? (string)$_GET['type'] : (isset($_GET['topic']) ? (string)$_GET['topic'] : ''));
if ($type === '' && isset($body['topic'])) $type = (string)$body['topic'];
$id = '';
if (isset($body['data']['id'])) $id = (string)$body['data']['id'];
else if (isset($_GET['data_id'])) $id = (string)$_GET['data_id'];
else if (isset($_GET['id'])) $id = (string)$_GET['id'];
else if (isset($body['resource'])) $id = basename((string)$body['resource']);

if ($id === '' || !lp_mp_ready()) { echo json_encode(array('ok' => true)); exit; }

$sigId = isset($_GET['data_id']) ? (string)$_GET['data_id'] : $id;
if (isset($_SERVER['HTTP_X_SIGNATURE']) && !mp_signature_ok($sigId)) {
    http_response_code(401);
    echo json_encode(array('ok' => false, 'error' => 'invalid signature'));
    exit;
}

if ($type === 'payment' || $type === 'payment.created' || $type === 'payment.updated') {
    mp_sync_payment($id);
} else if ($type === 'merchant_order' || $type === 'topic_merchant_order_wh') {
    $r = mp_request('GET', '/merchant_orders/' . preg_replace('/\D+/', '', $id));
    if (is_array($r['json']) && !empty($r['json']['payments'])) {
        foreach ($r['json']['payments'] as $p) if (!empty($p['id'])) mp_sync_payment($p['id']);
    }
}
echo json_encode(array('ok' => true));
