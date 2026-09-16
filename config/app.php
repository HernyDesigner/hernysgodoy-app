<?php

date_default_timezone_set('America/Argentina/Buenos_Aires');

if (!defined('APP_NAME')) {
    define('APP_NAME', 'Turnero HG');
}

if (!defined('APP_URL')) {
    define('APP_URL', 'https://hernysgodoy.com/app');
}

if (!defined('APP_ENV')) {
    define('APP_ENV', 'development');
}

if (!defined('SESSION_NAME')) {
    define('SESSION_NAME', 'turnero_hg_session');
}

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}