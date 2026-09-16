<?php

class Availability
{
    private $db;
    private int $slotInterval = 15;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function getAvailableSlots(
        int $businessId,
        int $serviceId,
        string $date,
        ?int $excludeAppointmentId = null,
        ?int $professionalId = null
    ): array {
        if (!$this->isValidDate($date)) {
            return [];
        }

        $service = $this->getService($businessId, $serviceId);

        if (!$service) {
            return [];
        }

        $duration = (int) $service['duration_default'];

        if ($duration <= 0) {
            $duration = 60;
        }

        $capacity = (int) ($service['capacity_per_slot'] ?? 1);

        if ($capacity <= 0) {
            $capacity = 1;
        }

        $blocksFullSchedule = (int) ($service['blocks_full_schedule'] ?? 1) === 1;

        if ($this->isBlockedDay($businessId, $date)) {
            return [];
        }

        $workingIntervals = $this->getWorkingIntervals($businessId, $date);

        if (empty($workingIntervals)) {
            return [];
        }

        $blockedIntervals = $this->getBlockedIntervals($businessId, $date);
        $fullBlockingAppointments = $this->getFullBlockingAppointmentIntervals(
            $businessId,
            $date,
            $excludeAppointmentId,
            $professionalId
        );

        $hardBusyIntervals = array_merge($blockedIntervals, $fullBlockingAppointments);

        $slots = [];

        foreach ($workingIntervals as $interval) {
            $current = strtotime($date . ' ' . $interval['start']);
            $end = strtotime($date . ' ' . $interval['end']);

            while (($current + ($duration * 60)) <= $end) {
                $slotStart = date('H:i:s', $current);
                $slotEnd = date('H:i:s', $current + ($duration * 60));

                $isAvailable = true;

                if ($this->overlapsAny($slotStart, $slotEnd, $hardBusyIntervals)) {
                    $isAvailable = false;
                }

                if ($isAvailable) {
                    if ($blocksFullSchedule) {
                        $totalOverlaps = $this->countAllOverlappingAppointments(
                            $businessId,
                            $date,
                            $slotStart,
                            $slotEnd,
                            $excludeAppointmentId,
                            $professionalId
                        );

                        if ($totalOverlaps > 0) {
                            $isAvailable = false;
                        }
                    } else {
                        $parallelOverlaps = $this->countParallelAppointments(
                            $businessId,
                            $date,
                            $slotStart,
                            $slotEnd,
                            $excludeAppointmentId,
                            $professionalId
                        );

                        if ($parallelOverlaps >= $capacity) {
                            $isAvailable = false;
                        }
                    }
                }

                if ($isAvailable) {
                    $slots[] = [
                        'time' => substr($slotStart, 0, 5),
                        'end_time' => substr($slotEnd, 0, 5),
                        'label' => substr($slotStart, 0, 5) . ' - ' . substr($slotEnd, 0, 5),
                    ];
                }

                $current += $this->slotInterval * 60;
            }
        }

        return $this->uniqueSlots($slots);
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

    private function getWorkingIntervals(int $businessId, string $date): array
    {
        $weekday = (int) date('w', strtotime($date));
        $intervals = [];

        $stmt = $this->db->prepare("
            SELECT *
            FROM business_hours
            WHERE business_id = ?
              AND weekday = ?
              AND is_active = 1
            LIMIT 1
        ");

        $stmt->execute([$businessId, $weekday]);
        $hours = $stmt->fetch();

        if ($hours) {
            $start = $hours['start_time'];
            $end = $hours['end_time'];
            $breakStart = $hours['break_start'];
            $breakEnd = $hours['break_end'];

            if ($breakStart && $breakEnd) {
                if ($this->isTimeBefore($start, $breakStart)) {
                    $intervals[] = [
                        'start' => $start,
                        'end' => $breakStart
                    ];
                }

                if ($this->isTimeBefore($breakEnd, $end)) {
                    $intervals[] = [
                        'start' => $breakEnd,
                        'end' => $end
                    ];
                }
            } else {
                $intervals[] = [
                    'start' => $start,
                    'end' => $end
                ];
            }
        }

        $extraHours = $this->db->prepare("
            SELECT start_time, end_time
            FROM schedule_exceptions
            WHERE business_id = ?
              AND date = ?
              AND type = 'extra_hours'
            ORDER BY start_time ASC
        ");

        $extraHours->execute([$businessId, $date]);

        foreach ($extraHours->fetchAll() as $extra) {
            if ($extra['start_time'] && $extra['end_time']) {
                $intervals[] = [
                    'start' => $extra['start_time'],
                    'end' => $extra['end_time']
                ];
            }
        }

        return $intervals;
    }

    private function isBlockedDay(int $businessId, string $date): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS total
            FROM schedule_exceptions
            WHERE business_id = ?
              AND date = ?
              AND type = 'blocked_day'
        ");

        $stmt->execute([$businessId, $date]);
        $row = $stmt->fetch();

        return (int) $row['total'] > 0;
    }

    private function getBlockedIntervals(int $businessId, string $date): array
    {
        $stmt = $this->db->prepare("
            SELECT start_time, end_time
            FROM schedule_exceptions
            WHERE business_id = ?
              AND date = ?
              AND type = 'blocked_range'
              AND start_time IS NOT NULL
              AND end_time IS NOT NULL
            ORDER BY start_time ASC
        ");

        $stmt->execute([$businessId, $date]);

        $intervals = [];

        foreach ($stmt->fetchAll() as $row) {
            $intervals[] = [
                'start' => $row['start_time'],
                'end' => $row['end_time']
            ];
        }

        return $intervals;
    }

    private function getFullBlockingAppointmentIntervals(
        int $businessId,
        string $date,
        ?int $excludeAppointmentId = null,
        ?int $professionalId = null
    ): array {
        $startDay = $date . ' 00:00:00';
        $endDay = $date . ' 23:59:59';

        $sql = "
            SELECT a.id, a.start_at, a.end_at
            FROM appointments a
            INNER JOIN services s ON s.id = a.service_id
            WHERE a.business_id = ?
              AND a.status NOT IN ('cancelled')
              AND s.blocks_full_schedule = 1
              AND a.start_at <= ?
              AND a.end_at >= ?
        ";

        $params = [
            $businessId,
            $endDay,
            $startDay
        ];

        if ($professionalId !== null) {
            $sql .= " AND a.professional_id = ?";
            $params[] = $professionalId;
        }

        if ($excludeAppointmentId !== null) {
            $sql .= " AND a.id != ?";
            $params[] = $excludeAppointmentId;
        }

        $sql .= " ORDER BY a.start_at ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $intervals = [];

        foreach ($stmt->fetchAll() as $appointment) {
            $intervals[] = [
                'start' => date('H:i:s', strtotime($appointment['start_at'])),
                'end' => date('H:i:s', strtotime($appointment['end_at']))
            ];
        }

        return $intervals;
    }

    private function countAllOverlappingAppointments(
        int $businessId,
        string $date,
        string $slotStart,
        string $slotEnd,
        ?int $excludeAppointmentId = null,
        ?int $professionalId = null
    ): int {
        return $this->countOverlappingAppointments(
            $businessId,
            $date,
            $slotStart,
            $slotEnd,
            $excludeAppointmentId,
            $professionalId,
            null
        );
    }

    private function countParallelAppointments(
        int $businessId,
        string $date,
        string $slotStart,
        string $slotEnd,
        ?int $excludeAppointmentId = null,
        ?int $professionalId = null
    ): int {
        return $this->countOverlappingAppointments(
            $businessId,
            $date,
            $slotStart,
            $slotEnd,
            $excludeAppointmentId,
            $professionalId,
            0
        );
    }

    private function countOverlappingAppointments(
        int $businessId,
        string $date,
        string $slotStart,
        string $slotEnd,
        ?int $excludeAppointmentId = null,
        ?int $professionalId = null,
        ?int $blocksFullSchedule = null
    ): int {
        $startAt = $date . ' ' . $slotStart;
        $endAt = $date . ' ' . $slotEnd;

        $sql = "
            SELECT COUNT(*) AS total
            FROM appointments a
            INNER JOIN services s ON s.id = a.service_id
            WHERE a.business_id = ?
              AND a.status NOT IN ('cancelled')
              AND a.start_at < ?
              AND a.end_at > ?
        ";

        $params = [
            $businessId,
            $endAt,
            $startAt
        ];

        if ($professionalId !== null) {
            $sql .= " AND a.professional_id = ?";
            $params[] = $professionalId;
        }

        if ($blocksFullSchedule !== null) {
            $sql .= " AND s.blocks_full_schedule = ?";
            $params[] = $blocksFullSchedule;
        }

        if ($excludeAppointmentId !== null) {
            $sql .= " AND a.id != ?";
            $params[] = $excludeAppointmentId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $row = $stmt->fetch();

        return (int) $row['total'];
    }

    private function overlapsAny(string $slotStart, string $slotEnd, array $intervals): bool
    {
        foreach ($intervals as $interval) {
            if ($this->overlaps($slotStart, $slotEnd, $interval['start'], $interval['end'])) {
                return true;
            }
        }

        return false;
    }

    private function overlaps(string $startA, string $endA, string $startB, string $endB): bool
    {
        return strtotime($startA) < strtotime($endB)
            && strtotime($endA) > strtotime($startB);
    }

    private function isTimeBefore(string $timeA, string $timeB): bool
    {
        return strtotime($timeA) < strtotime($timeB);
    }

    private function isValidDate(string $date): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);

        return $d && $d->format('Y-m-d') === $date;
    }

    private function uniqueSlots(array $slots): array
    {
        $unique = [];

        foreach ($slots as $slot) {
            $unique[$slot['time']] = $slot;
        }

        ksort($unique);

        return array_values($unique);
    }
}