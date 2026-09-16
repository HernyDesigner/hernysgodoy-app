<?php

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

function statusClass(string $status): string
{
    $classes = [
        'pending_approval' => 'warning',
        'pending_deposit' => 'warning',
        'confirmed' => 'success',
        'completed' => 'success',
        'cancelled' => 'danger',
        'rescheduled' => 'muted',
        'no_show' => 'danger',
        'consultation' => 'muted',
    ];

    return $classes[$status] ?? 'muted';
}

function originLabel(string $origin): string
{
    $labels = [
        'whatsapp' => 'WhatsApp',
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'web' => 'Web',
        'manual' => 'Manual',
    ];

    return $labels[$origin] ?? $origin;
}

function cleanPhone($phone): string
{
    return preg_replace('/\D+/', '', (string) $phone);
}

$isPendingApprovalList = $type === 'pending_approval';

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= h($title) ?> - Turnero HG</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f4f5;
            color: #111827;
        }

        .topbar {
            background: #111111;
            color: white;
            padding: 18px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .topbar a {
            color: white;
            text-decoration: none;
            font-size: 14px;
            margin-left: 16px;
        }

        .container {
            padding: 32px;
            max-width: 1280px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
            margin-bottom: 24px;
        }

        .header h1 {
            margin: 0 0 8px;
            font-size: 30px;
        }

        .header p {
            margin: 0;
            color: #6b7280;
        }

        .card {
            background: white;
            border-radius: 18px;
            padding: 22px;
            box-shadow: 0 10px 24px rgba(0,0,0,0.05);
            overflow-x: auto;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 18px;
            font-size: 14px;
        }

        .alert.error {
            background: #fee2e2;
            color: #991b1b;
        }

        .alert.success {
            background: #dcfce7;
            color: #166534;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 980px;
        }

        th, td {
            padding: 13px 10px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            font-size: 14px;
            vertical-align: top;
        }

        th {
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .client {
            font-weight: bold;
        }

        .muted {
            color: #6b7280;
            font-size: 12px;
            margin-top: 4px;
        }

        .badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        .badge.success {
            background: #dcfce7;
            color: #166534;
        }

        .badge.warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge.danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge.muted {
            background: #e5e7eb;
            color: #374151;
        }

        .btn {
            border: none;
            background: #111111;
            color: white;
            padding: 10px 13px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: bold;
            font-size: 13px;
            cursor: pointer;
            display: inline-block;
            white-space: nowrap;
        }

        .btn.secondary {
            background: #e5e7eb;
            color: #111827;
        }

        .btn.success {
            background: #16a34a;
            color: white;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .empty {
            background: #f9fafb;
            border: 1px dashed #d1d5db;
            color: #6b7280;
            padding: 24px;
            border-radius: 14px;
            text-align: center;
        }

        .back-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        form {
            margin: 0;
        }

        @media (max-width: 760px) {
            .container {
                padding: 20px;
            }

            .header {
                flex-direction: column;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/mobile-app.css?v=3">
</head>
<body>

<div class="topbar">
    <strong>Turnero HG</strong>

    <div>
        <a href="<?= APP_URL ?>/dashboard">Dashboard</a>
        <a href="<?= APP_URL ?>/calendar">Calendario</a>
        <a href="<?= APP_URL ?>/services">Servicios</a>
        <a href="<?= APP_URL ?>/logout">Cerrar sesión</a>
    </div>
</div>

<div class="container">

    <div class="header">
        <div>
            <h1><?= h($title) ?></h1>
            <p><?= h($business['name']) ?> · <?= h($description) ?></p>
        </div>

        <div class="back-actions">
            <a href="<?= APP_URL ?>/dashboard" class="btn secondary">Volver al dashboard</a>
            <a href="<?= APP_URL ?>/calendar" class="btn secondary">Ver calendario</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert error"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?> 
        <div class="alert success"><?= h($success) ?></div>

        <?php if (stripos($success, 'confirmado') !== false): ?>
            <div style="margin: 12px 0 18px;">
                <a href="<?= APP_URL ?>/appointments/confirmed-messages" class="btn">
                    Ir a mensajes de confirmación
                </a>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (empty($appointments)): ?>

        <div class="empty">
            No hay turnos para mostrar en esta sección.
        </div>

    <?php else: ?>

        <div class="card">
            <table class="app-table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>WhatsApp</th>
                        <th>Servicio</th>
                        <th>Profesional</th>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Estado</th>
                        <th>Origen</th>
                        <th>Notas</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <td data-label="Cliente">
                                <div class="client"><?= h($appointment['customer_name']) ?></div>
                                <?php if ($appointment['customer_email']): ?>
                                    <div class="muted"><?= h($appointment['customer_email']) ?></div>
                                <?php endif; ?>
                            </td>

                            <td data-label="WhatsApp">
                                <?php $phone = cleanPhone($appointment['customer_phone']); ?>

                                <?php if ($phone): ?>
                                    <a
                                        href="https://wa.me/<?= h($phone) ?>"
                                        target="_blank"
                                        class="btn secondary"
                                    >
                                        <?= h($phone) ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>

                            <td data-label="Servicio">
                                <strong><?= h($appointment['service_name']) ?></strong>
                                <div class="muted">
                                    <?= h($appointment['duration_default']) ?> min
                                    <?php if ($appointment['price_label']): ?>
                                        · <?= h($appointment['price_label']) ?>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td data-label="Profesional">
                                <?= h($appointment['professional_name'] ?? '-') ?>
                            </td>

                            <td data-label="Fecha">
                                <?= h(date('d/m/Y', strtotime($appointment['start_at']))) ?>
                            </td>

                            <td data-label="Hora">
                                <?= h(date('H:i', strtotime($appointment['start_at']))) ?>
                                -
                                <?= h(date('H:i', strtotime($appointment['end_at']))) ?>
                            </td>

                            <td data-label="Estado">
                                <span class="badge <?= h(statusClass($appointment['status'])) ?>">
                                    <?= h(statusLabel($appointment['status'])) ?>
                                </span>

                                <?php if ($appointment['deposit_status'] === 'pending'): ?>
                                    <div class="muted">Seña pendiente</div>
                                <?php elseif ($appointment['deposit_status'] === 'paid'): ?>
                                    <div class="muted">Seña pagada</div>
                                <?php endif; ?>
                            </td>

                            <td data-label="Origen">
                                <?= h(originLabel($appointment['origin_channel'])) ?>
                            </td>

                            <td data-label="Notas">
                                <?php if ($appointment['customer_notes']): ?>
                                    <div><strong>Cliente:</strong> <?= h($appointment['customer_notes']) ?></div>
                                <?php endif; ?>

                                <?php if ($appointment['internal_notes']): ?>
                                    <div class="muted"><strong>Interna:</strong> <?= h($appointment['internal_notes']) ?></div>
                                <?php endif; ?>

                                <?php if (!$appointment['customer_notes'] && !$appointment['internal_notes']): ?>
                                    -
                                <?php endif; ?>
                            </td>

                            <td data-label="Acciones">
                                <div class="actions">

                                    <?php if ($isPendingApprovalList): ?>
                                        <form
                                            method="POST"
                                            action="<?= APP_URL ?>/appointments/confirm"
                                            onsubmit="return confirm('¿Confirmar este turno?');"
                                        >
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                            <input
                                                type="hidden"
                                                name="appointment_id"
                                                value="<?= h($appointment['id']) ?>"
                                            >

                                            <button type="submit" class="btn success">
                                                CONFIRMAR
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <a
                                        href="<?= APP_URL ?>/appointments/edit?id=<?= h($appointment['id']) ?>"
                                        class="btn secondary"
                                    >
                                        Editar turno
                                    </a>

                                    <?php if (($appointment['status'] ?? '') === 'confirmed'): ?>
                                        <form
                                            method="POST"
                                            action="<?= APP_URL ?>/appointments/send-confirmed-whatsapp"
                                            target="_blank"
                                            style="margin:0;"
                                        >
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="appointment_id" value="<?= h($appointment['id']) ?>">

                                            <button type="submit" class="btn">
                                                WhatsApp confirmado
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                </div>

                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</div>

</body>
<?php require __DIR__ . '/partials/mobile_nav.php'; ?>
</html>