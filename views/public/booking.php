<?php require_once __DIR__ . '/../../config/app.php'; ?>

<?php
$businessSlug = $business['slug'] ?? ($_GET['business_slug'] ?? 'meli-figarola');
$businessName = $business['name'] ?? 'Meli Figarola';
$primaryColor = $business['primary_color'] ?? '#111111';
$secondaryColor = $business['secondary_color'] ?? '#ffffff';
$origin = $_GET['origen'] ?? $_GET['origin'] ?? 'web';

$allowedOrigins = ['whatsapp', 'instagram', 'facebook', 'web'];

if (!in_array($origin, $allowedOrigins, true)) {
    $origin = 'web';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reservar turno | <?= h($businessName) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        :root {
            --brand-primary: <?= h($primaryColor) ?>;
            --brand-secondary: <?= h($secondaryColor) ?>;
            --bg: #f3f4f6;
            --surface: #ffffff;
            --text: #071426;
            --muted: #6b7280;
            --border: #e5e7eb;
            --success-bg: #ecfdf5;
            --success-border: #bbf7d0;
            --success-text: #166534;
            --danger-bg: #fef2f2;
            --danger-border: #fecaca;
            --danger-text: #991b1b;
            --shadow: 0 14px 34px rgba(15, 23, 42, 0.10);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            padding-bottom: 96px;
        }

        button,
        input,
        textarea {
            font-family: inherit;
        }

        .page {
            max-width: 620px;
            margin: 0 auto;
            min-height: 100vh;
            background: var(--bg);
        }

        .hero {
            background: var(--brand-primary);
            color: white;
            border-radius: 0 0 28px 28px;
            padding: 28px 22px 32px;
            box-shadow: var(--shadow);
        }

        .hero small {
            display: block;
            opacity: .76;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .hero h1 {
            margin: 0;
            font-size: 30px;
            line-height: 1.05;
            letter-spacing: -0.04em;
        }

        .hero p {
            margin: 10px 0 0;
            opacity: .86;
            line-height: 1.45;
            font-size: 15px;
        }

        .content {
            padding: 18px;
        }

        .screen {
            display: none;
            animation: screenEnter .24s ease both;
        }

        .screen.active {
            display: block;
        }

        @keyframes screenEnter {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .step-header {
            margin: 4px 0 16px;
        }

        .step-header h2 {
            margin: 0 0 6px;
            font-size: 22px;
            letter-spacing: -0.03em;
        }

        .step-header p {
            margin: 0;
            color: var(--muted);
            line-height: 1.45;
            font-size: 14px;
        }

        .card {
            background: var(--surface);
            border-radius: 24px;
            padding: 18px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255,255,255,.7);
            margin-bottom: 16px;
        }

        .service-list {
            overflow: hidden;
            background: white;
            border-radius: 22px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .service-list-title {
            background: var(--brand-primary);
            color: white;
            padding: 15px 17px;
            font-size: 18px;
            font-weight: 800;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .service-item {
            width: 100%;
            border: 0;
            border-bottom: 1px solid #f0f0f0;
            background: white;
            padding: 13px 16px;
            display: grid;
            grid-template-columns: 1fr 28px;
            gap: 12px;
            text-align: left;
            cursor: pointer;
        }

        .service-item:last-child {
            border-bottom: 0;
        }

        .service-item.active {
            background: #f9fafb;
        }

        .service-name {
            display: block;
            color: #020617;
            font-size: 17px;
            font-weight: 800;
            line-height: 1.18;
            margin-bottom: 4px;
        }

        .service-meta {
            color: #7a7f87;
            font-size: 14px;
            line-height: 1.35;
        }

        .service-description {
            display: block;
            color: var(--brand-primary);
            font-size: 13px;
            margin-top: 4px;
            text-decoration: underline;
        }

        .radio-circle {
            width: 24px;
            height: 24px;
            border-radius: 999px;
            border: 2px solid #b7b7b7;
            align-self: center;
            position: relative;
        }

        .service-item.active .radio-circle {
            border-color: var(--brand-primary);
        }

        .service-item.active .radio-circle::after {
            content: "";
            position: absolute;
            inset: 5px;
            border-radius: 999px;
            background: var(--brand-primary);
        }

        .selected-summary {
            display: none;
            background: white;
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 14px;
            margin-bottom: 16px;
        }

        .selected-summary.show {
            display: block;
        }

        .selected-summary span {
            color: var(--muted);
            font-size: 14px;
        }

        .selected-summary strong {
            display: block;
            font-size: 17px;
            margin-top: 3px;
        }

        .calendar-card {
            background: white;
            border-radius: 24px;
            padding: 18px 14px 16px;
            box-shadow: var(--shadow);
            margin-bottom: 18px;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            padding: 0 5px;
        }

        .calendar-header strong {
            font-size: 20px;
            letter-spacing: -0.02em;
        }

        .calendar-nav {
            border: none;
            background: transparent;
            font-size: 32px;
            line-height: 1;
            padding: 2px 8px;
            cursor: pointer;
            color: #111111;
        }

        .calendar-nav:disabled {
            opacity: .22;
            cursor: not-allowed;
        }

        .weekdays,
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
        }

        .weekdays span {
            text-align: center;
            color: #5f6773;
            font-size: 12px;
            margin-bottom: 6px;
        }

        .day-btn {
            min-height: 38px;
            border: 0;
            background: transparent;
            border-radius: 999px;
            font-size: 16px;
            font-weight: 800;
            color: #43a100;
            cursor: pointer;
        }

        .day-btn.other-month {
            visibility: hidden;
        }

        .day-btn.disabled {
            color: #c7cbd1;
            cursor: not-allowed;
        }

        .day-btn.available:hover {
            background: #f3f4f6;
        }

        .day-btn.selected {
            background: var(--brand-primary);
            color: white;
        }

        .selected-date-label {
            text-align: center;
            font-size: 18px;
            font-weight: 800;
            margin: 18px 0 16px;
        }

        .slots-notice {
            border-radius: 18px;
            padding: 14px;
            background: #f9fafb;
            border: 1px solid var(--border);
            color: var(--muted);
            line-height: 1.4;
            margin-bottom: 14px;
        }

        .slots {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 9px;
        }

        .slot-btn {
            min-height: 46px;
            border: 1px solid var(--border);
            background: white;
            border-radius: 14px;
            font-weight: 900;
            cursor: pointer;
            color: #030712;
        }

        .slot-btn.active {
            background: var(--brand-primary);
            color: white;
            border-color: var(--brand-primary);
        }

        .field {
            margin-bottom: 15px;
        }

        label {
            display: block;
            font-weight: 900;
            font-size: 14px;
            margin-bottom: 8px;
        }

        input,
        textarea {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 16px;
            min-height: 52px;
            padding: 13px;
            font-size: 16px;
            background: white;
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        .help {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.35;
            margin-top: 6px;
        }

        .fixed-cta {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 80;
            padding: 12px 18px 16px;
            background: linear-gradient(to top, rgba(243,244,246,1), rgba(243,244,246,.78), rgba(243,244,246,0));
            transform: translateY(105%);
            opacity: 0;
            pointer-events: none;
            transition: .24s ease;
        }

        .fixed-cta.show {
            transform: translateY(0);
            opacity: 1;
            pointer-events: auto;
        }

        .fixed-cta-inner {
            max-width: 584px;
            margin: 0 auto;
        }

        .btn {
            width: 100%;
            min-height: 56px;
            border: none;
            border-radius: 16px;
            background: var(--brand-primary);
            color: white;
            font-weight: 900;
            font-size: 16px;
            cursor: pointer;
            box-shadow: 0 12px 26px rgba(15, 23, 42, 0.18);
        }

        .btn:disabled {
            background: #8e8e8e;
            cursor: not-allowed;
            box-shadow: none;
        }

        .back-btn {
            border: none;
            background: transparent;
            color: var(--muted);
            font-size: 14px;
            font-weight: 800;
            margin-bottom: 12px;
            cursor: pointer;
            padding: 4px 0;
        }

        .result {
            display: none;
            border-radius: 22px;
            padding: 20px;
            margin-bottom: 16px;
        }

        .result.error {
            display: block;
            background: var(--danger-bg);
            border: 1px solid var(--danger-border);
            color: var(--danger-text);
        }

        .result.success {
            display: block;
            background: var(--success-bg);
            border: 1px solid var(--success-border);
            color: var(--success-text);
        }

        .result strong {
            display: block;
            font-size: 18px;
            margin-bottom: 8px;
        }

        .summary {
            margin-top: 12px;
            background: rgba(255,255,255,.62);
            border-radius: 16px;
            padding: 12px;
        }

        .summary strong {
            display: block;
            margin-bottom: 4px;
        }

        .secondary-action {
            margin-top: 14px;
            border: 1px solid var(--success-border);
            background: white;
            color: var(--success-text);
            box-shadow: none;
        }

        @media (max-width: 390px) {
            .slots {
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 7px;
            }

            .slot-btn {
                font-size: 13px;
                min-height: 42px;
            }

            .hero h1 {
                font-size: 26px;
            }

            .service-name {
                font-size: 16px;
            }
        }
        #screenDateTime.active #dateTimeStepHeader {
            position: sticky;
            top: 0;
            z-index: 20;
            background: var(--bg);
            padding: 12px 0 14px;
            margin-top: -4px;
        }
        @media (max-width: 760px) {
            #screenDateTime.active #dateTimeStepHeader {
                top: 0;
                padding: 10px 0 12px;
            }
        }
        #screenDateTime.active #dateTimeStepHeader {
            position: sticky;
            top: 0;
            z-index: 30;
            background: var(--bg);
            padding: 12px 0 14px;
            margin-top: -4px;
        }
    </style>
</head>

<body>

<main class="page">
    <section class="hero">
        <small>Reserva online</small>
        <h1><?= h($businessName) ?></h1>
        <p>Elegí servicio, fecha y horario. La solicitud queda pendiente hasta que el equipo la confirme.</p>
    </section>

    <div class="content">
        <div id="result" class="result"></div>

        <form id="bookingForm">
            <input type="hidden" name="business_slug" id="businessSlug" value="<?= h($businessSlug) ?>">
            <input type="hidden" name="origin_channel" id="originChannel" value="<?= h($origin) ?>">
            <input type="hidden" name="service_id" id="serviceId" value="">
            <input type="hidden" name="date" id="date" value="">
            <input type="hidden" name="time" id="selectedTime" value="">
            <input type="hidden" name="booking_request_token" id="bookingRequestToken" value="">

            <section class="screen active" id="screenService">
                <div class="step-header" id="dateTimeStepHeader">
                    <h2>1. Servicio</h2>
                    <p>Seleccioná un solo servicio para solicitar tu turno.</p>
                </div>

                <div class="service-list">
                    <div class="service-list-title">
                        <span>Mis servicios</span>
                        <span>⌃</span>
                    </div>

                    <div id="servicesList"></div>
                </div>
            </section>

            <section class="screen" id="screenDateTime">
                <button type="button" class="back-btn" data-back="service">← Cambiar servicio</button>

                <div class="selected-summary show" id="selectedServiceSummary">
                    <span>Servicio seleccionado</span>
                    <strong id="selectedServiceName">-</strong>
                    <div class="help" id="selectedServiceMeta"></div>
                </div>

                <div class="step-header">
                    <h2>2. Fecha y horario</h2>
                    <p>Elegí un día disponible y luego seleccioná un horario.</p>
                </div>

                <div class="calendar-card">
                    <div class="calendar-header">
                        <button type="button" class="calendar-nav" id="prevMonthBtn">‹</button>
                        <strong id="monthLabel">-</strong>
                        <button type="button" class="calendar-nav" id="nextMonthBtn">›</button>
                    </div>

                    <div class="weekdays">
                        <span>Lun</span>
                        <span>Mar</span>
                        <span>Mié</span>
                        <span>Jue</span>
                        <span>Vie</span>
                        <span>Sáb</span>
                        <span>Dom</span>
                    </div>

                    <div class="calendar-grid" id="calendarGrid"></div>
                </div>

                <div class="selected-date-label" id="selectedDateLabel">
                    Seleccioná una fecha
                </div>

                <div id="slotsArea">
                    <div class="slots-notice" id="slotsNotice">
                        Seleccioná una fecha para ver horarios disponibles.
                    </div>

                    <div class="slots" id="slots"></div>
                </div>
            </section>

            <section class="screen" id="screenData">
                <button type="button" class="back-btn" data-back="datetime">← Cambiar fecha u horario</button>

                <div class="selected-summary show">
                    <span>Resumen del turno</span>
                    <strong id="summaryService">-</strong>
                    <div class="help" id="summaryDateTime"></div>
                </div>

                <div class="step-header">
                    <h2>3. Tus datos</h2>
                    <p>Completá tus datos para enviar la solicitud.</p>
                </div>

                <section class="card">
                    <div class="field">
                        <label for="customer_name">Nombre *</label>
                        <input type="text" id="customer_name" name="customer_name" required>
                    </div>

                    <div class="field">
                        <label for="customer_phone">WhatsApp *</label>
                        <input type="tel" id="customer_phone" name="customer_phone" required placeholder="Ej: 549...">
                    </div>

                    <div class="field">
                        <label for="customer_notes">Comentario opcional</label>
                        <textarea id="customer_notes" name="customer_notes" placeholder="Ej: consulta, detalle del servicio, preferencia, etc."></textarea>
                    </div>
                </section>
            </section>
            <section class="screen" id="screenConfirm">
                <div class="step-header">
                    <h2>Solicitud enviada</h2>
                    <p>Tu turno fue solicitado correctamente y queda pendiente de confirmación.</p>
                </div>

                <section class="card">
                    <div id="confirmationContent"></div>

                    <button type="button" class="btn secondary-action" id="newBookingBtn">
                        Solicitar otro turno
                    </button>
                </section>
            </section>
        </form>
    </div>
</main>

<div class="fixed-cta" id="fixedCta">
    <div class="fixed-cta-inner">
        <button type="button" class="btn" id="mainCtaBtn">
            Continuar
        </button>
    </div>
</div>

<script>
const businessSlug = document.getElementById('businessSlug').value;

const bookingForm = document.getElementById('bookingForm');
const servicesList = document.getElementById('servicesList');

const serviceIdInput = document.getElementById('serviceId');
const dateInput = document.getElementById('date');
const selectedTimeInput = document.getElementById('selectedTime');
const bookingRequestTokenInput = document.getElementById('bookingRequestToken');

const fixedCta = document.getElementById('fixedCta');
const mainCtaBtn = document.getElementById('mainCtaBtn');

const screenService = document.getElementById('screenService');
const screenDateTime = document.getElementById('screenDateTime');
const screenData = document.getElementById('screenData');
const screenConfirm = document.getElementById('screenConfirm');
const confirmationContent = document.getElementById('confirmationContent');

const selectedServiceName = document.getElementById('selectedServiceName');
const selectedServiceMeta = document.getElementById('selectedServiceMeta');
const summaryService = document.getElementById('summaryService');
const summaryDateTime = document.getElementById('summaryDateTime');

const monthLabel = document.getElementById('monthLabel');
const calendarGrid = document.getElementById('calendarGrid');
const prevMonthBtn = document.getElementById('prevMonthBtn');
const nextMonthBtn = document.getElementById('nextMonthBtn');

const selectedDateLabel = document.getElementById('selectedDateLabel');
const slotsNotice = document.getElementById('slotsNotice');
const slotsContainer = document.getElementById('slots');
const dateTimeStepHeader = document.getElementById('dateTimeStepHeader');
const slotsArea = document.getElementById('slotsArea');

const resultBox = document.getElementById('result');

const customerNameInput = document.getElementById('customer_name');
const customerPhoneInput = document.getElementById('customer_phone');

let services = [];
let selectedService = null;
let currentStep = 'service';
setNewBookingRequestToken();

function setNewBookingRequestToken() {
    if (window.crypto && window.crypto.randomUUID) {
        bookingRequestTokenInput.value = window.crypto.randomUUID();
        return;
    }

    bookingRequestTokenInput.value = String(Date.now()) + '-' + Math.random().toString(16).slice(2);
}

const today = new Date();
const minDate = new Date(today);
minDate.setDate(minDate.getDate() + 2);

const maxDate = new Date(today);
maxDate.setDate(maxDate.getDate() + 30);

let visibleMonth = new Date(minDate.getFullYear(), minDate.getMonth(), 1);

loadServices();
renderCalendar();

function loadServices() {
    servicesList.innerHTML = '<div class="service-item"><span class="service-meta">Cargando servicios...</span></div>';

    fetch('<?= APP_URL ?>/api/public/services.php?business_slug=' + encodeURIComponent(businessSlug))
        .then(response => response.json())
        .then(data => {
            if (!data.success || !data.services || data.services.length === 0) {
                servicesList.innerHTML = '<div class="service-item"><span class="service-meta">No hay servicios disponibles para reservar online.</span></div>';
                return;
            }

            services = data.services;
            renderServices();
        })
        .catch(() => {
            servicesList.innerHTML = '<div class="service-item"><span class="service-meta">Error al cargar servicios.</span></div>';
        });
}

function renderServices() {
    servicesList.innerHTML = '';

    services.forEach(service => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'service-item';
        button.dataset.id = service.id;

        const price = service.price_label ? service.price_label : 'Precio a definir';
        const duration = service.duration_default ? service.duration_default + ' min' : 'Duración a confirmar';

        button.innerHTML = `
            <span>
                <span class="service-name">${escapeHtml(service.name)}</span>
                <span class="service-meta">${escapeHtml(service.category_name || 'Servicio')} · ${escapeHtml(price)} · ${escapeHtml(duration)}</span>
                ${service.description ? '<span class="service-description">Leer más...</span>' : ''}
            </span>
            <span class="radio-circle"></span>
        `;

        button.addEventListener('click', () => selectService(service, button));

        servicesList.appendChild(button);
    });
}

