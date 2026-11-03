<?php

    class AdminController
    {
        private $db;

        public function __construct()
        {
            $this->db = Database::connect();
        }

        public function dashboard(): void
        {
            Auth::requireLogin();

            $businessId = $this->getCurrentBusinessId();
            $professionalId = $this->getCurrentProfessionalId();

            $business = $this->getBusiness($businessId);
            $stats = $this->getDashboardStats($businessId, $professionalId);
            $todayAppointments = $this->getTodayAppointments($businessId, $professionalId);
            $upcomingAppointments = $this->getUpcomingAppointments($businessId, $professionalId);

            require __DIR__ . '/../views/admin/dashboard.php';
        }

        public function calendar(): void
        {
            Auth::requireLogin();
            Auth::startSession();

            $businessId = $this->getCurrentBusinessId();
            $business = $this->getBusiness($businessId);
            $createResult = $this->consumeAppointmentCreateResult(
                $_GET['create_result'] ?? null
            );
            $success = $createResult['success'] ?? null;

            require __DIR__ . '/../views/admin/calendar.php';
        }

        public function createAppointment(): void
    {
        Auth::requireLogin();

        $businessId = $this->getCurrentBusinessId();
        $professionalId = $this->getCurrentProfessionalId();
        $business = $this->getBusiness($businessId);
        $services = $this->getActiveServices($businessId, $professionalId);
        $professionals = $this->getActiveProfessionals($businessId, $professionalId);

        Auth::startSession();

        $createResult = $this->consumeAppointmentCreateResult($_GET['result'] ?? null);
        $error = $createResult['error'] ?? null;
        $success = $createResult['success'] ?? null;
        $old = isset($createResult['old']) && is_array($createResult['old'])
            ? $createResult['old']
            : [];

        require __DIR__ . '/../views/admin/appointment_create.php';
    }

    public function storeAppointment(): void
    {
        Auth::requireLogin();
        Auth::startSession();

        $createResultId = bin2hex(random_bytes(16));
        $csrfToken = $_POST['csrf_token'] ?? null;

        if (!Auth::validateCsrfToken($csrfToken)) {
            $this->redirectAppointmentCreateWithError(
                $createResultId,
                'La sesión del formulario venció. Volvé a intentarlo.'
            );
        }

        $businessId = $this->getCurrentBusinessId();
        $scopedProfessionalId = $this->getCurrentProfessionalId();

        $customerName = trim($_POST['customer_name'] ?? '');
        $customerPhone = preg_replace('/\D+/', '', $_POST['customer_phone'] ?? '');
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $professionalId = $scopedProfessionalId ?? (int) ($_POST['professional_id'] ?? 0);
        $date = trim($_POST['date'] ?? '');
        $time = trim($_POST['time'] ?? '');
        $status = trim($_POST['status'] ?? 'pending_approval');
        $originChannel = trim($_POST['origin_channel'] ?? 'manual');
        $customerNotes = trim($_POST['customer_notes'] ?? '');
        $internalNotes = trim($_POST['internal_notes'] ?? '');

        if ($customerName === '') {
            $this->redirectAppointmentCreateWithError(
                $createResultId,
                'El nombre del cliente es obligatorio.'
            );
        }

        if ($customerPhone === '') {
            $this->redirectAppointmentCreateWithError(
                $createResultId,
                'El WhatsApp del cliente es obligatorio.'
            );
        }

        if ($serviceId <= 0) {
            $this->redirectAppointmentCreateWithError($createResultId, 'Seleccioná un servicio.');
        }

        if ($date === '' || $time === '') {
            $this->redirectAppointmentCreateWithError($createResultId, 'Seleccioná fecha y hora.');
        }

        $allowedStatuses = [
            'pending_approval',
            'pending_deposit',
            'approved',
            'completed',
            'cancelled',
            'rescheduled',
            'no_show',
            'consultation'
        ];

        if (!in_array($status, $allowedStatuses, true)) {
            $this->redirectAppointmentCreateWithError($createResultId, 'Estado de turno inválido.');
        }

        $allowedOrigins = [
            'whatsapp',
            'instagram',
            'facebook',
            'web',
            'manual'
        ];

        if (!in_array($originChannel, $allowedOrigins, true)) {
            $originChannel = 'manual';
        }

        $service = $this->getService($businessId, $serviceId);

        if (!$service) {
            $this->redirectAppointmentCreateWithError(
                $createResultId,
                'El servicio seleccionado no existe.'
            );
        }

        if ($professionalId <= 0) {
                $professionalId = $this->getDefaultProfessionalId($businessId);
            }

            if (!$this->professionalCanDoService($businessId, $professionalId, $serviceId)) {
                $this->redirectAppointmentCreateWithError(
                    $createResultId,
                    'El profesional seleccionado no está asignado a este servicio.'
                );
            }

        $businessTimeZone = new DateTimeZone('America/Argentina/Buenos_Aires');
        $startInput = $date . ' ' . $time;
        $appointmentStart = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i',
            $startInput,
            $businessTimeZone
        );
        $startErrors = DateTimeImmutable::getLastErrors();

        if (
            !$appointmentStart
            || ($startErrors !== false && ($startErrors['warning_count'] > 0 || $startErrors['error_count'] > 0))
            || $appointmentStart->format('Y-m-d H:i') !== $startInput
        ) {
            $this->redirectAppointmentCreateWithError(
                $createResultId,
                'La fecha u hora no es válida.'
            );
        }

        if ($appointmentStart <= new DateTimeImmutable('now', $businessTimeZone)) {
            $this->redirectAppointmentCreateWithError(
                $createResultId,
                'No podés crear un turno en una fecha u hora que ya pasó.'
            );
        }

        $startAt = $appointmentStart->format('Y-m-d H:i:s');

        $duration = (int) $service['duration_default'];

        if ($duration <= 0) {
            $duration = 60;
        }

        $endAt = $appointmentStart
            ->modify("+{$duration} minutes")
            ->format('Y-m-d H:i:s');

        $availability = new Availability();

        $availableSlots = $availability->getAvailableSlots(
            $businessId,
            $serviceId,
            $date,
            null,
            $professionalId
        );

        $slotAvailable = false;

        foreach ($availableSlots as $slot) {
            if ($slot['time'] === substr($time, 0, 5)) {
                $slotAvailable = true;
                break;
            }
        }

        if (!$slotAvailable) {
            $this->redirectAppointmentCreateWithError(
                $createResultId,
                'Ese horario ya no está disponible para el profesional seleccionado.'
            );
        }

        try {
            $this->db->beginTransaction();

            if ($appointmentStart <= new DateTimeImmutable('now', $businessTimeZone)) {
                throw new RuntimeException('El horario seleccionado ya pasó. Elegí uno futuro.');
            }

            if (!$this->professionalCanDoService($businessId, $professionalId, $serviceId)) {
                throw new RuntimeException('El profesional ya no está asignado a este servicio.');
            }

            $customerId = $this->findOrCreateCustomer(
                $businessId,
                $customerName,
                $customerPhone,
                $scopedProfessionalId === null
            );

            $depositRequired = (int) $service['requires_deposit'];
            $depositStatus = $depositRequired ? 'pending' : 'not_required';
            $depositAmount = $service['deposit_amount'] ?? null;

            $totalAmount = $service['price'] ?? null;

            $publicToken = bin2hex(random_bytes(32));

            $stmt = $this->db->prepare("
                INSERT INTO appointments
                (
                    business_id,
                    customer_id,
                    service_id,
                    professional_id,
                    start_at,
                    end_at,
                    status,
                    origin_channel,
                    total_amount,
                    deposit_required,
                    deposit_status,
                    deposit_amount,
                    customer_notes,
                    internal_notes,
                    public_token,
                    created_by
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    'admin'
                )
            ");

            $stmt->execute([
                $businessId,
                $customerId,
                $serviceId,
                $professionalId,
                $startAt,
                $endAt,
                $status,
                $originChannel,
                $totalAmount,
                $depositRequired,
                $depositStatus,
                $depositAmount,
                $customerNotes,
                $internalNotes,
                $publicToken
            ]);

            $this->db->commit();
            $this->storeAppointmentCreateResult($createResultId, [
                'success' => 'Turno creado correctamente.',
            ]);

            header(
                'Location: ' . APP_URL . '/calendar?create_result=' . rawurlencode($createResultId)
            );
            exit;

        } catch (Exception $e) {
            $this->db->rollBack();

            $this->redirectAppointmentCreateWithError(
                $createResultId,
                'Error al crear el turno: ' . $e->getMessage()
            );
        }
    }

    public function editAppointment(): void
    {
        Auth::requireLogin();
        Auth::startSession();

        $businessId = $this->getCurrentBusinessId();
        $professionalId = $this->getCurrentProfessionalId();
        $business = $this->getBusiness($businessId);

        $appointmentId = (int) ($_GET['id'] ?? 0);

        if ($appointmentId <= 0) {
            die('ID de turno inválido.');
        }

        $appointment = $this->getAppointment($businessId, $appointmentId, $professionalId);

        if (!$appointment) {
            die('No se encontró el turno.');
        }

        $canCancelAppointment = $this->canCancelAppointmentStatus($appointment['status']);
        $cancellationWhatsAppUrl = $appointment['status'] === 'cancelled'
            ? $this->buildCancellationWhatsAppUrl($appointment)
            : null;

        $services = $this->getActiveServices($businessId, $professionalId);
        $professionals = $this->getProfessionalsForService(
            $businessId,
            (int) $appointment['service_id'],
            $professionalId
        );

        $error = $_SESSION['flash_error'] ?? null;
        $success = $_SESSION['flash_success'] ?? null;

        unset($_SESSION['flash_error'], $_SESSION['flash_success']);

        require __DIR__ . '/../views/admin/appointment_edit.php';
    }

    public function updateAppointment(): void
    {
        Auth::requireLogin();
        Auth::startSession();

        $csrfToken = $_POST['csrf_token'] ?? null;

        if (!Auth::validateCsrfToken($csrfToken)) {
            $appointmentId = (int) ($_POST['appointment_id'] ?? 0);

            if ($appointmentId > 0) {
                $this->redirectEditWithError(
                    $appointmentId,
                    'La sesión del formulario venció. Volvé a intentarlo.'
                );
            }

            die('Token CSRF inválido.');
        }

        $businessId = $this->getCurrentBusinessId();
        $scopedProfessionalId = $this->getCurrentProfessionalId();

        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);

        if ($appointmentId <= 0) {
            die('ID de turno inválido.');
        }

        $appointment = $this->getAppointment($businessId, $appointmentId, $scopedProfessionalId);

        if (!$appointment) {
            die('No se encontró el turno.');
        }

        if ($scopedProfessionalId !== null) {
            $customerName = (string) $appointment['customer_name'];
            $customerPhone = (string) $appointment['customer_phone'];
        } else {
            $customerName = trim($_POST['customer_name'] ?? '');
            $customerPhone = preg_replace('/\D+/', '', $_POST['customer_phone'] ?? '');
        }
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $professionalId = $scopedProfessionalId ?? (int) ($_POST['professional_id'] ?? 0);
        $date = trim($_POST['date'] ?? '');
        $time = trim($_POST['time'] ?? '');
        $status = trim($_POST['status'] ?? $appointment['status']);
        $originChannel = trim($_POST['origin_channel'] ?? 'manual');
        $customerNotes = trim($_POST['customer_notes'] ?? '');
        $internalNotes = trim($_POST['internal_notes'] ?? '');

        if ($scopedProfessionalId === null) {
            if ($customerName === '') {
                $this->redirectEditWithError($appointmentId, 'El nombre del cliente es obligatorio.');
            }

            if ($customerPhone === '') {
                $this->redirectEditWithError($appointmentId, 'El WhatsApp del cliente es obligatorio.');
            }
        }

        if ($serviceId <= 0) {
            $this->redirectEditWithError($appointmentId, 'Seleccioná un servicio.');
        }

        if ($professionalId <= 0) {
            $professionalId = $this->getDefaultProfessionalId($businessId);
        }

        if (!$this->professionalCanDoService($businessId, $professionalId, $serviceId)) {
            $this->redirectEditWithError($appointmentId, 'El profesional seleccionado no está asignado a este servicio.');
        }

        if ($date === '' || $time === '') {
            $this->redirectEditWithError($appointmentId, 'Seleccioná fecha y hora.');
        }

        $allowedStatuses = [
            'pending_approval',
            'pending_deposit',
            'approved',
            'confirmed',
            'completed',
            'cancelled',
            'rescheduled',
            'no_show',
            'consultation'
        ];

        if (!in_array($status, $allowedStatuses, true)) {
            $this->redirectEditWithError($appointmentId, 'Estado inválido.');
        }

        if ($status === 'confirmed' && $appointment['status'] !== 'confirmed') {
            $this->redirectEditWithError($appointmentId, 'Confirmá el envío desde la acción de WhatsApp.');
        }

        $allowedOrigins = [
            'whatsapp',
            'instagram',
            'facebook',
            'web',
            'manual'
        ];

        if (!in_array($originChannel, $allowedOrigins, true)) {
            $originChannel = 'manual';
        }

        $service = $this->getService($businessId, $serviceId);

        if (!$service) {
            $this->redirectEditWithError($appointmentId, 'El servicio seleccionado no existe.');
        }

        $startAt = $date . ' ' . $time . ':00';

        if (strtotime($startAt) === false) {
            $this->redirectEditWithError($appointmentId, 'La fecha u hora no es válida.');
        }

        $duration = (int) $service['duration_default'];

        if ($duration <= 0) {
            $duration = 60;
        }

        $endAt = date('Y-m-d H:i:s', strtotime($startAt . " + {$duration} minutes"));

        if ($status !== 'cancelled') {
            $availability = new Availability();

            $availableSlots = $availability->getAvailableSlots(
                $businessId,
                $serviceId,
                $date,
                $appointmentId,
                $professionalId
            );

            $slotAvailable = false;

            foreach ($availableSlots as $slot) {
                if ($slot['time'] === substr($time, 0, 5)) {
                    $slotAvailable = true;
                    break;
                }
            }

            if (!$slotAvailable) {
                $this->redirectEditWithError($appointmentId, 'Ese horario ya no está disponible para el profesional seleccionado.');
            }
        }

        try {
            $this->db->beginTransaction();

            $lockSql = "
                SELECT status, cancelled_at, customer_id, start_at FROM appointments
                WHERE id = ? AND business_id = ?
            ";
            $lockParams = [$appointmentId, $businessId];

            if ($scopedProfessionalId !== null) {
                $lockSql .= " AND professional_id = ?";
                $lockParams[] = $scopedProfessionalId;
            }

            $lockSql .= " FOR UPDATE";

            $lock = $this->db->prepare($lockSql);
            $lock->execute($lockParams);
            $current = $lock->fetch();

            if (!$current || $current['status'] !== $appointment['status']) {
                throw new RuntimeException('El estado del turno cambió. Recargá el formulario.');
            }

            $currentStatus = (string) $current['status'];
            $currentStartTimestamp = strtotime((string) $current['start_at']);
            $requestedStartTimestamp = strtotime($startAt);
            $scheduleChanged = $currentStartTimestamp === false
                || $requestedStartTimestamp === false
                || date('Y-m-d H:i', $currentStartTimestamp) !== date('Y-m-d H:i', $requestedStartTimestamp);

            if ($currentStatus === 'confirmed' && $status === 'confirmed' && $scheduleChanged) {
                $status = 'approved';
            }

            if (!$this->professionalCanDoService($businessId, $professionalId, $serviceId)) {
                throw new RuntimeException('El profesional ya no está asignado a este servicio.');
            }

            if (
                $status === 'cancelled'
                && $currentStatus !== 'cancelled'
                && !$this->canCancelAppointmentStatus($currentStatus)
            ) {
                throw new RuntimeException('Los turnos completados o marcados como no asistió no pueden cancelarse.');
            }

            if ($scopedProfessionalId !== null) {
                $customerId = (int) $current['customer_id'];

                if ($customerId <= 0) {
                    throw new RuntimeException('El turno no tiene una clienta válida asociada.');
                }
            } else {
                $customerId = $this->findOrCreateCustomer(
                    $businessId,
                    $customerName,
                    $customerPhone
                );
            }

            $depositRequired = (int) $service['requires_deposit'];
            $depositStatus = $depositRequired ? ($_POST['deposit_status'] ?? 'pending') : 'not_required';
            $depositAmount = $service['deposit_amount'] ?? null;
            $totalAmount = $service['price'] ?? null;

            $allowedDepositStatuses = ['not_required', 'pending', 'paid'];

            if (!in_array($depositStatus, $allowedDepositStatuses, true)) {
                $depositStatus = $depositRequired ? 'pending' : 'not_required';
            }

            $cancelledAt = null;

            if ($status === 'cancelled') {
                $cancelledAt = $currentStatus === 'cancelled'
                    ? $current['cancelled_at']
                    : date('Y-m-d H:i:s');
            }

            $updateSql = "
                UPDATE appointments
                SET
                    customer_id = ?,
                    service_id = ?,
                    professional_id = ?,
                    start_at = ?,
                    end_at = ?,
                    status = ?,
                    origin_channel = ?,
                    total_amount = ?,
                    deposit_required = ?,
                    deposit_status = ?,
                    deposit_amount = ?,
                    customer_notes = ?,
                    internal_notes = ?,
                    cancelled_at = ?
                WHERE id = ?
                  AND business_id = ?
            ";

            $updateParams = [
                $customerId,
                $serviceId,
                $professionalId,
                $startAt,
                $endAt,
                $status,
                $originChannel,
                $totalAmount,
                $depositRequired,
                $depositStatus,
                $depositAmount,
                $customerNotes,
                $internalNotes,
                $cancelledAt,
                $appointmentId,
                $businessId
            ];

            if ($scopedProfessionalId !== null) {
                $updateSql .= " AND professional_id = ?";
                $updateParams[] = $scopedProfessionalId;
            }

            $stmt = $this->db->prepare($updateSql);
            $stmt->execute($updateParams);

            $this->db->commit();

            $_SESSION['flash_success'] = 'Turno actualizado correctamente.';

            header('Location: ' . APP_URL . '/appointments/edit?id=' . $appointmentId);
            exit;

        } catch (Exception $e) {
            $this->db->rollBack();

            $this->redirectEditWithError($appointmentId, 'Error al actualizar el turno: ' . $e->getMessage());
        }
    }

    public function services(): void
    {
        Auth::requireLogin();

        $businessId = $this->getCurrentBusinessId();
        $business = $this->getBusiness($businessId);
        $services = $this->getServicesForAdmin($businessId);

        Auth::startSession();

        $error = $_SESSION['flash_error'] ?? null;
        $success = $_SESSION['flash_success'] ?? null;

        unset($_SESSION['flash_error'], $_SESSION['flash_success']);

        require __DIR__ . '/../views/admin/services.php';
    }

    public function createService(): void
    {
        Auth::requireLogin();

        $businessId = $this->getCurrentBusinessId();
        $business = $this->getBusiness($businessId);
        $categories = $this->getServiceCategories($businessId);

        Auth::startSession();

        $error = $_SESSION['flash_error'] ?? null;
        $old = $_SESSION['flash_old'] ?? [];

        unset($_SESSION['flash_error'], $_SESSION['flash_old']);

        require __DIR__ . '/../views/admin/service_create.php';
    }

    public function storeService(): void
    {
        Auth::requireLogin();
        Auth::startSession();

        $csrfToken = $_POST['csrf_token'] ?? null;

        if (!Auth::validateCsrfToken($csrfToken)) {
            $_SESSION['flash_error'] = 'La sesión del formulario venció. Volvé a intentarlo.';
            header('Location: ' . APP_URL . '/services/create');
            exit;
        }

        $businessId = $this->getCurrentBusinessId();

        $_SESSION['flash_old'] = $_POST;

        $data = $this->validateServiceData($_POST);

        if (!$data['valid']) {
            $_SESSION['flash_error'] = $data['error'];
            header('Location: ' . APP_URL . '/services/create');
            exit;
        }

        $service = $data['service'];

        try {
            $stmt = $this->db->prepare("
                INSERT INTO services
                (
                    business_id,
                    category_id,
                    name,
                    description,
                    price_type,
                    price,
                    price_label,
                    duration_type,
                    duration_min,
                    duration_default,
                    duration_max,
                    capacity_per_slot,
                    blocks_full_schedule,
                    requires_deposit,
                    deposit_type,
                    deposit_amount,
                    requires_approval,
                    is_bookable,
                    is_active,
                    sort_order
                )
                VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $businessId,
                $service['category_id'],
                $service['name'],
                $service['description'],
                $service['price_type'],
                $service['price'],
                $service['price_label'],
                $service['duration_type'],
                $service['duration_min'],
                $service['duration_default'],
                $service['duration_max'],
                $service['capacity_per_slot'],
                $service['blocks_full_schedule'],
                $service['requires_deposit'],
                $service['deposit_type'],
                $service['deposit_amount'],
                $service['requires_approval'],
                $service['is_bookable'],
                $service['is_active'],
                $service['sort_order']
            ]);

            unset($_SESSION['flash_old']);

            $_SESSION['flash_success'] = 'Servicio creado correctamente.';

            header('Location: ' . APP_URL . '/services');
            exit;

        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Error al crear el servicio: ' . $e->getMessage();
            header('Location: ' . APP_URL . '/services/create');
            exit;
        }
    }

    public function editService(): void
    {
        Auth::requireLogin();

        $businessId = $this->getCurrentBusinessId();
        $business = $this->getBusiness($businessId);

        $serviceId = (int) ($_GET['id'] ?? 0);

        if ($serviceId <= 0) {
            die('ID de servicio inválido.');
        }

        $service = $this->getService($businessId, $serviceId);

        if (!$service) {
            die('No se encontró el servicio.');
        }

        $categories = $this->getServiceCategories($businessId);

        Auth::startSession();

        $error = $_SESSION['flash_error'] ?? null;
        $success = $_SESSION['flash_success'] ?? null;

        unset($_SESSION['flash_error'], $_SESSION['flash_success']);

        require __DIR__ . '/../views/admin/service_edit.php';
    }

    public function updateService(): void
    {
        Auth::requireLogin();
        Auth::startSession();

        $csrfToken = $_POST['csrf_token'] ?? null;

        if (!Auth::validateCsrfToken($csrfToken)) {
            $serviceId = (int) ($_POST['service_id'] ?? 0);

            if ($serviceId > 0) {
                $_SESSION['flash_error'] = 'La sesión del formulario venció. Volvé a intentarlo.';
                header('Location: ' . APP_URL . '/services/edit?id=' . $serviceId);
                exit;
            }

            die('Token CSRF inválido.');
        }

        $businessId = $this->getCurrentBusinessId();

        $serviceId = (int) ($_POST['service_id'] ?? 0);

        if ($serviceId <= 0) {
            die('ID de servicio inválido.');
        }

        $existingService = $this->getService($businessId, $serviceId);

        if (!$existingService) {
            die('No se encontró el servicio.');
        }

        $data = $this->validateServiceData($_POST);

        if (!$data['valid']) {
            $_SESSION['flash_error'] = $data['error'];
            header('Location: ' . APP_URL . '/services/edit?id=' . $serviceId);
            exit;
        }

        $service = $data['service'];

        try {
            $stmt = $this->db->prepare("
                UPDATE services
                SET
                    category_id = ?,
                    name = ?,
                    description = ?,
                    price_type = ?,
                    price = ?,
                    price_label = ?,
                    duration_type = ?,
                    duration_min = ?,
                    duration_default = ?,
                    duration_max = ?,
                    capacity_per_slot = ?,
                    blocks_full_schedule = ?,
                    requires_deposit = ?,
                    deposit_type = ?,
                    deposit_amount = ?,
                    requires_approval = ?,
                    is_bookable = ?,
                    is_active = ?,
                    sort_order = ?
                WHERE id = ?
                  AND business_id = ?
            ");

            $stmt->execute([
                $service['category_id'],
                $service['name'],
                $service['description'],
                $service['price_type'],
                $service['price'],
                $service['price_label'],
                $service['duration_type'],
                $service['duration_min'],
                $service['duration_default'],
                $service['duration_max'],
                $service['capacity_per_slot'],
                $service['blocks_full_schedule'],
                $service['requires_deposit'],
                $service['deposit_type'],
                $service['deposit_amount'],
                $service['requires_approval'],
                $service['is_bookable'],
                $service['is_active'],
                $service['sort_order'],
                $serviceId,
                $businessId
            ]);

            $_SESSION['flash_success'] = 'Servicio actualizado correctamente.';

            header('Location: ' . APP_URL . '/services/edit?id=' . $serviceId);
            exit;

        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Error al actualizar el servicio: ' . $e->getMessage();
            header('Location: ' . APP_URL . '/services/edit?id=' . $serviceId);
            exit;
        }
    }

    public function schedule(): void
    {
        Auth::requireScheduleAccess();
        Auth::startSession();

        $businessId = $this->getCurrentBusinessId();
        $business = $this->getBusiness($businessId);
        $scheduleProfessionalId = $this->resolveScheduleProfessionalId(
            $businessId,
            $_GET['professional_id'] ?? null
        );
        $canSelectScheduleScope = Auth::isAdministrator();
        $scheduleProfessionals = $canSelectScheduleScope
            ? $this->getScheduleProfessionals($businessId)
            : [];
        $scheduleProfessional = $scheduleProfessionalId !== null
            ? $this->getScheduleProfessional($businessId, $scheduleProfessionalId)
            : null;

        $businessHours = $this->getBusinessHoursMap($businessId, $scheduleProfessionalId);
        $exceptions = $this->getScheduleExceptions($businessId, $scheduleProfessionalId);

        $error = $_SESSION['flash_error'] ?? null;
        $success = $_SESSION['flash_success'] ?? null;

        unset($_SESSION['flash_error'], $_SESSION['flash_success']);

        require __DIR__ . '/../views/admin/schedule.php';
    }

    public function updateBusinessHours(): void
    {
        Auth::requireScheduleAccess();
        Auth::startSession();

        $csrfToken = $_POST['csrf_token'] ?? null;

        if (!Auth::validateCsrfToken($csrfToken)) {
            $_SESSION['flash_error'] = 'La sesión del formulario venció. Volvé a intentarlo.';
            header('Location: ' . APP_URL . '/schedule');
            exit;
        }

        $businessId = $this->getCurrentBusinessId();
        $scheduleProfessionalId = $this->resolveScheduleProfessionalId(
            $businessId,
            $_POST['professional_id'] ?? null
        );
        $redirectUrl = $this->getScheduleUrl($scheduleProfessionalId);
        $hours = $_POST['hours'] ?? [];

        try {
            if (!is_array($hours)) {
                throw new RuntimeException('Los horarios enviados no son válidos.');
            }

            $preparedHours = [];

            foreach (range(0, 6) as $weekday) {
                $row = $hours[$weekday] ?? [];

                if (!is_array($row)) {
                    throw new RuntimeException('Los horarios enviados no son válidos.');
                }

                $mode = $scheduleProfessionalId !== null
                    ? (string) ($row['mode'] ?? '')
                    : 'custom';

                if ($scheduleProfessionalId !== null && $mode === 'inherit') {
                    $preparedHours[$weekday] = ['mode' => 'inherit'];
                    continue;
                }

                if ($mode !== 'custom') {
                    throw new RuntimeException('El modo de horario seleccionado no es válido.');
                }

                $isActive = isset($row['is_active']) ? 1 : 0;

                $startTime = $this->normalizeTime($row['start_time'] ?? '09:00');
                $endTime = $this->normalizeTime($row['end_time'] ?? '18:00');
                $breakStart = $this->normalizeTime($row['break_start'] ?? '');
                $breakEnd = $this->normalizeTime($row['break_end'] ?? '');

                if ($isActive) {
                    if (!$startTime || !$endTime) {
                        throw new Exception('Completá horario de inicio y cierre en los días activos.');
                    }

                    if (!$this->isTimeBefore($startTime, $endTime)) {
                        throw new Exception('El horario de inicio debe ser anterior al horario de cierre.');
                    }

                    if (($breakStart && !$breakEnd) || (!$breakStart && $breakEnd)) {
                        throw new Exception('Si cargás una pausa, completá inicio y fin de pausa.');
                    }

                    if ($breakStart && $breakEnd) {
                        if (!$this->isTimeBefore($breakStart, $breakEnd)) {
                            throw new Exception('El inicio de pausa debe ser anterior al fin de pausa.');
                        }

                        if (
                            !$this->isTimeBeforeOrEqual($startTime, $breakStart) ||
                            !$this->isTimeBeforeOrEqual($breakEnd, $endTime)
                        ) {
                            throw new Exception('La pausa debe estar dentro del horario laboral.');
                        }
                    }
                }

                if (!$startTime) {
                    $startTime = '09:00:00';
                }

                if (!$endTime) {
                    $endTime = '18:00:00';
                }

                $preparedHours[$weekday] = [
                    'mode' => 'custom',
                    'is_active' => $isActive,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'break_start' => $breakStart,
                    'break_end' => $breakEnd,
                ];
            }

            $this->db->beginTransaction();
            $this->lockScheduleScope($businessId, $scheduleProfessionalId);

            foreach ($preparedHours as $weekday => $row) {
                if ($row['mode'] === 'inherit') {
                    $delete = $this->db->prepare("
                        DELETE FROM business_hours
                        WHERE business_id = ?
                          AND professional_id = ?
                          AND weekday = ?
                    ");
                    $delete->execute([$businessId, $scheduleProfessionalId, $weekday]);
                    continue;
                }

                if ($scheduleProfessionalId === null) {
                    $existing = $this->db->prepare("
                        SELECT id
                        FROM business_hours
                        WHERE business_id = ?
                          AND professional_id IS NULL
                          AND weekday = ?
                        ORDER BY id DESC
                        LIMIT 1
                        FOR UPDATE
                    ");
                    $existing->execute([$businessId, $weekday]);
                } else {
                    $existing = $this->db->prepare("
                        SELECT id
                        FROM business_hours
                        WHERE business_id = ?
                          AND professional_id = ?
                          AND weekday = ?
                        ORDER BY id DESC
                        LIMIT 1
                        FOR UPDATE
                    ");
                    $existing->execute([$businessId, $scheduleProfessionalId, $weekday]);
                }

                $businessHour = $existing->fetch();

                if ($businessHour) {
                    $scopeCondition = $scheduleProfessionalId === null
                        ? 'AND professional_id IS NULL'
                        : 'AND professional_id = ?';
                    $stmt = $this->db->prepare("
                        UPDATE business_hours
                        SET
                            start_time = ?,
                            end_time = ?,
                            break_start = ?,
                            break_end = ?,
                            is_active = ?
                        WHERE id = ?
                          AND business_id = ?
                          {$scopeCondition}
                    ");

                    $params = [
                        $row['start_time'],
                        $row['end_time'],
                        $row['break_start'],
                        $row['break_end'],
                        $row['is_active'],
                        $businessHour['id'],
                        $businessId,
                    ];

                    if ($scheduleProfessionalId !== null) {
                        $params[] = $scheduleProfessionalId;
                    }

                    $stmt->execute($params);
                } else {
                    $stmt = $this->db->prepare("
                        INSERT INTO business_hours
                        (
                            business_id,
                            professional_id,
                            weekday,
                            start_time,
                            end_time,
                            break_start,
                            break_end,
                            is_active
                        )
                        VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $stmt->execute([
                        $businessId,
                        $scheduleProfessionalId,
                        $weekday,
                        $row['start_time'],
                        $row['end_time'],
                        $row['break_start'],
                        $row['break_end'],
                        $row['is_active'],
                    ]);
                }
            }

            $this->db->commit();
            $_SESSION['flash_success'] = 'Horarios laborales actualizados correctamente.';

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: ' . $redirectUrl);
        exit;
    }

    public function storeScheduleException(): void
    {
        Auth::requireScheduleAccess();
        Auth::startSession();

        $csrfToken = $_POST['csrf_token'] ?? null;

        if (!Auth::validateCsrfToken($csrfToken)) {
            $_SESSION['flash_error'] = 'La sesión del formulario venció. Volvé a intentarlo.';
            header('Location: ' . APP_URL . '/schedule');
            exit;
        }

        $businessId = $this->getCurrentBusinessId();
        $scheduleProfessionalId = $this->resolveScheduleProfessionalId(
            $businessId,
            $_POST['professional_id'] ?? null
        );
        $redirectUrl = $this->getScheduleUrl($scheduleProfessionalId);

        $date = trim($_POST['date'] ?? '');
        $type = trim($_POST['type'] ?? '');
        $startTime = $this->normalizeTime($_POST['start_time'] ?? '');
        $endTime = $this->normalizeTime($_POST['end_time'] ?? '');
        $reason = trim($_POST['reason'] ?? '');

        try {
            if ($date === '') {
                throw new Exception('Seleccioná una fecha.');
            }

            $dateObject = DateTime::createFromFormat('!Y-m-d', $date);
            $dateErrors = DateTime::getLastErrors();

            if (
                !$dateObject
                || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
                || $dateObject->format('Y-m-d') !== $date
            ) {
                throw new RuntimeException('La fecha seleccionada no es válida.');
            }

            if (!in_array($type, ['blocked_day', 'blocked_range', 'extra_hours'], true)) {
                throw new Exception('Tipo de excepción inválido.');
            }

            if ($type === 'blocked_day') {
                $startTime = null;
                $endTime = null;
            }

            if ($type === 'blocked_range' || $type === 'extra_hours') {
                if (!$startTime || !$endTime) {
                    throw new Exception('Para rangos horarios, completá hora desde y hora hasta.');
                }

                if (!$this->isTimeBefore($startTime, $endTime)) {
                    throw new Exception('La hora desde debe ser anterior a la hora hasta.');
                }
            }

            $this->db->beginTransaction();
            $this->lockScheduleScope($businessId, $scheduleProfessionalId);

            $stmt = $this->db->prepare("
                INSERT INTO schedule_exceptions
                (
                    business_id,
                    professional_id,
                    date,
                    start_time,
                    end_time,
                    type,
                    reason
                )
                VALUES
                (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $businessId,
                $scheduleProfessionalId,
                $date,
                $startTime,
                $endTime,
                $type,
                $reason !== '' ? $reason : null
            ]);

            $this->db->commit();
            $_SESSION['flash_success'] = 'Bloqueo o excepción creada correctamente.';

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: ' . $redirectUrl);
        exit;
    }

    public function deleteScheduleException(): void
    {
        Auth::requireScheduleAccess();
        Auth::startSession();

        $csrfToken = $_POST['csrf_token'] ?? null;

        if (!Auth::validateCsrfToken($csrfToken)) {
            $_SESSION['flash_error'] = 'La sesión del formulario venció. Volvé a intentarlo.';
            header('Location: ' . APP_URL . '/schedule');
            exit;
        }

        $businessId = $this->getCurrentBusinessId();
        $scheduleProfessionalId = $this->resolveScheduleProfessionalId(
            $businessId,
            $_POST['professional_id'] ?? null
        );
        $redirectUrl = $this->getScheduleUrl($scheduleProfessionalId);
        $exceptionId = (int) ($_POST['exception_id'] ?? 0);

        if ($exceptionId <= 0) {
            $_SESSION['flash_error'] = 'ID de bloqueo inválido.';
            header('Location: ' . $redirectUrl);
            exit;
        }

        try {
            $this->db->beginTransaction();
            $this->lockScheduleScope($businessId, $scheduleProfessionalId);

            if ($scheduleProfessionalId === null) {
                $existing = $this->db->prepare("
                    SELECT id
                    FROM schedule_exceptions
                    WHERE id = ?
                      AND business_id = ?
                      AND professional_id IS NULL
                    FOR UPDATE
                ");
                $existing->execute([$exceptionId, $businessId]);
            } else {
                $existing = $this->db->prepare("
                    SELECT id
                    FROM schedule_exceptions
                    WHERE id = ?
                      AND business_id = ?
                      AND professional_id = ?
                    FOR UPDATE
                ");
                $existing->execute([$exceptionId, $businessId, $scheduleProfessionalId]);
            }

            if (!$existing->fetch()) {
                throw new RuntimeException('El bloqueo no existe o no pertenece al alcance seleccionado.');
            }

            $scopeCondition = $scheduleProfessionalId === null
                ? 'AND professional_id IS NULL'
                : 'AND professional_id = ?';
            $stmt = $this->db->prepare("
                DELETE FROM schedule_exceptions
                WHERE id = ?
                  AND business_id = ?
                  {$scopeCondition}
            ");

            $params = [$exceptionId, $businessId];

            if ($scheduleProfessionalId !== null) {
                $params[] = $scheduleProfessionalId;
            }

            $stmt->execute($params);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('No se pudo eliminar el bloqueo seleccionado.');
            }

            $this->db->commit();
            $_SESSION['flash_success'] = 'Bloqueo eliminado correctamente.';
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: ' . $redirectUrl);
        exit;
    }

    public function appointmentsToday(): void
    {
        $this->renderAppointmentsList(
            'today',
            'Turnos de hoy',
            'Todos los turnos programados para hoy.'
        );
    }

    public function appointmentsWeek(): void
    {
        $this->renderAppointmentsList(
            'week',
            'Turnos de esta semana',
            'Todos los turnos programados para la semana actual.'
        );
    }

    public function appointmentsPendingApproval(): void
    {
        $this->renderAppointmentsList(
            'pending_approval',
            'Pendientes de aprobación',
            'Turnos que todavía requieren aprobación interna.'
        );
    }

    public function appointmentsPendingDeposit(): void
    {
        $this->renderAppointmentsList(
            'pending_deposit',
            'Pendientes de seña',
            'Turnos que están pendientes de confirmación por seña.'
        );
    }

    public function approveAppointment(): void
    {
        Auth::requireLogin();
        Auth::startSession();

        $csrfToken = $_POST['csrf_token'] ?? null;

        if (!is_string($csrfToken) || !Auth::validateCsrfToken($csrfToken)) {
            $_SESSION['flash_error'] = 'La sesión del formulario venció. Volvé a intentarlo.';
            header('Location: ' . APP_URL . '/appointments/pending-approval');
            exit;
        }

        $businessId = $this->getCurrentBusinessId();
        $professionalId = $this->getCurrentProfessionalId();
        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);

        if ($appointmentId <= 0) {
            $_SESSION['flash_error'] = 'ID de turno inválido.';
            header('Location: ' . APP_URL . '/appointments/pending-approval');
            exit;
        }

        try {
            $this->db->beginTransaction();

            $lockSql = "
                SELECT status
                FROM appointments
                WHERE id = ?
                  AND business_id = ?
            ";
            $lockParams = [$appointmentId, $businessId];

            if ($professionalId !== null) {
                $lockSql .= " AND professional_id = ?";
                $lockParams[] = $professionalId;
            }

            $lockSql .= " FOR UPDATE";

            $lock = $this->db->prepare($lockSql);
            $lock->execute($lockParams);
            $current = $lock->fetch();

            if (!$current || $current['status'] !== 'pending_approval') {
                throw new RuntimeException('El turno ya no está pendiente de aprobación.');
            }

            $updateSql = "
                UPDATE appointments
                SET status = 'approved'
                WHERE id = ?
                  AND business_id = ?
                  AND status = 'pending_approval'
            ";
            $updateParams = [$appointmentId, $businessId];

            if ($professionalId !== null) {
                $updateSql .= " AND professional_id = ?";
                $updateParams[] = $professionalId;
            }

            $stmt = $this->db->prepare($updateSql);
            $stmt->execute($updateParams);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('El estado del turno cambió durante la aprobación.');
            }

            $this->db->commit();
            $_SESSION['flash_success'] = 'Turno aprobado. Ya podés preparar la confirmación por WhatsApp.';
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            $_SESSION['flash_error'] = 'No se pudo aprobar el turno.';
        }

        header('Location: ' . APP_URL . '/appointments/pending-approval');
        exit;
    }

    public function appointmentsApproved(): void
    {
        $this->renderAppointmentsList(
            'approved',
            'Pendientes de confirmación',
            'Turnos aprobados que esperan confirmación por WhatsApp.'
        );
    }

    public function appointmentsConfirmed(): void
    {
        $this->renderAppointmentsList(
            'confirmed',
            'Turnos confirmados',
            'Turnos cuyo envío por WhatsApp se registró manualmente.'
        );
    }

    public function appointmentsRescheduled(): void
    {
        $this->renderAppointmentsList(
            'rescheduled',
            'Turnos reprogramados',
            'Todos los turnos reprogramados.'
        );
    }

    public function appointmentsConsultation(): void
    {
        $this->renderAppointmentsList(
            'consultation',
            'Turnos en consulta',
            'Turnos registrados como consulta.'
        );
    }

    private function renderAppointmentsList(string $type, string $title, string $description): void
    {
        Auth::requireLogin();
        Auth::startSession();

        $businessId = $this->getCurrentBusinessId();
        $business = $this->getBusiness($businessId);
        $appointments = $this->getAppointmentsList(
            $businessId,
            $type,
            $this->getCurrentProfessionalId()
        );

        $error = $_SESSION['flash_error'] ?? null;
        $success = $_SESSION['flash_success'] ?? null;

        unset($_SESSION['flash_error'], $_SESSION['flash_success']);

        require __DIR__ . '/../views/admin/appointments_list.php';
    }

    private function getCurrentBusinessId(): int
    {
        $businessId = Auth::businessId();

        if ($businessId === null) {
            http_response_code(403);
            echo 'Acceso no permitido.';
            exit;
        }

        return $businessId;
    }

    private function getCurrentProfessionalId(): ?int
    {
        if (!Auth::isProfessional()) {
            return null;
        }

        $professionalId = Auth::professionalId();

        if ($professionalId === null) {
            http_response_code(403);
            echo 'Acceso no permitido.';
            exit;
        }

        return $professionalId;
    }

    private function getBusiness(int $businessId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM businesses
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$businessId]);
        $business = $stmt->fetch();

        if (!$business) {
            die('No se encontró el negocio.');
        }

        return $business;
    }

    private function getDashboardStats(int $businessId, ?int $professionalId = null): array
    {
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');

        $weekStart = date('Y-m-d 00:00:00', strtotime('monday this week'));
        $weekEnd = date('Y-m-d 23:59:59', strtotime('sunday this week'));

        return [
            'today' => $this->countAppointments($businessId, $todayStart, $todayEnd, $professionalId),
            'week' => $this->countAppointments($businessId, $weekStart, $weekEnd, $professionalId),
            'pending_approval' => $this->countByStatus($businessId, 'pending_approval', $professionalId),
            'pending_deposit' => $this->countPendingDeposit($businessId, $professionalId),
            'approved' => $this->countByStatus($businessId, 'approved', $professionalId),
            'confirmed' => $this->countByStatus($businessId, 'confirmed', $professionalId),
        ];
    }

    private function countAppointments(
        int $businessId,
        string $start,
        string $end,
        ?int $professionalId = null
    ): int
    {
        $professionalScope = $professionalId !== null ? ' AND professional_id = ?' : '';

        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS total
            FROM appointments
            WHERE business_id = ?
              AND start_at BETWEEN ? AND ?
              AND status NOT IN ('cancelled')
              {$professionalScope}
        ");

        $params = [$businessId, $start, $end];

        if ($professionalId !== null) {
            $params[] = $professionalId;
        }

        $stmt->execute($params);
        $row = $stmt->fetch();

        return (int) $row['total'];
    }

    private function countByStatus(
        int $businessId,
        string $status,
        ?int $professionalId = null
    ): int
    {
        $professionalScope = $professionalId !== null ? ' AND professional_id = ?' : '';

        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS total
            FROM appointments
            WHERE business_id = ?
              AND status = ?
              {$professionalScope}
        ");

        $params = [$businessId, $status];

        if ($professionalId !== null) {
            $params[] = $professionalId;
        }

        $stmt->execute($params);
        $row = $stmt->fetch();

        return (int) $row['total'];
    }

    private function countPendingDeposit(int $businessId, ?int $professionalId = null): int
    {
        $professionalScope = $professionalId !== null ? ' AND professional_id = ?' : '';

        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS total
            FROM appointments
            WHERE business_id = ?
              AND status NOT IN ('cancelled', 'completed')
              AND (
                status = 'pending_deposit'
                OR deposit_status = 'pending'
              )
              {$professionalScope}
        ");

        $params = [$businessId];

        if ($professionalId !== null) {
            $params[] = $professionalId;
        }

        $stmt->execute($params);
        $row = $stmt->fetch();

        return (int) $row['total'];
    }

    private function getTodayAppointments(int $businessId, ?int $professionalId = null): array
    {
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');

        $professionalScope = $professionalId !== null ? ' AND a.professional_id = ?' : '';

        $stmt = $this->db->prepare("
            SELECT
                a.id,
                a.start_at,
                a.end_at,
                a.status,
                a.origin_channel,
                a.deposit_status,
                c.name AS customer_name,
                c.phone AS customer_phone,
                s.name AS service_name
            FROM appointments a
            INNER JOIN customers c ON c.id = a.customer_id AND c.business_id = a.business_id
            INNER JOIN services s ON s.id = a.service_id
            WHERE a.business_id = ?
              AND a.start_at BETWEEN ? AND ?
              AND a.status NOT IN ('cancelled')
              {$professionalScope}
            ORDER BY a.start_at ASC
        ");

        $params = [$businessId, $todayStart, $todayEnd];

        if ($professionalId !== null) {
            $params[] = $professionalId;
        }

        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    private function getUpcomingAppointments(int $businessId, ?int $professionalId = null): array
    {
        $now = date('Y-m-d H:i:s');

        $professionalScope = $professionalId !== null ? ' AND a.professional_id = ?' : '';

        $stmt = $this->db->prepare("
            SELECT
                a.id,
                a.start_at,
                a.end_at,
                a.status,
                a.origin_channel,
                a.deposit_status,
                c.name AS customer_name,
                c.phone AS customer_phone,
                s.name AS service_name
            FROM appointments a
            INNER JOIN customers c ON c.id = a.customer_id AND c.business_id = a.business_id
            INNER JOIN services s ON s.id = a.service_id
            WHERE a.business_id = ?
              AND a.start_at >= ?
              AND a.status NOT IN ('cancelled', 'completed')
              {$professionalScope}
            ORDER BY a.start_at ASC
            LIMIT 8
        ");

        $params = [$businessId, $now];

        if ($professionalId !== null) {
            $params[] = $professionalId;
        }

        $stmt->execute($params);

        return $stmt->fetchAll();
    }
    private function getActiveServices(int $businessId, ?int $professionalId = null): array
    {
        $professionalScope = $professionalId !== null
            ? "
              AND EXISTS (
                  SELECT 1
                  FROM service_professionals sp
                  WHERE sp.business_id = s.business_id
                    AND sp.service_id = s.id
                    AND sp.professional_id = ?
                    AND sp.is_active = 1
              )
            "
            : '';

        $stmt = $this->db->prepare("
            SELECT
                s.id,
                s.name,
                s.price_type,
                s.price,
                s.price_label,
                s.duration_default,
                s.requires_deposit,
                s.requires_approval,
                s.is_bookable,
                c.name AS category_name
            FROM services s
            LEFT JOIN service_categories c ON c.id = s.category_id
            WHERE s.business_id = ?
              AND s.is_active = 1
              {$professionalScope}
            ORDER BY c.sort_order ASC, s.sort_order ASC, s.name ASC
        ");

        $params = [$businessId];

        if ($professionalId !== null) {
            $params[] = $professionalId;
        }

        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    private function getService(int $businessId, int $serviceId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM services
            WHERE business_id = ?
              AND id = ?
              AND is_active = 1
            LIMIT 1
        ");

        $stmt->execute([$businessId, $serviceId]);

        $service = $stmt->fetch();

        return $service ?: null;
    }

    private function hasOverlappingAppointment(int $businessId, string $startAt, string $endAt): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS total
            FROM appointments
            WHERE business_id = ?
              AND status NOT IN ('cancelled')
              AND start_at < ?
              AND end_at > ?
        ");

        $stmt->execute([
            $businessId,
            $endAt,
            $startAt
        ]);

        $row = $stmt->fetch();

        return (int) $row['total'] > 0;
    }

    private function findOrCreateCustomer(
        int $businessId,
        string $name,
        string $phone,
        bool $updateExisting = true
    ): int
    {
        $stmt = $this->db->prepare("
            SELECT id
            FROM customers
            WHERE business_id = ?
              AND phone = ?
            LIMIT 1
        ");

        $stmt->execute([
            $businessId,
            $phone
        ]);

        $customer = $stmt->fetch();

        if ($customer && $updateExisting) {
            $update = $this->db->prepare("
                UPDATE customers
                SET name = ?, updated_at = NOW()
                WHERE id = ?
                  AND business_id = ?
            ");

            $update->execute([
                $name,
                $customer['id'],
                $businessId
            ]);
        }

        if ($customer) {
            return (int) $customer['id'];
        }

        $insert = $this->db->prepare("
            INSERT INTO customers
            (business_id, name, phone, is_returning_customer)
            VALUES
            (?, ?, ?, 0)
        ");

        $insert->execute([
            $businessId,
            $name,
            $phone
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function redirectWithError(string $message): void
    {
        $_SESSION['flash_error'] = $message;

        header('Location: ' . APP_URL . '/appointments/create');
        exit;
    }

    private function redirectAppointmentCreateWithError(string $resultId, string $message): void
    {
        $this->storeAppointmentCreateResult($resultId, [
            'error' => $message,
            'old' => $this->getAppointmentCreateOldInput(),
        ]);

        header('Location: ' . APP_URL . '/appointments/create?result=' . rawurlencode($resultId));
        exit;
    }

    private function storeAppointmentCreateResult(string $resultId, array $result): void
    {
        $this->pruneAppointmentCreateResults();

        $results = $_SESSION['appointment_create_results'] ?? [];
        $result['created_at'] = time();
        $results[$resultId] = $result;

        uasort($results, static function (array $left, array $right): int {
            return (int) ($left['created_at'] ?? 0) <=> (int) ($right['created_at'] ?? 0);
        });

        while (count($results) > 20) {
            $oldestResultId = array_key_first($results);

            if ($oldestResultId === null) {
                break;
            }

            unset($results[$oldestResultId]);
        }

        $_SESSION['appointment_create_results'] = $results;
    }

    private function consumeAppointmentCreateResult($resultId): array
    {
        if (!is_string($resultId) || preg_match('/\A[a-f0-9]{32}\z/', $resultId) !== 1) {
            return [];
        }

        $this->pruneAppointmentCreateResults();
        $results = $_SESSION['appointment_create_results'] ?? [];
        $result = $results[$resultId] ?? [];

        unset($results[$resultId]);

        if (empty($results)) {
            unset($_SESSION['appointment_create_results']);
        } else {
            $_SESSION['appointment_create_results'] = $results;
        }

        if (!is_array($result)) {
            return [];
        }

        unset($result['created_at']);

        return $result;
    }

    private function pruneAppointmentCreateResults(): void
    {
        $results = $_SESSION['appointment_create_results'] ?? [];

        if (!is_array($results)) {
            unset($_SESSION['appointment_create_results']);
            return;
        }

        $expiresBefore = time() - 900;

        foreach ($results as $resultId => $result) {
            if (!is_array($result) || (int) ($result['created_at'] ?? 0) < $expiresBefore) {
                unset($results[$resultId]);
            }
        }

        if (empty($results)) {
            unset($_SESSION['appointment_create_results']);
            return;
        }

        $_SESSION['appointment_create_results'] = $results;
    }

    private function getAppointmentCreateOldInput(): array
    {
        $fields = [
            'customer_name',
            'customer_phone',
            'service_id',
            'professional_id',
            'date',
            'time',
            'status',
            'origin_channel',
            'customer_notes',
            'internal_notes',
        ];
        $old = [];

        foreach ($fields as $field) {
            if (isset($_POST[$field]) && is_scalar($_POST[$field])) {
                $old[$field] = (string) $_POST[$field];
            }
        }

        return $old;
    }

    private function getAppointment(
        int $businessId,
        int $appointmentId,
        ?int $professionalId = null
    ): ?array
    {
        $professionalScope = $professionalId !== null ? ' AND a.professional_id = ?' : '';

        $stmt = $this->db->prepare("
            SELECT
                a.*,
                c.name AS customer_name,
                c.phone AS customer_phone,
                c.email AS customer_email,
                s.name AS service_name,
                s.duration_default AS service_duration
            FROM appointments a
            INNER JOIN customers c ON c.id = a.customer_id AND c.business_id = a.business_id
            INNER JOIN services s ON s.id = a.service_id
            WHERE a.business_id = ?
              AND a.id = ?
              {$professionalScope}
            LIMIT 1
        ");

        $params = [
            $businessId,
            $appointmentId
        ];

        if ($professionalId !== null) {
            $params[] = $professionalId;
        }

        $stmt->execute($params);

        $appointment = $stmt->fetch();

        return $appointment ?: null;
    }
    
    private function redirectEditWithError(int $appointmentId, string $message): void
    {
        $_SESSION['flash_error'] = $message;

        header('Location: ' . APP_URL . '/appointments/edit?id=' . $appointmentId);
        exit;
    }

    private function getServicesForAdmin(int $businessId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                s.*,
                c.name AS category_name
            FROM services s
            LEFT JOIN service_categories c ON c.id = s.category_id
            WHERE s.business_id = ?
            ORDER BY c.sort_order ASC, s.sort_order ASC, s.name ASC
        ");

        $stmt->execute([$businessId]);

        return $stmt->fetchAll();
    }

    private function getServiceCategories(int $businessId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                name,
                sort_order,
                is_active
            FROM service_categories
            WHERE business_id = ?
              AND is_active = 1
            ORDER BY sort_order ASC, name ASC
        ");

        $stmt->execute([$businessId]);

        return $stmt->fetchAll();
    }

    private function validateServiceData(array $input): array
    {
        $name = trim($input['name'] ?? '');
        $description = trim($input['description'] ?? '');

        $categoryId = (int) ($input['category_id'] ?? 0);
        $categoryId = $categoryId > 0 ? $categoryId : null;

        $priceType = trim($input['price_type'] ?? 'fixed');
        $priceRaw = trim($input['price'] ?? '');
        $priceLabel = trim($input['price_label'] ?? '');

        $durationType = trim($input['duration_type'] ?? 'fixed');
        $durationMin = trim($input['duration_min'] ?? '');
        $durationDefault = trim($input['duration_default'] ?? '');
        $durationMax = trim($input['duration_max'] ?? '');

        $capacityPerSlot = (int) ($input['capacity_per_slot'] ?? 1);
        $blocksFullSchedule = isset($input['blocks_full_schedule']) ? 1 : 0;

        $requiresDeposit = isset($input['requires_deposit']) ? 1 : 0;
        $depositType = trim($input['deposit_type'] ?? '');
        $depositAmountRaw = trim($input['deposit_amount'] ?? '');

        $requiresApproval = isset($input['requires_approval']) ? 1 : 0;
        $isBookable = isset($input['is_bookable']) ? 1 : 0;
        $isActive = isset($input['is_active']) ? 1 : 0;

        $sortOrder = (int) ($input['sort_order'] ?? 0);

        if ($name === '') {
            return [
                'valid' => false,
                'error' => 'El nombre del servicio es obligatorio.'
            ];
        }

        $allowedPriceTypes = ['fixed', 'from', 'consult'];

        if (!in_array($priceType, $allowedPriceTypes, true)) {
            return [
                'valid' => false,
                'error' => 'Tipo de precio inválido.'
            ];
        }

        $price = null;

        if ($priceType !== 'consult') {
            if ($priceRaw !== '') {
                $price = (float) str_replace(',', '.', $priceRaw);
            }

            if ($price !== null && $price < 0) {
                return [
                    'valid' => false,
                    'error' => 'El precio no puede ser negativo.'
                ];
            }
        }

        if ($priceType === 'consult') {
            $price = null;

            if ($priceLabel === '') {
                $priceLabel = 'Consultar';
            }
        }

        if ($priceType === 'fixed' && $priceLabel === '' && $price !== null) {
            $priceLabel = '$' . number_format($price, 0, ',', '.');
        }

        if ($priceType === 'from' && $priceLabel === '' && $price !== null) {
            $priceLabel = 'Desde $' . number_format($price, 0, ',', '.');
        }

        $allowedDurationTypes = ['fixed', 'variable'];

        if (!in_array($durationType, $allowedDurationTypes, true)) {
            return [
                'valid' => false,
                'error' => 'Tipo de duración inválido.'
            ];
        }

        if ($durationDefault === '' || (int) $durationDefault <= 0) {
            return [
                'valid' => false,
                'error' => 'La duración estimada es obligatoria.'
            ];
        }

        $durationDefault = (int) $durationDefault;
        $durationMin = $durationMin !== '' ? (int) $durationMin : null;
        $durationMax = $durationMax !== '' ? (int) $durationMax : null;

        if ($durationType === 'fixed') {
            $durationMin = null;
            $durationMax = null;
        }

        if ($durationType === 'variable') {
            if ($durationMin === null || $durationMax === null) {
                return [
                    'valid' => false,
                    'error' => 'Para duración variable, completá duración mínima y máxima.'
                ];
            }

            if ($durationMin <= 0 || $durationMax <= 0) {
                return [
                    'valid' => false,
                    'error' => 'Las duraciones deben ser mayores a cero.'
                ];
            }

            if ($durationMin > $durationDefault || $durationDefault > $durationMax) {
                return [
                    'valid' => false,
                    'error' => 'La duración estimada debe estar entre la mínima y la máxima.'
                ];
            }
        }

        if ($capacityPerSlot <= 0) {
            return [
                'valid' => false,
                'error' => 'La capacidad por horario debe ser mayor a cero.'
            ];
        }

        if ($capacityPerSlot > 10) {
            return [
                'valid' => false,
                'error' => 'La capacidad por horario no puede ser mayor a 10.'
            ];
        }

        $depositType = $requiresDeposit ? $depositType : null;
        $depositAmount = null;

        if ($requiresDeposit) {
            if (!in_array($depositType, ['fixed', 'percentage'], true)) {
                $depositType = 'fixed';
            }

            if ($depositAmountRaw !== '') {
                $depositAmount = (float) str_replace(',', '.', $depositAmountRaw);
            }

            if ($depositAmount !== null && $depositAmount < 0) {
                return [
                    'valid' => false,
                    'error' => 'La seña no puede ser negativa.'
                ];
            }
        }

        return [
            'valid' => true,
            'service' => [
                'category_id' => $categoryId,
                'name' => $name,
                'description' => $description !== '' ? $description : null,
                'price_type' => $priceType,
                'price' => $price,
                'price_label' => $priceLabel !== '' ? $priceLabel : null,
                'duration_type' => $durationType,
                'duration_min' => $durationMin,
                'duration_default' => $durationDefault,
                'duration_max' => $durationMax,
                'capacity_per_slot' => $capacityPerSlot,
                'blocks_full_schedule' => $blocksFullSchedule,
                'requires_deposit' => $requiresDeposit,
                'deposit_type' => $depositType,
                'deposit_amount' => $depositAmount,
                'requires_approval' => $requiresApproval,
                'is_bookable' => $isBookable,
                'is_active' => $isActive,
                'sort_order' => $sortOrder
            ]
        ];
    }

    private function resolveScheduleProfessionalId(int $businessId, $requestedProfessionalId): ?int
    {
        if (Auth::isProfessional()) {
            $professionalId = Auth::professionalId();

            if (
                !Auth::canManageOwnSchedule()
                || $professionalId === null
                || !$this->getScheduleProfessional($businessId, $professionalId, true)
            ) {
                $this->denyScheduleAccess();
            }

            return $professionalId;
        }

        if (!Auth::isAdministrator()) {
            $this->denyScheduleAccess();
        }

        if (
            $requestedProfessionalId === null
            || $requestedProfessionalId === ''
            || $requestedProfessionalId === 'general'
        ) {
            return null;
        }

        $rawProfessionalId = (string) $requestedProfessionalId;

        if (!preg_match('/^[1-9]\d*$/', $rawProfessionalId)) {
            $this->denyScheduleAccess();
        }

        $professionalId = (int) $rawProfessionalId;

        if (!$this->getScheduleProfessional($businessId, $professionalId)) {
            $this->denyScheduleAccess();
        }

        return $professionalId;
    }

    private function getScheduleProfessional(
        int $businessId,
        int $professionalId,
        bool $requireActive = false
    ): ?array
    {
        $activeCondition = $requireActive ? 'AND is_active = 1' : '';
        $stmt = $this->db->prepare("
            SELECT id, business_id, name, is_active, can_manage_own_schedule
            FROM professionals
            WHERE id = ?
              AND business_id = ?
              {$activeCondition}
            LIMIT 1
        ");
        $stmt->execute([$professionalId, $businessId]);
        $professional = $stmt->fetch();

        return $professional ?: null;
    }

    private function getScheduleProfessionals(int $businessId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, is_active
            FROM professionals
            WHERE business_id = ?
            ORDER BY is_active DESC, sort_order ASC, name ASC
        ");
        $stmt->execute([$businessId]);

        return $stmt->fetchAll();
    }

    private function getScheduleUrl(?int $professionalId): string
    {
        if (!Auth::isAdministrator() || $professionalId === null) {
            return APP_URL . '/schedule';
        }

        return APP_URL . '/schedule?professional_id=' . $professionalId;
    }

    private function lockScheduleScope(int $businessId, ?int $professionalId): void
    {
        if ($professionalId === null) {
            if (!Auth::isAdministrator()) {
                throw new RuntimeException('No tenés permiso para modificar el horario general.');
            }

            $stmt = $this->db->prepare("
                SELECT id
                FROM businesses
                WHERE id = ?
                FOR UPDATE
            ");
            $stmt->execute([$businessId]);

            if (!$stmt->fetch()) {
                throw new RuntimeException('No se encontró el negocio del horario.');
            }

            return;
        }

        if (Auth::isProfessional()) {
            $user = Auth::user();
            $authenticatedProfessionalId = Auth::professionalId();

            if (
                !$user
                || !Auth::canManageOwnSchedule()
                || $authenticatedProfessionalId !== $professionalId
            ) {
                throw new RuntimeException('No tenés permiso para modificar este horario.');
            }

            $stmt = $this->db->prepare("
                SELECT id
                FROM professionals
                WHERE id = ?
                  AND business_id = ?
                  AND user_id = ?
                  AND is_active = 1
                  AND can_manage_own_schedule = 1
                FOR UPDATE
            ");
            $stmt->execute([$professionalId, $businessId, (int) $user['id']]);
        } elseif (Auth::isAdministrator()) {
            $stmt = $this->db->prepare("
                SELECT id
                FROM professionals
                WHERE id = ?
                  AND business_id = ?
                FOR UPDATE
            ");
            $stmt->execute([$professionalId, $businessId]);
        } else {
            throw new RuntimeException('No tenés permiso para modificar este horario.');
        }

        if (!$stmt->fetch()) {
            throw new RuntimeException('La profesional ya no pertenece al alcance permitido.');
        }
    }

    private function denyScheduleAccess(): void
    {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Acceso no permitido.';
        exit;
    }

    private function getBusinessHoursMap(int $businessId, ?int $professionalId): array
    {
        $default = [];

        foreach (range(0, 6) as $weekday) {
            $default[$weekday] = [
                'weekday' => $weekday,
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
                'break_start' => null,
                'break_end' => null,
                'is_active' => 0,
                'schedule_mode' => $professionalId === null ? 'custom' : 'inherit',
                'is_inherited' => $professionalId !== null,
            ];
        }

        $generalRows = $this->getBusinessHoursRows($businessId, null);

        if ($professionalId === null) {
            foreach ($generalRows as $weekday => $row) {
                $default[$weekday] = array_merge($row, [
                    'schedule_mode' => 'custom',
                    'is_inherited' => false,
                ]);
            }

            return $default;
        }

        $ownRows = $this->getBusinessHoursRows($businessId, $professionalId);

        foreach (range(0, 6) as $weekday) {
            if (isset($ownRows[$weekday])) {
                $default[$weekday] = array_merge($ownRows[$weekday], [
                    'schedule_mode' => 'custom',
                    'is_inherited' => false,
                ]);
                continue;
            }

            if (isset($generalRows[$weekday])) {
                $default[$weekday] = array_merge($generalRows[$weekday], [
                    'schedule_mode' => 'inherit',
                    'is_inherited' => true,
                ]);
            }
        }

        return $default;
    }

    private function getBusinessHoursRows(int $businessId, ?int $professionalId): array
    {
        if ($professionalId === null) {
            $stmt = $this->db->prepare("
                SELECT *
                FROM business_hours
                WHERE business_id = ?
                  AND professional_id IS NULL
                ORDER BY weekday ASC, id ASC
            ");
            $stmt->execute([$businessId]);
        } else {
            $stmt = $this->db->prepare("
                SELECT *
                FROM business_hours
                WHERE business_id = ?
                  AND professional_id = ?
                ORDER BY weekday ASC, id ASC
            ");
            $stmt->execute([$businessId, $professionalId]);
        }

        $rowsByWeekday = [];

        foreach ($stmt->fetchAll() as $row) {
            $weekday = (int) $row['weekday'];

            if ($weekday >= 0 && $weekday <= 6) {
                $rowsByWeekday[$weekday] = $row;
            }
        }

        return $rowsByWeekday;
    }

    private function getScheduleExceptions(int $businessId, ?int $professionalId): array
    {
        if ($professionalId === null) {
            $stmt = $this->db->prepare("
                SELECT *
                FROM schedule_exceptions
                WHERE business_id = ?
                  AND professional_id IS NULL
                  AND date >= CURDATE()
                ORDER BY date ASC, start_time ASC, id ASC
                LIMIT 100
            ");
            $stmt->execute([$businessId]);
        } else {
            $stmt = $this->db->prepare("
                SELECT *
                FROM schedule_exceptions
                WHERE business_id = ?
                  AND (professional_id IS NULL OR professional_id = ?)
                  AND date >= CURDATE()
                ORDER BY date ASC, start_time ASC, id ASC
                LIMIT 100
            ");
            $stmt->execute([$businessId, $professionalId]);
        }

        $exceptions = $stmt->fetchAll();

        foreach ($exceptions as &$exception) {
            $exceptionProfessionalId = isset($exception['professional_id'])
                ? (int) $exception['professional_id']
                : null;
            $exception['is_general'] = $exceptionProfessionalId === null;
            $exception['can_delete'] = $professionalId === null
                ? $exceptionProfessionalId === null
                : $exceptionProfessionalId === $professionalId;
        }
        unset($exception);

        return $exceptions;
    }

    private function normalizeTime(?string $time): ?string
    {
        $time = trim((string) $time);

        if ($time === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            return $time . ':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
            return $time;
        }

        return null;
    }

    private function isTimeBefore(string $timeA, string $timeB): bool
    {
        return strtotime($timeA) < strtotime($timeB);
    }

    private function isTimeBeforeOrEqual(string $timeA, string $timeB): bool
    {
        return strtotime($timeA) <= strtotime($timeB);
    }

    private function getAppointmentsList(
        int $businessId,
        string $type,
        ?int $professionalId = null
    ): array
{
    $where = "";
    $params = [$businessId];

    if ($type === 'today') {
        $where = "
            AND DATE(a.start_at) = CURDATE()
            AND a.status NOT IN ('cancelled')
        ";
    }

    if ($type === 'week') {
        $where = "
            AND a.start_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
            AND a.start_at < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)
            AND a.status NOT IN ('cancelled')
        ";
    }

    if ($type === 'pending_approval') {
        $where = "
            AND a.status = 'pending_approval'
        ";
    }

    if ($type === 'pending_deposit') {
        $where = "
            AND a.status NOT IN ('cancelled', 'completed')
            AND (
                a.status = 'pending_deposit'
                OR a.deposit_status = 'pending'
            )
        ";
    }

    if ($type === 'confirmed') {
        $where = "
            AND a.status = 'confirmed'
        ";
    }

    if ($type === 'approved') {
        $where = "
            AND a.status = 'approved'
        ";
    }

    if ($type === 'rescheduled') {
        $where = "
            AND a.status = 'rescheduled'
        ";
    }

    if ($type === 'consultation') {
        $where = "
            AND a.status = 'consultation'
        ";
    }

    if ($professionalId !== null) {
        $where .= " AND a.professional_id = ?";
        $params[] = $professionalId;
    }

    $stmt = $this->db->prepare("
        SELECT
            a.id,
            a.start_at,
            a.end_at,
            a.status,
            a.origin_channel,
            a.deposit_status,
            a.customer_notes,
            a.internal_notes,
            c.name AS customer_name,
            c.phone AS customer_phone,
            c.email AS customer_email,
            s.name AS service_name,
            s.price_label,
            s.duration_default,
            p.name AS professional_name
        FROM appointments a
        INNER JOIN customers c ON c.id = a.customer_id AND c.business_id = a.business_id
        INNER JOIN services s ON s.id = a.service_id
        LEFT JOIN professionals p ON p.id = a.professional_id
        WHERE a.business_id = ?
        {$where}
        ORDER BY a.start_at ASC
    ");

    $stmt->execute($params);

    return $stmt->fetchAll();
}

private function getActiveProfessionals(int $businessId, ?int $professionalId = null): array
{
    $professionalScope = $professionalId !== null ? ' AND id = ?' : '';

    $stmt = $this->db->prepare("
        SELECT
            id,
            name,
            phone,
            email,
            role_label,
            is_owner,
            sort_order
        FROM professionals
        WHERE business_id = ?
          AND is_active = 1
          {$professionalScope}
        ORDER BY sort_order ASC, name ASC
    ");

    $params = [$businessId];

    if ($professionalId !== null) {
        $params[] = $professionalId;
    }

    $stmt->execute($params);

    return $stmt->fetchAll();
}

private function getProfessionalsForService(
    int $businessId,
    int $serviceId,
    ?int $professionalId = null
): array
{
    $professionalScope = $professionalId !== null ? ' AND p.id = ?' : '';

    $stmt = $this->db->prepare("
        SELECT
            p.id,
            p.name,
            p.phone,
            p.email,
            p.role_label,
            p.is_owner,
            p.sort_order
        FROM professionals p
        INNER JOIN service_professionals sp
            ON sp.professional_id = p.id
            AND sp.business_id = p.business_id
            AND sp.is_active = 1
        WHERE p.business_id = ?
          AND sp.service_id = ?
          AND p.is_active = 1
          {$professionalScope}
        ORDER BY p.sort_order ASC, p.name ASC
    ");

    $params = [
        $businessId,
        $serviceId
    ];

    if ($professionalId !== null) {
        $params[] = $professionalId;
    }

    $stmt->execute($params);

    return $stmt->fetchAll();
}

private function getDefaultProfessionalId(int $businessId): int
{
    $stmt = $this->db->prepare("
        SELECT id
        FROM professionals
        WHERE business_id = ?
          AND is_active = 1
        ORDER BY is_owner DESC, sort_order ASC, id ASC
        LIMIT 1
    ");

    $stmt->execute([$businessId]);
    $professional = $stmt->fetch();

    return $professional ? (int) $professional['id'] : 0;
}

private function professionalCanDoService(int $businessId, int $professionalId, int $serviceId): bool
{
    if ($professionalId <= 0 || $serviceId <= 0) {
        return false;
    }

    $stmt = $this->db->prepare("
        SELECT COUNT(*) AS total
        FROM service_professionals sp
        INNER JOIN professionals p
            ON p.id = sp.professional_id
            AND p.business_id = sp.business_id
            AND p.is_active = 1
        INNER JOIN services s
            ON s.id = sp.service_id
            AND s.business_id = sp.business_id
            AND s.is_active = 1
        WHERE sp.business_id = ?
          AND sp.professional_id = ?
          AND sp.service_id = ?
          AND sp.is_active = 1
    ");

    $stmt->execute([
        $businessId,
        $professionalId,
        $serviceId
    ]);

    $row = $stmt->fetch();

    return (int) $row['total'] > 0;
}

public function professionals(): void
{
    Auth::requireAdministrativeAccess();

    $businessId = $this->getBusinessId();

    $stmt = $this->db->prepare("
        SELECT
            p.*,
            u.id AS access_user_id,
            u.email AS access_email,
            u.role AS access_role,
            u.status AS access_status,
            u.last_login_at AS access_last_login_at,

            (
                SELECT COUNT(*)
                FROM service_professionals sp
                WHERE sp.professional_id = p.id
                  AND sp.business_id = p.business_id
                  AND sp.is_active = 1
            ) AS services_count,

            (
                SELECT COUNT(*)
                FROM appointments a
                WHERE a.professional_id = p.id
                  AND a.business_id = p.business_id
                  AND a.status NOT IN ('cancelled')
                  AND a.start_at >= NOW()
            ) AS upcoming_appointments

        FROM professionals p
        LEFT JOIN users u
            ON u.id = p.user_id
            AND u.business_id = p.business_id
        WHERE p.business_id = ?
        ORDER BY p.is_active DESC, p.sort_order ASC, p.name ASC
    ");

    $stmt->execute([$businessId]);
    $professionals = $stmt->fetchAll();

    require __DIR__ . '/../views/admin/professionals.php';
}

public function createProfessional(): void
{
    Auth::requireAdministrativeAccess();

    $businessId = $this->getBusinessId();

    $professional = [
        'id' => null,
        'name' => '',
        'phone' => '',
        'email' => '',
        'role_label' => '',
        'is_owner' => 0,
        'can_manage_own_schedule' => 1,
        'is_active' => 1,
        'sort_order' => 0,
    ];

    $assignedServiceIds = [];
    $services = $this->getAllServicesForProfessionalForm($businessId);

    require __DIR__ . '/../views/admin/professional_create.php';
}

public function storeProfessional(): void
{
    Auth::requireAdministrativeAccess();
    Auth::startSession();

    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!Auth::validateCsrfToken($csrfToken)) {
        $this->redirectWithError('La sesión del formulario venció. Volvé a intentarlo.');
    }

    $businessId = $this->getBusinessId();

    $data = $this->validateProfessionalData($_POST);

    if (!$data['valid']) {
        $this->redirectWithError($data['error']);
    }

    $professional = $data['professional'];
    $serviceIds = $data['service_ids'];

    $conflicts = $this->getServiceAssignmentConflicts($businessId, $serviceIds, null);

if (!empty($conflicts)) {
    $this->redirectWithError($this->buildServiceConflictMessage($conflicts));
}

    try {
        $this->db->beginTransaction();

        if ($professional['is_owner'] === 1) {
            $this->unsetOtherOwners($businessId);
        }

        $stmt = $this->db->prepare("
            INSERT INTO professionals
            (
                business_id,
                name,
                phone,
                email,
                role_label,
                is_owner,
                can_manage_own_schedule,
                is_active,
                sort_order
            )
            VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $businessId,
            $professional['name'],
            $professional['phone'],
            $professional['email'],
            $professional['role_label'],
            $professional['is_owner'],
            $professional['can_manage_own_schedule'],
            $professional['is_active'],
            $professional['sort_order'],
        ]);

        $professionalId = (int) $this->db->lastInsertId();

        $this->syncProfessionalServices($businessId, $professionalId, $serviceIds);

        $this->db->commit();

        header('Location: ' . APP_URL . '/professionals');
        exit;

    } catch (Throwable $e) {
        $this->db->rollBack();
        $this->redirectWithError('No se pudo crear el profesional.');
    }
}

public function editProfessional(): void
{
    Auth::requireAdministrativeAccess();
    Auth::startSession();

    $businessId = $this->getBusinessId();
    $professionalId = (int) ($_GET['id'] ?? 0);

    if ($professionalId <= 0) {
        $this->redirectWithError('Profesional inválido.');
    }

    $professional = $this->getProfessionalById($businessId, $professionalId);

    if (!$professional) {
        $this->redirectWithError('El profesional no existe.');
    }

    $assignedServiceIds = $this->getAssignedServiceIds($businessId, $professionalId);
    $services = $this->getAllServicesForProfessionalForm($businessId);
    $error = $_SESSION['flash_error'] ?? null;
    $success = $_SESSION['flash_success'] ?? null;

    unset($_SESSION['flash_error'], $_SESSION['flash_success']);

    require __DIR__ . '/../views/admin/professional_edit.php';
}

public function updateProfessional(): void
{
    Auth::requireAdministrativeAccess();
    Auth::startSession();

    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!Auth::validateCsrfToken($csrfToken)) {
        $this->redirectWithError('La sesión del formulario venció. Volvé a intentarlo.');
    }

    $businessId = $this->getBusinessId();
    $professionalId = (int) ($_POST['professional_id'] ?? 0);

    if ($professionalId <= 0) {
        $this->redirectWithError('Profesional inválido.');
    }

    $currentProfessional = $this->getProfessionalById($businessId, $professionalId);

    if (!$currentProfessional) {
        $this->redirectWithError('El profesional no existe.');
    }

    $data = $this->validateProfessionalData($_POST);

    if (!$data['valid']) {
        $this->redirectWithError($data['error']);
    }

    $professional = $data['professional'];
    $serviceIds = $data['service_ids'];

    $conflicts = $this->getServiceAssignmentConflicts($businessId, $serviceIds, $professionalId);

    if (!empty($conflicts)) {
        $this->redirectWithError($this->buildServiceConflictMessage($conflicts));
    }

    try {
        $this->db->beginTransaction();

        if ($professional['is_owner'] === 1) {
            $this->unsetOtherOwners($businessId, $professionalId);
        }

        $stmt = $this->db->prepare("
            UPDATE professionals
            SET
                name = ?,
                phone = ?,
                email = ?,
                role_label = ?,
                is_owner = ?,
                can_manage_own_schedule = ?,
                is_active = ?,
                sort_order = ?
            WHERE id = ?
              AND business_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $professional['name'],
            $professional['phone'],
            $professional['email'],
            $professional['role_label'],
            $professional['is_owner'],
            $professional['can_manage_own_schedule'],
            $professional['is_active'],
            $professional['sort_order'],
            $professionalId,
            $businessId,
        ]);

        $this->syncProfessionalServices($businessId, $professionalId, $serviceIds);

        $this->db->commit();

        header('Location: ' . APP_URL . '/professionals');
        exit;

    } catch (Throwable $e) {
        $this->db->rollBack();
        $this->redirectWithError('No se pudo actualizar el profesional.');
    }
}

public function createProfessionalAccess(): void
{
    Auth::requireAdministrativeAccess();
    Auth::startSession();

    $professionalId = (int) ($_POST['professional_id'] ?? 0);
    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!is_string($csrfToken) || !Auth::validateCsrfToken($csrfToken)) {
        $this->redirectProfessionalAccess($professionalId, 'La sesión del formulario venció. Volvé a intentarlo.');
    }

    $businessId = $this->getBusinessId();
    $emailInput = $_POST['access_email'] ?? '';
    $passwordInput = $_POST['access_password'] ?? '';
    $passwordConfirmationInput = $_POST['access_password_confirmation'] ?? '';
    $email = is_string($emailInput) ? strtolower(trim($emailInput)) : '';
    $password = is_string($passwordInput) ? $passwordInput : '';
    $passwordConfirmation = is_string($passwordConfirmationInput) ? $passwordConfirmationInput : '';
    $passwordHash = null;

    try {
        if ($professionalId <= 0) {
            throw new RuntimeException('Profesional inválido.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Ingresá un email de acceso válido.');
        }

        $this->validateProfessionalAccessPassword($password, $passwordConfirmation);
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        if ($passwordHash === false) {
            throw new RuntimeException('No se pudo preparar la contraseña.');
        }

        $this->db->beginTransaction();

        $professionalQuery = $this->db->prepare("
            SELECT id, business_id, name, is_active, user_id
            FROM professionals
            WHERE id = ?
              AND business_id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $professionalQuery->execute([$professionalId, $businessId]);
        $professional = $professionalQuery->fetch();

        if (!$professional) {
            throw new RuntimeException('El profesional no existe.');
        }

        if ((int) $professional['is_active'] !== 1) {
            throw new RuntimeException('Activá primero al profesional para crearle un acceso.');
        }

        if ($professional['user_id'] !== null) {
            throw new RuntimeException('El profesional ya tiene una cuenta vinculada.');
        }

        $emailQuery = $this->db->prepare("
            SELECT id
            FROM users
            WHERE email = ?
            LIMIT 1
            FOR UPDATE
        ");
        $emailQuery->execute([$email]);

        if ($emailQuery->fetch()) {
            throw new RuntimeException('Ese email ya pertenece a otra cuenta.');
        }

        $insertUser = $this->db->prepare("
            INSERT INTO users
            (
                business_id,
                name,
                email,
                password_hash,
                role,
                status,
                auth_version
            )
            VALUES
            (?, ?, ?, ?, 'professional', 'active', 0)
        ");
        $insertUser->execute([
            $businessId,
            (string) $professional['name'],
            $email,
            $passwordHash,
        ]);

        $userId = (int) $this->db->lastInsertId();

        if ($userId <= 0) {
            throw new RuntimeException('No se pudo crear la cuenta de acceso.');
        }

        $linkProfessional = $this->db->prepare("
            UPDATE professionals
            SET user_id = ?
            WHERE id = ?
              AND business_id = ?
              AND user_id IS NULL
        ");
        $linkProfessional->execute([$userId, $professionalId, $businessId]);

        if ($linkProfessional->rowCount() !== 1) {
            throw new RuntimeException('El vínculo del profesional cambió durante la operación.');
        }

        $this->db->commit();
        $this->redirectProfessionalAccess($professionalId, 'Acceso profesional creado correctamente.', true);
    } catch (RuntimeException $e) {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        $this->redirectProfessionalAccess($professionalId, $e->getMessage());
    } catch (Throwable $e) {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        $this->redirectProfessionalAccess($professionalId, 'No se pudo crear el acceso profesional.');
    } finally {
        $password = '';
        $passwordConfirmation = '';
        $passwordHash = null;
    }
}

public function updateProfessionalAccessStatus(): void
{
    Auth::requireAdministrativeAccess();
    Auth::startSession();

    $professionalId = (int) ($_POST['professional_id'] ?? 0);
    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!is_string($csrfToken) || !Auth::validateCsrfToken($csrfToken)) {
        $this->redirectProfessionalAccess($professionalId, 'La sesión del formulario venció. Volvé a intentarlo.');
    }

    $status = (string) ($_POST['access_status'] ?? '');

    if (!in_array($status, ['active', 'inactive'], true)) {
        $this->redirectProfessionalAccess($professionalId, 'El estado de acceso no es válido.');
    }

    $businessId = $this->getBusinessId();

    try {
        $this->db->beginTransaction();
        $access = $this->lockProfessionalAccess($businessId, $professionalId);

        if ($status === 'active' && (int) $access['professional']['is_active'] !== 1) {
            throw new RuntimeException('Activá primero al profesional para habilitar su acceso.');
        }

        $update = $this->db->prepare("
            UPDATE users
            SET
                status = ?,
                auth_version = auth_version + 1
            WHERE id = ?
              AND business_id = ?
              AND role = 'professional'
        ");
        $update->execute([
            $status,
            (int) $access['user']['id'],
            $businessId,
        ]);

        if ($update->rowCount() !== 1) {
            throw new RuntimeException('No se pudo actualizar el estado del acceso.');
        }

        (new RememberToken($this->db))->revokeAllForUser((int) $access['user']['id']);
        $this->db->commit();
        $message = $status === 'active'
            ? 'Acceso profesional activado.'
            : 'Acceso profesional desactivado.';
        $this->redirectProfessionalAccess($professionalId, $message, true);
    } catch (RuntimeException $e) {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        $this->redirectProfessionalAccess($professionalId, $e->getMessage());
    } catch (Throwable $e) {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        $this->redirectProfessionalAccess($professionalId, 'No se pudo actualizar el acceso profesional.');
    }
}

public function updateProfessionalAccessPassword(): void
{
    Auth::requireAdministrativeAccess();
    Auth::startSession();

    $professionalId = (int) ($_POST['professional_id'] ?? 0);
    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!is_string($csrfToken) || !Auth::validateCsrfToken($csrfToken)) {
        $this->redirectProfessionalAccess($professionalId, 'La sesión del formulario venció. Volvé a intentarlo.');
    }

    $businessId = $this->getBusinessId();
    $passwordInput = $_POST['access_password'] ?? '';
    $passwordConfirmationInput = $_POST['access_password_confirmation'] ?? '';
    $password = is_string($passwordInput) ? $passwordInput : '';
    $passwordConfirmation = is_string($passwordConfirmationInput) ? $passwordConfirmationInput : '';
    $passwordHash = null;

    try {
        $this->validateProfessionalAccessPassword($password, $passwordConfirmation);
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        if ($passwordHash === false) {
            throw new RuntimeException('No se pudo preparar la contraseña.');
        }

        $this->db->beginTransaction();
        $access = $this->lockProfessionalAccess($businessId, $professionalId);

        $update = $this->db->prepare("
            UPDATE users
            SET
                password_hash = ?,
                auth_version = auth_version + 1
            WHERE id = ?
              AND business_id = ?
              AND role = 'professional'
        ");
        $update->execute([
            $passwordHash,
            (int) $access['user']['id'],
            $businessId,
        ]);

        if ($update->rowCount() !== 1) {
            throw new RuntimeException('No se pudo actualizar la contraseña.');
        }

        (new RememberToken($this->db))->revokeAllForUser((int) $access['user']['id']);
        $this->db->commit();
        $this->redirectProfessionalAccess(
            $professionalId,
            'Contraseña actualizada. Las sesiones anteriores se cerrarán en su próxima petición.',
            true
        );
    } catch (RuntimeException $e) {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        $this->redirectProfessionalAccess($professionalId, $e->getMessage());
    } catch (Throwable $e) {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        $this->redirectProfessionalAccess($professionalId, 'No se pudo actualizar la contraseña.');
    } finally {
        $password = '';
        $passwordConfirmation = '';
        $passwordHash = null;
    }
}

private function validateProfessionalAccessPassword(string $password, string $confirmation): void
{
    $passwordLength = function_exists('mb_strlen')
        ? mb_strlen($password)
        : strlen($password);

    if (trim($password) === '' || $passwordLength < 12) {
        throw new RuntimeException('La contraseña debe tener al menos 12 caracteres.');
    }

    if (!hash_equals($password, $confirmation)) {
        throw new RuntimeException('Las contraseñas no coinciden.');
    }
}

private function lockProfessionalAccess(int $businessId, int $professionalId): array
{
    if ($professionalId <= 0) {
        throw new RuntimeException('Profesional inválido.');
    }

    $professionalQuery = $this->db->prepare("
        SELECT id, business_id, name, is_active, user_id
        FROM professionals
        WHERE id = ?
          AND business_id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $professionalQuery->execute([$professionalId, $businessId]);
    $professional = $professionalQuery->fetch();

    if (!$professional) {
        throw new RuntimeException('El profesional no existe.');
    }

    $userId = (int) ($professional['user_id'] ?? 0);

    if ($userId <= 0) {
        throw new RuntimeException('El profesional no tiene una cuenta vinculada.');
    }

    $userQuery = $this->db->prepare("
        SELECT id, business_id, email, role, status, auth_version
        FROM users
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $userQuery->execute([$userId]);
    $user = $userQuery->fetch();

    if (
        !$user
        || (int) $user['business_id'] !== $businessId
        || (string) $user['role'] !== 'professional'
    ) {
        throw new RuntimeException('La cuenta vinculada es administrativa o no pertenece al negocio.');
    }

    return [
        'professional' => $professional,
        'user' => $user,
    ];
}

private function redirectProfessionalAccess(
    int $professionalId,
    string $message,
    bool $success = false
): void {
    $_SESSION[$success ? 'flash_success' : 'flash_error'] = $message;

    $location = APP_URL . '/professionals';

    if ($professionalId > 0) {
        $location = APP_URL . '/professionals/edit?id=' . $professionalId;
    }

    header('Location: ' . $location);
    exit;
}

private function validateProfessionalData(array $input): array
{
    $name = trim($input['name'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $email = trim($input['email'] ?? '');
    $roleLabel = trim($input['role_label'] ?? '');

    $isOwner = isset($input['is_owner']) ? 1 : 0;
    $canManageOwnSchedule = isset($input['can_manage_own_schedule']) ? 1 : 0;
    $isActive = isset($input['is_active']) ? 1 : 0;
    $sortOrder = (int) ($input['sort_order'] ?? 0);

    $serviceIds = $input['service_ids'] ?? [];

    if (!is_array($serviceIds)) {
        $serviceIds = [];
    }

    $serviceIds = array_values(array_unique(array_filter(array_map('intval', $serviceIds))));

    if ($name === '') {
        return [
            'valid' => false,
            'error' => 'El nombre del profesional es obligatorio.'
        ];
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            'valid' => false,
            'error' => 'El email del profesional no es válido.'
        ];
    }

    return [
        'valid' => true,
        'professional' => [
            'name' => $name,
            'phone' => $phone !== '' ? $phone : null,
            'email' => $email !== '' ? $email : null,
            'role_label' => $roleLabel !== '' ? $roleLabel : null,
            'is_owner' => $isOwner,
            'can_manage_own_schedule' => $canManageOwnSchedule,
            'is_active' => $isActive,
            'sort_order' => $sortOrder,
        ],
        'service_ids' => $serviceIds
    ];
}

private function getProfessionalById(int $businessId, int $professionalId): ?array
{
    $stmt = $this->db->prepare("
        SELECT
            p.*,
            u.id AS access_user_id,
            u.email AS access_email,
            u.role AS access_role,
            u.status AS access_status,
            u.last_login_at AS access_last_login_at
        FROM professionals p
        LEFT JOIN users u
            ON u.id = p.user_id
            AND u.business_id = p.business_id
        WHERE p.business_id = ?
          AND p.id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $businessId,
        $professionalId
    ]);

    $professional = $stmt->fetch();

    return $professional ?: null;
}

private function getAllServicesForProfessionalForm(int $businessId): array
{
    $stmt = $this->db->prepare("
        SELECT
            s.id,
            s.name,
            s.price_label,
            s.duration_default,
            s.capacity_per_slot,
            s.blocks_full_schedule,
            sc.name AS category_name,

            (
                SELECT sp2.professional_id
                FROM service_professionals sp2
                INNER JOIN professionals p2 ON p2.id = sp2.professional_id
                WHERE sp2.business_id = s.business_id
                  AND sp2.service_id = s.id
                  AND sp2.is_active = 1
                ORDER BY p2.is_active DESC, p2.sort_order ASC, p2.name ASC
                LIMIT 1
            ) AS assigned_professional_id,

            (
                SELECT p2.name
                FROM service_professionals sp2
                INNER JOIN professionals p2 ON p2.id = sp2.professional_id
                WHERE sp2.business_id = s.business_id
                  AND sp2.service_id = s.id
                  AND sp2.is_active = 1
                ORDER BY p2.is_active DESC, p2.sort_order ASC, p2.name ASC
                LIMIT 1
            ) AS assigned_professional_name,

            (
                SELECT COUNT(*)
                FROM service_professionals sp3
                WHERE sp3.business_id = s.business_id
                  AND sp3.service_id = s.id
                  AND sp3.is_active = 1
            ) AS assigned_professionals_count

        FROM services s
        LEFT JOIN service_categories sc ON sc.id = s.category_id
        WHERE s.business_id = ?
          AND s.is_active = 1
        ORDER BY sc.sort_order ASC, s.sort_order ASC, s.name ASC
    ");

    $stmt->execute([$businessId]);

    return $stmt->fetchAll();
}

private function getAssignedServiceIds(int $businessId, int $professionalId): array
{
    $stmt = $this->db->prepare("
        SELECT service_id
        FROM service_professionals
        WHERE business_id = ?
          AND professional_id = ?
          AND is_active = 1
    ");

    $stmt->execute([
        $businessId,
        $professionalId
    ]);

    return array_map('intval', array_column($stmt->fetchAll(), 'service_id'));
}

private function syncProfessionalServices(int $businessId, int $professionalId, array $serviceIds): void
{
    $delete = $this->db->prepare("
        DELETE FROM service_professionals
        WHERE business_id = ?
          AND professional_id = ?
    ");

    $delete->execute([
        $businessId,
        $professionalId
    ]);

    if (empty($serviceIds)) {
        return;
    }

    $insert = $this->db->prepare("
        INSERT INTO service_professionals
        (
            business_id,
            service_id,
            professional_id,
            is_active
        )
        VALUES
        (?, ?, ?, 1)
    ");

    foreach ($serviceIds as $serviceId) {
        if ($serviceId <= 0) {
            continue;
        }

        $insert->execute([
            $businessId,
            $serviceId,
            $professionalId
        ]);
    }
}

private function unsetOtherOwners(int $businessId, ?int $exceptProfessionalId = null): void
{
    $sql = "
        UPDATE professionals
        SET is_owner = 0
        WHERE business_id = ?
    ";

    $params = [$businessId];

    if ($exceptProfessionalId !== null) {
        $sql .= " AND id != ?";
        $params[] = $exceptProfessionalId;
    }

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
}

private function getBusinessId(): int
{
    $businessId = Auth::businessId();

    if ($businessId === null) {
        http_response_code(403);
        echo 'Acceso no permitido.';
        exit;
    }

    return $businessId;
}

private function getServiceAssignmentConflicts(
    int $businessId,
    array $serviceIds,
    ?int $exceptProfessionalId = null
): array {
    $serviceIds = array_values(array_unique(array_filter(array_map('intval', $serviceIds))));

    if (empty($serviceIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));

    $sql = "
        SELECT
            sp.service_id,
            s.name AS service_name,
            p.id AS professional_id,
            p.name AS professional_name
        FROM service_professionals sp
        INNER JOIN services s ON s.id = sp.service_id
        INNER JOIN professionals p ON p.id = sp.professional_id
        WHERE sp.business_id = ?
          AND sp.service_id IN ($placeholders)
          AND sp.is_active = 1
    ";

    $params = array_merge([$businessId], $serviceIds);

    if ($exceptProfessionalId !== null) {
        $sql .= " AND sp.professional_id != ?";
        $params[] = $exceptProfessionalId;
    }

    $sql .= " ORDER BY s.name ASC";

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

private function buildServiceConflictMessage(array $conflicts): string
{
    $items = [];

    foreach ($conflicts as $conflict) {
        $items[] = '"' . $conflict['service_name'] . '" ya está asignado a "' . $conflict['professional_name'] . '"';
    }

    return 'No se pudo guardar la asignación. '
        . implode(' | ', $items)
        . '. En este sistema cada servicio pertenece a una sola profesional. Para cambiarlo, primero quitá ese servicio desde la profesional actual y luego asignalo a la nueva.';
}

public function confirmedMessages(): void
{
    Auth::requireLogin();
    Auth::startSession();

    $businessId = $this->getCurrentBusinessId();
    $professionalId = $this->getCurrentProfessionalId();

    $error = $_SESSION['flash_error'] ?? null;
    $success = $_SESSION['flash_success'] ?? null;
    unset($_SESSION['flash_error'], $_SESSION['flash_success']);

    $appointments = $this->getApprovedAppointmentsPendingMessage($businessId, $professionalId);

    require __DIR__ . '/../views/admin/confirmed_messages.php';
}

public function confirmedMessagesBulk(): void
{
    Auth::requireLogin();
    Auth::startSession();

    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!is_string($csrfToken) || !Auth::validateCsrfToken($csrfToken)) {
        $this->redirectConfirmationWithError('La sesión del formulario venció. Volvé a intentarlo.');
    }

    $businessId = $this->getCurrentBusinessId();
    $professionalId = $this->getCurrentProfessionalId();

    $appointmentIds = $_POST['appointment_ids'] ?? [];

    if (!is_array($appointmentIds)) {
        $appointmentIds = [];
    }

    $appointmentIds = array_values(array_unique(array_filter(array_map('intval', $appointmentIds))));

    if (empty($appointmentIds)) {
        $this->redirectConfirmationWithError('Seleccioná al menos un turno aprobado.');
    }

    $appointments = $this->getApprovedAppointmentsByIds(
        $businessId,
        $appointmentIds,
        $professionalId
    );

    if (count($appointments) !== count($appointmentIds)) {
        $this->redirectConfirmationWithError('Uno o más turnos no están disponibles para esta cuenta.');
    }

    require __DIR__ . '/../views/admin/confirmed_messages_bulk.php';
}

public function openWhatsApp(): void
{
    Auth::requireLogin();
    Auth::startSession();

    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!is_string($csrfToken) || !Auth::validateCsrfToken($csrfToken)) {
        $this->redirectConfirmationWithError('La sesión del formulario venció. Volvé a intentarlo.');
    }

    $businessId = $this->getCurrentBusinessId();
    $professionalId = $this->getCurrentProfessionalId();
    $appointmentId = (int) ($_POST['appointment_id'] ?? 0);

    if ($appointmentId <= 0) {
        $this->redirectConfirmationWithError('Turno inválido.');
    }

    $appointment = $this->getAppointmentForWhatsApp(
        $businessId,
        $appointmentId,
        $professionalId
    );

    if (!$appointment) {
        $this->redirectConfirmationWithError('No se encontró el turno aprobado.');
    }

    if ($appointment['status'] !== 'approved') {
        $this->redirectConfirmationWithError('Sólo se puede preparar el mensaje de turnos aprobados.');
    }

    $messageBody = $this->buildConfirmedAppointmentMessage($appointment);

    $phone = $this->formatPhoneForWhatsApp($appointment['customer_phone']);

    if ($phone === '') {
        $this->redirectConfirmationWithError('El cliente no tiene un WhatsApp válido.');
    }

    $whatsappUrl = 'https://wa.me/' . $phone . '?text=' . urlencode($messageBody);

    header('Location: ' . $whatsappUrl);
    exit;
}

public function markWhatsAppSent(): void
{
    Auth::requireLogin();
    Auth::startSession();

    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!is_string($csrfToken) || !Auth::validateCsrfToken($csrfToken)) {
        $this->redirectConfirmationWithError('La sesión del formulario venció. Volvé a intentarlo.');
    }

    $businessId = $this->getCurrentBusinessId();
    $professionalId = $this->getCurrentProfessionalId();
    $appointmentId = (int) ($_POST['appointment_id'] ?? 0);

    if ($appointmentId <= 0) {
        $this->redirectConfirmationWithError('Turno inválido.');
    }

    try {
        $this->db->beginTransaction();

        $lockSql = "
            SELECT status
            FROM appointments
            WHERE id = ? AND business_id = ?
        ";
        $lockParams = [$appointmentId, $businessId];

        if ($professionalId !== null) {
            $lockSql .= " AND professional_id = ?";
            $lockParams[] = $professionalId;
        }

        $lockSql .= " FOR UPDATE";

        $lock = $this->db->prepare($lockSql);
        $lock->execute($lockParams);
        $row = $lock->fetch();

        if (!$row || $row['status'] !== 'approved') {
            $this->db->rollBack();
            $this->redirectConfirmationWithError('El turno ya no está pendiente de confirmación.');
        }

        $appointment = $this->getAppointmentForWhatsApp(
            $businessId,
            $appointmentId,
            $professionalId
        );

        if (!$appointment) {
            throw new RuntimeException('No se encontró el turno aprobado.');
        }

        $this->logManualAppointmentMessage(
            $businessId,
            $appointment,
            'appointment_confirmed',
            $this->buildConfirmedAppointmentMessage($appointment)
        );

        $updateSql = "
            UPDATE appointments
            SET status = 'confirmed'
            WHERE id = ? AND business_id = ? AND status = 'approved'
        ";
        $updateParams = [$appointmentId, $businessId];

        if ($professionalId !== null) {
            $updateSql .= " AND professional_id = ?";
            $updateParams[] = $professionalId;
        }

        $update = $this->db->prepare($updateSql);
        $update->execute($updateParams);

        if ($update->rowCount() !== 1) {
            throw new RuntimeException('El estado del turno cambió durante la confirmación.');
        }

        $this->db->commit();
        $_SESSION['flash_success'] = 'Envío manual registrado. El turno quedó confirmado.';
        header('Location: ' . APP_URL . '/appointments/confirmed-messages');
        exit;
    } catch (Throwable $e) {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        $this->redirectConfirmationWithError('No se pudo registrar el envío. Volvé a intentarlo.');
    }
}

private function redirectConfirmationWithError(string $message): void
{
    $_SESSION['flash_error'] = $message;
    header('Location: ' . APP_URL . '/appointments/confirmed-messages');
    exit;
}

private function getApprovedAppointmentsPendingMessage(
    int $businessId,
    ?int $professionalId = null
): array
{
    $professionalScope = $professionalId !== null
        ? ' AND a.professional_id = ?'
        : '';

    $stmt = $this->db->prepare("
        SELECT
            a.id,
            a.business_id,
            a.customer_id,
            a.service_id,
            a.professional_id,
            a.start_at,
            a.end_at,
            a.status,
            a.origin_channel,

            c.name AS customer_name,
            c.phone AS customer_phone,

            s.name AS service_name,
            s.price_label,
            s.duration_default,

            p.name AS professional_name,
            p.phone AS professional_phone

        FROM appointments a
        INNER JOIN customers c ON c.id = a.customer_id AND c.business_id = a.business_id
        INNER JOIN services s ON s.id = a.service_id
        LEFT JOIN professionals p ON p.id = a.professional_id

        WHERE a.business_id = ?
          AND a.status = 'approved'
          {$professionalScope}

        ORDER BY a.start_at ASC
    ");

    $params = [$businessId];

    if ($professionalId !== null) {
        $params[] = $professionalId;
    }

    $stmt->execute($params);

    return $stmt->fetchAll();
}

private function getApprovedAppointmentsByIds(
    int $businessId,
    array $appointmentIds,
    ?int $professionalId = null
): array
{
    $appointmentIds = array_values(array_unique(array_filter(array_map('intval', $appointmentIds))));

    if (empty($appointmentIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($appointmentIds), '?'));
    $professionalScope = $professionalId !== null
        ? ' AND a.professional_id = ?'
        : '';

    $stmt = $this->db->prepare("
        SELECT
            a.id,
            a.business_id,
            a.customer_id,
            a.service_id,
            a.professional_id,
            a.start_at,
            a.end_at,
            a.status,
            a.origin_channel,

            c.name AS customer_name,
            c.phone AS customer_phone,

            s.name AS service_name,
            s.price_label,
            s.duration_default,

            p.name AS professional_name,
            p.phone AS professional_phone

        FROM appointments a
        INNER JOIN customers c ON c.id = a.customer_id AND c.business_id = a.business_id
        INNER JOIN services s ON s.id = a.service_id
        LEFT JOIN professionals p ON p.id = a.professional_id

        WHERE a.business_id = ?
          AND a.status = 'approved'
          AND a.id IN ($placeholders)
          {$professionalScope}

        ORDER BY a.start_at ASC
    ");

    $params = array_merge([$businessId], $appointmentIds);

    if ($professionalId !== null) {
        $params[] = $professionalId;
    }

    $stmt->execute($params);

    return $stmt->fetchAll();
}

private function getAppointmentForWhatsApp(
    int $businessId,
    int $appointmentId,
    ?int $professionalId = null
): ?array
{
    $professionalScope = $professionalId !== null
        ? ' AND a.professional_id = ?'
        : '';

    $stmt = $this->db->prepare("
        SELECT
            a.id,
            a.business_id,
            a.customer_id,
            a.service_id,
            a.professional_id,
            a.start_at,
            a.end_at,
            a.status,
            a.origin_channel,

            c.name AS customer_name,
            c.phone AS customer_phone,

            s.name AS service_name,
            s.price_label,
            s.duration_default,

            p.name AS professional_name,
            p.phone AS professional_phone,

            b.name AS business_name,
            b.whatsapp AS business_whatsapp

        FROM appointments a
        INNER JOIN customers c ON c.id = a.customer_id AND c.business_id = a.business_id
        INNER JOIN services s ON s.id = a.service_id
        INNER JOIN businesses b ON b.id = a.business_id
        LEFT JOIN professionals p ON p.id = a.professional_id

        WHERE a.business_id = ?
          AND a.id = ?
          {$professionalScope}
        LIMIT 1
    ");

    $params = [$businessId, $appointmentId];

    if ($professionalId !== null) {
        $params[] = $professionalId;
    }

    $stmt->execute($params);

    $appointment = $stmt->fetch();

    return $appointment ?: null;
}

private function canCancelAppointmentStatus(?string $status): bool
{
    return in_array($status, [
        'pending_approval',
        'pending_deposit',
        'approved',
        'confirmed',
        'rescheduled',
        'consultation',
    ], true);
}

private function buildCancellationWhatsAppUrl(array $appointment): ?string
{
    $phone = $this->formatPhoneForWhatsApp($appointment['customer_phone'] ?? null);

    if (!preg_match('/^\d{10,15}$/', $phone)) {
        return null;
    }

    return 'https://wa.me/' . $phone . '?text=' . rawurlencode(
        $this->buildCancelledAppointmentMessage($appointment)
    );
}

private function buildCancelledAppointmentMessage(array $appointment): string
{
    $customerName = trim((string) ($appointment['customer_name'] ?? ''));
    $serviceName = trim((string) ($appointment['service_name'] ?? ''));
    $timestamp = strtotime((string) ($appointment['start_at'] ?? ''));
    $date = $timestamp ? date('d/m/Y', $timestamp) : '-';
    $time = $timestamp ? date('H:i', $timestamp) : '-';

    return "Hola {$customerName}, quedó cancelado tu turno de {$serviceName} del {$date} a las {$time}. "
        . 'Si querés coordinar otro turno, escribime.';
}

private function buildConfirmedAppointmentMessage(array $appointment): string
{
    $customerName = trim($appointment['customer_name'] ?? '');
    $businessName = trim($appointment['business_name'] ?? 'Meli Figarola');
    $serviceName = trim($appointment['service_name'] ?? 'el servicio solicitado');
    $professionalName = trim($appointment['professional_name'] ?? '');

    $dateLabel = $this->formatAppointmentDate((string) $appointment['start_at']);
    $timeLabel = date('H:i', strtotime((string) $appointment['start_at']));

    $message = "Hola {$customerName} 👋\n\n";
    $message .= "Te confirmamos tu turno en {$businessName}.\n\n";
    $message .= "Servicio: {$serviceName}\n";

    if ($professionalName !== '') {
        $message .= "Profesional: {$professionalName}\n";
    }

    $message .= "Fecha: {$dateLabel}\n";
    $message .= "Horario: {$timeLabel} hs\n\n";
    $message .= "Por favor, avisá con anticipación si necesitás modificar o cancelar el turno.\n\n";
    $message .= "¡Te esperamos!";

    return $message;
}

private function logManualAppointmentMessage(
    int $businessId,
    array $appointment,
    string $messageType,
    string $messageBody
): void {
    $user = Auth::user();
    $userId = isset($user['id']) ? (int) $user['id'] : null;

    $stmt = $this->db->prepare("
        INSERT INTO appointment_message_logs
        (
            business_id,
            appointment_id,
            customer_id,
            professional_id,
            user_id,
            channel,
            message_type,
            phone,
            message_body,
            sent_manually_at
        )
        VALUES
        (?, ?, ?, ?, ?, 'whatsapp', ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $businessId,
        (int) $appointment['id'],
        (int) $appointment['customer_id'],
        !empty($appointment['professional_id']) ? (int) $appointment['professional_id'] : null,
        $userId,
        $messageType,
        (string) $appointment['customer_phone'],
        $messageBody
    ]);
}

private function formatAppointmentDate(string $dateTime): string
{
    $timestamp = strtotime($dateTime);

    if (!$timestamp) {
        return $dateTime;
    }

    $days = [
        'Sunday' => 'domingo',
        'Monday' => 'lunes',
        'Tuesday' => 'martes',
        'Wednesday' => 'miércoles',
        'Thursday' => 'jueves',
        'Friday' => 'viernes',
        'Saturday' => 'sábado',
    ];

    $months = [
        'January' => 'enero',
        'February' => 'febrero',
        'March' => 'marzo',
        'April' => 'abril',
        'May' => 'mayo',
        'June' => 'junio',
        'July' => 'julio',
        'August' => 'agosto',
        'September' => 'septiembre',
        'October' => 'octubre',
        'November' => 'noviembre',
        'December' => 'diciembre',
    ];

    $dayName = $days[date('l', $timestamp)] ?? date('l', $timestamp);
    $day = date('d', $timestamp);
    $month = $months[date('F', $timestamp)] ?? date('F', $timestamp);

    return "{$dayName} {$day} de {$month}";
}

private function formatPhoneForWhatsApp(?string $phone): string
{
    $phone = preg_replace('/[^0-9]/', '', (string) $phone);

    if ($phone === '') {
        return '';
    }

    if (str_starts_with($phone, '549')) {
        return $phone;
    }

    if (str_starts_with($phone, '54')) {
        return $phone;
    }

    $phone = ltrim($phone, '0');

    if (strlen($phone) >= 10) {
        return '549' . $phone;
    }

    return $phone;
}

}
