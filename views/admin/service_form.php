<?php

$formValues = [];

if ($isEdit && isset($service) && is_array($service)) {
    $formValues = $service;
}

if (!$isEdit && isset($old) && is_array($old)) {
    $formValues = $old;
}

$field = function (string $key, string $default = '') use ($formValues): string {
    if (isset($formValues[$key]) && $formValues[$key] !== null) {
        return (string) $formValues[$key];
    }

    return $default;
};

$selected = function (string $key, string $value) use ($field): string {
    return $field($key) === $value ? 'selected' : '';
};

$checked = function (string $key, bool $default = false) use ($isEdit, $formValues): string {
    if ($isEdit) {
        return isset($formValues[$key]) && (int) $formValues[$key] === 1 ? 'checked' : '';
    }

    if (!$isEdit && isset($formValues[$key])) {
        return 'checked';
    }

    return $default ? 'checked' : '';
};

$categorySelected = function ($categoryId) use ($field): string {
    return $field('category_id') === (string) $categoryId ? 'selected' : '';
};

$priceType = $field('price_type', 'fixed');
$durationType = $field('duration_type', 'fixed');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= h($pageTitle) ?> - Turnero HG</title>
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
            max-width: 960px;
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

        .section-title {
            margin: 28px 0 16px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 20px;
        }

        .section-title:first-child {
            margin-top: 0;
            padding-top: 0;
            border-top: none;
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

        .checkbox-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 14px;
            border-radius: 12px;
            margin-bottom: 12px;
        }

        .checkbox-row input {
            width: auto;
            min-height: auto;
            margin-top: 2px;
        }

        .checkbox-row strong {
            display: block;
            margin-bottom: 3px;
        }

        .checkbox-row span {
            color: #6b7280;
            font-size: 13px;
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
            margin-top: 24px;
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

        .conditional-box {
            display: none;
        }

        .conditional-box.active {
            display: block;
        }

        @media (max-width: 760px) {
            .container { padding: 20px; }

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
        <a href="<?= APP_URL ?>/services">Servicios</a>
        <a href="<?= APP_URL ?>/logout">Cerrar sesión</a>
    </div>
</div>

<div class="container">

    <div class="header">
        <h1><?= h($pageTitle) ?></h1>
        <p><?= h($business['name']) ?> · Configuración comercial y operativa del servicio.</p>
    </div>

    <div class="card">

        <?php if (!empty($error)): ?>
            <div class="alert error"><?= h($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert success"><?= h($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= h($formAction) ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

            <?php if ($isEdit): ?>
                <input type="hidden" name="service_id" value="<?= h($field('id')) ?>">
            <?php endif; ?>

            <h2 class="section-title">Datos principales</h2>

            <div class="form-grid">
                <div class="field">
                    <label for="name">Nombre del servicio *</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="<?= h($field('name')) ?>"
                        required
                    >
                </div>

                <div class="field">
                    <label for="category_id">Categoría</label>
                    <select id="category_id" name="category_id">
                        <option value="">Sin categoría</option>

                        <?php foreach ($categories as $category): ?>
                            <option
                                value="<?= h($category['id']) ?>"
                                <?= $categorySelected($category['id']) ?>
                            >
                                <?= h($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field full">
                    <label for="description">Descripción</label>
                    <textarea id="description" name="description"><?= h($field('description')) ?></textarea>
                </div>
            </div>

            <h2 class="section-title">Precio</h2>

            <div class="form-grid">
                <div class="field">
                    <label for="price_type">Tipo de precio *</label>
                    <select id="price_type" name="price_type">
                        <option value="fixed" <?= $selected('price_type', 'fixed') ?>>Precio fijo</option>
                        <option value="from" <?= $selected('price_type', 'from') ?>>Desde</option>
                        <option value="consult" <?= $selected('price_type', 'consult') ?>>Consultar</option>
                    </select>
                </div>

                <div class="field">
                    <label for="price">Precio</label>
                    <input
                        type="number"
                        id="price"
                        name="price"
                        value="<?= h($field('price')) ?>"
                        step="0.01"
                        min="0"
                    >
                    <span class="help">Ejemplo: 15000. Si el precio es “Consultar”, puede quedar vacío.</span>
                </div>

                <div class="field full">
                    <label for="price_label">Texto visible del precio</label>
                    <input
                        type="text"
                        id="price_label"
                        name="price_label"
                        value="<?= h($field('price_label')) ?>"
                        placeholder="Ej: $15.000 / Desde $25.000 / Consultar"
                    >
                </div>
            </div>

            <h2 class="section-title">Duración</h2>

            <div class="form-grid">
                <div class="field">
                    <label for="duration_type">Tipo de duración *</label>
                    <select id="duration_type" name="duration_type">
                        <option value="fixed" <?= $selected('duration_type', 'fixed') ?>>Duración fija</option>
                        <option value="variable" <?= $selected('duration_type', 'variable') ?>>Duración variable</option>
                    </select>
                </div>

                <div class="field">
                    <label for="duration_default">Duración estimada *</label>
                    <input
                        type="number"
                        id="duration_default"
                        name="duration_default"
                        value="<?= h($field('duration_default', '60')) ?>"
                        min="1"
                        required
                    >
                    <span class="help">Esta duración se usa para calcular el final del turno.</span>
                </div>

                <div class="field duration-variable-field">
                    <label for="duration_min">Duración mínima</label>
                    <input
                        type="number"
                        id="duration_min"
                        name="duration_min"
                        value="<?= h($field('duration_min')) ?>"
                        min="1"
                    >
                </div>

                <div class="field duration-variable-field">
                    <label for="duration_max">Duración máxima</label>
                    <input
                        type="number"
                        id="duration_max"
                        name="duration_max"
                        value="<?= h($field('duration_max')) ?>"
                        min="1"
                    >
                </div>
            </div>

            <h2 class="section-title">Capacidad de agenda</h2>

            <div class="form-grid">
                <div class="field">
                    <label for="capacity_per_slot">Capacidad por horario *</label>
                    <input
                        type="number"
                        id="capacity_per_slot"
                        name="capacity_per_slot"
                        value="<?= h($field('capacity_per_slot', '1')) ?>"
                        min="1"
                        max="10"
                        required
                    >
                    <span class="help">Cantidad máxima de turnos simultáneos para este servicio. Ej: Color puede ser 3.</span>
                </div>
            </div>

            <div class="checkbox-row">
                <input
                    type="checkbox"
                    id="blocks_full_schedule"
                    name="blocks_full_schedule"
                    <?= $checked('blocks_full_schedule', true) ?>
                >
                <label for="blocks_full_schedule">
                    <strong>Bloquea toda la agenda</strong>
                    <span>Activar para servicios personalizados como corte o balayage. Desactivar para servicios que permiten turnos simultáneos.</span>
                </label>
            </div>

            <h2 class="section-title">Reglas del servicio</h2>

            <div class="checkbox-row">
                <input
                    type="checkbox"
                    id="requires_deposit"
                    name="requires_deposit"
                    <?= $checked('requires_deposit') ?>
                >
                <label for="requires_deposit">
                    <strong>Requiere seña</strong>
                    <span>El turno queda pendiente hasta que Meli confirme la seña.</span>
                </label>
            </div>

            <div id="depositBox" class="conditional-box">
                <div class="form-grid">
                    <div class="field">
                        <label for="deposit_type">Tipo de seña</label>
                        <select id="deposit_type" name="deposit_type">
                            <option value="fixed" <?= $selected('deposit_type', 'fixed') ?>>Monto fijo</option>
                            <option value="percentage" <?= $selected('deposit_type', 'percentage') ?>>Porcentaje</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="deposit_amount">Monto / porcentaje de seña</label>
                        <input
                            type="number"
                            id="deposit_amount"
                            name="deposit_amount"
                            value="<?= h($field('deposit_amount')) ?>"
                            step="0.01"
                            min="0"
                        >
                    </div>
                </div>
            </div>

            <div class="checkbox-row">
                <input
                    type="checkbox"
                    id="requires_approval"
                    name="requires_approval"
                    <?= $checked('requires_approval') ?>
                >
                <label for="requires_approval">
                    <strong>Requiere aprobación</strong>
                    <span>El cliente puede solicitar el turno, pero Meli debe aprobarlo.</span>
                </label>
            </div>

            <div class="checkbox-row">
                <input
                    type="checkbox"
                    id="is_bookable"
                    name="is_bookable"
                    <?= $checked('is_bookable', true) ?>
                >
                <label for="is_bookable">
                    <strong>Permite reserva online</strong>
                    <span>Si está desactivado, el servicio aparece como consulta o no se ofrece para reserva directa.</span>
                </label>
            </div>

            <div class="checkbox-row">
                <input
                    type="checkbox"
                    id="is_active"
                    name="is_active"
                    <?= $checked('is_active', true) ?>
                >
                <label for="is_active">
                    <strong>Servicio activo</strong>
                    <span>Si está desactivado, no aparece en formularios ni página pública.</span>
                </label>
            </div>

            <h2 class="section-title">Orden</h2>

            <div class="form-grid">
                <div class="field">
                    <label for="sort_order">Orden de aparición</label>
                    <input
                        type="number"
                        id="sort_order"
                        name="sort_order"
                        value="<?= h($field('sort_order', '0')) ?>"
                    >
                </div>
            </div>

            <div class="actions">
                <button type="submit" class="btn">
                    <?= $isEdit ? 'Guardar cambios' : 'Crear servicio' ?>
                </button>

                <a href="<?= APP_URL ?>/services" class="btn secondary">Volver a servicios</a>
            </div>

        </form>

    </div>

</div>

<script>
const priceType = document.getElementById('price_type');
const priceInput = document.getElementById('price');
const durationType = document.getElementById('duration_type');
const durationVariableFields = document.querySelectorAll('.duration-variable-field');
const requiresDeposit = document.getElementById('requires_deposit');
const depositBox = document.getElementById('depositBox');

function togglePrice() {
    if (priceType.value === 'consult') {
        priceInput.disabled = true;
        priceInput.value = '';
    } else {
        priceInput.disabled = false;
    }
}

function toggleDurationFields() {
    const isVariable = durationType.value === 'variable';

    durationVariableFields.forEach(function(field) {
        field.style.display = isVariable ? 'block' : 'none';
    });
}

function toggleDepositBox() {
    if (requiresDeposit.checked) {
        depositBox.classList.add('active');
    } else {
        depositBox.classList.remove('active');
    }
}

priceType.addEventListener('change', togglePrice);
durationType.addEventListener('change', toggleDurationFields);
requiresDeposit.addEventListener('change', toggleDepositBox);

togglePrice();
toggleDurationFields();
toggleDepositBox();
</script>

</body>
<?php require __DIR__ . '/partials/mobile_nav.php'; ?>
</html>