function selectService(service, button) {
    selectedService = service;
    serviceIdInput.value = service.id;

    document.querySelectorAll('.service-item').forEach(item => item.classList.remove('active'));
    button.classList.add('active');

    selectedServiceName.textContent = service.name;
    selectedServiceMeta.textContent = buildServiceMeta(service);

    clearDateTimeSelection();
    updateCta();
}

function buildServiceMeta(service) {
    const parts = [];

    if (service.price_label) {
        parts.push('Precio: ' + service.price_label);
    }

    if (service.duration_default) {
        parts.push('Duración estimada: ' + service.duration_default + ' min');
    }

    return parts.join(' · ');
}

function showStep(step) {
    currentStep = step;

    screenService.classList.toggle('active', step === 'service');
    screenDateTime.classList.toggle('active', step === 'datetime');
    screenData.classList.toggle('active', step === 'data');
    screenConfirm.classList.toggle('active', step === 'confirm');

    if (step !== 'confirm') {
        resultBox.className = 'result';
        resultBox.innerHTML = '';
    }

    updateCta();

    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

function updateCta() {
    if (currentStep === 'service') {
        mainCtaBtn.textContent = 'Elegir fecha y horario';

        if (selectedService) {
            fixedCta.classList.add('show');
            mainCtaBtn.disabled = false;
        } else {
            fixedCta.classList.remove('show');
            mainCtaBtn.disabled = true;
        }

        return;
    }

    if (currentStep === 'datetime') {
        mainCtaBtn.textContent = 'Último paso';

        fixedCta.classList.add('show');
        mainCtaBtn.disabled = !(dateInput.value && selectedTimeInput.value);

        return;
    }

    if (currentStep === 'data') {
        mainCtaBtn.textContent = 'Solicitar turno';

        fixedCta.classList.add('show');
        mainCtaBtn.disabled = !(
            customerNameInput.value.trim()
            && customerPhoneInput.value.trim()
            && serviceIdInput.value
            && dateInput.value
            && selectedTimeInput.value
        );
    }

    if (currentStep === 'confirm') {
        fixedCta.classList.remove('show');
        mainCtaBtn.disabled = true;
        return;
    }
}

mainCtaBtn.addEventListener('click', function () {
    if (currentStep === 'service') {
        if (!selectedService) {
            return;
        }

        showStep('datetime');
        return;
    }

    if (currentStep === 'datetime') {
        if (!dateInput.value || !selectedTimeInput.value) {
            return;
        }

        summaryService.textContent = selectedService ? selectedService.name : '-';
        summaryDateTime.textContent = formatDateLabel(dateInput.value) + ' · ' + selectedTimeInput.value + ' hs';

        showStep('data');
        return;
    }

    if (currentStep === 'data') {
        submitBooking();
    }
});

document.querySelectorAll('[data-back]').forEach(button => {
    button.addEventListener('click', function () {
        const target = this.dataset.back;

        if (target === 'service') {
            showStep('service');
        }

        if (target === 'datetime') {
            showStep('datetime');
        }
    });
});

customerNameInput.addEventListener('input', updateCta);
customerPhoneInput.addEventListener('input', updateCta);

prevMonthBtn.addEventListener('click', function () {
    visibleMonth.setMonth(visibleMonth.getMonth() - 1);
    renderCalendar();
});

nextMonthBtn.addEventListener('click', function () {
    visibleMonth.setMonth(visibleMonth.getMonth() + 1);
    renderCalendar();
});

function renderCalendar() {
    calendarGrid.innerHTML = '';

    const year = visibleMonth.getFullYear();
    const month = visibleMonth.getMonth();

    monthLabel.textContent = monthName(month) + ', ' + year;

    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);

    let startOffset = firstDay.getDay() - 1;

    if (startOffset < 0) {
        startOffset = 6;
    }

    for (let i = 0; i < startOffset; i++) {
        const empty = document.createElement('button');
        empty.type = 'button';
        empty.className = 'day-btn other-month';
        empty.disabled = true;
        calendarGrid.appendChild(empty);
    }

    for (let day = 1; day <= lastDay.getDate(); day++) {
        const date = new Date(year, month, day);
        const dateValue = formatDateValue(date);

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'day-btn';
        button.textContent = day;

        const isEnabled = date >= startOfDay(minDate) && date <= startOfDay(maxDate);

        if (!isEnabled) {
            button.classList.add('disabled');
            button.disabled = true;
        } else {
            button.classList.add('available');

            button.addEventListener('click', function () {
                selectDate(dateValue);
            });
        }

        if (dateInput.value === dateValue) {
            button.classList.add('selected');
        }

        calendarGrid.appendChild(button);
    }

    const prevLimit = new Date(minDate.getFullYear(), minDate.getMonth(), 1);
    const nextLimit = new Date(maxDate.getFullYear(), maxDate.getMonth(), 1);

    prevMonthBtn.disabled = visibleMonth <= prevLimit;
    nextMonthBtn.disabled = visibleMonth >= nextLimit;
}

