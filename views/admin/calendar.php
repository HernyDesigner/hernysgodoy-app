<?php

$user = Auth::user();

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Calendario - Turnero HG</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- FullCalendar -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>

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

        a {
            color: inherit;
        }

        .topbar {
            background: #111111;
            color: white;
            padding: 18px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .topbar strong {
            font-size: 18px;
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
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
        }

        .header h1 {
            margin: 0 0 8px;
            font-size: 30px;
        }

        .header p {
            margin: 0;
            color: #6b7280;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
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

        .calendar-card {
            background: white;
            border-radius: 18px;
            padding: 22px;
            box-shadow: 0 10px 24px rgba(0,0,0,0.05);
        }

        #calendar {
            min-height: 720px;
        }

        .legend {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 18px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: #374151;
        }

        .dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }

        .fc .fc-button-primary {
            background: #111111;
            border-color: #111111;
        }

        .fc .fc-button-primary:not(:disabled).fc-button-active,
        .fc .fc-button-primary:not(:disabled):active {
            background: #374151;
            border-color: #374151;
        }

        .fc-event {
            cursor: pointer;
            border-radius: 8px;
            padding: 2px 4px;
        }

        /*
        |--------------------------------------------------------------------------
        | Modal
        |--------------------------------------------------------------------------
        */

        .modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: rgba(0, 0, 0, 0.55);
            display: none;
            align-items: flex-start;
            justify-content: center;
            padding: 32px 16px;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal {
            width: 100%;
            max-width: 620px;
            max-height: calc(100vh - 64px);
            background: #ffffff;
            border-radius: 22px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 24px 70px rgba(0, 0, 0, 0.25);
        }

        #appointmentModal .modal-header {
            flex: 0 0 auto;
            background: #111111;
            color: #ffffff;
            padding: 20px 28px;
            border-bottom: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        #appointmentModal .modal-header h2,
        #appointmentModal .modal-header h3 {
            margin: 0;
            color: #ffffff;
            font-size: 26px;
            line-height: 1.1;
            font-weight: 700;
        }

        #appointmentModal .modal-close,
        #appointmentModal #closeAppointmentModal {
            width: 36px;
            height: 36px;
            min-width: 36px;
            border: none;
            background: transparent;
            color: #ffffff;
            font-size: 34px;
            line-height: 1;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border-radius: 999px;
        }

        #appointmentModal .modal-close:hover,
        #appointmentModal #closeAppointmentModal:hover {
            background: rgba(255, 255, 255, 0.12);
        }

        .modal-close {
            background: transparent;
            border: none;
            color: white;
            font-size: 26px;
            cursor: pointer;
            line-height: 1;
        }
        .modal-body {
            flex: 1 1 auto;
            overflow-y: auto;
            padding: 20px 22px;
            -webkit-overflow-scrolling: touch;
        }

        .modal-footer {
            flex: 0 0 auto;
            padding: 16px 22px;
            border-top: 1px solid #e5e7eb;
            background: #ffffff;
        }


        body.modal-open {
            overflow: hidden;
        }

        @media (max-width: 760px) {
            .modal-overlay {
                padding: 12px;
                align-items: flex-start;
            }

            .modal {
                max-height: calc(100vh - 100px);
                border-radius: 20px;
            }

            .modal-body {
                padding: 16px;
                max-height: calc(100vh - 170px);
            }

            .modal-footer {
                padding: 14px 16px;
            }
            #appointmentModal .modal-header {
                padding: 18px 20px;
            }

            #appointmentModal .modal-header h2,
            #appointmentModal .modal-header h3 {
                font-size: 24px;
            }

            #appointmentModal .modal-close,
            #appointmentModal #closeAppointmentModal {
                width: 34px;
                height: 34px;
                min-width: 34px;
                font-size: 32px;
            }
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 18px;
        }

        .detail {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 12px;
        }

        .detail span {
            display: block;
            color: #6b7280;
            font-size: 12px;
            margin-bottom: 4px;
        }

        .detail strong {
            font-size: 14px;
        }

        .notes {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 14px;
            margin-top: 14px;
        }

        .notes span {
            display: block;
            color: #6b7280;
            font-size: 12px;
            margin-bottom: 6px;
        }

        .modal-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: bold;
            background: #e5e7eb;
            color: #111827;
        }

        @media (max-width: 760px) {
            .container {
                padding: 20px;
            }

            .header {
                flex-direction: column;
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }

            #calendar {
                min-height: 620px;
            }
        }
        .legend {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    align-items: center;
}

.legend-link {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    color: #111827;
    text-decoration: none;
    font-size: 14px;
}

.legend-link:hover {
    text-decoration: underline;
}

.dot {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    display: inline-block;
}

.dot.confirmed {
    background: #16a34a;
}

.dot.pending-approval {
    background: #f59e0b;
}

.dot.pending-deposit {
    background: #dc2626;
}

.dot.rescheduled {
    background: #2563eb;
}

