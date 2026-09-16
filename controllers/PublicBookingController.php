<?php

class PublicBookingController
{
    private $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function show(string $slug): void
    {
        $business = $this->getBusinessBySlug($slug);

        if (!$business) {
            http_response_code(404);
            echo 'Página de reservas no encontrada.';
            exit;
        }

        require __DIR__ . '/../views/public/booking.php';
    }

    private function getBusinessBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM businesses
            WHERE slug = ?
              AND status = 'active'
            LIMIT 1
        ");

        $stmt->execute([$slug]);
        $business = $stmt->fetch();

        return $business ?: null;
    }
}