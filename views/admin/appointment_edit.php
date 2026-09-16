<?php

$user = Auth::user();

function selectedValue($current, string $value): string
{
    return (string) $current === $value ? 'selected' : '';
}

function serviceLabel(array $service): string
{
    $category = $service['category_name'] ? $service['category_name'] . ' · ' : '';
    $duration = $service['duration_default'] ? ' · ' . $service['duration_default'] . ' min' : '';
    $price = $service['price_label'] ? ' · ' . $service['price_label'] : '';

    return $category . $service['name'] . $duration . $price;
}

$appointmentDate = date('Y-m-d', strtotime($appointment['start_at']));
$appointmentTime = date('H:i', strtotime($appointment['start_at']));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar turno - Turnero HG</title>
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
            max-width: 920px;
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

        .card {
            background: white;
            border-radius: 18px;
            padding: 28px;
            box-shadow: 0 10px 24px rgba(0,0,0,0.05);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .field {
            margin-bottom: 18px;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 7px;
        }

        input,
        select,
        textarea {
            width: 100%;
            min-height: 44px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 15px;
            font-family: Arial, sans-serif;
            background: white;
        }

        textarea {
            min-height: 90px;
            resize: vertical;
        }

        .help {
            display: block;
            margin-top: 6px;
            font-size: 12px;
            color: #6b7280;
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

        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        .btn {
            border: none;
            background: #111111;
            color: white;
            padding: 13px 18px;
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

        .summary {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 14px;
            margin-bottom: 20px;
            color: #374151;
            font-size: 14px;
        }

        .summary strong {
            display: block;
            margin-bottom: 4px;
        }

        @media (max-width: 760px) {
            .container {
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
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
        <h1>Editar turno</h1>
        <p><?= h($business['name']) ?> · Modificar datos, estado o cancelar turno.</p>
    </div>

    <div class="card">

        <?php if ($error): ?>
            <div class="alert error"><?= h($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert success"><?= h($success) ?></div>
        <?php endif; ?>

        <div class="summary">
            <strong>Turno #<?= h($appointment['id']) ?></strong>
            Cliente actual: <?= h($appointment['customer_name']) ?> ·
            Servicio actual: <?= h($appointment['service_name']) ?> ·
            Inicio: <?= h(date('d/m/Y H:i', strtotime($appointment['start_at']))) ?>
        </div>

        <form method="POST" action="<?= APP_URL ?>/appointments/update" id="editAppointmentForm">

            <input type="hidden" name="appointment_id" value="<?= h($appointment['id']) ?>">

            <div class="form-grid">

                <div class="field">
                    <label for="customer_name">Nombre del cliente *</label>
                    <input
                        type="text"
                        id="customer_name"
                        name="customer_name"
                        value="<?= h($appointment['customer_name']) ?>"
                        required
                    >
                </div>

                <div class="field">
                    <label for="customer_phone">WhatsApp *</label>
                    <input
                        type="text"
                        id="customer_phone"
                        name="customer_phone"
                        value="<?= h($appointment['customer_phone']) ?>"
                        required
                    >
                    <span class="help">Usar código país. Ejemplo Argentina: 549...</span>
                </div>

                <div class="field full">
                    <label for="service_id">Servicio *</label>
                    <select id="service_id" name="service_id" required>
                        <?php foreach ($services as $service): ?>
                            <option
                                value="<?= h($service['id']) ?>"
                                data-duration="<?= h($service['duration_default']) ?>"
                                data-requires-deposit="<?= h($service['requires_deposit']) ?>"
                                data-requires-approval="<?= h($service['requires_approval']) ?>"
                                <?= selectedValue($appointment['service_id'], (string) $service['id']) ?>
                            >
                                <?= h(serviceLabel($service)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="help">Si cambiás el servicio, el sistema recalcula la duración del turno.</span>
                </div>

                <div class="field full">
                    <label for="professional_id">Profesional *</label>
                    <select id="professional_id" name="professional_id" required>
                        <?php foreach ($professionals as $professional): ?>
                            <option
                                value="<?= h($professional['id']) ?>"
                                <?= (int) $appointment['professional_id'] === (int) $professional['id'] ? 'selected' : '' ?>
                            >
                                <?= h($professional['name']) ?>
                                <?= $professional['role_label'] ? ' · ' . h($professional['role_label']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="help">Sólo se muestran profesionales asignados al servicio actual.</span>
                </div>

                <div class="field">
                    <label for="date">Fecha *</label>
                    <input
                        type="date"
                        id="date"
                        name="date"
                        value="<?= h($appointmentDate) ?>"
                        required
                    >
                </div>

                <div class="field">
                    <label for="time">Hora *</label>
                    <select id="time" name="time" required>
                        <option value="<?= h($appointmentTime) ?>"><?= h($appointmentTime) ?> — horario actual</option>
                    </select>
                    <span class="help">El sistema muestra sólo horarios disponibles. El horario actual se mantiene disponible para este turno.</span>
                </div>

                <div class="field">
                    <label for="status">Estado *</label>
                    <select id="status" name="status" required>
                        <option value="confirmed" <?= selectedValue($appointment['status'], 'confirmed') ?>>Confirmado</option>
                        <option value="pending_approval" <?= selectedValue($appointment['status'], 'pending_approval') ?>>Pendiente de aprobación</option>
                        <option value="pending_deposit" <?= selectedValue($appointment['status'], 'pending_deposit') ?>>Pendiente de seña</option>
                        <option value="consultation" <?= selectedValue($appointment['status'], 'consultation') ?>>Consulta</option>
                        <option value="rescheduled" <?= selectedValue($appointment['status'], 'rescheduled') ?>>Reprogramado</option>
                        <option value="completed" <?= selectedValue($appointment['status'], 'completed') ?>>Completado</option>
                        <option value="no_show" <?= selectedValue($appointment['status'], 'no_show') ?>>No asistió</option>
                        <option value="cancelled" <?= selectedValue($appointment['status'], 'cancelled') ?>>Cancelado</option>
                    </select>
                </div>

                <div class="field">
                    <label for="origin_channel">Origen *</label>
                    <select id="origin_channel" name="origin_channel" required>
                        <option value="manual" <?= selectedValue($appointment['origin_channel'], 'manual') ?>>Manual</option>
                        <option value="whatsapp" <?= selectedValue($appointment['origin_channel'], 'whatsapp') ?>>WhatsApp</option>
                        <option value="instagram" <?= selectedValue($appointment['origin_channel'], 'instagram') ?>>Instagram</option>
                        <option value="facebook" <?= selectedValue($appointment['origin_channel'], 'facebook') ?>>Facebook</option>
                        <option value="web" <?= selectedValue($appointment['origin_channel'], 'web') ?>>Web</option>
                    </select>
                </div>

                <div class="field">
                    <label for="deposit_status">Estado de seña</label>
                    <select id="deposit_status" name="deposit_status">
                        <option value="not_required" <?= selectedValue($appointment['deposit_status'], 'not_required') ?>>No requiere</option>
                        <option value="pending" <?= selectedValue($appointment['deposit_status'], 'pending') ?>>Pendiente</option>
                        <option value="paid" <?= selectedValue($appointment['deposit_status'], 'paid') ?>>Pagada</option>
                    </select>
                </div>

                <div class="field">
                    <label>Duración actual</label>
                    <input
                        type="text"
                        id="durationPreview"
                        value="<?= h($appointment['service_duration']) ?> minutos"
                        disabled
                    >
                    <span class="help">Se recalcula automáticamente según el servicio seleccionado.</span>
                </div>

                <div class="field full">
                    <label for="customer_notes">Notas del cliente</label>
                    <textarea
                        id="customer_notes"
                        name="customer_notes"
                    ><?= h($appointment['customer_notes']) ?></textarea>
                </div>

                <div class="field full">
                    <label for="internal_notes">Notas internas</label>
                    <textarea
                        id="internal_notes"
                        name="internal_notes"
                    ><?= h($appointment['internal_notes']) ?></textarea>
                </div>

            </div>

            <div class="actions">
                <button type="submit" class="btn">Guardar cambios</button>
                <button type="button" class="btn danger" onclick="cancelAppointment()">Cancelar turno</button>
                <a href="<?= APP_URL ?>/calendar" class="btn secondary">Volver al calendario</a>
            </div>

        </form>

    </div>

</div>

<script>
    const serviceSelect = document.getElementById('service_id');
    const professionalSelect = document.getElementById('professional_id');
    const dateInput = document.getElementById('date');
    const timeSelect = document.getElementById('time');
    const durationPreview = document.getElementById('durationPreview');
    const statusSelect = document.getElementById('status');

    const appointmentId = '<?= h($appointment['id']) ?>';
    const currentTime = '<?= h($appointmentTime) ?>';
    const currentProfessionalId = '<?= h($appointment['professional_id']) ?>';

    serviceSelect.addEventListener('change', function () {
    const selected = this.options[this.selectedIndex];

        if (selected) {
            const duration = selected.getAttribute('data-duration') || '60';
            durationPreview.value = duration + ' minutos';
        }

        loadProfessionals();
    });

    professionalSelect.addEventListener('change', loadAvailableSlots);
    dateInput.addEventListener('change', loadAvailableSlots);

    loadProfessionals(currentProfessionalId);

    function loadAvailableSlots() {
        const serviceId = serviceSelect.value;
        const date = dateInput.value;
        const professionalId = professionalSelect.value;
        

        timeSelect.innerHTML = '<option value="">Cargando horarios...</option>';

        if (!serviceId || !date || !professionalId) {
            timeSelect.innerHTML = '<option value="">Seleccioná servicio, profesional y fecha</option>';
            return;
        }

        fetch(
            '<?= APP_URL ?>/api/admin/availability.php?service_id='
            + encodeURIComponent(serviceId)
            + '&date='
            + encodeURIComponent(date)
            + '&professional_id='
            + encodeURIComponent(professionalId)
            + '&exclude_id='
            + encodeURIComponent(appointmentId)
        )
            .then(response => response.json())
            .then(data => {
                timeSelect.innerHTML = '';

                const currentOption = document.createElement('option');
                currentOption.value = currentTime;
                currentOption.textContent = currentTime + ' — horario actual';
                currentOption.selected = true;
                timeSelect.appendChild(currentOption);

                if (!data.success || !data.slots || data.slots.length === 0) {
                    return;
                }

                data.slots.forEach(slot => {
                    if (slot.time === currentTime) {
                        return;
                    }

                    const option = document.createElement('option');
                    option.value = slot.time;
                    option.textContent = slot.label;
                    timeSelect.appendChild(option);
                });
            })
            .catch(() => {
                timeSelect.innerHTML = '<option value="' + currentTime + '">' + currentTime + ' — horario actual</option>';
            });
    }

    loadAvailableSlots();

    function cancelAppointment() {
        const confirmed = confirm('¿Seguro querés cancelar este turno?');

        if (!confirmed) {
            return;
        }

        statusSelect.value = 'cancelled';

        document.getElementById('editAppointmentForm').submit();
    }

    function loadProfessionals(selectedProfessionalId = null) {
        const serviceId = serviceSelect.value;

        professionalSelect.innerHTML = '<option value="">Cargando profesionales...</option>';

        if (!serviceId) {
            professionalSelect.innerHTML = '<option value="">Primero seleccioná un servicio</option>';
            loadAvailableSlots();
            return;
        }

        fetch('<?= APP_URL ?>/api/admin/professionals.php?service_id=' + encodeURIComponent(serviceId))
            .then(response => response.json())
            .then(data => {
                professionalSelect.innerHTML = '';

                if (!data.success || !data.professionals || data.professionals.length === 0) {
                    professionalSelect.innerHTML = '<option value="">No hay profesionales asignados</option>';
                    loadAvailableSlots();
                    return;
                }

                data.professionals.forEach(professional => {
                    const option = document.createElement('option');
                    option.value = professional.id;
                    option.textContent = professional.name + (professional.role_label ? ' · ' + professional.role_label : '');

                    if (selectedProfessionalId && String(professional.id) === String(selectedProfessionalId)) {
                        option.selected = true;
                    }

                    professionalSelect.appendChild(option);
                });

                loadAvailableSlots();
            })
            .catch(() => {
                professionalSelect.innerHTML = '<option value="">Error al cargar profesionales</option>';
                loadAvailableSlots();
            });
        }
</script>

</body>
<?php require __DIR__ . '/partials/mobile_nav.php'; ?>
</html>