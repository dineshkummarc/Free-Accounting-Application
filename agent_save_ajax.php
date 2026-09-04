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

\Core\Commission::ensureTables($pdo);

$agentId         = !empty($_POST['agent_id']) ? (int)$_POST['agent_id'] : 0;
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

    $isUpdate = false;
    if ($agentId > 0) {
        // Verify agent exists and belongs to current tenant
        $stFind = $pdo->prepare('SELECT id FROM sales_agents WHERE id = ? AND tenant_id = ?');
        $stFind->execute([$agentId, $tid]);
        if (!$stFind->fetch()) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Sales Agent or Channel Partner not found.']);
            exit;
        }

        // Perform UPDATE
        $stUpdate = $pdo->prepare('UPDATE sales_agents SET 
            name = ?, 
            type = ?, 
            email = ?, 
            phone = ?, 
            company_name = ?, 
            commission_type = ?, 
            calculation_base = ?, 
            rate = ?, 
            slab_json = ?, 
            milestone_target = ?, 
            milestone_rate = ?, 
            custom_formula = ?, 
            notes = ? 
            WHERE id = ? AND tenant_id = ?');
        $stUpdate->execute([
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
            $notes ?: null,
            $agentId,
            $tid
        ]);

        // Update or insert commission_rules
        $stRuleCheck = $pdo->prepare('SELECT id FROM commission_rules WHERE agent_id = ? AND tenant_id = ? LIMIT 1');
        $stRuleCheck->execute([$agentId, $tid]);
        $existingRuleId = $stRuleCheck->fetchColumn();

        if ($existingRuleId) {
            $stRule = $pdo->prepare('UPDATE commission_rules SET 
                commission_type = ?, 
                calculation_base = ?, 
                rate = ?, 
                slab_json = ?, 
                milestone_target = ?, 
                milestone_rate = ?, 
                custom_formula = ? 
                WHERE id = ? AND tenant_id = ?');
            $stRule->execute([
                $commissionType,
                $calculationBase,
                $rate,
                $slabJson,
                $milestoneTarget,
                $milestoneRate,
                $customFormula ?: null,
                $existingRuleId,
                $tid
            ]);
        } else {
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
        }

        $isUpdate = true;
        if (function_exists('log_audit')) {
            log_audit($pdo, 'update_sales_agent', 'sales_agents', $agentId, "Updated $type: $name with $commissionType commission");
        }
    } else {
        // Perform INSERT
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

        if (function_exists('log_audit')) {
            log_audit($pdo, 'create_sales_agent', 'sales_agents', $agentId, "Created $type: $name with $commissionType commission");
        }
    }

    $pdo->commit();

    $agentData = [
        'id'               => $agentId,
        'tenant_id'        => $tid,
        'name'             => $name,
        'type'             => $type,
        'type_label'       => $type === 'channel_partner' ? 'Channel Partner' : 'Sales Agent',
        'company_name'     => $companyName,
        'email'            => $email,
        'phone'            => $phone,
        'notes'            => $notes,
        'commission_type'  => $commissionType,
        'calculation_base' => $calculationBase,
        'rate'             => $rate,
        'slab_json'        => $slabJson ? json_decode($slabJson, true) : null,
        'milestone_target' => $milestoneTarget,
        'milestone_rate'   => $milestoneRate,
        'custom_formula'   => $customFormula,
        'badge'            => \Core\Commission::getBadge([
            'commission_type'  => $commissionType,
            'calculation_base' => $calculationBase,
            'rate'             => $rate,
            'slab_json'        => $slabJson,
            'milestone_target' => $milestoneTarget,
            'milestone_rate'   => $milestoneRate
        ])
    ];

    $partnerTypeLabel = ($type === 'channel_partner' ? 'Channel Partner' : 'Sales Agent');
    $actionMsg = $isUpdate ? "$partnerTypeLabel '$name' updated successfully." : "$partnerTypeLabel '$name' added successfully.";

    echo json_encode([
        'success'   => true,
        'is_update' => $isUpdate,
        'message'   => $actionMsg,
        'csrf'      => $newCsrfToken,
        'agent'     => $agentData
    ]);
} catch (\PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