.dot.consultation {
    background: #7c3aed;
}
    </style>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/mobile-app.css?v=3">
</head>
<body>

<div class="topbar">
    <strong>Turnero HG</strong>

    <div>
        <a href="<?= APP_URL ?>/dashboard">Dashboard</a>
        <a href="<?= APP_URL ?>/logout">Cerrar sesión</a>
    </div>
</div>

<div class="container">

    <div class="header">
        <div>
            <h1>Calendario de <?= h($business['name']) ?></h1>
            <p>Vista visual de turnos, solicitudes y estados.</p>
        </div>

        <div class="actions">
            <a href="<?= APP_URL ?>/appointments/create" class="btn">Crear turno manual</a>
            <a href="<?= APP_URL ?>/dashboard" class="btn secondary">Volver al dashboard</a>
        </div>
    </div>

    <div class="calendar-card">

        <div class="legend">
            <a href="<?= APP_URL ?>/appointments/confirmed" class="legend-link">
                <span class="dot confirmed"></span>
                Confirmado
            </a>

            <a href="<?= APP_URL ?>/appointments/pending-approval" class="legend-link">
                <span class="dot pending-approval"></span>
                Pendiente de aprobación
            </a>

            <a href="<?= APP_URL ?>/appointments/pending-deposit" class="legend-link">
                <span class="dot pending-deposit"></span>
                Pendiente de seña
            </a>

            <a href="<?= APP_URL ?>/appointments/rescheduled" class="legend-link">
                <span class="dot rescheduled"></span>
                Reprogramado
            </a>

            <a href="<?= APP_URL ?>/appointments/consultation" class="legend-link">
                <span class="dot consultation"></span>
                Consulta
            </a>
        </div>

        <div id="calendar"></div>
    </div>

</div>

<!-- Modal detalle turno -->
<div class="modal-overlay" id="appointmentModal">
    <div class="modal">

        <div class="modal-header">
            <h2>Detalle del turno</h2>

            <button
                type="button"
                class="modal-close"
                id="closeAppointmentModal"
                aria-label="Cerrar detalle del turno"
            >
                ×
            </button>
        </div>

        <div class="modal-body">
            <div class="detail-grid">
                <div class="detail">
                    <span>Cliente</span>
                    <strong id="modalCustomerName">-</strong>
                </div>

                <div class="detail">
                    <span>WhatsApp</span>
                    <strong id="modalCustomerPhone">-</strong>
                </div>

                <div class="detail">
                    <span>Servicio</span>
                    <strong id="modalServiceName">-</strong>
                </div>

                <div class="detail">
                    <span>Profesional</span>
                    <strong id="modalProfessional">-</strong>
                </div>

                <div class="detail">
                    <span>Fecha y hora</span>
                    <strong id="modalDateTime">-</strong>
                </div>

                <div class="detail">
                    <span>Estado</span>
                    <strong id="modalStatus">-</strong>
                </div>

                <div class="detail">
                    <span>Origen</span>
                    <strong id="modalOrigin">-</strong>
                </div>

                <div class="detail">
                    <span>Seña</span>
                    <strong id="modalDeposit">-</strong>
                </div>

                <div class="detail">
                    <span>Precio</span>
                    <strong id="modalPrice">-</strong>
                </div>
            </div>

            <div class="notes">
                <span>Notas del cliente</span>
                <div id="modalCustomerNotes">Sin notas.</div>
            </div>

            <div class="notes">
                <span>Notas internas</span>
                <div id="modalInternalNotes">Sin notas internas.</div>
            </div>
        </div>

        <div class="modal-footer">
            <a href="#" target="_blank" class="btn" id="modalWhatsappBtn">Abrir WhatsApp</a>
            <a href="#" class="btn secondary" id="modalEditBtn">Editar turno</a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('calendar');

    const isMobileCalendar = window.innerWidth <= 760;

const calendar = new FullCalendar.Calendar(calendarEl, {
    locale: 'es',
    initialView: isMobileCalendar ? 'listWeek' : 'timeGridWeek',
    height: 'auto',
    nowIndicator: true,
    allDaySlot: false,

    headerToolbar: isMobileCalendar
        ? {
            left: 'prev,next today',
            center: 'title',
            right: ''
        }
        : {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
        },

    buttonText: {
        today: 'Hoy',
        month: 'Mes',
        week: 'Semana',
        day: 'Día',
        list: 'Lista'
    },

    events: '<?= APP_URL ?>/api/admin/appointments.php',

    eventClick: function(info) {
        openAppointmentModal(info.event);
        
    }
});

    calendar.render();
});

