<?php

class WhatsAppMessage
{
    public const TYPE_CONFIRMED = 'appointment_confirmed';

    public static function buildAppointmentConfirmedMessage(array $appointment): string
    {
        $customerName = self::safe($appointment['customer_name'] ?? '');
        $businessName = self::safe($appointment['business_name'] ?? 'Meli Figarola');
        $serviceName = self::safe($appointment['service_name'] ?? '');
        $professionalName = self::safe($appointment['professional_name'] ?? '');
        $date = self::formatDate($appointment['start_at'] ?? '');
        $time = self::formatTime($appointment['start_at'] ?? '');

        $message = "Hola {$customerName} 👋\n\n";
        $message .= "Te confirmamos tu turno en {$businessName}.\n\n";
        $message .= "Servicio: {$serviceName}\n";

        if ($professionalName !== '') {
            $message .= "Profesional: {$professionalName}\n";
        }

        $message .= "Fecha: {$date}\n";
        $message .= "Horario: {$time}\n\n";
        $message .= "Si necesitás modificar o cancelar el turno, avisá con anticipación por este mismo WhatsApp.\n\n";
        $message .= "¡Te esperamos! ✨";

        return $message;
    }

    public static function buildMessage(array $appointment): string
    {
        return self::buildAppointmentConfirmedMessage($appointment);
    }

    public static function buildUrl(string $phone, string $message): string
    {
        $phone = self::normalizePhoneForWhatsApp($phone);

        return 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);
    }

    public static function normalizePhoneForWhatsApp(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);

        if ($digits === '') {
            return '';
        }

        if (substr($digits, 0, 2) === '00') {
            $digits = substr($digits, 2);
        }

        if (substr($digits, 0, 3) === '549') {
            return $digits;
        }

        if (substr($digits, 0, 2) === '54') {
            $rest = substr($digits, 2);

            if (substr($rest, 0, 1) === '9') {
                return $digits;
            }

            return '549' . $rest;
        }

        $digits = ltrim($digits, '0');

        if (strlen($digits) >= 10 && strlen($digits) <= 11) {
            return '549' . $digits;
        }

        return $digits;
    }

    public static function messageTypeLabel(): string
    {
        return 'Turno confirmado';
    }

    private static function formatDate(string $datetime): string
    {
        if ($datetime === '') {
            return '-';
        }

        return date('d/m/Y', strtotime($datetime));
    }

    private static function formatTime(string $datetime): string
    {
        if ($datetime === '') {
            return '-';
        }

        return date('H:i', strtotime($datetime));
    }

    private static function safe(string $value): string
    {
        return trim($value);
    }
}