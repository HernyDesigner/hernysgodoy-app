<?php require_once __DIR__ . '/../../config/app.php'; ?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Profesionales | <?= APP_NAME ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/mobile-app.css?v=6">

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f4f5;
            color: #111827;
        }

        .topbar {
            background: #111111;
            color: white;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .topbar a {
            color: white;
            text-decoration: none;
            margin-left: 16px;
            font-size: 14px;
        }

        .container {
            max-width: 1180px;
            margin: 0 auto;
            padding: 28px 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            margin-bottom: 22px;
        }

        .header h1 {
            margin: 0 0 6px;
            font-size: 30px;
        }

        .header p {
            margin: 0;
            color: #6b7280;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #111111;
            color: white;
            border: none;
            border-radius: 12px;
            padding: 11px 16px;
            text-decoration: none;
            font-weight: 700;
            cursor: pointer;
        }

        .btn.secondary {
            background: white;
            color: #111111;
            border: 1px solid #d1d5db;
        }

        .professionals-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .professional-card {
            background: white;
            border-radius: 22px;
            padding: 20px;
            box-shadow: 0 10px 26px rgba(15, 23, 42, 0.06);
            border: 1px solid #e5e7eb;
        }

        .professional-card.inactive {
            opacity: .62;
        }

        .professional-card h2 {
            margin: 0 0 6px;
            font-size: 20px;
        }

        .muted {
            color: #6b7280;
            font-size: 14px;
        }

        .badges {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            margin: 14px 0;
        }

        .badge {
            display: inline-flex;
            border-radius: 999px;
            padding: 6px 9px;
            background: #f3f4f6;
            color: #374151;
            font-size: 12px;
            font-weight: 700;
        }

        .badge.active {
            background: #dcfce7;
            color: #166534;
        }

        .badge.owner {
            background: #fef3c7;
            color: #92400e;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin: 16px 0;
        }

        .stat {
            background: #f9fafb;
            border-radius: 14px;
            padding: 12px;
        }

        .stat strong {
            display: block;
            font-size: 22px;
        }

        .stat span {
            color: #6b7280;
            font-size: 12px;
        }

        .card-actions {
            display: flex;
            gap: 10px;
            margin-top: 16px;
        }

        @media (max-width: 900px) {
            .professionals-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 560px) {
            .header {
                flex-direction: column;
                align-items: stretch;
            }

            .professionals-grid {
                grid-template-columns: 1fr;
            }

            .card-actions {
                flex-direction: column;
            }

            .card-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="topbar">
    <strong><?= APP_NAME ?></strong>
    <div>
        <a href="<?= APP_URL ?>/dashboard">Dashboard</a>
        <a href="<?= APP_URL ?>/calendar">Calendario</a>
        <a href="<?= APP_URL ?>/services">Servicios</a>
        <a href="<?= APP_URL ?>/logout">Salir</a>
    </div>
</div>

<main class="container">
    <section class="header">
        <div>
            <h1>Profesionales</h1>
            <p>Administrá integrantes, servicios asignados y disponibilidad futura.</p>
        </div>

        <a href="<?= APP_URL ?>/professionals/create" class="btn">+ Nuevo profesional</a>
    </section>

    <?php if (empty($professionals)): ?>
        <div class="professional-card">
            <h2>No hay profesionales cargados</h2>
            <p class="muted">Creá el primer integrante del equipo para asignarle servicios y turnos.</p>
        </div>
    <?php else: ?>
        <section class="professionals-grid">
            <?php foreach ($professionals as $professional): ?>
                <article class="professional-card <?= (int) $professional['is_active'] === 1 ? '' : 'inactive' ?>">
                    <h2><?= h($professional['name']) ?></h2>

                    <div class="muted">
                        <?= h($professional['role_label'] ?: 'Profesional') ?>
                    </div>

                    <?php if (!empty($professional['phone'])): ?>
                        <div class="muted" style="margin-top: 6px;">
                            WhatsApp: <?= h($professional['phone']) ?>
                        </div>
                    <?php endif; ?>

                    <div class="badges">
                        <?php if ((int) $professional['is_active'] === 1): ?>
                            <span class="badge active">Activo</span>
                        <?php else: ?>
                            <span class="badge">Inactivo</span>
                        <?php endif; ?>

                        <?php if ((int) $professional['is_owner'] === 1): ?>
                            <span class="badge owner">Dueña/Admin</span>
                        <?php endif; ?>

                        <?php if ((int) $professional['can_manage_own_schedule'] === 1): ?>
                            <span class="badge">Maneja agenda</span>
                        <?php endif; ?>
                    </div>

                    <div class="stats">
                        <div class="stat">
                            <strong><?= h($professional['services_count']) ?></strong>
                            <span>Servicios</span>
                        </div>

                        <div class="stat">
                            <strong><?= h($professional['upcoming_appointments']) ?></strong>
                            <span>Próximos turnos</span>
                        </div>
                    </div>

                    <div class="card-actions">
                        <a href="<?= APP_URL ?>/professionals/edit?id=<?= h($professional['id']) ?>" class="btn secondary">
                            Editar
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/partials/mobile_nav.php'; ?>

</body>
</html>