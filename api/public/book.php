<?php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Availability.php';

header('Content-Type: application/json; charset=utf-8');

$db = Database::connect();

$slug = trim($_POST['business_slug'] ?? $_POST['slug'] ?? 'meli-figarola');
$serviceId = (int) ($_POST['service_id'] ?? 0);

$date = trim($_POST['date'] ?? '');
$time = trim($_POST['time'] ?? '');

$customerName = trim($_POST['customer_name'] ?? '');
$customerPhone = trim($_POST['customer_phone'] ?? '');
$customerNotes = trim($_POST['customer_notes'] ?? '');

$originChannel = trim($_POST['origin_channel'] ?? $_POST['origen'] ?? 'web');
$bookingRequestToken = trim($_POST['booking_request_token'] ?? '');

$allowedOrigins = ['whatsapp', 'instagram', 'facebook', 'web', 'manual'];

if (!in_array($originChannel, $allowedOrigins, true)) {
    $originChannel = 'web';
}

if ($serviceId <= 0 || $date === '' || $time === '' || $customerName === '' || $customerPhone === '' || $bookingRequestToken === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Completá todos los datos obligatorios. Actualizá la página si el problema continúa.'
    ]);
    exit;
}

$dateObject = DateTime::createFromFormat('Y-m-d', $date);

if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
    echo json_encode([
        'success' => false,
        'message' => 'Fecha inválida.'
    ]);
    exit;
}

$today = new DateTime('today');
$minDate = (clone $today)->modify('+2 days');
$maxDate = (clone $today)->modify('+30 days');

if ($dateObject < $minDate || $dateObject > $maxDate) {
    echo json_encode([
        'success' => false,
        'message' => 'La fecha debe estar entre 2 y 30 días desde hoy.'
    ]);
    exit;
}

$time = substr($time, 0, 5);

if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
    echo json_encode([
        'success' => false,
        'message' => 'Horario inválido.'
    ]);
    exit;
}

$business = getBusinessBySlug($db, $slug);

if (!$business) {
    echo json_encode([
        'success' => false,
        'message' => 'Negocio no encontrado.'
    ]);
    exit;
}

$businessId = (int) $business['id'];

$existingAppointment = getAppointmentByBookingRequestToken(
    $db,
    $businessId,
    $bookingRequestToken
);

if ($existingAppointment) {
    echo json_encode([
        'success' => true,
        'message' => 'Tu solicitud de turno ya fue registrada. Queda pendiente de confirmación.',
        'appointment' => [
            'id' => (int) $existingAppointment['id'],
            'status' => $existingAppointment['status'],
            'status_label' => 'Pendiente de confirmación',
            'service_name' => $existingAppointment['service_name'],
            'professional_name' => $existingAppointment['professional_name'],
            'date' => date('Y-m-d', strtotime($existingAppointment['start_at'])),
            'time' => date('H:i', strtotime($existingAppointment['start_at'])),
            'customer_name' => $existingAppointment['customer_name']
        ]
    ]);
    exit;
}

$service = getService($db, $businessId, $serviceId);

if (!$service) {
    echo json_encode([
        'success' => false,
        'message' => 'El servicio seleccionado no está disponible para reserva.'
    ]);
    exit;
}

$professional = getSingleProfessionalForService($db, $businessId, $serviceId);

if (!$professional) {
    echo json_encode([
        'success' => false,
        'message' => 'Este servicio todavía no tiene una profesional única asignada.'
    ]);
    exit;
}

$professionalId = (int) $professional['id'];

if (!slotIsAvailable($businessId, $serviceId, $date, $time, $professionalId)) {
    echo json_encode([
        'success' => false,
        'message' => 'Ese horario ya no está disponible. Elegí otro horario.'
    ]);
    exit;
}

$duration = (int) ($service['duration_default'] ?? 60);

if ($duration <= 0) {
    $duration = 60;
}

$startAt = $date . ' ' . $time . ':00';
$endAt = date('Y-m-d H:i:s', strtotime($startAt . ' +' . $duration . ' minutes'));

$customerPhone = normalizePhone($customerPhone);

