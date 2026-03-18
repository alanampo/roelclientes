<?php
// api_ia/cliente_ficha.php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$cfg = __DIR__ . '/config_api_ia.php';
if (!is_file($cfg)) {
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>'config_api_ia.php no encontrado'], JSON_UNESCAPED_UNICODE);
  exit;
}
require_once $cfg;

if (!isset($DB_CRM) || !($DB_CRM instanceof PDO) || !isset($DB_SALES) || !($DB_SALES instanceof PDO)) {
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>'PDO no inicializado ($DB_CRM/$DB_SALES)'], JSON_UNESCAPED_UNICODE);
  exit;
}

$DEBUG = (isset($_GET['debug']) && $_GET['debug'] === '1');
if ($DEBUG) {
  $DB_CRM->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $DB_SALES->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

set_exception_handler(function(Throwable $e) use ($DEBUG) {
  http_response_code(500);
  echo json_encode([
    'status' => 'error',
    'message' => $DEBUG ? ('Error: '.$e->getMessage()) : 'Error en consulta',
    'meta' => ['generated_at' => gmdate('c')],
  ], JSON_UNESCAPED_UNICODE);
  exit;
});

/* =========================
   Helpers básicos
========================= */

function readJsonBody(): array {
  $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
  $raw = file_get_contents('php://input');
  if (!$raw) return [];
  // en hosting a veces viene vacío; si trae JSON lo aceptamos.
  if ($ct !== '' && stripos($ct, 'application/json') === false) return [];
  $j = json_decode($raw, true);
  return is_array($j) ? $j : [];
}

function normDigits(string $s): string {
  $d = preg_replace('/\D+/', '', $s);
  return $d ? $d : '';
}

function normEmail(string $s): string {
  $s = trim($s);
  return $s !== '' ? mb_strtolower($s, 'UTF-8') : '';
}

function normRut(string $s): string {
  $s = trim($s);
  if ($s === '') return '';
  $s = str_replace(['.', ' ', '‐', '-', '–', '—'], '', $s);
  $s = mb_strtolower($s, 'UTF-8');
  $s = preg_replace('/[^0-9k]/', '', $s);
  return $s ? $s : '';
}

function normPhoneChile(string $raw): array {
  if (function_exists('ia_normalize_phone')) {
    $x = ia_normalize_phone($raw);
    return [
      'digits' => (string)($x['digits'] ?? ''),
      'local'  => (string)($x['local']  ?? ''),
      'e164'   => (string)($x['e164']   ?? ''),
    ];
  }
  $d = normDigits($raw);
  if ($d === '') return ['digits'=>'', 'local'=>'', 'e164'=>''];
  if (strpos($d, '00') === 0) $d = substr($d, 2);
  $local = $d;
  if (strpos($d, '56') === 0 && strlen($d) >= 11) $local = substr($d, 2);
  $e164 = (strlen($local) === 9) ? ('+56'.$local) : '';
  return ['digits'=>$d, 'local'=>$local, 'e164'=>$e164];
}

function getInput(): array {
  $body = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') ? readJsonBody() : [];

  $crmContactId = (int)($_GET['crm_contact_id'] ?? ($body['crm_contact_id'] ?? 0));
  $customerId   = (int)($_GET['customer_id']    ?? ($body['customer_id']    ?? 0));

  $q       = trim((string)($_GET['q']      ?? ($body['q']      ?? '')));
  $rut     = trim((string)($_GET['rut']    ?? ($body['rut']    ?? '')));
  $email   = trim((string)($_GET['email']  ?? ($body['email']  ?? '')));
  $phone   = trim((string)($_GET['phone']  ?? ($body['phone']  ?? '')));

  $limitOrders     = (int)($_GET['limit_orders']     ?? ($body['limit_orders']     ?? 50));
  $limitMessages   = (int)($_GET['limit_messages']   ?? ($body['limit_messages']   ?? 30));
  $limitActivity   = (int)($_GET['limit_activity']   ?? ($body['limit_activity']   ?? 50));
  $includeSegments = (int)($_GET['include_segments'] ?? ($body['include_segments'] ?? 0));

  $limitOrders   = max(0, min(200, $limitOrders));
  $limitMessages = max(0, min(200, $limitMessages));
  $limitActivity = max(0, min(200, $limitActivity));

  return [
    'crm_contact_id' => $crmContactId,
    'customer_id'    => $customerId,
    'q'              => $q,
    'rut'            => $rut,
    'email'          => $email,
    'phone'          => $phone,
    'limit_orders'   => $limitOrders,
    'limit_messages' => $limitMessages,
    'limit_activity' => $limitActivity,
    'include_segments' => ($includeSegments === 1),
  ];
}

function jsonTryDecode(?string $s): mixed {
  if ($s === null) return null;
  $s = trim($s);
  if ($s === '') return null;
  $j = json_decode($s, true);
  return (json_last_error() === JSON_ERROR_NONE) ? $j : null;
}

function fetchAll(PDO $db, string $sql, array $bind = []): array {
  $st = $db->prepare($sql);
  foreach ($bind as $k=>$v) $st->bindValue($k, $v);
  $st->execute();
  return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function fetchOne(PDO $db, string $sql, array $bind = []): ?array {
  $st = $db->prepare($sql);
  foreach ($bind as $k=>$v) $st->bindValue($k, $v);
  $st->execute();
  $r = $st->fetch(PDO::FETCH_ASSOC);
  return $r ? $r : null;
}

function pickBestMatch(array $rows, array $preferKeys): ?array {
  if (!$rows) return null;
  foreach ($preferKeys as $k => $v) {
    if ($v === null || $v === '') continue;
    foreach ($rows as $r) {
      if (!isset($r[$k])) continue;
      if ((string)$r[$k] === (string)$v) return $r;
    }
  }
  return $rows[0];
}

/* =========================
   Searches (conexión correcta por DB)
========================= */

function searchCrmContacts(PDO $db, array $in): array {
  if (!function_exists('ia_table_exists') || !ia_table_exists($db, 'crm_contacts')) return [];

  $where = [];
  $bind  = [];

  if ($in['crm_contact_id'] > 0) {
    $where[] = "c.id = :cid";
    $bind[':cid'] = $in['crm_contact_id'];
  }

  if ($in['customer_id'] > 0) {
    $where[] = "c.prod_customer_id = :pcid";
    $bind[':pcid'] = $in['customer_id'];
  }

  $rut = normRut($in['rut']);
  if ($rut !== '') {
    // nota: rut_clean existe en tu BD
    $where[] = "(c.rut_clean = :rutclean OR c.rut LIKE :rut OR c.notes LIKE :rut2)";
    $bind[':rutclean'] = $rut;
    $bind[':rut']  = '%'.$in['rut'].'%';
    $bind[':rut2'] = '%'.$rut.'%';
  }

  $email = normEmail($in['email']);
  if ($email !== '') {
    $where[] = "c.email = :email";
    $bind[':email'] = $email;
  }

  $pn = normPhoneChile($in['phone']);
  if ($pn['digits'] !== '') {
    // en tu CRM tienes phone_normalized
    $where[] = "(c.phone_normalized LIKE :p_d OR c.phone_normalized LIKE :p_l OR c.phone_normalized LIKE :p_e OR c.phone LIKE :p_raw)";
    $bind[':p_d']   = '%'.$pn['digits'].'%';
    $bind[':p_l']   = '%'.$pn['local'].'%';
    $bind[':p_e']   = $pn['e164'] !== '' ? ('%'.$pn['e164'].'%') : '%'.$pn['digits'].'%';
    $bind[':p_raw'] = '%'.$in['phone'].'%';
  }

  $q = trim((string)$in['q']);
  if ($q !== '') {
    $qEmail = normEmail($q);
    $qRut   = normRut($q);
    $qPhone = normPhoneChile($q);

    $or = [];
    $or[] = "c.name LIKE :qname";
    $bind[':qname'] = '%'.$q.'%';

    if ($qEmail !== '') { $or[] = "c.email = :qemail"; $bind[':qemail'] = $qEmail; }
    if ($qRut !== '') {
      $or[] = "(c.rut_clean = :qrutclean OR c.rut LIKE :qrut OR c.notes LIKE :qrut2)";
      $bind[':qrutclean'] = $qRut;
      $bind[':qrut']  = '%'.$q.'%';
      $bind[':qrut2'] = '%'.$qRut.'%';
    }
    if ($qPhone['digits'] !== '') {
      $or[] = "(c.phone_normalized LIKE :qpd OR c.phone_normalized LIKE :qpl OR c.phone LIKE :qpraw)";
      $bind[':qpd']   = '%'.$qPhone['digits'].'%';
      $bind[':qpl']   = '%'.$qPhone['local'].'%';
      $bind[':qpraw'] = '%'.$q.'%';
    }
    if (ctype_digit($q)) {
      $or[] = "c.id = :qid"; $bind[':qid'] = (int)$q;
      $or[] = "c.prod_customer_id = :qpcid"; $bind[':qpcid'] = (int)$q;
    }
    $where[] = '('.implode(' OR ', $or).')';
  }

  if (!$where) return [];
  $sql = "SELECT c.* FROM `crm_contacts` c WHERE ".implode(' AND ', $where)." ORDER BY c.id DESC LIMIT 10";
  return fetchAll($db, $sql, $bind);
}

function searchSalesCustomers(PDO $db, array $in): array {
  if (!function_exists('ia_table_exists') || !ia_table_exists($db, 'customers')) return [];

  $where = [];
  $bind  = [];

  if ($in['customer_id'] > 0) {
    $where[] = "id = :id";
    $bind[':id'] = $in['customer_id'];
  }

  $rut = normRut($in['rut']);
  if ($rut !== '') {
    $where[] = "(rut_clean = :rut OR rut = :rut2 OR rut LIKE :rut3)";
    $bind[':rut']  = $rut;
    $bind[':rut2'] = $in['rut'];
    $bind[':rut3'] = '%'.$in['rut'].'%';
  }

  $email = normEmail($in['email']);
  if ($email !== '') {
    $where[] = "email = :email";
    $bind[':email'] = $email;
  }

  $pn = normPhoneChile($in['phone']);
  if ($pn['digits'] !== '') {
    $where[] = "(telefono LIKE :tel_d OR telefono LIKE :tel_l OR telefono LIKE :tel_e)";
    $bind[':tel_d'] = '%'.$pn['digits'].'%';
    $bind[':tel_l'] = '%'.$pn['local'].'%';
    $bind[':tel_e'] = $pn['e164'] !== '' ? ('%'.$pn['e164'].'%') : '%'.$pn['digits'].'%';
  }

  $q = trim((string)$in['q']);
  if ($q !== '') {
    $qEmail = normEmail($q);
    $qRut   = normRut($q);
    $qPhone = normPhoneChile($q);

    $or = [];
    $or[] = "nombre LIKE :qname"; $bind[':qname'] = '%'.$q.'%';
    if ($qEmail !== '') { $or[] = "email = :qemail"; $bind[':qemail'] = $qEmail; }
    if ($qRut !== '')   { $or[] = "(rut_clean = :qrut OR rut LIKE :qrut2)"; $bind[':qrut'] = $qRut; $bind[':qrut2'] = '%'.$q.'%'; }
    if ($qPhone['digits'] !== '') { $or[] = "(telefono LIKE :qtel_d OR telefono LIKE :qtel_l)"; $bind[':qtel_d'] = '%'.$qPhone['digits'].'%'; $bind[':qtel_l'] = '%'.$qPhone['local'].'%'; }
    if (ctype_digit($q)) { $or[] = "id = :qid"; $bind[':qid'] = (int)$q; }
    $where[] = '('.implode(' OR ', $or).')';
  }

  if (!$where) return [];
  $sql = "SELECT * FROM `customers` WHERE ".implode(' AND ', $where)." ORDER BY id DESC LIMIT 10";
  return fetchAll($db, $sql, $bind);
}

/* =========================
   Insights (robusto a nombres de columnas)
========================= */

function getFirstInt(array $row, array $keys, int $default = 0): int {
  foreach ($keys as $k) {
    if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') return (int)$row[$k];
  }
  return $default;
}
function getFirstStr(array $row, array $keys, string $default = ''): string {
  foreach ($keys as $k) {
    if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') return (string)$row[$k];
  }
  return $default;
}

function buildInsights(?array $crmContact, ?array $salesCustomer, array $orders, ?array $waThread): array {
  $ins = [
    'kpis' => [
      'orders_total' => count($orders),
      'orders_delivered' => 0,
      'orders_cancelled' => 0,
      'total_spent_clp' => 0,
      'avg_ticket_clp' => null,
      'last_order_at' => null,
      'last_order_status' => null,
    ],
    'facts' => [],
  ];

  $tot = 0; $del = 0; $can = 0; $lastAt = null; $lastStatus = null;

  foreach ($orders as $o) {
    $tot += getFirstInt($o, ['total_clp','total','total_amount','grand_total'], 0);
    $st  = getFirstStr($o, ['status','estado','order_status'], 'unknown');

    if ($st === 'delivered' || $st === 'entregado' || $st === '8') $del++;
    if ($st === 'cancelled' || $st === 'anulado' || $st === 'cancelado') $can++;

    $dt = getFirstStr($o, ['created_at','date_add','fecha','createdAt'], '');
    if ($dt !== '' && (!$lastAt || $dt > $lastAt)) { $lastAt = $dt; $lastStatus = $st; }
  }

  $ins['kpis']['orders_delivered'] = $del;
  $ins['kpis']['orders_cancelled'] = $can;
  $ins['kpis']['total_spent_clp'] = $tot;
  $ins['kpis']['avg_ticket_clp'] = count($orders) > 0 ? (int)round($tot / count($orders)) : null;
  $ins['kpis']['last_order_at'] = $lastAt;
  $ins['kpis']['last_order_status'] = $lastStatus;

  $name = $crmContact['name'] ?? ($salesCustomer['nombre'] ?? null);
  if ($name) $ins['facts'][] = "Cliente: ".$name;

  $email = $crmContact['email'] ?? ($salesCustomer['email'] ?? null);
  if ($email) $ins['facts'][] = "Email: ".$email;

  $phone = $crmContact['phone'] ?? ($salesCustomer['telefono'] ?? null);
  if ($phone) $ins['facts'][] = "Teléfono: ".$phone;

  $reg = $crmContact['region'] ?? ($salesCustomer['region'] ?? null);
  $com = $crmContact['comuna'] ?? ($salesCustomer['comuna'] ?? null);
  if ($reg || $com) $ins['facts'][] = "Ubicación: ".trim(($reg ? $reg : '').($com ? " / ".$com : ''));

  if ($salesCustomer && isset($salesCustomer['rut']) && $salesCustomer['rut'] !== '') $ins['facts'][] = "RUT: ".$salesCustomer['rut'];

  if (count($orders) > 0) {
    $ins['facts'][] = "Pedidos: ".count($orders)." (entregados: ".$del.", anulados: ".$can.")";
    $ins['facts'][] = "Gasto total (CLP): ".$tot;
    if ($lastAt) $ins['facts'][] = "Último pedido: ".$lastAt." (estado: ".$lastStatus.")";
  } else {
    $ins['facts'][] = "Pedidos: 0 (en BD carrito)";
  }

  if ($waThread) {
    $stage = $waThread['stage'] ?? '';
    $status = $waThread['status'] ?? '';
    if ($stage || $status) $ins['facts'][] = "WhatsApp: status=".$status.", etapa=".$stage;
    if (!empty($waThread['ai_next_action'])) $ins['facts'][] = "Siguiente acción sugerida (IA): ".(string)$waThread['ai_next_action'];
  }

  return $ins;
}

/* =========================
   MAIN
========================= */

$in = getInput();

// 1) Validación mínima de tablas base
$crmHas = function_exists('ia_table_exists') ? ia_table_exists($DB_CRM, 'crm_contacts') : false;
$salesHas = function_exists('ia_table_exists') ? ia_table_exists($DB_SALES, 'customers') : false;

if (!$crmHas && !$salesHas) {
  http_response_code(500);
  echo json_encode([
    'status' => 'error',
    'message' => 'No hay acceso a tablas base (crm_contacts/customers). Revisa credenciales y GRANTS.',
    'debug' => $DEBUG ? [
      'crm_session' => function_exists('ia_db_session') ? ia_db_session($DB_CRM) : null,
      'sales_session' => function_exists('ia_db_session') ? ia_db_session($DB_SALES) : null,
    ] : null,
    'meta' => ['generated_at' => gmdate('c')],
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

$crmMatches   = $crmHas ? searchCrmContacts($DB_CRM, $in) : [];
$salesMatches = $salesHas ? searchSalesCustomers($DB_SALES, $in) : [];

$crmContact = null;
if ($crmMatches) {
  $prefer = [];
  if ($in['crm_contact_id'] > 0) $prefer['id'] = (string)$in['crm_contact_id'];
  if ($in['customer_id'] > 0) $prefer['prod_customer_id'] = (string)$in['customer_id'];
  if ($in['email'] !== '') $prefer['email'] = normEmail($in['email']);
  $crmContact = pickBestMatch($crmMatches, $prefer);
}

$salesCustomer = null;
if ($salesMatches) {
  $prefer = [];
  if ($in['customer_id'] > 0) $prefer['id'] = (string)$in['customer_id'];
  if ($in['email'] !== '') $prefer['email'] = normEmail($in['email']);
  $salesCustomer = pickBestMatch($salesMatches, $prefer);
}

$linkedCustomerId = 0;
if ($crmContact && isset($crmContact['prod_customer_id']) && (int)$crmContact['prod_customer_id'] > 0) {
  $linkedCustomerId = (int)$crmContact['prod_customer_id'];
} elseif ($salesCustomer && isset($salesCustomer['id'])) {
  $linkedCustomerId = (int)$salesCustomer['id'];
}

$explicit = ($in['crm_contact_id'] > 0) || ($in['customer_id'] > 0);
if (!$explicit) {
  $many = (count($crmMatches) > 1) || (count($salesMatches) > 1);
  $none = (!$crmMatches && !$salesMatches);

  if ($many) {
    echo json_encode([
      'status' => 'multiple',
      'message' => 'Hay múltiples coincidencias. Especifica crm_contact_id o customer_id para obtener la ficha completa.',
      'query' => $in,
      'matches' => [
        'crm_contacts' => array_map(function($r){
          return [
            'id' => (int)($r['id'] ?? 0),
            'prod_customer_id' => isset($r['prod_customer_id']) ? (int)$r['prod_customer_id'] : null,
            'name' => $r['name'] ?? null,
            'email' => $r['email'] ?? null,
            'phone' => $r['phone'] ?? null,
            'region' => $r['region'] ?? null,
            'comuna' => $r['comuna'] ?? null,
          ];
        }, $crmMatches),
        'customers' => array_map(function($r){
          return [
            'id' => (int)($r['id'] ?? 0),
            'rut' => $r['rut'] ?? null,
            'nombre' => $r['nombre'] ?? null,
            'email' => $r['email'] ?? null,
            'telefono' => $r['telefono'] ?? null,
            'region' => $r['region'] ?? null,
            'comuna' => $r['comuna'] ?? null,
            'is_active' => isset($r['is_active']) ? (int)$r['is_active'] : null,
          ];
        }, $salesMatches),
      ],
      'meta' => [
        'generated_at' => gmdate('c'),
      ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($none) {
    echo json_encode([
      'status' => 'not_found',
      'message' => 'No se encontró el cliente con los criterios entregados.',
      'query' => $in,
      'meta' => ['generated_at' => gmdate('c')],
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }
}

// Refresca customer completo si tenemos id
if ($linkedCustomerId > 0 && $salesHas) {
  $salesCustomer = fetchOne($DB_SALES, "SELECT * FROM `customers` WHERE id = :id LIMIT 1", [':id'=>$linkedCustomerId]) ?: $salesCustomer;
}

$crm = [
  'contact' => $crmContact,
  'contacts_normalized' => [],
  'deals' => [],
  'tasks' => [],
  'tickets' => [],
  'messages' => [],
  'campaign_runs' => [],
  'wa' => [
    'threads' => [],
    'kanban_moves' => [],
    'ai_log' => [],
    'leads' => [],
  ],
  'activity' => [],
  'segments' => null,
  'warnings' => [],
];

if ($crmContact && $crmHas) {
  $cid = (int)($crmContact['id'] ?? 0);

  $want = [
    'crm_contacts_normalized',
    'crm_deals',
    'crm_tasks',
    'crm_tickets',
    'crm_messages',
    'crm_wa_threads',
    'crm_wsp_leads',
    'crm_wa_kanban_moves',
    'crm_wa_ai_log',
    'crm_activity_log',
    'crm_segments',
  ];

  $exists = [];
  foreach ($want as $t) {
    $exists[$t] = (function_exists('ia_table_exists') ? ia_table_exists($DB_CRM, $t) : false);
    if (!$exists[$t]) $crm['warnings'][] = "Tabla CRM ausente o sin permisos: {$t}";
  }

  if ($exists['crm_contacts_normalized']) {
    $crm['contacts_normalized'] = fetchAll($DB_CRM,
      "SELECT * FROM `crm_contacts_normalized` WHERE crm_contact_id = :cid ORDER BY id DESC LIMIT 20",
      [':cid'=>$cid]
    );
  }

  if ($exists['crm_deals']) {
    $crm['deals'] = fetchAll($DB_CRM,
      "SELECT * FROM `crm_deals` WHERE contact_id = :cid ORDER BY id DESC LIMIT 50",
      [':cid'=>$cid]
    );
  }

  if ($exists['crm_tasks']) {
    $crm['tasks'] = fetchAll($DB_CRM,
      "SELECT * FROM `crm_tasks` WHERE contact_id = :cid ORDER BY COALESCE(due_at, created_at) DESC LIMIT 50",
      [':cid'=>$cid]
    );
  }

  if ($exists['crm_tickets']) {
    $crm['tickets'] = fetchAll($DB_CRM,
      "SELECT * FROM `crm_tickets` WHERE contact_id = :cid ORDER BY id DESC LIMIT 50",
      [':cid'=>$cid]
    );
  }

  if ($exists['crm_messages'] && $in['limit_messages'] > 0) {
    $crm['messages'] = fetchAll($DB_CRM,
      "SELECT * FROM `crm_messages` WHERE contact_id = :cid ORDER BY id DESC LIMIT ".(int)$in['limit_messages'],
      [':cid'=>$cid]
    );
  }

  if ($exists['crm_wa_threads']) {
    $crm['wa']['threads'] = fetchAll($DB_CRM,
      "SELECT * FROM `crm_wa_threads` WHERE crm_contact_id = :cid ORDER BY updated_at DESC LIMIT 5",
      [':cid'=>$cid]
    );
  }

  if ($exists['crm_wsp_leads']) {
    $crm['wa']['leads'] = fetchAll($DB_CRM,
      "SELECT * FROM `crm_wsp_leads` WHERE converted_contact_id = :cid ORDER BY id DESC LIMIT 20",
      [':cid'=>$cid]
    );
  }

  $waIds = [];
  foreach ($crm['wa']['threads'] as $t) {
    if (!empty($t['wa_id'])) $waIds[] = (string)$t['wa_id'];
  }
  $waIds = array_values(array_unique($waIds));

  if ($waIds) {
    $ph = [];
    $bind = [];
    foreach ($waIds as $i=>$wa) {
      $k = ':wa'.$i;
      $ph[] = $k;
      $bind[$k] = $wa;
    }

    if ($exists['crm_wa_kanban_moves']) {
      $crm['wa']['kanban_moves'] = fetchAll($DB_CRM,
        "SELECT * FROM `crm_wa_kanban_moves` WHERE wa_id IN (".implode(',', $ph).") ORDER BY id DESC LIMIT 200",
        $bind
      );
    }

    if ($exists['crm_wa_ai_log']) {
      $crm['wa']['ai_log'] = fetchAll($DB_CRM,
        "SELECT * FROM `crm_wa_ai_log` WHERE wa_id IN (".implode(',', $ph).") ORDER BY id DESC LIMIT 200",
        $bind
      );
    }
  }

  if ($exists['crm_activity_log'] && $in['limit_activity'] > 0) {
    $crm['activity'] = fetchAll($DB_CRM,
      "SELECT * FROM `crm_activity_log` WHERE (entity_type = 'contact' AND entity_id = :cid) ORDER BY id DESC LIMIT ".(int)$in['limit_activity'],
      [':cid'=>$cid]
    );
  }

  if ($in['include_segments'] && $exists['crm_segments']) {
    $crm['segments'] = fetchAll($DB_CRM,
      "SELECT id, name, description, rules_json, is_active, created_at, updated_at FROM `crm_segments` WHERE is_active = 1 ORDER BY id DESC LIMIT 100"
    );
  }
}

$commerce = [
  'customer' => $salesCustomer,
  'orders' => [],
  'orders_items' => [],
  'orders_stats' => [
    'by_status' => [],
    'total_orders' => 0,
    'sum_total_clp' => 0,
    'sum_shipping_clp' => 0,
    'sum_subtotal_clp' => 0,
    'last_order_at' => null,
  ],
  'production_requests' => [],
  'notes' => [
    'payments' => 'Pagos: si tienes una BD/tabla de pagos, se integra.',
    'invoices' => 'Facturas/DTE: si tienes BD/tabla, se integra.',
  ],
  'warnings' => [],
];

if ($salesHas && $linkedCustomerId > 0 && $in['limit_orders'] > 0) {
  $ordersAllView = (function_exists('ia_table_exists') && ia_table_exists($DB_SALES, 'v_orders_all')) ? '`v_orders_all`' : null;
  $itemsAllView  = (function_exists('ia_table_exists') && ia_table_exists($DB_SALES, 'v_order_items_all')) ? '`v_order_items_all`' : null;

  if ($ordersAllView) {
    $commerce['orders'] = fetchAll($DB_SALES,
      "SELECT * FROM {$ordersAllView} WHERE customer_id = :cid ORDER BY created_at DESC LIMIT ".(int)$in['limit_orders'],
      [':cid'=>$linkedCustomerId]
    );

    if ($itemsAllView && $commerce['orders']) {
      $pairs = [];
      foreach ($commerce['orders'] as $o) {
        $tipo = $o['tipo'] ?? null;
        $oid  = $o['id'] ?? null;
        if ($tipo && $oid) $pairs[] = ['tipo'=>(string)$tipo, 'order_id'=>(int)$oid];
      }

      $byTipo = [];
      foreach ($pairs as $p) {
        $t = $p['tipo'];
        if (!isset($byTipo[$t])) $byTipo[$t] = [];
        $byTipo[$t][] = (int)$p['order_id'];
      }

      foreach ($byTipo as $tipo=>$ids) {
        $ids = array_values(array_unique(array_filter($ids)));
        if (!$ids) continue;

        $ph = [];
        $bind = [':tipo'=>$tipo];
        foreach ($ids as $i=>$id) {
          $k = ':id'.$i;
          $ph[] = $k;
          $bind[$k] = $id;
        }

        $rows = fetchAll($DB_SALES,
          "SELECT * FROM {$itemsAllView} WHERE tipo = :tipo AND order_id IN (".implode(',', $ph).") ORDER BY order_id DESC, id ASC",
          $bind
        );

        foreach ($rows as $r) {
          $key = $tipo.':'.(string)($r['order_id'] ?? '');
          if ($key === $tipo.':') continue;
          if (!isset($commerce['orders_items'][$key])) $commerce['orders_items'][$key] = [];
          $commerce['orders_items'][$key][] = $r;
        }
      }
    }
  } else {
    if (function_exists('ia_table_exists') && ia_table_exists($DB_SALES, 'orders')) {
      $commerce['orders'] = fetchAll($DB_SALES,
        "SELECT 'detalle' AS tipo, * FROM `orders` WHERE customer_id = :cid ORDER BY created_at DESC LIMIT ".(int)$in['limit_orders'],
        [':cid'=>$linkedCustomerId]
      );
    } else {
      $commerce['warnings'][] = "No existe v_orders_all ni tabla orders en BD ventas.";
    }
  }

  if (function_exists('ia_table_exists') && ia_table_exists($DB_SALES, 'production_requests')) {
    $commerce['production_requests'] = fetchAll($DB_SALES,
      "SELECT * FROM `production_requests` WHERE customer_id = :cid ORDER BY id DESC LIMIT 50",
      [':cid'=>$linkedCustomerId]
    );
  }

  $byStatus = [];
  $sumT = 0; $sumS = 0; $sumSub = 0; $last = null;

  foreach ($commerce['orders'] as $o) {
    $st = getFirstStr($o, ['status','estado','order_status'], 'unknown');
    $byStatus[$st] = ($byStatus[$st] ?? 0) + 1;

    $sumT   += getFirstInt($o, ['total_clp','total','total_amount','grand_total'], 0);
    $sumSub += getFirstInt($o, ['subtotal_clp','subtotal','subtotal_amount','total_products'], 0);
    $sumS   += getFirstInt($o, ['shipping_cost_clp','shipping','shipping_cost','total_shipping'], 0);

    $dt = getFirstStr($o, ['created_at','date_add','fecha','createdAt'], '');
    if ($dt !== '' && (!$last || $dt > $last)) $last = $dt;
  }

  $commerce['orders_stats'] = [
    'by_status' => $byStatus,
    'total_orders' => count($commerce['orders']),
    'sum_total_clp' => $sumT,
    'sum_subtotal_clp' => $sumSub,
    'sum_shipping_clp' => $sumS,
    'last_order_at' => $last,
  ];
}

// JSON decode extras
if ($crm['contact'] && array_key_exists('preferences_json', $crm['contact'])) {
  $crm['contact']['preferences'] = jsonTryDecode((string)$crm['contact']['preferences_json']);
}

foreach (['messages','tickets','deals','tasks','activity'] as $k) {
  foreach ($crm[$k] as &$r) {
    if (isset($r['meta_json']))       $r['meta']       = jsonTryDecode((string)$r['meta_json']);
    if (isset($r['evaluation_json'])) $r['evaluation'] = jsonTryDecode((string)$r['evaluation_json']);
    if (isset($r['metrics_json']))    $r['metrics']    = jsonTryDecode((string)$r['metrics_json']);
    if (isset($r['plan_json']))       $r['plan']       = jsonTryDecode((string)$r['plan_json']);
  }
  unset($r);
}

foreach ($crm['wa']['threads'] as &$t) {
  if (isset($t['tags_json'])) $t['tags'] = jsonTryDecode((string)$t['tags_json']);
}
unset($t);

$waThreadTop = $crm['wa']['threads'][0] ?? null;
$insights = buildInsights($crmContact, $salesCustomer, $commerce['orders'], $waThreadTop);

echo json_encode([
  'status' => 'ok',
  'query' => $in,
  'crm' => $crm,
  'commerce' => $commerce,
  'insights' => $insights,
  'meta' => [
    'generated_at' => gmdate('c'),
  ],
], JSON_UNESCAPED_UNICODE);
