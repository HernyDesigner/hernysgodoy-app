<?php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../core/Database.php';

header('Content-Type: application/json; charset=utf-8');

$db = Database::connect();

$slug = trim($_GET['business_slug'] ?? $_GET['slug'] ?? 'meli-figarola');
$serviceId = (int) ($_GET['service_id'] ?? 0);

if ($serviceId <= 0) {
    echo json_encode([
        'success' => true,
        'professionals' => []
    ]);
    exit;
}

$stmt = $db->prepare("
    SELECT id
    FROM businesses
    WHERE slug = ?
      AND status = 'active'
    LIMIT 1
");

$stmt->execute([$slug]);
$business = $stmt->fetch();

if (!$business) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Negocio no encontrado.'
    ]);
    exit;
}

$businessId = (int) $business['id'];

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