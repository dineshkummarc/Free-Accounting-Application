<?php
require __DIR__ . '/bootstrap.php';
if (!has_role(['owner', 'admin', 'accountant'])) {
    echo json_encode(['success' => false, 'error' => 'Access denied.']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();

$rawInput = json_decode(file_get_contents('php://input'), true);
$name = trim($rawInput['name'] ?? $_POST['name'] ?? '');

// CSRF verification — token may come from JSON body (AJAX) or standard POST field (form submit)
// JS sends JSON body so we must read the token from the decoded payload, not $_POST
$submittedToken = $rawInput['csrf'] ?? $_POST['csrf'] ?? '';
if (!$submittedToken || !hash_equals($_SESSION['csrf'] ?? '', $submittedToken)) {
    http_response_code(419);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token. Please refresh the page.']);
    exit;
}
// Rotate the token after successful validation (matches bootstrap verify_csrf() behaviour)
$_SESSION['csrf'] = bin2hex(random_bytes(24));
$newCsrfToken = $_SESSION['csrf'];

if (empty($name)) {
    echo json_encode(['success' => false, 'error' => 'Category name cannot be empty.', 'csrf' => $newCsrfToken]);
    exit;
}

// Check if category already exists for this tenant
$stChk = $pdo->prepare("SELECT id, name FROM expense_categories WHERE tenant_id = ? AND LOWER(name) = LOWER(?)");
$stChk->execute([$tid, $name]);
$existing = $stChk->fetch();

if ($existing) {
    echo json_encode(['success' => true, 'id' => (int)$existing['id'], 'name' => $existing['name'], 'exists' => true, 'csrf' => $newCsrfToken]);
    exit;
}

// Insert new expense category
$stIns = $pdo->prepare("INSERT INTO expense_categories (tenant_id, name) VALUES (?, ?)");
$stIns->execute([$tid, $name]);
$newId = (int)$pdo->lastInsertId();

log_audit($pdo, 'create_expense_category', 'expense_categories', $newId, "Created expense category '$name' via quick dropdown");

echo json_encode([
    'success' => true,
    'id' => $newId,
    'name' => $name,
    'exists' => false,
    'csrf' => $newCsrfToken
]);
