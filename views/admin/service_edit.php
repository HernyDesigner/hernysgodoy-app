<?php

function selectedServiceValue($current, string $value): string
{
    return (string) $current === $value ? 'selected' : '';
}

function checkedServiceValue($current): string
{
    return (int) $current === 1 ? 'checked' : '';
}

$isEdit = true;
$formAction = APP_URL . '/services/update';
$pageTitle = 'Editar servicio';

require __DIR__ . '/service_form.php';