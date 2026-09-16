<?php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../core/Database.php';

header('Content-Type: application/json; charset=utf-8');

$db = Database::connect();

$slug = trim($_GET['business_slug'] ?? $_GET['slug'] ?? 'meli-figarola');

$stmt = $db->prepare("
    SELECT
        id,
        name,
        slug,
        primary_color,
        secondary_color,
        whatsapp,
        instagram,
        facebook,
        status
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

$stmt = $db->prepare("
    SELECT
        s.id,
        s.category_id,
        s.name,
        s.description,
        s.price_type,
        s.price,
        s.price_label,
        s.duration_type,
        s.duration_default,
        s.duration_min,
        s.duration_max,
        s.requires_deposit,
        s.deposit_type,
        s.deposit_amount,
        s.requires_approval,
        s.is_bookable,
        s.capacity_per_slot,
        s.blocks_full_schedule,
        sc.name AS category_name,

        (
            SELECT COUNT(*)
            FROM service_professionals sp
            INNER JOIN professionals p ON p.id = sp.professional_id
            WHERE sp.service_id = s.id
              AND sp.business_id = s.business_id
              AND sp.is_active = 1
              AND p.is_active = 1
        ) AS active_professionals_count

    FROM services s
    LEFT JOIN service_categories sc ON sc.id = s.category_id
    WHERE s.business_id = ?
      AND s.is_active = 1
      AND s.is_bookable = 1
    HAVING active_professionals_count = 1
    ORDER BY sc.sort_order ASC, s.sort_order ASC, s.name ASC
");

$stmt->execute([(int) $business['id']]);
$services = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'business' => $business,
    'services' => $services
]);