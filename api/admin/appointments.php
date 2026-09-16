<?php

require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../core/Database.php';
require __DIR__ . '/../../core/Auth.php';

header('Content-Type: application/json; charset=utf-8');

Auth::startSession();

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

$db = Database::connect();

$user = Auth::user();

$businessId = 1;

if ($user['role'] !== 'super_admin') {
    $businessId = (int) $user['business_id'];
}

$startRaw = $_GET['start'] ?? date('Y-m-d 00:00:00');
$endRaw = $_GET['end'] ?? date('Y-m-d 23:59:59');

$start = date('Y-m-d H:i:s', strtotime($startRaw));
$end = date('Y-m-d H:i:s', strtotime($endRaw));

$stmt = $db->prepare("
    SELECT
        a.id,
        a.start_at,
        a.end_at,
        a.status,
        a.origin_channel,
        a.deposit_status,
        a.deposit_amount,
        a.total_amount,
        a.customer_notes,
        a.internal_notes,

        c.name AS customer_name,
        c.phone AS customer_phone,
        c.email AS customer_email,

        s.name AS service_name,
        s.price_label,
        s.duration_default,

        p.name AS professional_name,
        p.phone AS professional_phone

    FROM appointments a

    INNER JOIN customers c 
        ON c.id = a.customer_id

    INNER JOIN services s 
        ON s.id = a.service_id

    LEFT JOIN professionals p 
        ON p.id = a.professional_id

    WHERE a.business_id = ?
      AND a.start_at < ?
      AND a.end_at > ?
      AND a.status NOT IN ('cancelled')

    ORDER BY a.start_at ASC
");

$stmt->execute([
    $businessId,
    $end,
    $start
]);

$appointments = $stmt->fetchAll();

$events = [];

foreach ($appointments as $row) {
    $events[] = [
        'id' => (string) $row['id'],
        'title' => $row['customer_name'] . ' · ' . $row['service_name'],
        'start' => $row['start_at'],
        'end' => $row['end_at'],
        'backgroundColor' => statusColor($row['status']),
        'borderColor' => statusColor($row['status']),
        'textColor' => '#ffffff',
        'extendedProps' => [
            'status' => $row['status'],
            'status_label' => statusLabel($row['status']),
            'origin_channel' => $row['origin_channel'],
            'deposit_status' => $row['deposit_status'],
            'deposit_amount' => $row['deposit_amount'],
            'total_amount' => $row['total_amount'],

            'customer_name' => $row['customer_name'],
            'customer_phone' => $row['customer_phone'],
            'customer_email' => $row['customer_email'],

            'service_name' => $row['service_name'],
            'price_label' => $row['price_label'],
            'duration_default' => $row['duration_default'],

            'professional_name' => $row['professional_name'] ?? '-',
            'professional_phone' => $row['professional_phone'] ?? null,

            'customer_notes' => $row['customer_notes'],
            'internal_notes' => $row['internal_notes'],
        ]
    ];
}

echo json_encode($events);
exit;

function statusLabel(string $status): string
{
    $labels = [
        'pending_approval' => 'Pendiente de aprobación',
        'pending_deposit' => 'Pendiente de seña',
        'confirmed' => 'Confirmado',
        'completed' => 'Completado',
        'cancelled' => 'Cancelado',
        'rescheduled' => 'Reprogramado',
        'no_show' => 'No asistió',
        'consultation' => 'Consulta',
    ];

    return $labels[$status] ?? $status;
}

function statusColor(string $status): string
{
    $colors = [
        'pending_approval' => '#f59e0b',
        'pending_deposit' => '#dc2626',
        'confirmed' => '#16a34a',
        'completed' => '#15803d',
        'cancelled' => '#6b7280',
        'rescheduled' => '#2563eb',
        'no_show' => '#991b1b',
        'consultation' => '#7c3aed',
    ];

    return $colors[$status] ?? '#111827';
}