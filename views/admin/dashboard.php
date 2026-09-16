<?php

$user = Auth::user();

function formatDateEs($date): string
{
    return date('d/m/Y', strtotime($date));
}

function formatTimeEs($date): string
{
    return date('H:i', strtotime($date));
}

function statusLabel($status): string
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

function statusClass($status): string
{
    $classes = [
        'pending_approval' => 'warning',
        'pending_deposit' => 'danger',
        'confirmed' => 'success',
        'completed' => 'muted',
        'cancelled' => 'muted',
        'rescheduled' => 'info',
        'no_show' => 'danger',
        'consultation' => 'info',
    ];

    return $classes[$status] ?? 'muted';
}

function originLabel($origin): string
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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Turnero HG</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f4f5;
            color: #111827;
        }

        a {
            color: inherit;
        }

        .topbar {
            background: #111111;
            color: white;
            padding: 18px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .topbar strong {
            font-size: 18px;
        }

        .topbar span {
            color: #d1d5db;
            font-size: 14px;
            margin-right: 16px;
        }

        .topbar a {
            color: white;
            text-decoration: none;
            font-size: 14px;
        }

        .container {
            padding: 32px;
            max-width: 1280px;
            margin: 0 auto;
        }

        .header {
            margin-bottom: 28px;
        }

        .header h1 {
            margin: 0 0 8px;
            font-size: 30px;
        }

        .header p {
            margin: 0;
            color: #6b7280;
        }

        .grid-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: white;
            border-radius: 18px;
            padding: 22px;
            box-shadow: 0 10px 24px rgba(0,0,0,0.05);
        }

        .stat-card span {
            color: #6b7280;
            font-size: 14px;
        }

        .stat-card strong {
            display: block;
            font-size: 34px;
            margin-top: 8px;
        }

        .main-grid {
            display: grid;
            grid-template-columns: 1.4fr 0.9fr;
            gap: 22px;
        }

        .card {
            background: white;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 10px 24px rgba(0,0,0,0.05);
            margin-bottom: 22px;
        }

        .card h2 {
            margin: 0 0 18px;
            font-size: 21px;
        }

        .appointment-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .appointment {
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 14px;
            display: grid;
            grid-template-columns: 88px 1fr auto;
            gap: 14px;
            align-items: center;
        }

        .appointment-time {
            font-weight: bold;
            font-size: 18px;
        }

        .appointment-data strong {
            display: block;
            margin-bottom: 4px;
        }

        .appointment-data small {
            color: #6b7280;
        }

        .badge {
            display: inline-block;
            padding: 6px 10px;
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

        .badge.info {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge.muted {
            background: #e5e7eb;
            color: #374151;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .quick-actions a {
            display: block;
            text-decoration: none;
            background: #111111;
            color: white;
            padding: 14px 16px;
            border-radius: 12px;
            font-weight: bold;
            font-size: 14px;
        }

        .quick-actions a.secondary {
            background: #e5e7eb;
            color: #111827;
        }

        .empty {
            color: #6b7280;
            background: #f9fafb;
            border: 1px dashed #d1d5db;
            padding: 18px;
            border-radius: 14px;
        }

        .business-box {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .business-logo {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: <?= h($business['primary_color']) ?>;
        }

        @media (max-width: 900px) {
            .grid-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .main-grid {
                grid-template-columns: 1fr;
            }

            .appointment {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 520px) {
            .container {
                padding: 20px;
            }

            .grid-stats {
                grid-template-columns: 1fr;
            }

            .topbar {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
        }
        .stat-card-link {
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .stat-card-link .stat-card {
            transition: transform .2s ease, box-shadow .2s ease;
            cursor: pointer;
        }

        .stat-card-link:hover .stat-card {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(0,0,0,0.08);
        }
    </style>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/mobile-app.css?v=6">
</head>
<body>

<div class="topbar">
    <strong>Turnero HG</strong>

    <div>
        <span><?= h($user['name']) ?> · <?= h($user['role']) ?></span>
        <a href="<?= APP_URL ?>/logout">Cerrar sesión</a>
    </div>
</div>

<div class="container">

    <div class="header">
        <div class="business-box">
            <div class="business-logo"></div>
            <div>
                <h1><?= h($business['name']) ?></h1>
                <p>Panel de turnos, solicitudes y accesos rápidos.</p>
            </div>
        </div>
    </div>

    <div class="dashboard-summary-grid">
        <a href="<?= APP_URL ?>/appointments/today" class="stat-card-link">
        <div class="stat-card">
            <span>Turnos de hoy</span>
            <strong><?= h($stats['today']) ?></strong>
        </div>
        </a>
        <a href="<?= APP_URL ?>/appointments/week" class="stat-card-link">
        <div class="stat-card">
            <span>Esta semana</span>
            <strong><?= h($stats['week']) ?></strong>
        </div>
        </a>
        <a href="<?= APP_URL ?>/appointments/pending-approval" class="stat-card-link">
        <div class="stat-card">
            <span>Pendientes de aprobación</span>
            <strong><?= h($stats['pending_approval']) ?></strong>
        </div>
        </a>

        <a href="<?= APP_URL ?>/appointments/pending-deposit" class="stat-card-link">
        <div class="stat-card">
            <span>Pendientes de seña</span>
            <strong><?= h($stats['pending_deposit']) ?></strong>
        </div>
        </a>
    </div>

    <div class="main-grid">

        <div>
            <div class="card">
                <h2>Turnos de hoy</h2>

                <?php if (empty($todayAppointments)): ?>
                    <div class="empty">
                        No hay turnos cargados para hoy.
                    </div>
                <?php else: ?>
                    <div class="appointment-list">
                        <?php foreach ($todayAppointments as $appointment): ?>
                            <div class="appointment">
                                <div class="appointment-time">
                                    <?= h(formatTimeEs($appointment['start_at'])) ?>
                                </div>

                                <div class="appointment-data">
                                    <strong><?= h($appointment['customer_name']) ?></strong>
                                    <small>
                                        <?= h($appointment['service_name']) ?>
                                        · <?= h(originLabel($appointment['origin_channel'])) ?>
                                        · <?= h($appointment['customer_phone']) ?>
                                    </small>
                                </div>

                                <div>
                                    <span class="badge <?= h(statusClass($appointment['status'])) ?>">
                                        <?= h(statusLabel($appointment['status'])) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2>Próximos turnos</h2>

                <?php if (empty($upcomingAppointments)): ?>
                    <div class="empty">
                        Todavía no hay próximos turnos cargados.
                    </div>
                <?php else: ?>
                    <div class="appointment-list">
                        <?php foreach ($upcomingAppointments as $appointment): ?>
                            <div class="appointment">
                                <div class="appointment-time">
                                    <?= h(formatDateEs($appointment['start_at'])) ?><br>
                                    <?= h(formatTimeEs($appointment['start_at'])) ?>
                                </div>

                                <div class="appointment-data">
                                    <strong><?= h($appointment['customer_name']) ?></strong>
                                    <small>
                                        <?= h($appointment['service_name']) ?>
                                        · <?= h(originLabel($appointment['origin_channel'])) ?>
                                        · <?= h($appointment['customer_phone']) ?>
                                    </small>
                                </div>

                                <div>
                                    <span class="badge <?= h(statusClass($appointment['status'])) ?>">
                                        <?= h(statusLabel($appointment['status'])) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <div class="card">
                <h2>Accesos rápidos</h2>

                <div class="quick-actions">
                    <a href="<?= APP_URL ?>/calendar">Ver calendario</a>
                    <a href="<?= APP_URL ?>/appointments/create">Crear turno manual</a>
                    <a href="<?= APP_URL ?>/services" class="secondary">Administrar servicios</a>
                    <a href="<?= APP_URL ?>/schedule" class="secondary">Horarios y bloqueos</a>
                    <a href="<?= APP_URL ?>/professionals" class="btn">Integrantes / profesionales</a>
                    <a href="<?= APP_URL ?>/appointments/confirmed-messages" class="btn">Mensajes de confirmación</a>
                </div>
            </div>

            <div class="card">
                <h2>Link público</h2>

                <p>Link recomendado para WhatsApp Business:</p>

                <div class="empty">
                    https://melifigarola.com/turnos?origen=whatsapp
                </div>

                <p style="color:#6b7280;font-size:14px;margin-top:14px;">
                    Más adelante este link se conectará con la página pública de reservas.
                </p>
            </div>
        </div>

    </div>

</div>

</body>
<?php require __DIR__ . '/partials/mobile_nav.php'; ?>
</html>