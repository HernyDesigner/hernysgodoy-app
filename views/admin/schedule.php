<?php

function weekdayName(int $weekday): string
{
    $days = [
        0 => 'Domingo',
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
    ];

    return $days[$weekday] ?? '';
}

function timeValue($time): string
{
    if (!$time) {
        return '';
    }

    return substr((string) $time, 0, 5);
}

function exceptionTypeLabel($type): string
{
    $labels = [
        'blocked_day' => 'Día bloqueado',
        'blocked_range' => 'Horario bloqueado',
        'extra_hours' => 'Horario especial',
    ];

    return $labels[$type] ?? $type;
}

function exceptionBadgeClass($type): string
{
    $classes = [
        'blocked_day' => 'danger',
        'blocked_range' => 'warning',
        'extra_hours' => 'success',
    ];

    return $classes[$type] ?? 'muted';
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Horarios y bloqueos - Turnero HG</title>
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
            max-width: 1180px;
            margin: 0 auto;
        }

        .header {
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

        .grid {
            display: grid;
            grid-template-columns: 1.25fr .85fr;
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
            font-size: 22px;
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

        .hours-table {
            width: 100%;
            border-collapse: collapse;
        }

        .hours-table th,
        .hours-table td {
            border-bottom: 1px solid #e5e7eb;
            padding: 12px 8px;
            text-align: left;
            vertical-align: middle;
            font-size: 14px;
        }

        .hours-table th {
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        input,
        select,
        textarea {
            width: 100%;
            min-height: 40px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 8px 10px;
            font-size: 14px;
            font-family: Arial, sans-serif;
            background: white;
        }

        input[type="checkbox"] {
            width: auto;
            min-height: auto;
        }

        textarea {
            min-height: 80px;
            resize: vertical;
        }

        label {
            display: block;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 7px;
        }

        .field {
            margin-bottom: 16px;
        }

        .btn {
            border: none;
            background: #111111;
            color: white;
            padding: 12px 16px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
            cursor: pointer;
            display: inline-block;
        }

        .btn.secondary {
            background: #e5e7eb;
            color: #111827;
        }

        .btn.danger {
            background: #dc2626;
            color: white;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        .exception-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .exception-item {
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 14px;
            background: #f9fafb;
        }

        .exception-top {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: flex-start;
        }

        .exception-title {
            font-weight: bold;
            margin-bottom: 4px;
        }

        .muted {
            color: #6b7280;
            font-size: 13px;
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

        .empty {
            color: #6b7280;
            background: #f9fafb;
            border: 1px dashed #d1d5db;
            padding: 18px;
            border-radius: 14px;
        }

        .help {
            display: block;
            margin-top: 6px;
            font-size: 12px;
            color: #6b7280;
        }

        .range-fields {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        @media (max-width: 920px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .hours-table {
                min-width: 780px;
            }

            .table-wrapper {
                overflow-x: auto;
            }
        }

        @media (max-width: 620px) {
            .container {
                padding: 20px;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .range-fields {
                grid-template-columns: 1fr;
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
        <h1>Horarios y bloqueos</h1>
        <p><?= h($business['name']) ?> · Configuración semanal, pausas y excepciones.</p>
    </div>

    <?php if ($error): ?>
        <div class="alert error"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert success"><?= h($success) ?></div>
    <?php endif; ?>

    <div class="grid">

        <div>
            <div class="card">
                <h2>Horarios laborales</h2>

                <form method="POST" action="<?= APP_URL ?>/schedule/update-hours">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

                    <div class="table-wrapper">
                        <table class="hours-table">
                            <thead>
                                <tr>
                                    <th>Día</th>
                                    <th>Activo</th>
                                    <th>Desde</th>
                                    <th>Hasta</th>
                                    <th>Pausa desde</th>
                                    <th>Pausa hasta</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($businessHours as $weekday => $hour): ?>
                                    <tr>
                                        <td>
                                            <strong><?= h(weekdayName((int) $weekday)) ?></strong>
                                        </td>

                                        <td>
                                            <input
                                                type="checkbox"
                                                name="hours[<?= h($weekday) ?>][is_active]"
                                                value="1"
                                                <?= (int) $hour['is_active'] === 1 ? 'checked' : '' ?>
                                            >
                                        </td>

                                        <td>
                                            <input
                                                type="time"
                                                name="hours[<?= h($weekday) ?>][start_time]"
                                                value="<?= h(timeValue($hour['start_time'])) ?>"
                                            >
                                        </td>

                                        <td>
                                            <input
                                                type="time"
                                                name="hours[<?= h($weekday) ?>][end_time]"
                                                value="<?= h(timeValue($hour['end_time'])) ?>"
                                            >
                                        </td>

                                        <td>
                                            <input
                                                type="time"
                                                name="hours[<?= h($weekday) ?>][break_start]"
                                                value="<?= h(timeValue($hour['break_start'])) ?>"
                                            >
                                        </td>

                                        <td>
                                            <input
                                                type="time"
                                                name="hours[<?= h($weekday) ?>][break_end]"
                                                value="<?= h(timeValue($hour['break_end'])) ?>"
                                            >
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="actions">
                        <button type="submit" class="btn">Guardar horarios</button>
                        <a href="<?= APP_URL ?>/calendar" class="btn secondary">Ver calendario</a>
                    </div>

                </form>
            </div>
        </div>

        <div>
            <div class="card">
                <h2>Agregar bloqueo / excepción</h2>

                <form method="POST" action="<?= APP_URL ?>/schedule/exceptions/store">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

                    <div class="field">
                        <label for="date">Fecha *</label>
                        <input type="date" id="date" name="date" required>
                    </div>

                    <div class="field">
                        <label for="type">Tipo *</label>
                        <select id="type" name="type" required>
                            <option value="blocked_day">Bloquear día completo</option>
                            <option value="blocked_range">Bloquear rango horario</option>
                            <option value="extra_hours">Horario especial disponible</option>
                        </select>
                        <span class="help">El horario especial se usará luego para abrir turnos fuera del horario habitual.</span>
                    </div>

                    <div class="range-fields" id="rangeFields">
                        <div class="field">
                            <label for="start_time">Hora desde</label>
                            <input type="time" id="start_time" name="start_time">
                        </div>

                        <div class="field">
                            <label for="end_time">Hora hasta</label>
                            <input type="time" id="end_time" name="end_time">
                        </div>
                    </div>

                    <div class="field">
                        <label for="reason">Motivo / nota interna</label>
                        <textarea id="reason" name="reason" placeholder="Ej: trámite, descanso, evento, horario especial..."></textarea>
                    </div>

                    <div class="actions">
                        <button type="submit" class="btn">Agregar</button>
                    </div>

                </form>
            </div>

            <div class="card">
                <h2>Próximos bloqueos</h2>

                <?php if (empty($exceptions)): ?>
                    <div class="empty">
                        No hay bloqueos futuros cargados.
                    </div>
                <?php else: ?>
                    <div class="exception-list">
                        <?php foreach ($exceptions as $exception): ?>
                            <div class="exception-item">
                                <div class="exception-top">
                                    <div>
                                        <div class="exception-title">
                                            <?= h(date('d/m/Y', strtotime($exception['date']))) ?>
                                        </div>

                                        <div class="muted">
                                            <?php if ($exception['start_time'] && $exception['end_time']): ?>
                                                <?= h(timeValue($exception['start_time'])) ?> a <?= h(timeValue($exception['end_time'])) ?>
                                            <?php else: ?>
                                                Día completo
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <span class="badge <?= h(exceptionBadgeClass($exception['type'])) ?>">
                                        <?= h(exceptionTypeLabel($exception['type'])) ?>
                                    </span>
                                </div>

                                <?php if ($exception['reason']): ?>
                                    <p class="muted"><?= h($exception['reason']) ?></p>
                                <?php endif; ?>

                                <form
                                    method="POST"
                                    action="<?= APP_URL ?>/schedule/exceptions/delete"
                                    onsubmit="return confirm('¿Seguro querés eliminar este bloqueo?');"
                                    style="margin-top:12px;"
                                >
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="exception_id" value="<?= h($exception['id']) ?>">
                                    <button type="submit" class="btn danger">Eliminar</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>

<script>
const typeSelect = document.getElementById('type');
const rangeFields = document.getElementById('rangeFields');
const startTime = document.getElementById('start_time');
const endTime = document.getElementById('end_time');

function toggleRangeFields() {
    if (typeSelect.value === 'blocked_day') {
        rangeFields.style.display = 'none';
        startTime.value = '';
        endTime.value = '';
    } else {
        rangeFields.style.display = 'grid';
    }
}

typeSelect.addEventListener('change', toggleRangeFields);
toggleRangeFields();
</script>

</body>
<?php require __DIR__ . '/partials/mobile_nav.php'; ?>
</html>
