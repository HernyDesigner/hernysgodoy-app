<?php

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require __DIR__ . '/config/app.php';
require __DIR__ . '/core/Database.php';
require __DIR__ . '/core/Auth.php';
require __DIR__ . '/core/Availability.php';
require __DIR__ . '/controllers/AuthController.php';
require __DIR__ . '/controllers/AdminController.php';
require __DIR__ . '/controllers/PublicBookingController.php';

Auth::startSession();

$route = $_GET['route'] ?? '';

$authController = new AuthController();
$adminController = new AdminController();
$publicBookingController = new PublicBookingController();

if (preg_match('#^reservas/([a-z0-9-]+)$#', $route, $matches)) {
    $publicBookingController->show($matches[1]);
    exit;
}

switch ($route) {
    
    case '':
        if (Auth::check()) {
            header('Location: ' . APP_URL . '/dashboard');
        } else {
            header('Location: ' . APP_URL . '/login');
        }
        exit;

    case 'login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $authController->login();
        } else {
            $authController->showLogin();
        }
        break;

    case 'logout':
        $authController->logout();
        break;

    case 'dashboard':
        $adminController->dashboard();
        break;

    case 'calendar':
        $adminController->calendar();
    break;

    case 'services':
    $adminController->services();
    break;

    case 'services/create':
        $adminController->createService();
        break;

    case 'services/store':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . APP_URL . '/services');
            exit;
        }

        $adminController->storeService();
        break;

    case 'services/edit':
        $adminController->editService();
        break;

    case 'services/update':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . APP_URL . '/services');
            exit;
        }

        $adminController->updateService();
        break;

    case 'professionals':
        $adminController->professionals();
        break;

    case 'professionals/create':
        $adminController->createProfessional();
        break;

    case 'professionals/store':
        $adminController->storeProfessional();
        break;

    case 'professionals/edit':
        $adminController->editProfessional();
        break;

    case 'professionals/update':
        $adminController->updateProfessional();
        break;

    case 'schedule':
        $adminController->schedule();
        break;

    case 'schedule/update-hours':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . APP_URL . '/schedule');
            exit;
        }

        $adminController->updateBusinessHours();
        break;

    case 'schedule/exceptions/store':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . APP_URL . '/schedule');
            exit;
        }

        $adminController->storeScheduleException();
        break;

    case 'schedule/exceptions/delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . APP_URL . '/schedule');
            exit;
        }

        $adminController->deleteScheduleException();
        break;

    case 'appointments/create':
    $adminController->createAppointment();
    break;

    case 'appointments/store':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ' . APP_URL . '/appointments/create');
        exit;
    }

    $adminController->storeAppointment();
    break;

    case 'appointments/edit':
    $adminController->editAppointment();
    break;

    case 'appointments/update':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ' . APP_URL . '/calendar');
        exit;
    }

    $adminController->updateAppointment();
    break;

    case 'appointments/today':
    $adminController->appointmentsToday();
    break;

    case 'appointments/week':
        $adminController->appointmentsWeek();
        break;

    case 'appointments/pending-approval':
        $adminController->appointmentsPendingApproval();
        break;

    case 'appointments/pending-deposit':
        $adminController->appointmentsPendingDeposit();
        break;

    case 'appointments/confirm':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . APP_URL . '/dashboard');
            exit;
        }

        $adminController->confirmAppointment();
        break;
    
    case 'appointments/confirmed':
        $adminController->appointmentsConfirmed();
        break;

    case 'appointments/rescheduled':
        $adminController->appointmentsRescheduled();
        break;

    case 'appointments/consultation':
        $adminController->appointmentsConsultation();
    break;

    case 'appointments/confirmed-messages':
    $adminController->confirmedMessages();
    break;

case 'appointments/confirmed-messages/bulk':
    $adminController->confirmedMessagesBulk();
    break;

case 'appointments/send-confirmed-whatsapp':
    $adminController->sendConfirmedWhatsApp();
    break;

    default:
        http_response_code(404);
        echo 'Página no encontrada.';
        break;
}