function selectDate(dateValue) {
    dateInput.value = dateValue;
    selectedTimeInput.value = '';

    selectedDateLabel.textContent = formatDateLabel(dateValue);

    renderCalendar();
    loadAvailableSlots();
    updateCta();
}

function scrollToSlotsArea() {
    const target = slotsArea || slotsNotice || dateTimeStepHeader;

    if (!target) {
        return;
    }

    const offset = 76;
    const targetPosition = target.getBoundingClientRect().top + window.scrollY - offset;

    window.scrollTo({
        top: targetPosition,
        behavior: 'smooth'
    });
}

function loadAvailableSlots() {
    const serviceId = serviceIdInput.value;
    const date = dateInput.value;

    slotsContainer.innerHTML = '';
    selectedTimeInput.value = '';
    updateCta();

    if (!serviceId || !date) {
        slotsNotice.textContent = 'Seleccioná una fecha para ver horarios disponibles.';
        return;
    }

    slotsNotice.textContent = 'Buscando horarios disponibles...';

    fetch(
        '<?= APP_URL ?>/api/public/availability.php?business_slug='
        + encodeURIComponent(businessSlug)
        + '&service_id='
        + encodeURIComponent(serviceId)
        + '&date='
        + encodeURIComponent(date)
    )
        .then(response => response.json())
        .then(data => {
            slotsContainer.innerHTML = '';

            if (!data.success) {
                slotsNotice.textContent = data.message || 'No se pudo consultar disponibilidad.';
                updateCta();
                return;
            }

            if (!data.slots || data.slots.length === 0) {
                slotsNotice.textContent = 'No hay horarios disponibles para esa fecha.';
                updateCta();
                return;
            }

            slotsNotice.textContent = 'Horarios disponibles para el servicio seleccionado.';

            data.slots.forEach(slot => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'slot-btn';
                button.textContent = slot.time;
                button.dataset.time = slot.time;

                button.addEventListener('click', function () {
                    document.querySelectorAll('.slot-btn').forEach(btn => btn.classList.remove('active'));
                    button.classList.add('active');

                    selectedTimeInput.value = slot.time;
                    updateCta();
                });

                slotsContainer.appendChild(button);
            });

            updateCta();

            setTimeout(function () {
                scrollToSlotsArea();
            }, 120);
        })
        .catch(() => {
            slotsNotice.textContent = 'Error al consultar disponibilidad.';
            updateCta();
        });
}

