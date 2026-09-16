<?php

$user = Auth::user();

function oldValue(array $old, string $key, string $default = ''): string
{
    return isset($old[$key]) ? (string) $old[$key] : $default;
}

function selectedValue(array $old, string $key, string $value): string
{
    return oldValue($old, $key) === $value ? 'selected' : '';
}

function serviceLabel(array $service): string
{
    $category = $service['category_name'] ? $service['category_name'] . ' · ' : '';
    $duration = $service['duration_default'] ? ' · ' . $service['duration_default'] . ' min' : '';
    $price = $service['price_label'] ? ' · ' . $service['price_label'] : '';

    return $category . $service['name'] . $duration . $price;
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear turno manual - Turnero HG</title>
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

        .preview {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 14px;
            margin-bottom: 20px;
            color: #374151;
            font-size: 14px;
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
        <h1>Crear turno manual</h1>
        <p><?= h($business['name']) ?> · Carga manual desde el backoffice.</p>
    </div>

    <div class="card">

        <?php if ($error): ?>
            <div class="alert error"><?= h($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert success"><?= h($success) ?></div>
        <?php endif; ?>

        <div class="preview">
            Este formulario sirve para cargar turnos que llegan por WhatsApp, Instagram, Facebook, presencialmente o por mensaje directo.
        </div>

        <form method="POST" action="<?= APP_URL ?>/appointments/store">

            <div class="form-grid">

                <div class="field">
                    <label for="customer_name">Nombre del cliente *</label>
                    <input
                        type="text"
                        id="customer_name"
                        name="customer_name"
                        value="<?= h(oldValue($old, 'customer_name')) ?>"
                        required
                    >
                </div>

                <div class="field">
                    <label for="customer_phone">WhatsApp *</label>
                    <input
                        type="text"
                        id="customer_phone"
                        name="customer_phone"
                        value="<?= h(oldValue($old, 'customer_phone')) ?>"
                        placeholder="Ej: 5491112345678"
                        required
                    >
                    <span class="help">Usar código país. Ejemplo Argentina: 549...</span>
                </div>

                <div class="field full">
                    <label for="service_id">Servicio *</label>
                    <select id="service_id" name="service_id" required>
                        <option value="">Seleccionar servicio</option>

                        <?php foreach ($services as $service): ?>
                            <option
                                value="<?= h($service['id']) ?>"
                                data-duration="<?= h($service['duration_default']) ?>"
                                data-requires-deposit="<?= h($service['requires_deposit']) ?>"
                                data-requires-approval="<?= h($service['requires_approval']) ?>"
                                <?= selectedValue($old, 'service_id', (string) $service['id']) ?>
                            >
                                <?= h(serviceLabel($service)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="help">La duración del turno se calcula con la duración estimada del servicio.</span>
                </div>

                <div class="field full">
                    <label for="professional_id">Profesional *</label>
                    <select id="professional_id" name="professional_id" required>
                        <option value="">Primero seleccioná un servicio</option>
                    </select>
                    <span class="help">El sistema mostrará sólo profesionales asignados al servicio elegido.</span>
                </div>

                <div class="field">
                    <label for="date">Fecha *</label>
                    <input
                        type="date"
                        id="date"
                        name="date"
                        value="<?= h(oldValue($old, 'date', date('Y-m-d'))) ?>"
                        required
                    >
                </div>

                <div class="field">
                    <label for="time">Hora *</label>
                    <select id="time" name="time" required>
                        <option value="">Primero seleccioná servicio y fecha</option>
                    </select>
                    <span class="help">El sistema muestra sólo horarios disponibles.</span>
                </div>

                <div class="field">
                    <label for="status">Estado *</label>
                    <select id="status" name="status" required>
                        <option value="confirmed" <?= selectedValue($old, 'status', 'confirmed') ?>>Confirmado</option>
                        <option value="pending_approval" <?= selectedValue($old, 'status', 'pending_approval') ?>>Pendiente de aprobación</option>
                        <option value="pending_deposit" <?= selectedValue($old, 'status', 'pending_deposit') ?>>Pendiente de seña</option>
                        <option value="consultation" <?= selectedValue($old, 'status', 'consultation') ?>>Consulta</option>
                        <option value="rescheduled" <?= selectedValue($old, 'status', 'rescheduled') ?>>Reprogramado</option>
                    </select>
                </div>

                <div class="field">
                    <label for="origin_channel">Origen *</label>
                    <select id="origin_channel" name="origin_channel" required>
                        <option value="manual" <?= selectedValue($old, 'origin_channel', 'manual') ?>>Manual</option>
                        <option value="whatsapp" <?= selectedValue($old, 'origin_channel', 'whatsapp') ?>>WhatsApp</option>
                        <option value="instagram" <?= selectedValue($old, 'origin_channel', 'instagram') ?>>Instagram</option>
                        <option value="facebook" <?= selectedValue($old, 'origin_channel', 'facebook') ?>>Facebook</option>
                        <option value="web" <?= selectedValue($old, 'origin_channel', 'web') ?>>Web</option>
                    </select>
                </div>

                <div class="field full">
                    <label for="customer_notes">Notas del cliente</label>
                    <textarea
                        id="customer_notes"
                        name="customer_notes"
                        placeholder="Ej: quiere consultar por pelo largo, color anterior, etc."
                    ><?= h(oldValue($old, 'customer_notes')) ?></textarea>
                </div>

                <div class="field full">
                    <label for="internal_notes">Notas internas</label>
                    <textarea
                        id="internal_notes"
                        name="internal_notes"
                        placeholder="Notas visibles sólo para Meli."
                    ><?= h(oldValue($old, 'internal_notes')) ?></textarea>
                </div>

            </div>

            <div class="actions">
                <button type="submit" class="btn">Guardar turno</button>
                <a href="<?= APP_URL ?>/calendar" class="btn secondary">Cancelar</a>
            </div>

        </form>

    </div>

</div>

<script>
    const serviceSelect = document.getElementById('service_id');
    const professionalSelect = document.getElementById('professional_id');
    const dateInput = document.getElementById('date');
    const timeSelect = document.getElementById('time');
    const statusSelect = document.getElementById('status');

    const oldTime = '<?= h(oldValue($old, 'time')) ?>';

    serviceSelect.addEventListener('change', function () {
    const selected = this.options[this.selectedIndex];

    if (!selected || !selected.value) {
        loadProfessionals();
        return;
    }

    const requiresDeposit = selected.getAttribute('data-requires-deposit') === '1';
    const requiresApproval = selected.getAttribute('data-requires-approval') === '1';

    if (requiresApproval) {
        statusSelect.value = 'pending_approval';
    } else if (requiresDeposit) {
        statusSelect.value = 'pending_deposit';
    } else {
        statusSelect.value = 'confirmed';
    }

    loadProfessionals();
    });

    professionalSelect.addEventListener('change', loadAvailableSlots);
    dateInput.addEventListener('change', loadAvailableSlots);

    loadProfessionals();

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
        )
        .then(response => response.json())
        .then(data => {
            timeSelect.innerHTML = '';

            if (!data.success || !data.slots || data.slots.length === 0) {
                timeSelect.innerHTML = '<option value="">No hay horarios disponibles</option>';
                return;
            }

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Seleccionar horario';
            timeSelect.appendChild(placeholder);

            data.slots.forEach(slot => {
                const option = document.createElement('option');
                option.value = slot.time;
                option.textContent = slot.label;

                if (oldTime && oldTime.substring(0, 5) === slot.time) {
                    option.selected = true;
                }

                timeSelect.appendChild(option);
            });
        })
        .catch(() => {
            timeSelect.innerHTML = '<option value="">Error al cargar horarios</option>';
        });
    }

    loadAvailableSlots();

    function loadProfessionals() {
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