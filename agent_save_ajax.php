<?php
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please sign in.']);
    exit;
}

if (!has_role(['owner', 'admin', 'accountant', 'sales'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden. Insufficient permissions.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

try {
    verify_csrf();
} catch (\Throwable $e) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'Invalid or expired CSRF token. Please refresh the page.']);
    exit;
}

$newCsrfToken = csrf_token();

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

$name            = trim($_POST['name'] ?? '');
$type            = in_array($_POST['type'] ?? '', ['agent', 'channel_partner']) ? $_POST['type'] : 'agent';
$companyName     = trim($_POST['company_name'] ?? '');
$email           = trim($_POST['email'] ?? '');
$phone           = trim($_POST['phone'] ?? '');
$notes           = trim($_POST['notes'] ?? '');
$commissionType  = in_array($_POST['commission_type'] ?? '', ['percentage', 'slab', 'fixed', 'milestone', 'custom']) ? $_POST['commission_type'] : 'percentage';
$calculationBase = in_array($_POST['calculation_base'] ?? '', ['total', 'subtotal', 'paid_amount']) ? $_POST['calculation_base'] : 'total';
$rate            = max(0, (float)($_POST['rate'] ?? 0));
$milestoneTarget = max(0, (float)($_POST['milestone_target'] ?? 0));
$milestoneRate   = max(0, (float)($_POST['milestone_rate'] ?? 0));
$customFormula   = trim($_POST['custom_formula'] ?? '');

// Process Slab / Tiered JSON
$slabJson = null;
if ($commissionType === 'slab') {
    if (!empty($_POST['slab_from']) && is_array($_POST['slab_from'])) {
        $tiers = [];
        $froms = $_POST['slab_from'];
        $tos   = $_POST['slab_to'] ?? [];
        $rates = $_POST['slab_rate'] ?? [];
        foreach ($froms as $idx => $fVal) {
            $f = max(0, (float)$fVal);
            $t = (isset($tos[$idx]) && trim($tos[$idx]) !== '') ? max(0, (float)$tos[$idx]) : null;
            $r = max(0, (float)($rates[$idx] ?? 0));
            $tiers[] = ['from' => $f, 'to' => $t, 'rate' => $r];
        }
        $slabJson = json_encode($tiers);
    } elseif (!empty($_POST['slab_json'])) {
        $raw = is_string($_POST['slab_json']) ? json_decode($_POST['slab_json'], true) : $_POST['slab_json'];
        $slabJson = is_array($raw) ? json_encode($raw) : null;
    }
}

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Agent or Channel Partner name is required.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $st = $pdo->prepare('INSERT INTO sales_agents (tenant_id, name, type, email, phone, company_name, commission_type, calculation_base, rate, slab_json, milestone_target, milestone_rate, custom_formula, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $st->execute([
        $tid,
        $name,
        $type,
        $email ?: null,
        $phone ?: null,
        $companyName ?: null,
        $commissionType,
        $calculationBase,
        $rate,
        $slabJson,
        $milestoneTarget,
        $milestoneRate,
        $customFormula ?: null,
        $notes ?: null
    ]);
    $agentId = (int)$pdo->lastInsertId();

    // Insert into commission_rules
    $stRule = $pdo->prepare('INSERT INTO commission_rules (agent_id, tenant_id, name, commission_type, calculation_base, rate, slab_json, milestone_target, milestone_rate, custom_formula, priority) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
    $stRule->execute([
        $agentId,
        $tid,
        'Default Commission Rule',
        $commissionType,
        $calculationBase,
        $rate,
        $slabJson,
        $milestoneTarget,
        $milestoneRate,
        $customFormula ?: null
    ]);

    $pdo->commit();

    if (function_exists('log_audit')) {
        log_audit($pdo, 'create_sales_agent', 'sales_agents', $agentId, "Created $type: $name with $commissionType commission");
    }

    $agentData = [
        'id'               => $agentId,
        'name'             => $name,
        'type'             => $type,
        'type_label'       => $type === 'channel_partner' ? 'Channel Partner' : 'Sales Agent',
        'company_name'     => $companyName,
        'email'            => $email,
        'phone'            => $phone,
        'commission_type'  => $commissionType,
        'calculation_base' => $calculationBase,
        'rate'             => $rate,
        'slab_json'        => $slabJson ? json_decode($slabJson, true) : null,
        'badge'            => \Core\Commission::getBadge([
            'commission_type'  => $commissionType,
            'calculation_base' => $calculationBase,
            'rate'             => $rate,
            'slab_json'        => $slabJson,
            'milestone_target' => $milestoneTarget,
            'milestone_rate'   => $milestoneRate
        ])
    ];

    echo json_encode([
        'success' => true,
        'message' => ($type === 'channel_partner' ? 'Channel Partner' : 'Sales Agent') . " '$name' added successfully.",
        'csrf'    => $newCsrfToken,
        'agent'   => $agentData
    ]);
} catch (\PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
