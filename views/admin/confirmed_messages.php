<?php require_once __DIR__ . '/../../config/app.php'; ?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mensajes de confirmación | <?= APP_NAME ?></title>
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
            max-width: 1180px;
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
        }

        .message-list {
            display: grid;
            gap: 14px;
        }

        .appointment-row {
            display: grid;
            grid-template-columns: 32px 1fr auto;
            gap: 14px;
            align-items: center;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 14px;
            background: #ffffff;
        }

        .appointment-row strong {
            display: block;
            font-size: 16px;
            margin-bottom: 3px;
        }

        .meta {
            color: #6b7280;
            font-size: 13px;
            line-height: 1.45;
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
            white-space: nowrap;
        }

        .btn.secondary {
            background: white;
            color: #111111;
            border: 1px solid #d1d5db;
        }

        .actions-top {
            display: flex;
            gap: 12px;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .empty {
            color: #6b7280;
            line-height: 1.45;
        }

        @media (max-width: 680px) {
            .appointment-row {
                grid-template-columns: 28px 1fr;
            }

            .appointment-row .row-action {
                grid-column: 1 / -1;
            }

            .row-action .btn {
                width: 100%;
            }

            .actions-top {
                flex-direction: column;
                align-items: stretch;
            }

            .actions-top .btn {
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
        <a href="<?= APP_URL ?>/logout">Salir</a>
    </div>
</div>

<main class="container">
    <section class="header">
        <h1>Mensajes de confirmación</h1>
        <p>Turnos confirmados que todavía no tienen registrado el mensaje prearmado de WhatsApp.</p>
    </section>

    <section class="card">
        <?php if (empty($appointments)): ?>
            <p class="empty">No hay turnos confirmados pendientes de mensaje.</p>
            <a href="<?= APP_URL ?>/calendar" class="btn secondary">Volver al calendario</a>
        <?php else: ?>
            <form method="POST" action="<?= APP_URL ?>/appointments/confirmed-messages/bulk">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="actions-top">
                    <button type="button" class="btn secondary" id="selectAllBtn">Seleccionar todos</button>
                    <button type="submit" class="btn">Preparar mensajes seleccionados</button>
                </div>

                <div class="message-list">
                    <?php foreach ($appointments as $appointment): ?>
                        <?php
                        $dateLabel = date('d/m/Y', strtotime($appointment['start_at']));
                        $timeLabel = date('H:i', strtotime($appointment['start_at']));
                        ?>
                        <article class="appointment-row">
                            <input
                                type="checkbox"
                                name="appointment_ids[]"
                                value="<?= h($appointment['id']) ?>"
                            >

                            <div>
                                <strong><?= h($appointment['customer_name']) ?></strong>
                                <div class="meta">
                                    <?= h($appointment['service_name']) ?>
                                    · <?= h($appointment['professional_name'] ?: 'Sin profesional') ?>
                                    · <?= h($dateLabel) ?>
                                    · <?= h($timeLabel) ?> hs
                                </div>
                                <div class="meta">
                                    WhatsApp: <?= h($appointment['customer_phone']) ?>
                                </div>
                            </div>

                            <div class="row-action">
                                <a
                                    href="<?= APP_URL ?>/appointments/send-confirmed-whatsapp?id=<?= h($appointment['id']) ?>"
                                    class="btn"
                                    target="_blank"
                                >
                                    Abrir WhatsApp
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </form>
        <?php endif; ?>
    </section>
</main>

<?php require __DIR__ . '/partials/mobile_nav.php'; ?>

<script>
const selectAllBtn = document.getElementById('selectAllBtn');

if (selectAllBtn) {
    selectAllBtn.addEventListener('click', function () {
        document.querySelectorAll('input[name="appointment_ids[]"]').forEach(function (checkbox) {
            checkbox.checked = true;
        });
    });
}
</script>

</body>
</html>