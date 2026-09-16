<?php require_once __DIR__ . '/../../config/app.php'; ?>

<?php
function professionalField(array $professional, string $key, string $default = ''): string
{
    return (string) ($professional[$key] ?? $default);
}

function professionalChecked(array $professional, string $key, bool $default = false): string
{
    $value = $professional[$key] ?? ($default ? 1 : 0);

    return (int) $value === 1 ? 'checked' : '';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= h($pageTitle) ?> | <?= APP_NAME ?></title>
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
        }

        .card {
            background: white;
            border-radius: 22px;
            padding: 24px;
            box-shadow: 0 10px 26px rgba(15, 23, 42, 0.06);
            border: 1px solid #e5e7eb;
            margin-bottom: 18px;
        }

        .section-title {
            font-size: 18px;
            margin: 0 0 18px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 7px;
            margin-bottom: 16px;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        label {
            font-weight: 700;
            font-size: 14px;
        }

        input,
        select,
        textarea {
            border: 1px solid #d1d5db;
            border-radius: 12px;
            padding: 11px 12px;
            font-size: 15px;
            width: 100%;
            box-sizing: border-box;
        }

        .help {
            color: #6b7280;
            font-size: 13px;
            line-height: 1.35;
        }

        .checkbox-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            margin-bottom: 12px;
            background: #f9fafb;
        }

        .checkbox-row input {
            width: auto;
            margin-top: 3px;
        }

        .checkbox-row label {
            display: flex;
            flex-direction: column;
            gap: 3px;
            font-weight: 700;
        }

        .checkbox-row span {
            font-weight: 400;
            color: #6b7280;
            font-size: 13px;
        }

        .services-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .service-check {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 13px;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            background: #f9fafb;
        }

        .service-check input {
            width: auto;
            margin-top: 3px;
        }

        .service-check strong {
            display: block;
            font-size: 14px;
        }

        .service-check span {
            display: block;
            color: #6b7280;
            font-size: 12px;
            margin-top: 2px;
            line-height: 1.35;
        }

        .actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #111111;
            color: white;
            border: none;
            border-radius: 12px;
            padding: 12px 17px;
            text-decoration: none;
            font-weight: 700;
            cursor: pointer;
        }

        .btn.secondary {
            background: white;
            color: #111111;
            border: 1px solid #d1d5db;
        }

        @media (max-width: 720px) {
            .form-grid,
            .services-list {
                grid-template-columns: 1fr;
            }

            .actions {
                flex-direction: column-reverse;
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
        <a href="<?= APP_URL ?>/professionals">Profesionales</a>
        <a href="<?= APP_URL ?>/logout">Salir</a>
    </div>
</div>

<main class="container">
    <section class="header">
        <h1><?= h($pageTitle) ?></h1>
        <p>Configurá los datos del integrante y los servicios que puede realizar.</p>
    </section>

    <form method="POST" action="<?= h($formAction) ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <?php if (!empty($professional['id'])): ?>
            <input type="hidden" name="professional_id" value="<?= h($professional['id']) ?>">
        <?php endif; ?>

        <section class="card">
            <h2 class="section-title">Datos del profesional</h2>

            <div class="form-grid">
                <div class="field">
                    <label for="name">Nombre *</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="<?= h(professionalField($professional, 'name')) ?>"
                        required
                    >
                </div>

                <div class="field">
                    <label for="role_label">Rol visible</label>
                    <input
                        type="text"
                        id="role_label"
                        name="role_label"
                        value="<?= h(professionalField($professional, 'role_label')) ?>"
                        placeholder="Ej: Colorista, Manicuría, Esteticista"
                    >
                </div>

                <div class="field">
                    <label for="phone">WhatsApp</label>
                    <input
                        type="text"
                        id="phone"
                        name="phone"
                        value="<?= h(professionalField($professional, 'phone')) ?>"
                        placeholder="Ej: 549..."
                    >
                    <span class="help">Por ahora se usará para datos internos y futuros mensajes prearmados.</span>
                </div>

                <div class="field">
                    <label for="email">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= h(professionalField($professional, 'email')) ?>"
                    >
                </div>

                <div class="field">
                    <label for="sort_order">Orden</label>
                    <input
                        type="number"
                        id="sort_order"
                        name="sort_order"
                        value="<?= h(professionalField($professional, 'sort_order', '0')) ?>"
                    >
                </div>
            </div>
        </section>

        <section class="card">
            <h2 class="section-title">Permisos y estado</h2>

            <div class="checkbox-row">
                <input
                    type="checkbox"
                    id="is_owner"
                    name="is_owner"
                    <?= professionalChecked($professional, 'is_owner') ?>
                >
                <label for="is_owner">
                    Dueña / administradora principal
                    <span>Puede representar a la responsable general del negocio. Sólo conviene tener una activa.</span>
                </label>
            </div>

            <div class="checkbox-row">
                <input
                    type="checkbox"
                    id="can_manage_own_schedule"
                    name="can_manage_own_schedule"
                    <?= professionalChecked($professional, 'can_manage_own_schedule', true) ?>
                >
                <label for="can_manage_own_schedule">
                    Puede manejar su propia agenda
                    <span>Preparado para la próxima etapa de acceso independiente desde su celular.</span>
                </label>
            </div>

            <div class="checkbox-row">
                <input
                    type="checkbox"
                    id="is_active"
                    name="is_active"
                    <?= professionalChecked($professional, 'is_active', true) ?>
                >
                <label for="is_active">
                    Profesional activo
                    <span>Si está inactivo, no debería aparecer para nuevos turnos.</span>
                </label>
            </div>
        </section>

        <section class="card">
            <h2 class="section-title">Servicios asignados</h2>

            <?php if (empty($services)): ?>
                <p class="help">Todavía no hay servicios activos para asignar.</p>
            <?php else: ?>
                <div class="services-list">
                    <?php foreach ($services as $service): ?>
                        <?php
                            $currentProfessionalId = (int) ($professional['id'] ?? 0);

                            $assignedProfessionalId = (int) ($service['assigned_professional_id'] ?? 0);
                            $assignedProfessionalName = $service['assigned_professional_name'] ?? '';
                            $assignedCount = (int) ($service['assigned_professionals_count'] ?? 0);

                            $isAssignedToThisProfessional = $assignedProfessionalId > 0
                                && $currentProfessionalId > 0
                                && $assignedProfessionalId === $currentProfessionalId;

                            $isAssignedToOtherProfessional = $assignedProfessionalId > 0
                                && !$isAssignedToThisProfessional;

                            $isAssigned = in_array((int) $service['id'], $assignedServiceIds, true);

                            $capacityLabel = 'Capacidad ' . (int) $service['capacity_per_slot'];

                            $blockLabel = (int) $service['blocks_full_schedule'] === 1
                                ? 'Bloquea agenda'
                                : 'Permite simultáneos';
                            ?>
                        <label class="service-check">
                            <input
                                type="checkbox"
                                name="service_ids[]"
                                value="<?= h($service['id']) ?>"
                                <?= $isAssigned ? 'checked' : '' ?>
                                data-service-name="<?= h($service['name']) ?>"
                                data-assigned-other="<?= $isAssignedToOtherProfessional ? '1' : '0' ?>"
                                data-assigned-professional="<?= h($assignedProfessionalName) ?>"
                            >
                            <span>
                                <strong><?= h($service['name']) ?></strong>
                                <span>
                                    <?= h($service['category_name'] ?: 'Sin categoría') ?>
                                    · <?= h($service['duration_default']) ?> min
                                    · <?= h($capacityLabel) ?>
                                    · <?= h($blockLabel) ?>
                                </span>
                            </span>
                        </label>
                        <?php if ($isAssignedToThisProfessional): ?>
                            <span style="color:#166534;font-weight:700;">
                                Asignado a este profesional
                            </span>
                        <?php elseif ($isAssignedToOtherProfessional): ?>
                            <span style="color:#92400e;font-weight:700;">
                                Ya asignado a <?= h($assignedProfessionalName) ?>
                            </span>
                        <?php elseif ($assignedCount > 1): ?>
                            <span style="color:#991b1b;font-weight:700;">
                                Conflicto: este servicio tiene más de una asignación
                            </span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <div class="actions">
            <a href="<?= APP_URL ?>/professionals" class="btn secondary">Cancelar</a>
            <button type="submit" class="btn"><?= h($submitLabel) ?></button>
        </div>
    </form>
</main>

<?php require __DIR__ . '/partials/mobile_nav.php'; ?>

<script>
document.querySelectorAll('input[name="service_ids[]"]').forEach(function (checkbox) {
    checkbox.addEventListener('change', function () {
        const assignedOther = this.dataset.assignedOther === '1';

        if (!assignedOther || !this.checked) {
            return;
        }

        const serviceName = this.dataset.serviceName || 'este servicio';
        const assignedProfessional = this.dataset.assignedProfessional || 'otra profesional';

        alert(
            'El servicio "' + serviceName + '" ya se encuentra asignado a "' + assignedProfessional + '".\n\n' +
            'En este sistema cada servicio pertenece a una sola profesional.\n\n' +
            'No se quitará automáticamente de la profesional actual.\n\n' +
            'Para cambiarlo, primero editá a "' + assignedProfessional + '" y quitale ese servicio. Luego volvé a asignarlo acá.'
        );

        this.checked = false;
    });
});
</script>

</body>
</html>