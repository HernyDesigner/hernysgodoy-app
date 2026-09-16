<?php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Availability.php';

header('Content-Type: application/json; charset=utf-8');

$db = Database::connect();

$slug = trim($_GET['business_slug'] ?? $_GET['slug'] ?? 'meli-figarola');
$serviceId = (int) ($_GET['service_id'] ?? 0);
$date = trim($_GET['date'] ?? '');

if ($serviceId <= 0 || $date === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Faltan datos para consultar disponibilidad.',
        'slots' => []
    ]);
    exit;
}

$dateObject = DateTime::createFromFormat('Y-m-d', $date);

if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
    echo json_encode([
        'success' => false,
        'message' => 'Fecha inválida.',
        'slots' => []
    ]);
    exit;
}

$today = new DateTime('today');
$minDate = (clone $today)->modify('+2 days');
$maxDate = (clone $today)->modify('+30 days');

if ($dateObject < $minDate || $dateObject > $maxDate) {
    echo json_encode([
        'success' => false,
        'message' => 'La fecha debe estar entre 2 y 30 días desde hoy.',
        'slots' => []
    ]);
    exit;
}

$business = getBusinessBySlug($db, $slug);

if (!$business) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Negocio no encontrado.',
        'slots' => []
    ]);
    exit;
}

$businessId = (int) $business['id'];

$service = getService($db, $businessId, $serviceId);

if (!$service) {
    echo json_encode([
        'success' => false,
        'message' => 'Servicio no disponible.',
        'slots' => []
    ]);
    exit;
}

$professional = getSingleProfessionalForService($db, $businessId, $serviceId);

if (!$professional) {
    echo json_encode([
        'success' => false,
        'message' => 'Este servicio todavía no tiene una profesional única asignada.',
        'slots' => []
    ]);
    exit;
}

$availability = new Availability();

$slots = $availability->getAvailableSlots(
    $businessId,
    $serviceId,
    $date,
    null,
    (int) $professional['id']
);

echo json_encode([
    'success' => true,
    'mode' => 'service_professional',
    'slots' => $slots
]);
exit;

function getBusinessBySlug(PDO $db, string $slug): ?array
{
    $stmt = $db->prepare("
        SELECT *
        FROM businesses
        WHERE slug = ?
          AND status = 'active'
        LIMIT 1
    ");

    $stmt->execute([$slug]);
    $business = $stmt->fetch();

    return $business ?: null;
}

function getService(PDO $db, int $businessId, int $serviceId): ?array
{
    $stmt = $db->prepare("
        SELECT *
        FROM services
        WHERE business_id = ?
          AND id = ?
          AND is_active = 1
          AND is_bookable = 1
        LIMIT 1
    ");

    $stmt->execute([
        $businessId,
        $serviceId
    ]);

    $service = $stmt->fetch();

    return $service ?: null;
}

function getSingleProfessionalForService(PDO $db, int $businessId, int $serviceId): ?array
{
    $stmt = $db->prepare("
        SELECT
            p.id,
            p.name,
            p.phone,
            p.role_label
        FROM service_professionals sp
        INNER JOIN professionals p ON p.id = sp.professional_id
        WHERE sp.business_id = ?
          AND sp.service_id = ?
          AND sp.is_active = 1
          AND p.is_active = 1
        ORDER BY p.sort_order ASC, p.name ASC
    ");

    $stmt->execute([
        $businessId,
        $serviceId
    ]);

    $professionals = $stmt->fetchAll();

    if (count($professionals) !== 1) {
        return null;
    }

    return $professionals[0];
}