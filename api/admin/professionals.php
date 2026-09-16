<?php

require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../core/Database.php';
require __DIR__ . '/../../core/Auth.php';

header('Content-Type: application/json; charset=utf-8');

Auth::startSession();

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'No autorizado'
    ]);
    exit;
}

$db = Database::connect();

$user = Auth::user();

$businessId = 1;

if ($user['role'] !== 'super_admin') {
    $businessId = (int) $user['business_id'];
}

$serviceId = (int) ($_GET['service_id'] ?? 0);

if ($serviceId <= 0) {
    echo json_encode([
        'success' => true,
        'professionals' => []
    ]);
    exit;
}

$stmt = $db->prepare("
    SELECT
        p.id,
        p.name,
        p.phone,
        p.role_label,
        p.is_owner
    FROM professionals p
    INNER JOIN service_professionals sp
        ON sp.professional_id = p.id
        AND sp.business_id = p.business_id
        AND sp.is_active = 1
    WHERE p.business_id = ?
      AND sp.service_id = ?
      AND p.is_active = 1
    ORDER BY p.sort_order ASC, p.name ASC
");

$stmt->execute([
    $businessId,
    $serviceId
]);

echo json_encode([
    'success' => true,
    'professionals' => $stmt->fetchAll()
]);