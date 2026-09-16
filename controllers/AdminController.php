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

            $business = $this->getBusiness($businessId);
            $stats = $this->getDashboardStats($businessId);
            $todayAppointments = $this->getTodayAppointments($businessId);
            $upcomingAppointments = $this->getUpcomingAppointments($businessId);

            require __DIR__ . '/../views/admin/dashboard.php';
        }

        public function calendar(): void
        {
            Auth::requireLogin();

            $businessId = $this->getCurrentBusinessId();
            $business = $this->getBusiness($businessId);

            require __DIR__ . '/../views/admin/calendar.php';
        }

        public function createAppointment(): void
    {
        Auth::requireLogin();

        $businessId = $this->getCurrentBusinessId();
        $business = $this->getBusiness($businessId);
        $services = $this->getActiveServices($businessId);
        $professionals = $this->getActiveProfessionals($businessId);

        Auth::startSession();

        $error = $_SESSION['flash_error'] ?? null;
        $success = $_SESSION['flash_success'] ?? null;
        $old = $_SESSION['flash_old'] ?? [];

        unset($_SESSION['flash_error'], $_SESSION['flash_success'], $_SESSION['flash_old']);

        require __DIR__ . '/../views/admin/appointment_create.php';
    }

    public function storeAppointment(): void
    {
        Auth::requireLogin();
        Auth::startSession();

        $csrfToken = $_POST['csrf_token'] ?? null;

        if (!Auth::validateCsrfToken($csrfToken)) {
            $this->redirectWithError('La sesión del formulario venció. Volvé a intentarlo.');
        }

        $businessId = $this->getCurrentBusinessId();

        $customerName = trim($_POST['customer_name'] ?? '');
        $customerPhone = preg_replace('/\D+/', '', $_POST['customer_phone'] ?? '');
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $professionalId = (int) ($_POST['professional_id'] ?? 0);
        $date = trim($_POST['date'] ?? '');
        $time = trim($_POST['time'] ?? '');
        $status = trim($_POST['status'] ?? 'confirmed');
        $originChannel = trim($_POST['origin_channel'] ?? 'manual');
        $customerNotes = trim($_POST['customer_notes'] ?? '');
        $internalNotes = trim($_POST['internal_notes'] ?? '');

        $_SESSION['flash_old'] = $_POST;

        if ($customerName === '') {
            $this->redirectWithError('El nombre del cliente es obligatorio.');
        }

        if ($customerPhone === '') {
            $this->redirectWithError('El WhatsApp del cliente es obligatorio.');
        }

        if ($serviceId <= 0) {
            $this->redirectWithError('Seleccioná un servicio.');
        }

        if ($date === '' || $time === '') {
            $this->redirectWithError('Seleccioná fecha y hora.');
        }

        $allowedStatuses = [
            'pending_approval',
            'pending_deposit',
            'confirmed',
            'completed',
            'cancelled',
            'rescheduled',
            'no_show',
            'consultation'
        ];

        if (!in_array($status, $allowedStatuses, true)) {
            $this->redirectWithError('Estado de turno inválido.');
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
            $this->redirectWithError('El servicio seleccionado no existe.');
        }

        if ($professionalId <= 0) {
                $professionalId = $this->getDefaultProfessionalId($businessId);
            }

            if (!$this->professionalCanDoService($businessId, $professionalId, $serviceId)) {
                $this->redirectWithError('El profesional seleccionado no está asignado a este servicio.');
            }

        $startAt = $date . ' ' . $time . ':00';

        if (strtotime($startAt) === false) {
            $this->redirectWithError('La fecha u hora no es válida.');
        }

        $duration = (int) $service['duration_default'];

        if ($duration <= 0) {
            $duration = 60;
        }

        $endAt = date('Y-m-d H:i:s', strtotime($startAt . " + {$duration} minutes"));

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
            $this->redirectWithError('Ese horario ya no está disponible para el profesional seleccionado.');
        }

        try {
            $this->db->beginTransaction();

            $customerId = $this->findOrCreateCustomer(
                $businessId,
                $customerName,
                $customerPhone
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

            unset($_SESSION['flash_old']);

            $_SESSION['flash_success'] = 'Turno creado correctamente.';

            header('Location: ' . APP_URL . '/calendar');
            exit;

        } catch (Exception $e) {
            $this->db->rollBack();

            $this->redirectWithError('Error al crear el turno: ' . $e->getMessage());
        }
    }

    public function editAppointment(): void
    {
        Auth::requireLogin();
        Auth::startSession();

        $businessId = $this->getCurrentBusinessId();
        $business = $this->getBusiness($businessId);

        $appointmentId = (int) ($_GET['id'] ?? 0);

        if ($appointmentId <= 0) {
            die('ID de turno inválido.');
        }

        $appointment = $this->getAppointment($businessId, $appointmentId);

        if (!$appointment) {
            die('No se encontró el turno.');
        }

        $services = $this->getActiveServices($businessId);
        $professionals = $this->getProfessionalsForService(
            $businessId,
            (int) $appointment['service_id']
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

        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);

        if ($appointmentId <= 0) {
            die('ID de turno inválido.');
        }

        $appointment = $this->getAppointment($businessId, $appointmentId);

        if (!$appointment) {
            die('No se encontró el turno.');
        }

        $customerName = trim($_POST['customer_name'] ?? '');
        $customerPhone = preg_replace('/\D+/', '', $_POST['customer_phone'] ?? '');
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $professionalId = (int) ($_POST['professional_id'] ?? 0);
        $date = trim($_POST['date'] ?? '');
        $time = trim($_POST['time'] ?? '');
        $status = trim($_POST['status'] ?? 'confirmed');
        $originChannel = trim($_POST['origin_channel'] ?? 'manual');
        $customerNotes = trim($_POST['customer_notes'] ?? '');
        $internalNotes = trim($_POST['internal_notes'] ?? '');

        if ($customerName === '') {
            $this->redirectEditWithError($appointmentId, 'El nombre del cliente es obligatorio.');
        }

        if ($customerPhone === '') {
            $this->redirectEditWithError($appointmentId, 'El WhatsApp del cliente es obligatorio.');
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

            $customerId = $this->findOrCreateCustomer(
                $businessId,
                $customerName,
                $customerPhone
            );

            $depositRequired = (int) $service['requires_deposit'];
            $depositStatus = $depositRequired ? ($_POST['deposit_status'] ?? 'pending') : 'not_required';
            $depositAmount = $service['deposit_amount'] ?? null;
            $totalAmount = $service['price'] ?? null;

            $allowedDepositStatuses = ['not_required', 'pending', 'paid'];

            if (!in_array($depositStatus, $allowedDepositStatuses, true)) {
                $depositStatus = $depositRequired ? 'pending' : 'not_required';
            }

            $cancelledAt = $status === 'cancelled' ? date('Y-m-d H:i:s') : null;

            $stmt = $this->db->prepare("
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
            ");

            $stmt->execute([
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
            ]);

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
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
        Auth::requireLogin();
        Auth::startSession();

        $businessId = $this->getCurrentBusinessId();
        $business = $this->getBusiness($businessId);

        $businessHours = $this->getBusinessHoursMap($businessId);
        $exceptions = $this->getScheduleExceptions($businessId);

        $error = $_SESSION['flash_error'] ?? null;
        $success = $_SESSION['flash_success'] ?? null;

        unset($_SESSION['flash_error'], $_SESSION['flash_success']);

        require __DIR__ . '/../views/admin/schedule.php';
    }

    public function updateBusinessHours(): void
    {
        Auth::requireLogin();
        Auth::startSession();

        $csrfToken = $_POST['csrf_token'] ?? null;

        if (!Auth::validateCsrfToken($csrfToken)) {
            $_SESSION['flash_error'] = 'La sesión del formulario venció. Volvé a intentarlo.';
            header('Location: ' . APP_URL . '/schedule');
            exit;
        }

        $businessId = $this->getCurrentBusinessId();
        $hours = $_POST['hours'] ?? [];

        try {
            foreach (range(0, 6) as $weekday) {
                $row = $hours[$weekday] ?? [];

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

                $existing = $this->db->prepare("
                    SELECT id
                    FROM business_hours
                    WHERE business_id = ?
                      AND weekday = ?
                    ORDER BY id ASC
                    LIMIT 1
                ");

                $existing->execute([$businessId, $weekday]);
                $businessHour = $existing->fetch();

                if ($businessHour) {
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
                    ");

                    $stmt->execute([
                        $startTime,
                        $endTime,
                        $breakStart,
                        $breakEnd,
                        $isActive,
                        $businessHour['id'],
                        $businessId
                    ]);
                } else {
                    $stmt = $this->db->prepare("
                        INSERT INTO business_hours
                        (
                            business_id,
                            weekday,
                            start_time,
                            end_time,
                            break_start,
                            break_end,
                            is_active
                        )
                        VALUES
                        (?, ?, ?, ?, ?, ?, ?)
                    ");

                    $stmt->execute([
                        $businessId,
                        $weekday,
                        $startTime,
                        $endTime,
                        $breakStart,
                        $breakEnd,
                        $isActive
                    ]);
                }
            }

            $_SESSION['flash_success'] = 'Horarios laborales actualizados correctamente.';

        } catch (Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: ' . APP_URL . '/schedule');
        exit;
    }

    public function storeScheduleException(): void
    {
        Auth::requireLogin();
        Auth::startSession();

        $csrfToken = $_POST['csrf_token'] ?? null;

        if (!Auth::validateCsrfToken($csrfToken)) {
            $_SESSION['flash_error'] = 'La sesión del formulario venció. Volvé a intentarlo.';
            header('Location: ' . APP_URL . '/schedule');
            exit;
        }

        $businessId = $this->getCurrentBusinessId();

        $date = trim($_POST['date'] ?? '');
        $type = trim($_POST['type'] ?? '');
        $startTime = $this->normalizeTime($_POST['start_time'] ?? '');
        $endTime = $this->normalizeTime($_POST['end_time'] ?? '');
        $reason = trim($_POST['reason'] ?? '');

        try {
            if ($date === '') {
                throw new Exception('Seleccioná una fecha.');
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

            $stmt = $this->db->prepare("
                INSERT INTO schedule_exceptions
                (
                    business_id,
                    date,
                    start_time,
                    end_time,
                    type,
                    reason
                )
                VALUES
                (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $businessId,
                $date,
                $startTime,
                $endTime,
                $type,
                $reason !== '' ? $reason : null
            ]);

            $_SESSION['flash_success'] = 'Bloqueo o excepción creada correctamente.';

        } catch (Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header('Location: ' . APP_URL . '/schedule');
        exit;
    }

    public function deleteScheduleException(): void
    {
        Auth::requireLogin();
        Auth::startSession();

        $csrfToken = $_POST['csrf_token'] ?? null;

        if (!Auth::validateCsrfToken($csrfToken)) {
            $_SESSION['flash_error'] = 'La sesión del formulario venció. Volvé a intentarlo.';
            header('Location: ' . APP_URL . '/schedule');
            exit;
        }

        $businessId = $this->getCurrentBusinessId();
        $exceptionId = (int) ($_POST['exception_id'] ?? 0);

        if ($exceptionId <= 0) {
            $_SESSION['flash_error'] = 'ID de bloqueo inválido.';
            header('Location: ' . APP_URL . '/schedule');
            exit;
        }

        $stmt = $this->db->prepare("
            DELETE FROM schedule_exceptions
            WHERE id = ?
              AND business_id = ?
        ");

        $stmt->execute([
            $exceptionId,
            $businessId
        ]);

        $_SESSION['flash_success'] = 'Bloqueo eliminado correctamente.';

        header('Location: ' . APP_URL . '/schedule');
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
            'Turnos que necesitan ser aprobados por Meli.'
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

    public function confirmAppointment(): void
    {
        Auth::requireLogin();
        Auth::startSession();

        $csrfToken = $_POST['csrf_token'] ?? null;

        if (!Auth::validateCsrfToken($csrfToken)) {
            $_SESSION['flash_error'] = 'La sesión del formulario venció. Volvé a intentarlo.';
            header('Location: ' . APP_URL . '/appointments/pending-approval');
            exit;
        }

        $businessId = $this->getCurrentBusinessId();
        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);

        if ($appointmentId <= 0) {
            $_SESSION['flash_error'] = 'ID de turno inválido.';
            header('Location: ' . APP_URL . '/appointments/pending-approval');
            exit;
        }

        $stmt = $this->db->prepare("
            UPDATE appointments
            SET status = 'confirmed'
            WHERE id = ?
            AND business_id = ?
            AND status = 'pending_approval'
        ");

        $stmt->execute([
            $appointmentId,
            $businessId
        ]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['flash_success'] = 'Turno confirmado correctamente. Recordá enviar los mensajes de confirmación por WhatsApp.';
        } else {
            $_SESSION['flash_error'] = 'No se pudo confirmar el turno.';
        }

        header('Location: ' . APP_URL . '/appointments/pending-approval');
        exit;
    }

    public function appointmentsConfirmed(): void
    {
        $this->renderAppointmentsList(
            'confirmed',
            'Turnos confirmados',
            'Todos los turnos confirmados.'
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
        $appointments = $this->getAppointmentsList($businessId, $type);

        $error = $_SESSION['flash_error'] ?? null;
        $success = $_SESSION['flash_success'] ?? null;

        unset($_SESSION['flash_error'], $_SESSION['flash_success']);

        require __DIR__ . '/../views/admin/appointments_list.php';
    }

    private function getCurrentBusinessId(): int
    {
        $user = Auth::user();

        if (!$user) {
            header('Location: ' . APP_URL . '/login');
            exit;
        }

        /*
         * Si sos super_admin, por ahora usamos Meli como negocio principal.
         * Más adelante vamos a crear selector de negocios.
         */
        if ($user['role'] === 'super_admin') {
            return 1;
        }

        return (int) $user['business_id'];
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

    private function getDashboardStats(int $businessId): array
    {
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');

        $weekStart = date('Y-m-d 00:00:00', strtotime('monday this week'));
        $weekEnd = date('Y-m-d 23:59:59', strtotime('sunday this week'));

        return [
            'today' => $this->countAppointments($businessId, $todayStart, $todayEnd),
            'week' => $this->countAppointments($businessId, $weekStart, $weekEnd),
            'pending_approval' => $this->countByStatus($businessId, 'pending_approval'),
            'pending_deposit' => $this->countPendingDeposit($businessId),
        ];
    }

    private function countAppointments(int $businessId, string $start, string $end): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS total
            FROM appointments
            WHERE business_id = ?
              AND start_at BETWEEN ? AND ?
              AND status NOT IN ('cancelled')
        ");

        $stmt->execute([$businessId, $start, $end]);
        $row = $stmt->fetch();

        return (int) $row['total'];
    }

    private function countByStatus(int $businessId, string $status): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS total
            FROM appointments
            WHERE business_id = ?
              AND status = ?
        ");

        $stmt->execute([$businessId, $status]);
        $row = $stmt->fetch();

        return (int) $row['total'];
    }

    private function countPendingDeposit(int $businessId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS total
            FROM appointments
            WHERE business_id = ?
              AND status NOT IN ('cancelled', 'completed')
              AND (
                status = 'pending_deposit'
                OR deposit_status = 'pending'
              )
        ");

        $stmt->execute([$businessId]);
        $row = $stmt->fetch();

        return (int) $row['total'];
    }

    private function getTodayAppointments(int $businessId): array
    {
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');

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
            INNER JOIN customers c ON c.id = a.customer_id
            INNER JOIN services s ON s.id = a.service_id
            WHERE a.business_id = ?
              AND a.start_at BETWEEN ? AND ?
              AND a.status NOT IN ('cancelled')
            ORDER BY a.start_at ASC
        ");

        $stmt->execute([$businessId, $todayStart, $todayEnd]);

        return $stmt->fetchAll();
    }

    private function getUpcomingAppointments(int $businessId): array
    {
        $now = date('Y-m-d H:i:s');

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
            INNER JOIN customers c ON c.id = a.customer_id
            INNER JOIN services s ON s.id = a.service_id
            WHERE a.business_id = ?
              AND a.start_at >= ?
              AND a.status NOT IN ('cancelled', 'completed')
            ORDER BY a.start_at ASC
            LIMIT 8
        ");

        $stmt->execute([$businessId, $now]);

        return $stmt->fetchAll();
    }
    private function getActiveServices(int $businessId): array
    {
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
            ORDER BY c.sort_order ASC, s.sort_order ASC, s.name ASC
        ");

        $stmt->execute([$businessId]);

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

    private function findOrCreateCustomer(int $businessId, string $name, string $phone): int
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

        if ($customer) {
            $update = $this->db->prepare("
                UPDATE customers
                SET name = ?, updated_at = NOW()
                WHERE id = ?
            ");

            $update->execute([
                $name,
                $customer['id']
            ]);

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
    private function getAppointment(int $businessId, int $appointmentId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                a.*,
                c.name AS customer_name,
                c.phone AS customer_phone,
                c.email AS customer_email,
                s.name AS service_name,
                s.duration_default AS service_duration
            FROM appointments a
            INNER JOIN customers c ON c.id = a.customer_id
            INNER JOIN services s ON s.id = a.service_id
            WHERE a.business_id = ?
              AND a.id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $businessId,
            $appointmentId
        ]);

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

    private function getBusinessHoursMap(int $businessId): array
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
            ];
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM business_hours
            WHERE business_id = ?
            ORDER BY weekday ASC, id ASC
        ");

        $stmt->execute([$businessId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as $row) {
            $weekday = (int) $row['weekday'];

            if (!isset($default[$weekday])) {
                continue;
            }

            $default[$weekday] = $row;
        }

        return $default;
    }

    private function getScheduleExceptions(int $businessId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM schedule_exceptions
            WHERE business_id = ?
              AND date >= CURDATE()
            ORDER BY date ASC, start_time ASC, id ASC
            LIMIT 100
        ");

        $stmt->execute([$businessId]);

        return $stmt->fetchAll();
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

    private function getAppointmentsList(int $businessId, string $type): array
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
            AND a.status = 'pending_deposit'
        ";
    }

    if ($type === 'confirmed') {
        $where = "
            AND a.status = 'confirmed'
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
        INNER JOIN customers c ON c.id = a.customer_id
        INNER JOIN services s ON s.id = a.service_id
        LEFT JOIN professionals p ON p.id = a.professional_id
        WHERE a.business_id = ?
        {$where}
        ORDER BY a.start_at ASC
    ");

    $stmt->execute($params);

    return $stmt->fetchAll();
}

private function getActiveProfessionals(int $businessId): array
{
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
        ORDER BY sort_order ASC, name ASC
    ");

    $stmt->execute([$businessId]);

    return $stmt->fetchAll();
}

private function getProfessionalsForService(int $businessId, int $serviceId): array
{
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
        ORDER BY p.sort_order ASC, p.name ASC
    ");

    $stmt->execute([
        $businessId,
        $serviceId
    ]);

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
        FROM service_professionals
        WHERE business_id = ?
          AND professional_id = ?
          AND service_id = ?
          AND is_active = 1
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
    Auth::requireLogin();

    $businessId = $this->getBusinessId();

    $stmt = $this->db->prepare("
        SELECT
            p.*,

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
        WHERE p.business_id = ?
        ORDER BY p.is_active DESC, p.sort_order ASC, p.name ASC
    ");

    $stmt->execute([$businessId]);
    $professionals = $stmt->fetchAll();

    require __DIR__ . '/../views/admin/professionals.php';
}

public function createProfessional(): void
{
    Auth::requireLogin();

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
    Auth::requireLogin();

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
    Auth::requireLogin();

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

    require __DIR__ . '/../views/admin/professional_edit.php';
}

public function updateProfessional(): void
{
    Auth::requireLogin();

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
        SELECT *
        FROM professionals
        WHERE business_id = ?
          AND id = ?
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
    $user = Auth::user();

    if (!$user) {
        return 1;
    }

    if (($user['role'] ?? '') === 'super_admin') {
        return 1;
    }

    $businessId = (int) ($user['business_id'] ?? 0);

    return $businessId > 0 ? $businessId : 1;
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

    $businessId = $this->getBusinessId();

    $appointments = $this->getConfirmedAppointmentsPendingMessage($businessId);

    require __DIR__ . '/../views/admin/confirmed_messages.php';
}

public function confirmedMessagesBulk(): void
{
    Auth::requireLogin();
    Auth::startSession();

    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!Auth::validateCsrfToken($csrfToken)) {
        $this->redirectWithError('La sesión del formulario venció. Volvé a intentarlo.');
    }

    $businessId = $this->getBusinessId();

    $appointmentIds = $_POST['appointment_ids'] ?? [];

    if (!is_array($appointmentIds)) {
        $appointmentIds = [];
    }

    $appointmentIds = array_values(array_unique(array_filter(array_map('intval', $appointmentIds))));

    if (empty($appointmentIds)) {
        $this->redirectWithError('Seleccioná al menos un turno confirmado.');
    }

    $appointments = $this->getConfirmedAppointmentsByIds($businessId, $appointmentIds);

    require __DIR__ . '/../views/admin/confirmed_messages_bulk.php';
}

public function sendConfirmedWhatsApp(): void
{
    Auth::requireLogin();

    $businessId = $this->getBusinessId();
    $appointmentId = (int) ($_GET['id'] ?? 0);

    if ($appointmentId <= 0) {
        $this->redirectWithError('Turno inválido.');
    }

    $appointment = $this->getAppointmentForConfirmedMessage($businessId, $appointmentId);

    if (!$appointment) {
        $this->redirectWithError('No se encontró el turno confirmado.');
    }

    if ($appointment['status'] !== 'confirmed') {
        $this->redirectWithError('Sólo se puede enviar mensaje de confirmación a turnos confirmados.');
    }

    $messageBody = $this->buildConfirmedAppointmentMessage($appointment);

    $this->logManualAppointmentMessage(
        $businessId,
        $appointment,
        'appointment_confirmed',
        $messageBody
    );

    $phone = $this->formatPhoneForWhatsApp($appointment['customer_phone']);

    if ($phone === '') {
        $this->redirectWithError('El cliente no tiene un WhatsApp válido.');
    }

    $whatsappUrl = 'https://wa.me/' . $phone . '?text=' . urlencode($messageBody);

    header('Location: ' . $whatsappUrl);
    exit;
}

private function getConfirmedAppointmentsPendingMessage(int $businessId): array
{
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
        INNER JOIN customers c ON c.id = a.customer_id
        INNER JOIN services s ON s.id = a.service_id
        LEFT JOIN professionals p ON p.id = a.professional_id

        LEFT JOIN appointment_message_logs aml
            ON aml.appointment_id = a.id
            AND aml.message_type = 'appointment_confirmed'

        WHERE a.business_id = ?
          AND a.status = 'confirmed'
          AND aml.id IS NULL
          AND a.start_at >= NOW()

        ORDER BY a.start_at ASC
    ");

    $stmt->execute([$businessId]);

    return $stmt->fetchAll();
}

private function getConfirmedAppointmentsByIds(int $businessId, array $appointmentIds): array
{
    $appointmentIds = array_values(array_unique(array_filter(array_map('intval', $appointmentIds))));

    if (empty($appointmentIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($appointmentIds), '?'));

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
        INNER JOIN customers c ON c.id = a.customer_id
        INNER JOIN services s ON s.id = a.service_id
        LEFT JOIN professionals p ON p.id = a.professional_id

        WHERE a.business_id = ?
          AND a.status = 'confirmed'
          AND a.id IN ($placeholders)

        ORDER BY a.start_at ASC
    ");

    $params = array_merge([$businessId], $appointmentIds);

    $stmt->execute($params);

    return $stmt->fetchAll();
}

private function getAppointmentForConfirmedMessage(int $businessId, int $appointmentId): ?array
{
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
        INNER JOIN customers c ON c.id = a.customer_id
        INNER JOIN services s ON s.id = a.service_id
        INNER JOIN businesses b ON b.id = a.business_id
        LEFT JOIN professionals p ON p.id = a.professional_id

        WHERE a.business_id = ?
          AND a.id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $businessId,
        $appointmentId
    ]);

    $appointment = $stmt->fetch();

    return $appointment ?: null;
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
        ON DUPLICATE KEY UPDATE
            user_id = VALUES(user_id),
            phone = VALUES(phone),
            message_body = VALUES(message_body),
            sent_manually_at = NOW()
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