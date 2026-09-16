<?php require_once __DIR__ . '/../../config/app.php'; ?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mensajes seleccionados | <?= APP_NAME ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/mobile-app.css?v=7">

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
            max-width: 980px;
            margin: 0 auto;
            padding: 28px 20px;
        }

        .header {
            margin-bottom: 22px;
        }

        .header h1 {
            margin: 0 0 6px;
            font-size: 30px;
        }

        .header p {
            margin: 0;
            color: #6b7280;
            line-height: 1.45;
        }

        .card {
            background: white;
            border-radius: 22px;
            padding: 20px;
            box-shadow: 0 10px 26px rgba(15, 23, 42, 0.06);
            border: 1px solid #e5e7eb;
            margin-bottom: 14px;
        }

        .card h2 {
            margin: 0 0 8px;
            font-size: 20px;
        }

        .meta {
            color: #6b7280;
            font-size: 14px;
            line-height: 1.45;
            margin-bottom: 14px;
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

        .actions {
            display: flex;
            gap: 10px;
        }

        @media (max-width: 620px) {
            .actions {
                flex-direction: column;
            }

            .actions .btn {
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
        <a href="<?= APP_URL ?>/appointments/confirmed-messages">Mensajes</a>
        <a href="<?= APP_URL ?>/logout">Salir</a>
    </div>
</div>

<main class="container">
    <section class="header">
        <h1>Mensajes seleccionados</h1>
        <p>Abrí cada WhatsApp y enviá manualmente el mensaje prearmado. El sistema registrará cada apertura.</p>
    </section>

    <?php if (empty($appointments)): ?>
        <section class="card">
            <p class="meta">No hay turnos válidos seleccionados.</p>
            <a href="<?= APP_URL ?>/appointments/confirmed-messages" class="btn secondary">Volver</a>
        </section>
    <?php else: ?>
        <?php foreach ($appointments as $appointment): ?>
            <?php
            $dateLabel = date('d/m/Y', strtotime($appointment['start_at']));
            $timeLabel = date('H:i', strtotime($appointment['start_at']));
            ?>
            <section class="card">
                <h2><?= h($appointment['customer_name']) ?></h2>

                <div class="meta">
                    <?= h($appointment['service_name']) ?>
                    · <?= h($appointment['professional_name'] ?: 'Sin profesional') ?>
                    · <?= h($dateLabel) ?>
                    · <?= h($timeLabel) ?> hs
                    <br>
                    WhatsApp: <?= h($appointment['customer_phone']) ?>
                </div>

                <div class="actions">
                    <a
                        href="<?= APP_URL ?>/appointments/send-confirmed-whatsapp?id=<?= h($appointment['id']) ?>"
                        class="btn"
                        target="_blank"
                    >
                        Abrir WhatsApp
                    </a>
                </div>
            </section>
        <?php endforeach; ?>

        <a href="<?= APP_URL ?>/appointments/confirmed-messages" class="btn secondary">Volver a pendientes</a>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/partials/mobile_nav.php'; ?>

</body>
</html>