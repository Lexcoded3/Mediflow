<?php
require_once '../../config.php';

header('Content-Type: application/json');

 $phone = trim($_GET['phone'] ?? '');
 $name = trim($_GET['name'] ?? '');

if (empty($phone) && empty($name)) {
    echo json_encode(['found' => false]);
    exit;
}

// Search by phone OR name
if (!empty($phone)) {
    $stmt = $conn->prepare("SELECT * FROM patients WHERE phone = ? LIMIT 1");
    $stmt->bind_param("s", $phone);
} else {
    $stmt = $conn->prepare("SELECT * FROM patients WHERE name LIKE ? LIMIT 5");
    $like = "%$name%";
    $stmt->bind_param("s", $like);
}

 $stmt->execute();
 $result = $stmt->get_result();

if ($result->num_rows > 0) {
    // If searching by phone, return single patient
    if (!empty($phone)) {
        $patient = $result->fetch_assoc();
        
        // Count previous visits
        $visit_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM visits WHERE patient_id = ?");
        $visit_stmt->bind_param("i", $patient['id']);
        $visit_stmt->execute();
        $visit_count = $visit_stmt->get_result()->fetch_assoc()['cnt'];
        
        echo json_encode([
            'found' => true,
            'patient' => [
                'id'         => $patient['id'],
                'patient_id' => $patient['patient_id'],
                'name'       => $patient['name'],
                'phone'      => $patient['phone'],
                'email'      => $patient['email'],
                'dob'        => $patient['dob'],
                'age'        => $patient['age'],
                'gender'     => $patient['gender'],
                'blood_group'=> $patient['blood_group'],
                'address'    => $patient['address'],
                'emergency_contact_name'  => $patient['emergency_contact_name'],
                'emergency_contact_phone' => $patient['emergency_contact_phone'],
            ],
            'visit_count' => $visit_count
        ]);
    } else {
        // Name search - return list
        $patients = [];
        while ($p = $result->fetch_assoc()) {
            $patients[] = [
                'id'         => $p['id'],
                'patient_id' => $p['patient_id'],
                'name'       => $p['name'],
                'phone'      => $p['phone'],
                'age'        => $p['age'],
                'gender'     => $p['gender'],
            ];
        }
        echo json_encode(['found' => true, 'list' => $patients]);
    }
} else {
    echo json_encode(['found' => false]);
}
?>