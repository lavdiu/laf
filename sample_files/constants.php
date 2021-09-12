<?php

use Laf\Util\Settings;

$settings = Settings::getInstance();

$settings->setProperty('email_smtp_config', array(
    'Host' => 'smtp.gmail.com',
    'Port' => 587,
    'SMTPAuth' => true,
    'Username' => 'email@domain.com',
    'Password' => 'passwordd',
    'debug' => 0,
    'SMTPDebug' => 0,
    'SMTPSecure' => 'tls',
));

$settings->setProperty('allowed_upload_images', $_ALLOWED_IMAGES_MIME = array(
    'image/jpg',
    'image/jpeg',
    'image/png'
));
$_server_name = filter_input(INPUT_SERVER, 'SERVER_NAME');
$_protocol = filter_input(INPUT_SERVER, 'HTTPS') != '' || filter_input(INPUT_SERVER, 'SERVER_PORT') == 443 ? 'https' : 'http';


if (in_array($_server_name, ['dev.local'])) {
    error_reporting(E_ALL);
    ini_set("display_errors", 1);
}

$settings->setProperty('servername', $_server_name);
$settings->setProperty('homepage', $_protocol . '://' . $_server_name . '/');
$settings->setProperty('protocol', $_protocol);
$settings->setProperty('login_url', $settings->getProperty('homepage') . 'login');
$settings->setProperty('reset_password', $settings->getProperty('homepage') . 'reset-password');
$settings->setProperty('register_url', $settings->getProperty('homepage') . 'register');
$settings->setProperty('404', $settings->getProperty('homepage') . 'errors/404');