function openAppointmentModal(event) {
    const modal = document.getElementById('appointmentModal');

    if (!modal) {
        alert('No se encontró el modal en el HTML.');
        return;
    }

    const props = event.extendedProps || {};

    const modalCustomerName = document.getElementById('modalCustomerName');
    const modalCustomerPhone = document.getElementById('modalCustomerPhone');
    const modalServiceName = document.getElementById('modalServiceName');
    const modalProfessional = document.getElementById('modalProfessional');
    const modalDateTime = document.getElementById('modalDateTime');
    const modalStatus = document.getElementById('modalStatus');
    const modalOrigin = document.getElementById('modalOrigin');
    const modalDeposit = document.getElementById('modalDeposit');
    const modalPrice = document.getElementById('modalPrice');
    const modalCustomerNotes = document.getElementById('modalCustomerNotes');
    const modalInternalNotes = document.getElementById('modalInternalNotes');
    const modalWhatsappBtn = document.getElementById('modalWhatsappBtn');
    const modalEditBtn = document.getElementById('modalEditBtn');

    if (modalCustomerName) {
        modalCustomerName.textContent = props.customer_name || '-';
    }

    if (modalCustomerPhone) {
        modalCustomerPhone.textContent = props.customer_phone || '-';
    }

    if (modalServiceName) {
        modalServiceName.textContent = props.service_name || '-';
    }

    if (modalProfessional) {
        modalProfessional.textContent = props.professional_name || '-';
    }

    const start = event.start ? formatDateTime(event.start) : '-';
    const end = event.end ? formatTime(event.end) : '-';

    if (modalDateTime) {
        modalDateTime.textContent = start + ' a ' + end;
    }

    if (modalStatus) {
        modalStatus.textContent = props.status_label || '-';
    }

    if (modalOrigin) {
        modalOrigin.textContent = originLabel(props.origin_channel);
    }

    if (modalDeposit) {
        modalDeposit.textContent = depositLabel(props.deposit_status, props.deposit_amount);
    }

    if (modalPrice) {
        modalPrice.textContent = priceLabel(props.price_label, props.total_amount);
    }

    if (modalCustomerNotes) {
        modalCustomerNotes.textContent = props.customer_notes || 'Sin notas.';
    }

    if (modalInternalNotes) {
        modalInternalNotes.textContent = props.internal_notes || 'Sin notas internas.';
    }

    const phone = cleanPhone(props.customer_phone || '');

    const message = encodeURIComponent(
        'Hola ' +
        (props.customer_name || '') +
        ', soy Meli Figarola. Te escribo por tu turno de ' +
        (props.service_name || '') +
        '.'
    );

    const whatsappUrl = phone
        ? 'https://wa.me/' + phone + '?text=' + message
        : '#';

    if (modalWhatsappBtn) {
        modalWhatsappBtn.setAttribute('href', whatsappUrl);
    }

    if (modalEditBtn) {
        modalEditBtn.setAttribute('href', '<?= APP_URL ?>/appointments/edit?id=' + event.id);
    }

    modal.classList.add('active');
    document.body.classList.add('modal-open');

    const modalBody = modal.querySelector('.modal-body');

    if (modalBody) {
        modalBody.scrollTop = 0;
    }
}

function closeAppointmentModal() {
    const modal = document.getElementById('appointmentModal');

    if (!modal) {
        return;
    }

    modal.classList.remove('active');
    document.body.classList.remove('modal-open');
}

const appointmentModal = document.getElementById('appointmentModal');

if (appointmentModal) {
    appointmentModal.addEventListener('click', function (event) {
        if (event.target === appointmentModal) {
            closeAppointmentModal();
        }
    });
}

document.addEventListener('keydown', function (event) {
    const modal = document.getElementById('appointmentModal');

    if (!modal) {
        return;
    }

    if (event.key === 'Escape' && modal.classList.contains('active')) {
        closeAppointmentModal();
    }
});

const closeAppointmentModalBtn = document.getElementById('closeAppointmentModal');

if (closeAppointmentModalBtn) {
    closeAppointmentModalBtn.addEventListener('click', closeAppointmentModal);
}

function formatDateTime(date) {
    return date.toLocaleDateString('es-AR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    }) + ' ' + formatTime(date);
}

function formatTime(date) {
    return date.toLocaleTimeString('es-AR', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

function originLabel(origin) {
    const labels = {
        whatsapp: 'WhatsApp',
        instagram: 'Instagram',
        facebook: 'Facebook',
        web: 'Web',
        manual: 'Manual'
    };

    return labels[origin] || origin || '-';
}

function depositLabel(status, amount) {
    if (!status || status === 'not_required') {
        return 'No requiere';
    }

    if (status === 'pending') {
        return amount ? 'Pendiente - $' + amount : 'Pendiente';
    }

    if (status === 'paid') {
        return amount ? 'Pagada - $' + amount : 'Pagada';
    }

    return status;
}

function priceLabel(priceLabel, totalAmount) {
    if (priceLabel) {
        return priceLabel;
    }

    if (totalAmount) {
        return '$' + totalAmount;
    }

    return '-';
}

function cleanPhone(phone) {
    return String(phone).replace(/\D/g, '');
}

document.getElementById('appointmentModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeAppointmentModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAppointmentModal();
    }
});
</script>
<?php require __DIR__ . '/partials/mobile_nav.php'; ?>

</body>
</html>