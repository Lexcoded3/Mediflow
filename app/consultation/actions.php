<?php
require_once '../config/config.php';
header('Content-Type: application/json');

$action   = $_GET['action']   ?? '';
$visit_id = $_GET['visit_id'] ?? '';

if (empty($action) || empty($visit_id)) {
    echo json_encode(['success' => false, 'msg' => 'Missing parameters']);
    exit;
}

// Get visit — always resolve the integer PK so every query below uses the correct type
$stmt = $conn->prepare("SELECT id, status FROM visits WHERE visit_id = ?");
$stmt->bind_param("s", $visit_id);
$stmt->execute();
$visit = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$visit) {
    echo json_encode(['success' => false, 'msg' => 'Visit not found']);
    exit;
}

$visit_int_id = (int)$visit['id']; // use this integer FK in all lab_orders queries

// ─── add_lab_order ────────────────────────────────────────────────────────────
if ($action === 'add_lab_order') {
    $test_name = trim($_POST['test_name'] ?? '');

    if (empty($test_name)) {
        echo json_encode(['success' => false, 'msg' => 'Test name is required']);
        exit;
    }

    // Use integer visit PK, not the string visit_id slug
    $stmt = $conn->prepare("INSERT INTO lab_orders (visit_id, test_name, status) VALUES (?, ?, 'ordered')");
    $stmt->bind_param("is", $visit_int_id, $test_name);
    $stmt->execute();
    $order_id = $conn->insert_id;
    $stmt->close();

    echo json_encode(['success' => true, 'order_id' => $order_id, 'test_name' => $test_name]);

// ─── add_scan_order ───────────────────────────────────────────────────────────
} elseif ($action === 'add_scan_order') {
    $scan_type = trim($_POST['scan_type'] ?? '');

    if (empty($scan_type)) {
        echo json_encode(['success' => false, 'msg' => 'Scan type is required']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO scans (visit_id, scan_type, status) VALUES (?, ?, 'ordered')");
    $stmt->bind_param("is", $visit_int_id, $scan_type);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'scan_type' => $scan_type]);

// ─── get_lab_orders ───────────────────────────────────────────────────────────
} elseif ($action === 'get_lab_orders') {
    $stmt = $conn->prepare("
        SELECT lo.*, lr.results, lr.remarks, lr.entered_at,
               CASE
                   WHEN lr.results IS NOT NULL THEN 'completed'
                   WHEN lo.status = 'collected'  THEN 'collected'
                   ELSE lo.status
               END as display_status
        FROM lab_orders lo
        LEFT JOIN lab_results lr ON lr.lab_order_id = lo.id
        WHERE lo.visit_id = ?
        ORDER BY lo.ordered_at DESC
    ");
    $stmt->bind_param("i", $visit_int_id);
    $stmt->execute();
    $list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['success' => true, 'orders' => $list]);

// ─── get_scans ────────────────────────────────────────────────────────────────
} elseif ($action === 'get_scans') {
    $stmt = $conn->prepare("SELECT * FROM scans WHERE visit_id = ? ORDER BY uploaded_at DESC");
    $stmt->bind_param("i", $visit_int_id);
    $stmt->execute();
    $list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['success' => true, 'scans' => $list]);

// ─── remove_lab_order ─────────────────────────────────────────────────────────
} elseif ($action === 'remove_lab_order') {
    $id = (int)($_GET['id'] ?? 0);

    if (!$id) {
        echo json_encode(['success' => false, 'msg' => 'Invalid ID']);
        exit;
    }

    // Scope delete to this visit so a rogue request can't delete another visit's orders
    $stmt = $conn->prepare("DELETE FROM lab_orders WHERE id = ? AND visit_id = ?");
    $stmt->bind_param("ii", $id, $visit_int_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true]);

// ─── unknown ──────────────────────────────────────────────────────────────────
} else {
    echo json_encode(['success' => false, 'msg' => 'Unknown action']);
}
?>