function clearDateTimeSelection() {
    dateInput.value = '';
    selectedTimeInput.value = '';
    selectedDateLabel.textContent = 'Seleccioná una fecha';
    slotsContainer.innerHTML = '';
    slotsNotice.textContent = 'Seleccioná una fecha para ver horarios disponibles.';
    renderCalendar();
}

function submitBooking() {
    if (bookingForm.dataset.sending === '1') {
        return;
    }

    bookingForm.dataset.sending = '1';
    mainCtaBtn.disabled = true;
    mainCtaBtn.textContent = 'Enviando solicitud...';

    resultBox.className = 'result';
    resultBox.innerHTML = '';

    const formData = new FormData(bookingForm);

    fetch('<?= APP_URL ?>/api/public/book.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                resultBox.className = 'result error';
                resultBox.textContent = data.message || 'No se pudo registrar el turno.';

                bookingForm.dataset.sending = '0';
                updateCta();

                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });

                return;
            }

            const appointment = data.appointment;

            confirmationContent.innerHTML = `
                <div class="result success" style="display:block;margin-bottom:16px;">
                    <strong>Solicitud enviada</strong>
                    <p>${escapeHtml(data.message)}</p>
                    <div class="summary">
                        <strong>${escapeHtml(appointment.service_name)}</strong>
                        <div>${escapeHtml(appointment.date)} · ${escapeHtml(appointment.time)} hs</div>
                        <div>Profesional: ${escapeHtml(appointment.professional_name || 'Asignada por el servicio')}</div>
                        <div>Estado: ${escapeHtml(appointment.status_label)}</div>
                    </div>
                </div>
            `;

            bookingForm.dataset.sending = '0';

            selectedService = null;
            serviceIdInput.value = '';
            dateInput.value = '';
            selectedTimeInput.value = '';
            customerNameInput.value = '';
            customerPhoneInput.value = '';
            document.getElementById('customer_notes').value = '';

            document.querySelectorAll('.service-item').forEach(item => item.classList.remove('active'));

            clearDateTimeSelection();
            fixedCta.classList.remove('show');

            showStep('confirm');

            const newBookingBtn = document.getElementById('newBookingBtn');

            if (newBookingBtn) {
                newBookingBtn.addEventListener('click', function () {
                    confirmationContent.innerHTML = '';
                    resultBox.className = 'result';
                    resultBox.innerHTML = '';

                    bookingForm.reset();
                    setNewBookingRequestToken();

                    selectedService = null;
                    serviceIdInput.value = '';
                    dateInput.value = '';
                    selectedTimeInput.value = '';
                    selectedTimeInput.value = '';

                    document.querySelectorAll('.service-item').forEach(item => item.classList.remove('active'));

                    clearDateTimeSelection();
                    showStep('service');
                });
            }
        })
        .catch(() => {
            resultBox.className = 'result error';
            resultBox.textContent = 'Error al enviar la solicitud.';

            bookingForm.dataset.sending = '0';
            updateCta();

            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
}

function formatDateValue(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return year + '-' + month + '-' + day;
}

function startOfDay(date) {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
}

function formatDateLabel(dateValue) {
    const parts = dateValue.split('-');
    const date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));

    const days = [
        'Domingo',
        'Lunes',
        'Martes',
        'Miércoles',
        'Jueves',
        'Viernes',
        'Sábado'
    ];

    const months = [
        'enero',
        'febrero',
        'marzo',
        'abril',
        'mayo',
        'junio',
        'julio',
        'agosto',
        'septiembre',
        'octubre',
        'noviembre',
        'diciembre'
    ];

    return days[date.getDay()] + ', ' + date.getDate() + ' de ' + months[date.getMonth()] + ' de ' + date.getFullYear();
}

function monthName(monthIndex) {
    const months = [
        'Enero',
        'Febrero',
        'Marzo',
        'Abril',
        'Mayo',
        'Junio',
        'Julio',
        'Agosto',
        'Septiembre',
        'Octubre',
        'Noviembre',
        'Diciembre'
    ];

    return months[monthIndex];
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
</script>

</body>
</html>