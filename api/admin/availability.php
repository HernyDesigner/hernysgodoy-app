<?php

require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../core/Database.php';
require __DIR__ . '/../../core/Auth.php';
require __DIR__ . '/../../core/Availability.php';

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

$user = Auth::user();

$businessId = 1;

if ($user['role'] !== 'super_admin') {
    $businessId = (int) $user['business_id'];
}

$serviceId = (int) ($_GET['service_id'] ?? 0);
$date = trim($_GET['date'] ?? '');
$excludeAppointmentId = isset($_GET['exclude_id']) && $_GET['exclude_id'] !== ''
    ? (int) $_GET['exclude_id']
    : null;

$professionalId = isset($_GET['professional_id']) && $_GET['professional_id'] !== ''
    ? (int) $_GET['professional_id']
    : null;

if ($serviceId <= 0 || $date === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Faltan service_id o date',
        'slots' => []
    ]);
    exit;
}

$availability = new Availability();

$slots = $availability->getAvailableSlots(
    $businessId,
    $serviceId,
    $date,
    $excludeAppointmentId,
    $professionalId
);

echo json_encode([
    'success' => true,
    'slots' => $slots
]);