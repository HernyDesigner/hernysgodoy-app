<?php

$currentRoute = $_GET['route'] ?? '';

function mobileNavActive(string $route, string $currentRoute): string
{
    if ($route === 'dashboard' && ($currentRoute === '' || $currentRoute === 'dashboard')) {
        return 'active';
    }

    return str_starts_with($currentRoute, $route) ? 'active' : '';
}

?>

<nav class="mobile-bottom-nav">
    <a href="<?= APP_URL ?>/dashboard" class="<?= mobileNavActive('dashboard', $currentRoute) ?>">
        <span>⌂</span>
        Inicio
    </a>

    <a href="<?= APP_URL ?>/calendar" class="<?= mobileNavActive('calendar', $currentRoute) ?>">
        <span>□</span>
        Agenda
    </a>

    <a href="<?= APP_URL ?>/appointments/today" class="<?= mobileNavActive('appointments', $currentRoute) ?>">
        <span>☰</span>
        Turnos
    </a>

    <a href="<?= APP_URL ?>/services" class="<?= mobileNavActive('services', $currentRoute) ?>">
        <span>✦</span>
        Servicios
    </a>

    <a href="<?= APP_URL ?>/schedule" class="<?= mobileNavActive('schedule', $currentRoute) ?>">
        <span>⚙</span>
        Ajustes
    </a>
</nav>