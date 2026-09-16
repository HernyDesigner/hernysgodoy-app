<?php

function oldValue(array $old, string $key, string $default = ''): string
{
    return isset($old[$key]) ? (string) $old[$key] : $default;
}

function selectedValue(array $old, string $key, string $value): string
{
    return oldValue($old, $key) === $value ? 'selected' : '';
}

function checkedValue(array $old, string $key, bool $default = false): string
{
    if (isset($old[$key])) {
        return 'checked';
    }

    return $default ? 'checked' : '';
}

$isEdit = false;
$formAction = APP_URL . '/services/store';
$pageTitle = 'Crear servicio';

require __DIR__ . '/service_form.php';