<?php

function priceTypeLabel($type): string
{
    return [
        'fixed' => 'Fijo',
        'from' => 'Desde',
        'consult' => 'Consultar'
    ][$type] ?? $type;
}

function durationLabel(array $service): string
{
    if ($service['duration_type'] === 'variable') {
        return $service['duration_min'] . ' / ' . $service['duration_default'] . ' / ' . $service['duration_max'] . ' min';
    }

    return $service['duration_default'] . ' min';
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Servicios - Turnero HG</title>
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
            align-items: flex-start;
            gap: 16px;
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

        .btn {
            display: inline-block;
            background: #111111;
            color: white;
            padding: 12px 16px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
        }

        .btn.secondary {
            background: #e5e7eb;
            color: #111827;
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

        .service-name {
            font-weight: bold;
        }

        .muted {
            color: #6b7280;
            font-size: 12px;
        }

        .badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: bold;
            margin: 2px;
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

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        @media (max-width: 760px) {
            .container { padding: 20px; }

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
        <a href="<?= APP_URL ?>/logout">Cerrar sesión</a>
    </div>
</div>

<div class="container">

    <div class="header">
        <div>
            <h1>Servicios</h1>
            <p><?= h($business['name']) ?> · Precios, duración, seña y disponibilidad.</p>
        </div>

        <div>
            <a href="<?= APP_URL ?>/services/create" class="btn">Crear servicio</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert error"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert success"><?= h($success) ?></div>
    <?php endif; ?>

    <div class="card">
        <table class="app-table">
            <thead>
                <tr>
                    <th>Servicio</th>
                    <th>Categoría</th>
                    <th>Precio</th>
                    <th>Duración</th>
                    <th>Reglas</th>
                    <th>Estado</th>
                    <th>Orden</th>
                    <th>Acción</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($services as $service): ?>
                    <tr>
                        <td data-label="Servicio">
                            <div class="service-name"><?= h($service['name']) ?></div>
                            <?php if ($service['description']): ?>
                                <div class="muted"><?= h($service['description']) ?></div>
                            <?php endif; ?>
                        </td>

                        <td data-label="Categoría">
                            <?= h($service['category_name'] ?? 'Sin categoría') ?>
                        </td>

                        <td data-label="Precio">
                            <strong><?= h($service['price_label'] ?? '-') ?></strong><br>
                            <span class="muted"><?= h(priceTypeLabel($service['price_type'])) ?></span>
                        </td>

                        <td data-label="Duración">
                            <?= h(durationLabel($service)) ?>
                        </td>

                        <td data-label="Reglas">
                            <?php if ((int) $service['requires_deposit'] === 1): ?>
                                <span class="badge warning">Requiere seña</span>
                            <?php endif; ?>

                            <?php if ((int) $service['requires_approval'] === 1): ?>
                                <span class="badge warning">Requiere aprobación</span>
                            <?php endif; ?>

                            <?php if ((int) $service['is_bookable'] === 0): ?>
                                <span class="badge danger">Sólo consulta</span>
                            <?php endif; ?>
                        </td>

                        <td data-label="Estado">
                            <?php if ((int) $service['is_active'] === 1): ?>
                                <span class="badge success">Activo</span>
                            <?php else: ?>
                                <span class="badge muted">Inactivo</span>
                            <?php endif; ?>

                            <?php if ((int) $service['is_bookable'] === 1): ?>
                                <span class="badge success">Reservable</span>
                            <?php else: ?>
                                <span class="badge muted">No reservable</span>
                            <?php endif; ?>
                        </td>

                        <td data-label="Orden"><?= h($service['sort_order']) ?></td>

                        <td data-label="Acción">
                            <div class="actions">
                                <a href="<?= APP_URL ?>/services/edit?id=<?= h($service['id']) ?>" class="btn secondary">Editar</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($services)): ?>
                    <tr>
                        <td colspan="8">Todavía no hay servicios cargados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
<?php require __DIR__ . '/partials/mobile_nav.php'; ?>
</html>