<?php
require_once '../config/config.php';
header('Content-Type: application/json');

$visit_id = trim($_GET['id'] ?? '');
if (empty($visit_id)) {
    echo json_encode(['found' => false]);
    exit;
}

$stmt = $conn->prepare("
    SELECT v.visit_id, v.token_number, v.status, v.visit_date, v.created_at,
           p.name as patient_name, p.phone, p.age, p.gender, p.blood_group,
           d.name as department_name,
           doc.name as doctor_name
    FROM visits v
    JOIN patients p ON v.patient_id = p.id
    JOIN departments d ON v.department_id = d.id
    LEFT JOIN doctors doc ON v.doctor_id = doc.id
    WHERE v.visit_id = ?
    LIMIT 1
");
$stmt->bind_param("s", $visit_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if ($row) {
    $row['created_at'] = date('h:i A', strtotime($row['created_at']));
    echo json_encode(['found' => true, 'visit' => $row]);
} else {
    echo json_encode(['found' => false]);
}