try {
    $db->beginTransaction();

    $customerId = findOrCreateCustomer(
        $db,
        $businessId,
        $customerName,
        $customerPhone
    );

    if (appointmentDuplicateExists(
        $db,
        $businessId,
        $customerId,
        $serviceId,
        $professionalId,
        $startAt
    )) {
        $db->rollBack();

        echo json_encode([
            'success' => false,
            'message' => 'Ya existe una solicitud de turno igual para ese servicio, fecha y horario. Si necesitás modificarlo, escribinos por WhatsApp.'
        ]);
        exit;
    }

    $status = 'pending_approval';

    $depositRequired = (int) ($service['requires_deposit'] ?? 0) === 1 ? 1 : 0;
    $depositStatus = $depositRequired ? 'pending' : 'not_required';
    $depositAmount = $depositRequired ? ($service['deposit_amount'] ?? null) : null;

    $totalAmount = $service['price'] ?? null;
    $publicToken = bin2hex(random_bytes(16));

    $stmt = $db->prepare("
        INSERT INTO appointments
        (
            business_id,
            customer_id,
            service_id,
            professional_id,
            start_at,
            end_at,
            status,
            origin_channel,
            total_amount,
            deposit_required,
            deposit_status,
            deposit_amount,
            customer_notes,
            internal_notes,
            public_token,
            booking_request_token,
            created_by
        )
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $businessId,
        $customerId,
        $serviceId,
        $professionalId,
        $startAt,
        $endAt,
        $status,
        $originChannel,
        $totalAmount,
        $depositRequired,
        $depositStatus,
        $depositAmount,
        $customerNotes !== '' ? $customerNotes : null,
        null,
        $publicToken,
        $bookingRequestToken,
        'public'
    ]);

    $appointmentId = (int) $db->lastInsertId();

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Tu solicitud de turno fue enviada. Queda pendiente de confirmación.',
        'appointment' => [
            'id' => $appointmentId,
            'status' => $status,
            'status_label' => 'Pendiente de confirmación',
            'service_name' => $service['name'],
            'professional_name' => $professional['name'],
            'date' => $date,
            'time' => $time,
            'customer_name' => $customerName
        ]
    ]);
    exit;

} catch (Throwable $e) {
    $db->rollBack();

    echo json_encode([
        'success' => false,
        'message' => 'No se pudo registrar el turno. Intentá nuevamente.'
    ]);
    exit;
}

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

function getAppointmentByBookingRequestToken(PDO $db, int $businessId, string $bookingRequestToken): ?array
{
    $stmt = $db->prepare("
        SELECT
            a.id,
            a.status,
            a.start_at,
            c.name AS customer_name,
            s.name AS service_name,
            p.name AS professional_name
        FROM appointments a
        INNER JOIN customers c ON c.id = a.customer_id
        INNER JOIN services s ON s.id = a.service_id
        LEFT JOIN professionals p ON p.id = a.professional_id
        WHERE a.business_id = ?
          AND a.booking_request_token = ?
        LIMIT 1
    ");

    $stmt->execute([
        $businessId,
        $bookingRequestToken
    ]);

    $appointment = $stmt->fetch();

    return $appointment ?: null;
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

function slotIsAvailable(
    int $businessId,
    int $serviceId,
    string $date,
    string $time,
    int $professionalId
): bool {
    $availability = new Availability();

    $slots = $availability->getAvailableSlots(
        $businessId,
        $serviceId,
        $date,
        null,
        $professionalId
    );

    foreach ($slots as $slot) {
        if ($slot['time'] === $time) {
            return true;
        }
    }

    return false;
}

function findOrCreateCustomer(PDO $db, int $businessId, string $name, string $phone): int
{
    $stmt = $db->prepare("
        SELECT id
        FROM customers
        WHERE business_id = ?
          AND phone = ?
        LIMIT 1
    ");

    $stmt->execute([
        $businessId,
        $phone
    ]);

    $customer = $stmt->fetch();

    if ($customer) {
        $update = $db->prepare("
            UPDATE customers
            SET name = ?
            WHERE id = ?
              AND business_id = ?
            LIMIT 1
        ");

        $update->execute([
            $name,
            (int) $customer['id'],
            $businessId
        ]);

        return (int) $customer['id'];
    }

    $insert = $db->prepare("
        INSERT INTO customers
        (
            business_id,
            name,
            phone,
            created_at
        )
        VALUES
        (?, ?, ?, NOW())
    ");

    $insert->execute([
        $businessId,
        $name,
        $phone
    ]);

    return (int) $db->lastInsertId();
}

function appointmentDuplicateExists(
    PDO $db,
    int $businessId,
    int $customerId,
    int $serviceId,
    int $professionalId,
    string $startAt
): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*) AS total
        FROM appointments
        WHERE business_id = ?
          AND customer_id = ?
          AND service_id = ?
          AND professional_id = ?
          AND start_at = ?
          AND status NOT IN ('cancelled')
    ");

    $stmt->execute([
        $businessId,
        $customerId,
        $serviceId,
        $professionalId,
        $startAt
    ]);

    $row = $stmt->fetch();

    return (int) $row['total'] > 0;
}

function normalizePhone(string $phone): string
{
    return preg_replace('/[^0-9]/', '', $phone);
}