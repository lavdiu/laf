<?php

$settings = \Laf\Util\Settings::getInstance();

/**
 * Social media links
 */
$settings->setProperty('social_facebook', 'https://www.facebook.com/intrepicure/');
$settings->setProperty('social_twitter', 'https://twitter.com/intrepicure');
$settings->setProperty('social_instagram', 'https://www.instagram.com/intrepicure/');
$settings->setProperty('social_pinterest', 'http://www.pinterest.com/intrepicure');
$settings->setProperty('social_youtube', 'https://www.youtube.com/c/intrepicure');
$settings->setProperty('social_googleplus', 'https://plus.google.com/+intrepicure');
$settings->setProperty('social_vine', 'https://vine.co/u/1311110511898890240');


$settings->setProperty('email_smtp_config', array(
    'Host' => 'smtp.gmail.com',
    'Port' => 587,
    'SMTPAuth' => true,
    'Username' => 'noreply@intrepicure.com',
    'Password' => 'xnrcopgzkhbdueri',
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


if(in_array($_server_name, ['intrepicurebeta.com', 'my.intrepicurebeta.com', 'intrepicure.local', 'my.intrepicure.local'])){
    error_reporting(E_ALL);
    ini_set("display_errors", 1);
}

$settings->setProperty('servername', $_server_name);
$settings->setProperty('base_servername', str_replace('my.', '', $_server_name));
$settings->setProperty('homepage', $_protocol . '://' . $_server_name . '/');
$settings->setProperty('my.homepage', $_protocol . '://my.' . $_server_name . '/');
$settings->setProperty('intrepicure.com', $_protocol . '://' . str_replace('my.', '', $_server_name) . '/');
$settings->setProperty('protocol', $_protocol);
$settings->setProperty('login_url', $settings->getProperty('intrepicure.com') . 'login');
$settings->setProperty('reset_password', $settings->getProperty('intrepicure.com') . 'reset-password');
$settings->setProperty('register_url', $settings->getProperty('intrepicure.com') . 'register');
$settings->setProperty('404', $settings->getProperty('homepage') . 'errors/404');






$settings->setProperty('stripe_key', 'pk_test_52ryD71QeCKXE3zJIhFzRJVP');
$settings->setProperty('stripe_key_test', 'sk_test_z1sIXwR6SOnJ2lx4IsDcgtyI');
